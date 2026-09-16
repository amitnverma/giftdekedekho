<?php
/**
 * The B2B partner portal: /partner/{slug}/...
 *
 * Each partner gets its own branded page, where its staff sign in, keep a list
 * of their own customers, create singles (one photo + video) and albums (several)
 * paid for in credits, print the QR sticker, and see how often their customers
 * open them.
 *
 * Isolation is the point of the design:
 *  - sign-in is a separate session key, checked against this URL's partner, so a
 *    login for one partner never opens another's portal, the storefront account
 *    area or the admin;
 *  - every read goes through a partner-scoped query (findForPartner, findContent)
 *    rather than trusting an id from the URL;
 *  - content is tagged channel 'partner', which keeps it out of GiftDekeDekho's
 *    AR queue and scan-anything page.
 */
require_once APP_PATH . '/services/ArPartnerService.php';

class PartnerController extends BaseController
{
    /** Partners work a shop counter all day, so their session outlasts the admin's. */
    private const SESSION_IDLE_SECONDS = 12 * 3600;

    private const GENERATE_LIMIT = 24;
    private const GENERATE_WINDOW_SECONDS = 300;
    private const MAX_PENDING_UPLOADS = 60;

    private ArPartner $partners;
    private ArPartnerService $service;
    private ArFrameService $frames;
    private array $partner = [];
    private array $user = [];

    public function __construct()
    {
        $this->partners = new ArPartner();
        $this->service = new ArPartnerService();
        $this->frames = new ArFrameService();
    }

