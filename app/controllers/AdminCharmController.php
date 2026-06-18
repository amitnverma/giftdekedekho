<?php

/**
 * Admin management of the charm / image-choice library.
 * Admins create sets, bulk-upload charm images, and edit labels/prices.
 */
class AdminCharmController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $this->viewAdmin('admin/charm_sets_index', [
            'metaTitle' => 'Charm Library',
            'sets' => (new CharmSet())->allWithCounts(),
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $name = trim((string)$this->input('name'));
            if ($name === '') {
                flash('error', 'Set name is required.');
                redirect('/admin/charm-sets/create');
            }
            $id = (new CharmSet())->create($name, (bool)$this->input('is_active', 1));
            flash('success', 'Charm set created. Now upload some charms.');
            redirect("/admin/charm-sets/{$id}/edit");
        }
        $this->viewAdmin('admin/charm_set_form', [
            'metaTitle' => 'New Charm Set',
            'set' => null,
            'charms' => [],
        ]);
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();
        $model = new CharmSet();
        $set = $model->find($id);
        if (!$set) { flash('error', 'Charm set not found.'); redirect('/admin/charm-sets'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $name = trim((string)$this->input('name'));
            if ($name === '') {
                flash('error', 'Set name is required.');
                redirect("/admin/charm-sets/{$id}/edit");
            }
            $model->updateSet($id, $name, (bool)$this->input('is_active'));

            // Update existing charms (label / price / active / delete).
            $labels = (array)($_POST['charm_label'] ?? []);
            $prices = (array)($_POST['charm_price'] ?? []);
            $active = (array)($_POST['charm_active'] ?? []);
            $deletes = (array)($_POST['charm_delete'] ?? []);
            foreach ($labels as $charmId => $label) {
                $charmId = (int)$charmId;
                $charm = $model->findCharm($charmId);
                if (!$charm || (int)$charm['set_id'] !== $id) continue;
                if (isset($deletes[$charmId])) {
                    $this->deleteCharmFile($charm['image_path']);
                    $model->deleteCharm($charmId);
                    continue;
                }
                $model->updateCharm($charmId, [
                    'label' => trim((string)$label) ?: $charm['label'],
                    'extra_charge' => (float)($prices[$charmId] ?? 0),
                    'is_active' => isset($active[$charmId]) ? 1 : 0,
                ]);
            }

            // Bulk-upload new charms.
            $this->handleUploads($id, $model);

            flash('success', 'Charm set saved.');
            redirect("/admin/charm-sets/{$id}/edit");
        }

        $this->viewAdmin('admin/charm_set_form', [
            'metaTitle' => 'Edit Charm Set',
            'set' => $set,
            'charms' => $model->charms($id),
        ]);
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $model = new CharmSet();
        foreach ($model->charms($id) as $charm) {
            $this->deleteCharmFile($charm['image_path']);
        }
        $model->delete($id);
        flash('success', 'Charm set deleted.');
        redirect('/admin/charm-sets');
    }

    /** Handle the multi-file "Add charms" upload on the set edit screen. */
    private function handleUploads(int $setId, CharmSet $model): void
    {
        if (empty($_FILES['charm_images']['name'][0])) return;
        $sort = $model->maxSort($setId) + 1;
        $count = count($_FILES['charm_images']['name']);

        for ($i = 0; $i < $count; $i++) {
            if (($_FILES['charm_images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $file = [
                'tmp_name' => $_FILES['charm_images']['tmp_name'][$i],
                'size' => $_FILES['charm_images']['size'][$i],
                'name' => $_FILES['charm_images']['name'][$i],
            ];
            $path = $this->saveCharmImage($file);
            if (!$path) continue;
            // Default label: the original filename (sans extension), else a number.
            $label = pathinfo($file['name'], PATHINFO_FILENAME);
            $label = trim(preg_replace('/[_-]+/', ' ', $label));
            if ($label === '') $label = (string)($sort + 1);
            $model->addCharm($setId, mb_substr($label, 0, 120), $path, 0.0, $sort);
            $sort++;
        }
    }

    private function saveCharmImage(array $file): ?string
    {
        if ($file['size'] > 5 * 1024 * 1024) return null;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) return null;

        $dir = UPLOAD_PATH . '/charms';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = 'charm_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) return null;

        return UPLOAD_URL . '/charms/' . $filename;
    }

    private function deleteCharmFile(string $path): void
    {
        if ($path === '' || !str_starts_with($path, UPLOAD_URL . '/charms/')) return;
        $abs = PUBLIC_PATH . '/uploads/charms/' . basename($path);
        if (is_file($abs)) @unlink($abs);
    }
}
