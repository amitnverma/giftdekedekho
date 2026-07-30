<?php
/**
 * Runs a photo through the MindAR compiler (tools/mindar-compile) to produce a
 * .mind image target, and reports how well the photo is likely to track.
 *
 * Two transports, because hosts differ — see tools/mindar-compile/README.txt:
 *   'shell' : shells out to `node compile.mjs` (default; needs shell_exec)
 *   'http'  : POSTs to a localhost Node service (for hosts with shell_exec off)
 *
 * Selected by `ar_compiler_mode` in config/local.php. Both transports return
 * the same array shape, so callers never care which one is in use.
 *
 * This is the single target-generation code path shared by both sales channels:
 * the online queue and the walk-in Quick Create flow both come through here.
 */
class ArTargetService
{
    private const RESULT_MARKER = '__GDD_RESULT__';

    /** Compiling is CPU-bound; a single image is ~5s, so this is generous. */
    private const TIMEOUT_SECONDS = 120;

    private string $mode;
    private string $toolDir;

    public function __construct()
    {
        $this->mode = gdd_local('ar_compiler_mode', getenv('GDD_AR_COMPILER_MODE') ?: 'shell') === 'http'
            ? 'http'
            : 'shell';
        $this->toolDir = BASE_PATH . '/tools/mindar-compile';
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * Whether the toolchain looks usable, with a human-readable reason when not.
     * Surfaced in the admin UI so a broken install is visible before someone
     * tries to serve a customer standing at the counter.
     *
     * @return array{ok: bool, message: string}
     */
    public function preflight(): array
    {
        if (!is_dir($this->toolDir . '/node_modules')) {
            return [
                'ok' => false,
                'message' => 'Compiler not installed. Run "npm ci" in tools/mindar-compile (see its README.txt).',
            ];
        }

        if ($this->mode === 'http') {
            $base = $this->serviceBaseUrl();
            if ($base === '') {
                return ['ok' => false, 'message' => 'ar_compiler_url is not set in config/local.php.'];
            }
            if ($this->serviceToken() === '') {
                return ['ok' => false, 'message' => 'ar_compiler_token is not set in config/local.php.'];
            }
            $ch = curl_init($base . '/health');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($status !== 200 || !is_string($body) || strpos($body, '"ok":true') === false) {
                return [
                    'ok' => false,
                    'message' => 'Compiler service is not responding at ' . $base . '. Is server.mjs running?',
                ];
            }
            return ['ok' => true, 'message' => 'Compiler service reachable at ' . $base . '.'];
        }

        if (!$this->shellAvailable()) {
            return [
                'ok' => false,
                'message' => 'shell_exec is disabled on this host. Switch ar_compiler_mode to "http" (see tools/mindar-compile/README.txt).',
            ];
        }

        $node = $this->nodeBinary();
        $version = $this->runShell(escapeshellarg($node) . ' -v 2>&1');
        if (!preg_match('/^v(\d+)\./', trim((string)$version), $m)) {
            return [
                'ok' => false,
                'message' => 'Node.js not found at "' . $node . '". Set ar_node_binary in config/local.php.',
            ];
        }
        if ((int)$m[1] < 18) {
            return ['ok' => false, 'message' => 'Node.js 18+ required, found ' . trim((string)$version) . '.'];
        }

        return ['ok' => true, 'message' => 'Node ' . trim((string)$version) . ' ready.'];
    }

    /**
     * Compile $photoAbsPath into $targetAbsPath.
     *
     * @return array{ok: bool, error?: string, detail?: string|null, score?: int, flag?: string, metrics?: array}
     */
    public function compile(string $photoAbsPath, string $targetAbsPath): array
    {
        if (!is_file($photoAbsPath)) {
            return ['ok' => false, 'error' => 'The photo file is missing on disk.'];
        }

        $targetDir = dirname($targetAbsPath);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
            return ['ok' => false, 'error' => 'Could not create the target directory.'];
        }
        if (!is_writable($targetDir)) {
            return ['ok' => false, 'error' => 'Target directory is not writable: ' . $targetDir];
        }

        $result = $this->mode === 'http'
            ? $this->compileViaHttp($photoAbsPath, $targetAbsPath)
            : $this->compileViaShell($photoAbsPath, $targetAbsPath);

        // Never leave a half-written target behind — the queue would show it as
        // generated when it isn't.
        if (empty($result['ok']) && is_file($targetAbsPath)) {
            @unlink($targetAbsPath);
        }

        return $result;
    }