    public function dispatch(string $slug, array $rest, string $method): void
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,59}$/', $slug) || !$this->partners->tableExists()) {
            $this->notFound();
            return;
        }
        $partner = $this->partners->findBySlug($slug);
        if (!$partner) {
            $this->notFound();
            return;
        }
        $this->partner = $partner;

        $path = '/' . implode('/', array_map('strval', $rest));
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $post = $method === 'POST';

        if ($path === '/login') {
            $this->login();
            return;
        }
        if ($path === '/logout') {
            $this->logout();
            return;
        }

        $this->requirePartnerLogin();

        switch (true) {
            case $path === '/':
                $this->dashboard();
                break;

            case $path === '/customers':
                $post ? $this->createCustomer() : $this->customers();
                break;
            case preg_match('#^/customers/(\d+)$#', $path, $m) === 1:
                $post ? $this->updateCustomer((int)$m[1]) : $this->customer((int)$m[1]);
                break;

            case $path === '/singles':
                $this->contentIndex('single');
                break;
            case $path === '/albums':
                $this->contentIndex('album');
                break;
            case $path === '/singles/create':
                $post ? $this->storeContent('single') : $this->createForm('single');
                break;
            case $path === '/albums/create':
                $post ? $this->storeContent('album') : $this->createForm('album');
                break;

            case $path === '/upload' && $post:
                $this->upload();
                break;

            case preg_match('#^/content/(\d+)$#', $path, $m) === 1:
                $this->content((int)$m[1]);
                break;
            case preg_match('#^/content/(\d+)/update$#', $path, $m) === 1 && $post:
                $this->updateContent((int)$m[1]);
                break;
            case preg_match('#^/content/(\d+)/items/(\d+)/replace$#', $path, $m) === 1 && $post:
                $this->replaceItem((int)$m[1], (int)$m[2]);
                break;
            case preg_match('#^/content/(\d+)/generate$#', $path, $m) === 1 && $post:
                $this->generate((int)$m[1]);
                break;
            case preg_match('#^/content/(\d+)/sticker$#', $path, $m) === 1:
                $this->sticker((int)$m[1]);
                break;
            case preg_match('#^/content/(\d+)/photo$#', $path, $m) === 1:
                $this->photo((int)$m[1]);
                break;
            case preg_match('#^/content/(\d+)/confirm-test$#', $path, $m) === 1 && $post:
                $this->confirmTest((int)$m[1]);
                break;

            case $path === '/credits':
                $this->credits();
                break;
            case $path === '/credits/request' && $post:
                $this->requestCredits();
                break;

            case $path === '/analytics':
                $this->analytics();
                break;

            default:
                $this->notFound();
        }
    }

    // ------------------------------------------------------------------ auth

    private function login(): void
    {
        if ($this->signedIn()) {
            $this->go('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $email = strtolower(trim((string)$this->input('email', '')));
            $password = (string)$this->input('password', '');
            $identifier = 'partner:' . $email;

            if ($this->loginRateLimited($identifier)) {
                flash('error', 'Too many sign-in attempts. Please wait 15 minutes and try again.');
                $this->go('/login');
            }

            $users = new ArPartnerUser();
            $user = $email === '' ? null : $users->findByEmail($email);
            // One message for every failure, so the form never confirms which
            // emails have accounts, or which partner they belong to.
            if (!$user || (int)$user['partner_id'] !== (int)$this->partner['id']
                || empty($user['is_active']) || !password_verify($password, $user['password_hash'])) {
                $this->recordLoginAttempt($identifier);
                $this->setOld(['email' => $email]);
                flash('error', 'Incorrect email or password.');
                $this->go('/login');
            }
            if (empty($this->partner['is_active'])) {
                flash('error', 'This account is paused. Please contact ' . siteSetting('site_name', SITE_NAME) . '.');
                $this->go('/login');
            }

            session_regenerate_id(true);
            $_SESSION['partner_auth'] = [
                'user_id'    => (int)$user['id'],
                'partner_id' => (int)$this->partner['id'],
                'seen'       => time(),
            ];
            $users->update((int)$user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
            $this->clearOld();
            $this->go('/');
        }

        renderRaw('partner/login', [
            'partner' => $this->partner,
            'brand'   => $this->brand(),
        ]);
    }

    private function logout(): void
    {
        // Only the partner session: the same browser may also be signed in to
        // the storefront, and that is not this page's to end.
        unset($_SESSION['partner_auth'], $_SESSION['partner_uploads']);
        session_regenerate_id(true);
        $this->go('/login');
    }

    private function signedIn(): bool
    {
        $auth = $_SESSION['partner_auth'] ?? null;
        return is_array($auth)
            && (int)($auth['partner_id'] ?? 0) === (int)$this->partner['id']
            && time() - (int)($auth['seen'] ?? 0) < self::SESSION_IDLE_SECONDS;
    }

    private function requirePartnerLogin(): void
    {
        if (!$this->signedIn()) {
            $this->respondUnauthenticated();
        }

        // Re-read on every request, so switching a login or the partner off in
        // the admin takes effect at once rather than at the next sign-in.
        $user = (new ArPartnerUser())->findForPartner((int)$this->partner['id'], (int)$_SESSION['partner_auth']['user_id']);
        if (!$user || empty($user['is_active']) || empty($this->partner['is_active'])) {
            unset($_SESSION['partner_auth']);
            $this->respondUnauthenticated();
        }

        $this->user = $user;
        $_SESSION['partner_auth']['seen'] = time();
    }

    private function respondUnauthenticated(): void
    {
        if ($this->wantsJson()) {
            $this->json(['ok' => false, 'error' => 'You have been signed out. Please sign in again.'], 401);
        }
        $this->go('/login');
    }

    private function loginRateLimited(string $identifier): bool
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $stmt->execute([$identifier]);
        return (int)$stmt->fetchColumn() >= 5;
    }

    private function recordLoginAttempt(string $identifier): void
    {
        Database::getInstance()
            ->prepare('INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)')
            ->execute([$identifier, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    }

    // ------------------------------------------------------------- dashboard

    private function dashboard(): void
    {
        $pid = (int)$this->partner['id'];
        $this->page('dashboard', 'Dashboard', 'home', [
            'counts'    => $this->service->contentCounts($pid),
            'customers' => (new ArPartnerCustomer())->countForPartner($pid),
            'scans'     => (new ArScanEvent())->totals($pid),
            'recent'    => $this->service->contentList($pid, [], 6),
        ]);
    }

    // ------------------------------------------------------------- customers

    private function customers(): void
    {
        $search = (string)$this->input('search', '');
        $this->page('customers', 'Customers', 'customers', [
            'customers' => (new ArPartnerCustomer())->forPartner((int)$this->partner['id'], $search),
            'search'    => $search,
        ]);
    }

    private function createCustomer(): void
    {
        $this->requireCsrf();
        $data = $this->customerInput();
        if (isset($data['error'])) {
            flash('error', $data['error']);
            $this->setOld($_POST);
            $this->go('/customers#add');
        }
        $data['partner_id'] = (int)$this->partner['id'];
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = (new ArPartnerCustomer())->create($data);
        $this->clearOld();
        flash('success', $data['name'] . ' added.');
        $this->go('/customers/' . $id);
    }

    private function customer(int $id): void
    {
        $customer = $this->findCustomerOr404($id);
        $this->page('customer_show', $customer['name'], 'customers', [
            'customer' => $customer,
            'content'  => $this->service->contentList((int)$this->partner['id'], ['customer_id' => $id]),
        ]);
    }

    private function updateCustomer(int $id): void
    {
        $this->requireCsrf();
        $this->findCustomerOr404($id);
        $data = $this->customerInput();
        if (isset($data['error'])) {
            flash('error', $data['error']);
            $this->go('/customers/' . $id);
        }
        (new ArPartnerCustomer())->update($id, $data);
        flash('success', 'Customer details saved.');
        $this->go('/customers/' . $id);
    }

    private function customerInput(): array
    {
        $name = trim((string)$this->input('name', ''));
        $phone = preg_replace('/[^\d+]/', '', (string)$this->input('phone', ''));
        $email = strtolower(trim((string)$this->input('email', '')));

        if (mb_strlen($name) < 2) {
            return ['error' => 'Please enter the customer\'s name.'];
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'That email address does not look right.'];
        }
        return [
            'name'      => mb_substr($name, 0, 120),
            'phone'     => $phone === '' ? null : substr($phone, 0, 20),
            'email'     => $email === '' ? null : $email,
            'reference' => $this->blankToNull(mb_substr((string)$this->input('reference', ''), 0, 80)),
            'notes'     => $this->blankToNull((string)$this->input('notes', '')),
        ];
    }

    private function findCustomerOr404(int $id): array
    {
        $customer = (new ArPartnerCustomer())->findForPartner((int)$this->partner['id'], $id);
        if (!$customer) {
            flash('error', 'That customer was not found.');
            $this->go('/customers');
        }
        return $customer;
    }

    // --------------------------------------------------------------- content

    private function contentIndex(string $kind): void
    {
        $search = (string)$this->input('search', '');
        $this->page('content_index', $kind === 'album' ? 'Albums' : 'Singles', $kind === 'album' ? 'albums' : 'singles', [
            'kind'    => $kind,
            'content' => $this->service->contentList((int)$this->partner['id'], ['kind' => $kind, 'search' => $search]),
            'search'  => $search,
        ]);
    }

    private function createForm(string $kind): void
    {
        if (!$this->kindAllowed($kind)) {
            flash('error', ($kind === 'album' ? 'Albums' : 'Singles') . ' are not enabled for your account.');
            $this->go('/');
        }

        $this->page('content_create', $kind === 'album' ? 'New Album' : 'New AR Content', $kind === 'album' ? 'albums' : 'singles', [
            'kind'           => $kind,
            'customers'      => (new ArPartnerCustomer())->optionsForPartner((int)$this->partner['id']),
            'preselect'      => (int)$this->input('customer', 0),
            'durationPrices' => ArPartner::durationPrices($this->partner),
            'validityPrices' => ArPartner::validityPrices($this->partner),
            'packs'          => ArPartner::creditPacks($this->partner),
            'maxPages'       => $this->maxAlbumPages(),
            'maxVideoMb'     => $this->maxVideoMb(),
            'uploadLimit'    => (string)ini_get('upload_max_filesize'),
            'compiler'       => $this->compilerOk(),
        ]);
    }

    /**
     * Create a single or album from files already uploaded one by one.
     *
     * Files arrive beforehand through upload(), each in its own request, so an
     * album of several videos never has to fit into one POST. This request only
     * carries the tokens those uploads returned, which can only ever resolve to
     * this partner's own files.
     */
    private function storeContent(string $kind): void
    {
        $this->requireCsrf();
        $back = $kind === 'album' ? '/albums/create' : '/singles/create';
        if (!$this->kindAllowed($kind)) {
            flash('error', 'That is not enabled for your account.');
            $this->go('/');
        }

        $validity = (string)$this->input('validity', '');
        $title = trim((string)$this->input('title', ''));
        if ($title === '') {
            $title = ($kind === 'album' ? 'Album' : 'Single Frame') . ' - ' . date('d M Y, h:i A');
        }

        // --- the pages, resolved from upload tokens
        $rows = is_array($_POST['pages'] ?? null) ? array_values($_POST['pages']) : [];
        if ($kind === 'single') {
            $rows = array_slice($rows, 0, 1);
        }
        if (!$rows) {
            $this->failCreate($back, 'Add a photo and the video it should play.');
        }
        if (count($rows) > $this->maxAlbumPages()) {
            $this->failCreate($back, 'An album can hold at most ' . $this->maxAlbumPages() . ' photos.');
        }

        $pages = [];
        $tokens = [];
        foreach ($rows as $n => $row) {
            $label = $kind === 'album' ? 'AR content ' . ($n + 1) . ': ' : '';
            $photo = $this->uploadFromToken((string)($row['photo_token'] ?? ''), 'photo');
            $video = $this->uploadFromToken((string)($row['video_token'] ?? ''), 'video');
            if ($photo === null) {
                $this->failCreate($back, $label . 'Upload the target image.');
            }
            if ($video === null) {
                $this->failCreate($back, $label . 'Upload the video.');
            }
            $tokens[] = (string)$row['photo_token'];
            $tokens[] = (string)$row['video_token'];
            $pages[] = [
                'photo_path'  => $photo['path'],
                'video_path'  => $video['path'],
                'max_seconds' => (int)($kind === 'album' ? ($row['duration'] ?? 0) : $this->input('duration', 0)),
                'title'       => mb_substr(trim((string)($row['title'] ?? '')), 0, 120),
            ];
        }

        $quote = $this->service->quote($this->partner, array_column($pages, 'max_seconds'), $validity);
        if (empty($quote['ok'])) {
            $this->failCreate($back, $quote['error']);
        }

        // --- the customer: an existing one of this partner's, or a new one
        $customers = new ArPartnerCustomer();
        $newCustomerId = null;
        $customerRef = (string)$this->input('customer_id', '');
        if ($customerRef === 'new') {
            $name = trim((string)$this->input('new_customer_name', ''));
            if (mb_strlen($name) < 2) {
                $this->failCreate($back, 'Enter the new customer\'s name.');
            }
            $phone = preg_replace('/[^\d+]/', '', (string)$this->input('new_customer_phone', ''));
            $newCustomerId = $customers->create([
                'partner_id' => (int)$this->partner['id'],
                'name'       => mb_substr($name, 0, 120),
                'phone'      => $phone === '' ? null : substr($phone, 0, 20),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $customer = $customers->find($newCustomerId);
        } else {
            $customer = $customers->findForPartner((int)$this->partner['id'], (int)$customerRef);
            if (!$customer) {
                $this->failCreate($back, 'Select a customer.');
            }
        }

        $result = $this->service->createContent($this->partner, (int)$this->user['id'], [
            'kind'        => $kind,
            'title'       => mb_substr($title, 0, 160),
            'customer_id' => (int)$customer['id'],
            'validity'    => $validity,
        ], $pages);

        if (empty($result['ok'])) {
            if ($newCustomerId !== null) {
                $customers->delete($newCustomerId);
            }
            // The form checks the balance before it can be submitted, so this is
            // a race (another tab spent the credits) or a real failure.
            foreach ($pages as $page) {
                $this->frames->deleteFile($page['photo_path']);
                $this->frames->deleteFile($page['video_path']);
            }
            foreach ($tokens as $token) {
                unset($_SESSION['partner_uploads'][$token]);
            }
            $this->failCreate($back, $result['error']);
        }

        // Paid for and saved: the uploads now belong to the frame.
        foreach ($tokens as $token) {
            unset($_SESSION['partner_uploads'][$token]);
        }
        $frameId = (int)$result['frame_id'];
        // Shown to GiftDekeDekho staff if they open the frame from the admin.
        (new ArFrame())->update($frameId, [
            'customer_name'  => mb_substr((string)$customer['name'], 0, 120),
            'customer_phone' => $customer['phone'] === null ? null : substr((string)$customer['phone'], 0, 15),
        ]);

        if (!$this->throttleGeneration(count($pages))) {
            flash('error', sprintf('Created for %s credits, but many photos were processed in the last few minutes. '
                . 'Wait a minute, then press "Prepare photos".', number_format($result['cost'])));
            $this->go('/content/' . $frameId);
        }

        @set_time_limit(30 + 25 * count($pages));
        $generated = $this->frames->generateTarget($frameId);
        $this->flashGeneration($generated, sprintf('Created for %s credits.', number_format($result['cost'])));
        $this->go('/content/' . $frameId);
    }

    private function failCreate(string $back, string $message): void
    {
        flash('error', $message);
        $this->setOld([
            'title'    => (string)$this->input('title', ''),
            'validity' => (string)$this->input('validity', ''),
        ]);
        $this->go($back);
    }

    /**
     * Store one photo or video for a form that has not been submitted yet.
     *
     * Answers in JSON. Stray output — a deprecation notice on the server's
     * newer PHP, say — is discarded so it can never corrupt the reply.
     */
    private function upload(): void
    {
        ob_start();

        if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->json(['ok' => false, 'error' => 'That file is larger than the server allows.'], 413);
        }
        if (!verifyCsrf()) {
            $this->json(['ok' => false, 'error' => 'Your session expired. Refresh the page and try again.'], 419);
        }

        $kind = (string)$this->input('kind', '');
        if ($kind !== 'photo' && $kind !== 'video') {
            $this->json(['ok' => false, 'error' => 'Unknown upload.'], 400);
        }
        if (empty($_FILES['file'])) {
            $this->json(['ok' => false, 'error' => 'Please choose a file.'], 400);
        }

        $uploads = $_SESSION['partner_uploads'] ?? [];
        if (count($uploads) >= self::MAX_PENDING_UPLOADS) {
            $this->json(['ok' => false, 'error' => 'Too many files waiting. Create or cancel what you have started, then refresh.'], 429);
        }

        $stored = $kind === 'photo'
            ? $this->frames->storePhoto($_FILES['file'])
            : $this->frames->storeVideo($_FILES['file'], $this->maxVideoMb() * 1024 * 1024);
        if (empty($stored['ok'])) {
            $this->json(['ok' => false, 'error' => $stored['error']], 422);
        }

        $token = bin2hex(random_bytes(16));
        $uploads[$token] = [
            'partner_id' => (int)$this->partner['id'],
            'kind'       => $kind,
            'path'       => $stored['path'],
            'at'         => time(),
        ];
        $_SESSION['partner_uploads'] = $uploads;

        $this->json(['ok' => true, 'token' => $token]);
    }

    private function uploadFromToken(string $token, string $kind): ?array
    {
        $upload = $_SESSION['partner_uploads'][$token] ?? null;
        if (!is_array($upload) || $upload['kind'] !== $kind || (int)$upload['partner_id'] !== (int)$this->partner['id']) {
            return null;
        }
        return $upload;
    }

    private function content(int $id): void
    {
        $frame = $this->findContentOr404($id);
        $items = $this->frames->items($frame);
        $scanUrl = ArFrameService::scanUrl($frame['slug']);

        require_once APP_PATH . '/services/QrCodeService.php';
        require_once APP_PATH . '/services/ArTargetService.php';
        $stat = (new ArScanEvent())->frameTotals($id);

        $this->page('content_show', (string)($frame['title'] ?: $frame['slug']), $frame['content_kind'] === 'album' ? 'albums' : 'singles', [
            'frame'      => $frame,
            'items'      => $items,
            'scanUrl'    => $scanUrl,
            'qr'         => (new QrCodeService())->pngDataUri($scanUrl, 'Q', 8),
            'editable'   => ArPartnerService::isEditable($frame),
            'expired'    => ArPartnerService::isExpired($frame),
            'customers'  => (new ArPartnerCustomer())->optionsForPartner((int)$this->partner['id']),
            'opens'      => $stat['opens'],
            'visitors'   => $stat['visitors'],
            'maxVideoMb' => $this->maxVideoMb(),
        ]);
    }

    private function updateContent(int $id): void
    {
        $this->requireCsrf();
        $frame = $this->findContentOr404($id);

        $data = ['is_active' => $this->input('is_active') ? 1 : 0];

        // Title and customer follow the same edit window as the photos: after
        // it closes, what was sold stays as it was sold.
        if (ArPartnerService::isEditable($frame)) {
            $title = trim((string)$this->input('title', ''));
            if ($title !== '') {
                $data['title'] = mb_substr($title, 0, 160);
            }
            $customer = (new ArPartnerCustomer())->findForPartner((int)$this->partner['id'], (int)$this->input('customer_id', 0));
            if ($customer) {
                $data['partner_customer_id'] = (int)$customer['id'];
                $data['customer_name'] = mb_substr((string)$customer['name'], 0, 120);
                $data['customer_phone'] = $customer['phone'] === null ? null : substr((string)$customer['phone'], 0, 15);
            }
        }

        (new ArFrame())->update($id, $data);
        flash('success', 'Saved.');
        $this->go('/content/' . $id);
    }

    /** Swap one page's photo and/or video during the edit window. */
    private function replaceItem(int $id, int $itemId): void
    {
        $this->requireCsrf();
        $frame = $this->findContentOr404($id);
        if (!ArPartnerService::isEditable($frame)) {
            flash('error', 'The edit window for this content has closed.');
            $this->go('/content/' . $id);
        }
        $itemModel = new ArFrameItem();
        $item = $itemModel->findForFrame($id, $itemId);
        if (!$item) {
            flash('error', 'That photo is not part of this content.');
            $this->go('/content/' . $id);
        }

        $photoToken = (string)$this->input('photo_token', '');
        $videoToken = (string)$this->input('video_token', '');
        $photo = $photoToken === '' ? null : $this->uploadFromToken($photoToken, 'photo');
        $video = $videoToken === '' ? null : $this->uploadFromToken($videoToken, 'video');
        if ($photo === null && $video === null) {
            flash('error', 'Upload a new image or video first.');
            $this->go('/content/' . $id . '#item-' . $itemId);
        }

        if ($video !== null) {
            $itemModel->update($itemId, ['video_type' => 'upload', 'video_path' => $video['path']]);
            unset($_SESSION['partner_uploads'][$videoToken]);
            if (!empty($item['video_path']) && $item['video_path'] !== $video['path']) {
                $this->frames->deleteFile($item['video_path']);
            }
        }

        if ($photo === null) {
            flash('success', 'Video replaced.');
            $this->go('/content/' . $id . '#item-' . $itemId);
        }

        // A new photo invalidates the old target and any earlier test.
        $itemModel->update($itemId, [
            'photo_path'         => $photo['path'],
            'target_path'        => null,
            'trackability_score' => null,
            'trackability_flag'  => null,
            'trackability_json'  => null,
            'verified_at'        => null,
        ]);
        unset($_SESSION['partner_uploads'][$photoToken]);
        $this->frames->deleteFile($item['photo_path']);
        $this->frames->deleteFile($item['target_path']);

        if (!$this->throttleGeneration()) {
            $this->frames->refreshFrame($id);
            flash('error', 'Image replaced, but many photos were processed in the last few minutes. Wait a minute, then press "Prepare photos".');
            $this->go('/content/' . $id . '#item-' . $itemId);
        }

        $this->flashGeneration(
            $this->frames->generateTarget($id, true),
            $video !== null ? 'Image and video replaced.' : 'Image replaced.'
        );
        $this->go('/content/' . $id . '#item-' . $itemId);
    }

    /** Compile any photo still missing a target — after a failure or a throttle. */
    private function generate(int $id): void
    {
        $this->requireCsrf();
        $frame = $this->findContentOr404($id);
        $missing = count(array_filter($this->frames->items($frame), fn($i) => empty($i['target_path'])));
        if ($missing === 0) {
            flash('success', 'Every photo is already prepared.');
            $this->go('/content/' . $id);
        }
        if (!$this->throttleGeneration($missing)) {
            flash('error', 'Many photos were processed in the last few minutes. Please wait a minute and try again.');
            $this->go('/content/' . $id);
        }
        @set_time_limit(30 + 25 * $missing);
        $this->flashGeneration($this->frames->generateTarget($id, true), 'Photos prepared.');
        $this->go('/content/' . $id);
    }

    private function sticker(int $id): void
    {
        $frame = $this->findContentOr404($id);
        require_once APP_PATH . '/services/QrCodeService.php';
        $scanUrl = ArFrameService::scanUrl($frame['slug']);

        renderRaw('admin/ar_sticker', [
            'frame'    => $frame,
            'scanUrl'  => $scanUrl,
            // Same settings as GiftDekeDekho's own stickers: printed small and handled.
            'qr'       => (new QrCodeService())->pngDataUri($scanUrl, 'Q', 12),
            'siteName' => $this->partner['name'],
            'backUrl'  => $this->portalUrl('/content/' . $id),
        ]);
    }

    private function photo(int $id): void
    {
        $frame = $this->findContentOr404($id);
        $items = $this->frames->items($frame);
        $wanted = (int)$this->input('item', 0);
        $position = 0;
        foreach ($items as $index => $item) {
            if ((int)$item['id'] === $wanted) {
                $position = $index;
                break;
            }
        }

        renderRaw('admin/ar_frame_photo', [
            'frame'     => $frame,
            'items'     => $items,
            'position'  => $position,
            'backUrl'   => $this->portalUrl('/content/' . $id),
            'photoBase' => '/partner/' . $this->partner['slug'] . '/content/' . $id . '/photo',
        ]);
    }

    /**
     * "I scanned it on my phone and it played". The test runs on the public
     * scan link, which needs no sign-in on the phone, so the result is recorded
     * here on the computer the partner is already signed in on.
     */
    private function confirmTest(int $id): void
    {
        $this->requireCsrf();
        $frame = $this->findContentOr404($id);
        $items = $this->frames->items($frame);
        if (!$items || empty($frame['target_path']) || array_filter($items, fn($i) => empty($i['target_path']))) {
            flash('error', 'Prepare every photo before recording the test.');
            $this->go('/content/' . $id);
        }
        if (!$this->input('confirmed')) {
            flash('error', 'Tick the box to confirm the video played.');
            $this->go('/content/' . $id);
        }
        $this->frames->markVerified($id);
        flash('success', 'Test recorded.');
        $this->go('/content/' . $id);
    }

    private function findContentOr404(int $id): array
    {
        $frame = $this->service->findContent((int)$this->partner['id'], $id);
        if (!$frame) {
            flash('error', 'That content was not found.');
            $this->go('/');
        }
        return $frame;
    }

    // --------------------------------------------------------------- credits

    private function credits(): void
    {
        $credits = new ArPartnerCredit();
        $this->page('credits', 'My Credits', 'credits', [
            'packs'    => ArPartner::creditPacks($this->partner),
            'ledger'   => $credits->ledger((int)$this->partner['id']),
            'requests' => $credits->requests((int)$this->partner['id'], 10),
            'support'  => $this->supportWhatsapp(),
        ]);
    }

    /**
     * Ask for a credit pack. Nothing is charged here: payment is arranged with
     * GiftDekeDekho directly, and the admin adds the credits once it is received.
     */
    private function requestCredits(): void
    {
        $this->requireCsrf();
        $packs = ArPartner::creditPacks($this->partner);
        $pack = $packs[(int)$this->input('pack', -1)] ?? null;
        if ($pack === null) {
            flash('error', 'Choose a credit pack.');
            $this->go('/credits');
        }

        $credits = new ArPartnerCredit();
        if ($credits->hasRecentPending((int)$this->partner['id'], $pack['price'])) {
            flash('success', 'Your request for this pack is already with us — we will add the credits once payment is received.');
            $this->go('/credits');
        }

        $credits->createRequest((int)$this->partner['id'], $pack['price'], $pack['credits'], (int)$this->user['id']);
        flash('success', sprintf(
            'Request sent for %s credits (%s). We will add them once payment is received.',
            number_format($pack['credits']),
            formatPrice($pack['price'])
        ));
        $this->go('/credits');
    }

    private function supportWhatsapp(): ?string
    {
        $digits = preg_replace('/\D/', '', (string)siteSetting('ar_partner_support_whatsapp', ''));
        return $digits === '' ? null : $digits;
    }

    // ------------------------------------------------------------- analytics

    private function analytics(): void
    {
        $pid = (int)$this->partner['id'];
        $events = new ArScanEvent();
        $this->page('analytics', 'Analytics', 'analytics', [
            'totals'  => $events->totals($pid),
            'counts'  => $this->service->contentCounts($pid),
            'daily'   => $events->daily($pid, 30),
            'content' => $events->perFrame($pid),
        ]);
    }

    // --------------------------------------------------------------- helpers

    private function page(string $view, string $title, string $nav, array $data = []): void
    {
        renderRaw('partner/layout', array_merge($data, [
            '_partnerView' => $view,
            'pageTitle'    => $title,
            'activeNav'    => $nav,
            'partner'      => $this->partner,
            'partnerUser'  => $this->user,
            'brand'        => $this->brand(),
            'base'         => '/partner/' . $this->partner['slug'],
        ]));
    }

    private function brand(): array
    {
        return ArPartnerService::brand($this->partner, (string)siteSetting('site_name', SITE_NAME));
    }

    private function kindAllowed(string $kind): bool
    {
        return $kind === 'album' ? !empty($this->partner['allow_albums']) : !empty($this->partner['allow_singles']);
    }

    private function maxAlbumPages(): int
    {
        return max(1, min(ArFrameItem::MAX_PER_FRAME, (int)$this->partner['max_album_pages']));
    }

    private function maxVideoMb(): int
    {
        return max(1, (int)$this->partner['max_video_mb']);
    }

    private function compilerOk(): bool
    {
        require_once APP_PATH . '/services/ArTargetService.php';
        return !empty((new ArTargetService())->preflight()['ok']);
    }

    private function flashGeneration(array $result, string $lead): void
    {
        if (empty($result['ok'])) {
            flash('error', $lead . ' But a photo could not be prepared: ' . ($result['error'] ?? 'unknown error')
                . ' Replace that image, or press "Prepare photos" to try again.');
            return;
        }
        if (isset($result['flag']) && $result['flag'] !== 'good') {
            flash('error', sprintf(
                '%s Heads up: %sthis photo may be hard to scan (%d/100). %s',
                $lead,
                ($result['total'] ?? 1) > 1 ? 'photo ' . $result['position'] . ' — ' : '',
                $result['score'],
                $result['advice'] ?? ''
            ));
            return;
        }
        flash('success', $lead . ' Your AR is live — print the QR sticker, or send the link to your customer.');
    }

    private function throttleGeneration(int $count = 1): bool
    {
        $now = time();
        $recent = array_values(array_filter(
            $_SESSION['partner_generate_times'] ?? [],
            fn($t) => ($now - (int)$t) < self::GENERATE_WINDOW_SECONDS
        ));
        if (count($recent) + $count > self::GENERATE_LIMIT) {
            $_SESSION['partner_generate_times'] = $recent;
            return false;
        }
        for ($i = 0; $i < $count; $i++) {
            $recent[] = $now;
        }
        $_SESSION['partner_generate_times'] = $recent;
        return true;
    }

    private function portalUrl(string $path): string
    {
        return url('/partner/' . $this->partner['slug'] . $path);
    }

    private function go(string $path): void
    {
        redirect('/partner/' . $this->partner['slug'] . ($path === '/' ? '' : $path));
    }

    private function wantsJson(): bool
    {
        return str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /** JSON reply with any stray buffered output thrown away first. */
    private function json(array $data, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        jsonResponse($data, $status);
    }

    private function notFound(): void
    {
        http_response_code(404);
        (new PageController())->notFound();
    }

    private function blankToNull(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
