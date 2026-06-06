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

        $data = [
            'category_id' => (int)$this->input('category_id'),
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
        $options = [];
        $optTypes = (array)($_POST['option_type'] ?? []);
        $optLabels = (array)($_POST['option_label'] ?? []);
        $optRequired = (array)($_POST['option_required'] ?? []);
        $optCharges = (array)($_POST['option_charge'] ?? []);
        $optLimits = (array)($_POST['option_char_limit'] ?? []);

        foreach ($optTypes as $i => $type) {
            $type = trim((string)$type);
            $label = trim((string)($optLabels[$i] ?? ''));
            if ($type === '' || $label === '') continue;
            $options[] = [
                'option_type' => $type,
                'label' => $label,
                'is_required' => isset($optRequired[$i]) ? 1 : 0,
                'extra_charge' => (float)($optCharges[$i] ?? 0),
                'char_limit' => isset($optLimits[$i]) && $optLimits[$i] !== '' ? (int)$optLimits[$i] : null,
            ];
        }
        $productModel->replaceCustomizationOptions($productId, $options);

        flash('success', $id ? 'Product updated.' : 'Product created.');
        redirect("/admin/products/{$productId}/edit");
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