    private function compileViaShell(string $photoAbsPath, string $targetAbsPath): array
    {
        if (!$this->shellAvailable()) {
            return [
                'ok' => false,
                'error' => 'shell_exec is disabled on this host. Switch ar_compiler_mode to "http".',
            ];
        }

        $command = sprintf(
            '%s %s %s %s 2>/dev/null',
            escapeshellarg($this->nodeBinary()),
            escapeshellarg($this->toolDir . '/compile.mjs'),
            escapeshellarg($photoAbsPath),
            escapeshellarg($targetAbsPath)
        );

        $output = $this->runShell($command);
        if ($output === null || trim((string)$output) === '') {
            return [
                'ok' => false,
                'error' => 'The compiler produced no output. Check that Node.js is installed and tools/mindar-compile has its dependencies.',
            ];
        }

        return $this->parseResult((string)$output);
    }

    private function compileViaHttp(string $photoAbsPath, string $targetAbsPath): array
    {
        $base = $this->serviceBaseUrl();
        if ($base === '') {
            return ['ok' => false, 'error' => 'ar_compiler_url is not configured.'];
        }

        $ch = curl_init($base . '/compile');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-GDD-Token: ' . $this->serviceToken(),
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'input' => $photoAbsPath,
                'output' => $targetAbsPath,
            ]),
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'error' => 'Could not reach the compiler service.', 'detail' => $error];
        }

        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'The compiler service returned an unreadable response.'];
        }
        if (empty($decoded['ok'])) {
            return [
                'ok' => false,
                'error' => (string)($decoded['error'] ?? 'Target compilation failed.'),
                'detail' => $decoded['detail'] ?? null,
            ];
        }

        return $this->normaliseSuccess($decoded);
    }

    /**
     * Pull our marked JSON line out of the compiler's stdout. TensorFlow.js
     * prints an unavoidable banner, so the marker is what makes this reliable.
     */
    private function parseResult(string $output): array
    {
        $payload = null;
        foreach (explode("\n", $output) as $line) {
            if (strncmp($line, self::RESULT_MARKER, strlen(self::RESULT_MARKER)) === 0) {
                $payload = substr($line, strlen(self::RESULT_MARKER));
                break;
            }
        }

        if ($payload === null) {
            return [
                'ok' => false,
                'error' => 'The compiler did not return a result.',
                'detail' => ENVIRONMENT === 'development' ? mb_substr($output, 0, 500) : null,
            ];
        }

        $decoded = json_decode(trim($payload), true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'The compiler result could not be read.'];
        }
        if (empty($decoded['ok'])) {
            return [
                'ok' => false,
                'error' => (string)($decoded['error'] ?? 'Target compilation failed.'),
                'detail' => $decoded['detail'] ?? null,
            ];
        }

        return $this->normaliseSuccess($decoded);
    }

    private function normaliseSuccess(array $decoded): array
    {
        $score = (int)($decoded['score'] ?? 0);
        $flag = (string)($decoded['flag'] ?? 'poor');
        if (!in_array($flag, ['poor', 'fair', 'good'], true)) {
            $flag = 'poor';
        }

        return [
            'ok' => true,
            'score' => max(0, min(100, $score)),
            'flag' => $flag,
            'metrics' => is_array($decoded['metrics'] ?? null) ? $decoded['metrics'] : [],
        ];
    }

    /**
     * Admin-facing sentence explaining what a trackability flag means in
     * practice, so the warning is actionable rather than just a number.
     */
    public static function trackabilityAdvice(string $flag): string
    {
        switch ($flag) {
            case 'good':
                return 'This photo has plenty of distinct detail and should track reliably.';
            case 'fair':
                return 'This photo will probably work, but scanning may need steadier hands or better light. Consider a sharper, more detailed photo if one is available.';
            default:
                return 'This photo has very little distinct detail and will track poorly. Strongly consider swapping it for a sharper, higher-contrast photo before printing.';
        }
    }

    private function nodeBinary(): string
    {
        return (string)gdd_local('ar_node_binary', getenv('GDD_AR_NODE_BINARY') ?: 'node');
    }

    private function serviceBaseUrl(): string
    {
        return rtrim((string)gdd_local('ar_compiler_url', getenv('GDD_AR_COMPILER_URL') ?: ''), '/');
    }

    private function serviceToken(): string
    {
        return (string)gdd_local('ar_compiler_token', getenv('GDD_AR_TOKEN') ?: '');
    }

    private function shellAvailable(): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        return !in_array('shell_exec', $disabled, true);
    }

    private function runShell(string $command): ?string
    {
        $output = @shell_exec($command);
        return $output === false ? null : $output;
    }
}
