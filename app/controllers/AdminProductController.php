<?php

class AdminProductController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $productModel = new Product();
        $categoryModel = new Category();

        $filters = [
            'search' => $this->input('search', ''),
            'category_id' => $this->input('category_id', ''),
            'status' => $this->input('status', ''),
        ];
        $page = max(1, (int)$this->input('page', 1));
        $perPage = 20;

        $total = $productModel->adminCount($filters);
        $pagination = paginate($total, $perPage, $page);
        $products = $productModel->adminList($filters, $perPage, $pagination['offset']);

        $this->viewAdmin('admin/products_index', [
            'metaTitle' => 'Products',
            'products' => $products,
            'categories' => $categoryModel->allActive(),
            'filters' => $filters,
            'pagination' => $pagination,
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();
        $categoryModel = new Category();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $this->save(null);
            return;
        }

        $this->viewAdmin('admin/products_form', [
            'metaTitle' => 'Add Product',
            'product' => null,
            'images' => [],
            'options' => [],
            'categories' => $categoryModel->allActive(),
            'selectedCategoryIds' => [],
            'charmSets' => (new CharmSet())->activeWithCounts(),
        ]);
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();
        $productModel = new Product();
        $categoryModel = new Category();

        $product = $productModel->find($id);
        if (!$product) { flash('error', 'Product not found.'); redirect('/admin/products'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $this->save($id);
            return;
        }

        $this->viewAdmin('admin/products_form', [
            'metaTitle' => 'Edit Product',
            'product' => $product,
            'images' => $productModel->images($id),
            'options' => $productModel->customizationOptions($id),
            'categories' => $categoryModel->allActive(),
            'selectedCategoryIds' => $productModel->categoryIds($id),
            'charmSets' => (new CharmSet())->activeWithCounts(),
        ]);
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        (new Product())->delete($id);
        flash('success', 'Product deleted.');
        redirect('/admin/products');
    }

    public function bulkAction(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        $action = (string)$this->input('bulk_action', '');
        $productModel = new Product();

        foreach ($ids as $id) {
            switch ($action) {
                case 'activate':
                    $productModel->update($id, ['is_active' => 1]);
                    break;
                case 'deactivate':
                    $productModel->update($id, ['is_active' => 0]);
                    break;
                case 'feature':
                    $productModel->update($id, ['is_featured' => 1]);
                    break;
                case 'unfeature':
                    $productModel->update($id, ['is_featured' => 0]);
                    break;
                case 'delete':
                    $productModel->delete($id);
                    break;
            }
        }

        flash('success', count($ids) . ' product(s) updated.');
        redirect('/admin/products');
    }

    public function deleteImage(int $imageId): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        (new Product())->deleteImage($imageId);
        jsonResponse(['ok' => true]);
    }

    private function save(?int $id): void
    {
        $productModel = new Product();

        $name = trim((string)$this->input('name'));
        if ($name === '') {
            flash('error', 'Product name is required.');
            redirect($id ? "/admin/products/{$id}/edit" : '/admin/products/create');
        }

        $categoryIds = array_values(array_filter(array_map('intval', (array)($_POST['category_ids'] ?? []))));
        if (empty($categoryIds)) {
            flash('error', 'Please select at least one category.');
            redirect($id ? "/admin/products/{$id}/edit" : '/admin/products/create');
        }
        $primaryCategoryId = $categoryIds[0];

        $data = [
            'category_id' => $primaryCategoryId,
            'name' => $name,
            'slug' => slugify($name),
            'short_description' => trim((string)$this->input('short_description', '')),
            'description' => (string)$this->input('description', ''),
            'base_price' => (float)$this->input('base_price', 0),
            'sale_price' => $this->input('sale_price') !== '' && $this->input('sale_price') !== null ? (float)$this->input('sale_price') : null,
            'stock_qty' => (int)$this->input('stock_qty', 0),
            'sku' => trim((string)$this->input('sku', '')) ?: null,
            'weight_grams' => $this->input('weight_grams') !== '' ? (int)$this->input('weight_grams') : null,
            'is_featured' => $this->input('is_featured') ? 1 : 0,
            'is_active' => $this->input('is_active') ? 1 : 0,
            'meta_title' => trim((string)$this->input('meta_title', '')) ?: null,
            'meta_description' => trim((string)$this->input('meta_description', '')) ?: null,
        ];

        if ($id) {
            $productModel->update($id, $data);
            $productId = $id;
        } else {
            $productId = $productModel->create($data);
        }

        $productModel->syncCategories($productId, $categoryIds);

        // Image uploads (multiple)
        if (!empty($_FILES['images']['name'][0])) {
            $existingImages = $productModel->images($productId);
            $hasPrimary = !empty(array_filter($existingImages, fn($i) => (int)$i['is_primary'] === 1));

            $count = count($_FILES['images']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $file = [
                    'name' => $_FILES['images']['name'][$i],
                    'type' => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error' => $_FILES['images']['error'][$i],
                    'size' => $_FILES['images']['size'][$i],
                ];
                $path = $this->handleImageUpload($file);
                if ($path) {
                    $isPrimary = !$hasPrimary && $i === 0;
                    $productModel->addImage($productId, $path, $i, $isPrimary);
                    if ($isPrimary) $hasPrimary = true;
                }
            }
        }

        // Set primary image
        $primaryImageId = (int)$this->input('primary_image_id', 0);
        if ($primaryImageId > 0) {
            $productModel->setPrimaryImage($productId, $primaryImageId);
        }

        // Customization options
        $options = $this->collectCustomizationOptions();
        $productModel->replaceCustomizationOptions($productId, $options);

        flash('success', $id ? 'Product updated.' : 'Product created.');
        redirect("/admin/products/{$productId}/edit");
    }

    /**
     * Apply a shared description / details update to many products at once.
     * Reached from the Products list via the "Bulk Edit Description / Details"
     * button, which carries the selected product ids. Renders the bulk-edit
     * form pre-loaded with those ids.
     */
    public function bulkEdit(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
        if (empty($ids)) {
            flash('error', 'Select at least one product to bulk edit.');
            redirect('/admin/products');
        }

        $productModel = new Product();
        $products = $productModel->byIds($ids);

        $this->viewAdmin('admin/products_bulk_edit', [
            'metaTitle' => 'Bulk Edit Description / Details',
            'products' => $products,
            'ids' => $ids,
            'charmSets' => (new CharmSet())->activeWithCounts(),
        ]);
    }

    /**
     * Persist the bulk description / details update. Only the fields whose
     * "update this" toggle was checked are written; the rest are left untouched
     * on every selected product.
     */
    public function bulkEditSave(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
        if (empty($ids)) {
            flash('error', 'No products selected.');
            redirect('/admin/products');
        }

        $updateShort = (bool)$this->input('update_short_description');
        $updateFull = (bool)$this->input('update_description');
        $updateOptions = (bool)$this->input('update_options');

        if (!$updateShort && !$updateFull && !$updateOptions) {
            flash('error', 'Tick at least one field to update.');
            redirect('/admin/products');
        }

        $data = [];
        if ($updateShort) {
            $data['short_description'] = trim((string)$this->input('short_description', ''));
        }
        if ($updateFull) {
            $data['description'] = (string)$this->input('description', '');
        }

        // Parse the customization options once (uploading any gift-wrap image a
        // single time) and reuse the same set across every selected product.
        $options = $updateOptions ? $this->collectCustomizationOptions() : null;

        $productModel = new Product();
        foreach ($ids as $pid) {
            if (!empty($data)) {
                $productModel->update($pid, $data);
            }
            if ($updateOptions) {
                $productModel->replaceCustomizationOptions($pid, $options);
            }
        }

        flash('success', count($ids) . ' product(s) updated.');
        redirect('/admin/products');
    }

    /**
     * Build the customization-option rows from the current POST request.
     * Shared by the single-product save and the bulk-edit save so both honour
     * the same parsing, charge and gift-wrap-image rules.
     */
    private function collectCustomizationOptions(): array
    {
        $options = [];
        $optTypes = (array)($_POST['option_type'] ?? []);
        $optLabels = (array)($_POST['option_label'] ?? []);
        $optRequired = (array)($_POST['option_required'] ?? []);
        $optCharges = (array)($_POST['option_charge'] ?? []);
        $optLimits = (array)($_POST['option_char_limit'] ?? []);
        $optSubOptions = (array)($_POST['option_sub_options'] ?? []);
        $optImageSets = (array)($_POST['option_image_set'] ?? []);
        $optGiftImgExisting = (array)($_POST['option_gift_image_existing'] ?? []);

        foreach ($optTypes as $i => $type) {
            $type = trim((string)$type);
            $label = trim((string)($optLabels[$i] ?? ''));
            if ($type === '' || $label === '') continue;
            $subOptions = $this->parseSubOptions($optSubOptions[$i] ?? '');
            $imageSetId = ($type === 'image_choice' && !empty($optImageSets[$i])) ? (int)$optImageSets[$i] : null;
            // image_choice prices come from the individual charms, so the
            // option-level base charge is always 0 to avoid double-charging.
            $extraCharge = ($type === 'image_choice') ? 0.0 : (float)($optCharges[$i] ?? 0);

            // Gift-wrap preview image: keep the existing one unless a new file
            // was uploaded for this row. Only gift_wrap options carry an image.
            $imagePath = null;
            if ($type === 'gift_wrap') {
                $imagePath = trim((string)($optGiftImgExisting[$i] ?? '')) ?: null;
                $newGiftImg = $this->giftImageFile($i);
                if ($newGiftImg) {
                    $uploaded = $this->handleImageUpload($newGiftImg);
                    if ($uploaded) $imagePath = $uploaded;
                }
            }

            $options[] = [
                'option_type' => $type,
                'label' => $label,
                'is_required' => isset($optRequired[$i]) ? 1 : 0,
                'extra_charge' => $extraCharge,
                'char_limit' => isset($optLimits[$i]) && $optLimits[$i] !== '' ? (int)$optLimits[$i] : null,
                'sub_options' => array_values($subOptions),
                'image_set_id' => $imageSetId,
                'image_path' => $imagePath,
            ];
        }
        return $options;
    }

    /**
     * Normalise the submitted sub-options for a customization option into a
     * clean list of choices: [['label' => 'Gold', 'price' => 20.0, 'image' => true], ...].
     * Accepts the JSON produced by the admin sub-option editor, and falls back
     * to legacy newline-separated plain text (one label per line).
     */
    private function parseSubOptions($raw): array
    {
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $raw = trim((string)$raw);
            if ($raw === '') return [];
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                // Legacy plain-text fallback (one label per line).
                $decoded = array_map(fn($l) => ['label' => $l], array_filter(array_map('trim', explode("\n", $raw))));
            }
        }

        $out = [];
        foreach ($decoded as $choice) {
            if (is_string($choice)) {
                $label = trim($choice);
                $price = 0.0;
                $image = false;
            } else {
                $label = trim((string)($choice['label'] ?? ''));
                $price = (float)($choice['price'] ?? 0);
                $image = !empty($choice['image']);
            }
            if ($label === '') continue;
            $out[] = ['label' => $label, 'price' => $price, 'image' => $image];
        }
        return $out;
    }

    /**
     * Pull a single uploaded gift-wrap image out of the parallel
     * option_gift_image[] file array for the given option-row index.
     * Returns null when no file was provided for that row.
     */
    private function giftImageFile($i): ?array
    {
        $f = $_FILES['option_gift_image'] ?? null;
        if (!is_array($f) || !isset($f['name'][$i])) return null;
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
        return [
            'name' => $f['name'][$i],
            'type' => $f['type'][$i],
            'tmp_name' => $f['tmp_name'][$i],
            'error' => $f['error'][$i],
            'size' => $f['size'][$i],
        ];
    }

    private function handleImageUpload(array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > 5 * 1024 * 1024) return null;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) return null;

        $dir = UPLOAD_PATH . '/products';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = 'prod_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) return null;

        return 'products/' . $filename;
    }
}
