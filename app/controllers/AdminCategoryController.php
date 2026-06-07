<?php

class AdminCategoryController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $categoryModel = new Category();
        $this->viewAdmin('admin/categories_index', [
            'metaTitle' => 'Categories',
            'categories' => $categoryModel->withProductCount(),
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();
        $categoryModel = new Category();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $this->save($categoryModel, null);
            return;
        }

        $this->viewAdmin('admin/categories_form', [
            'metaTitle' => 'Add Category',
            'category' => null,
            'parents' => $categoryModel->activeTopLevel(),
        ]);
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();
        $categoryModel = new Category();
        $category = $categoryModel->find($id);
        if (!$category) { flash('error', 'Category not found.'); redirect('/admin/categories'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $this->save($categoryModel, $id, $category);
            return;
        }

        $this->viewAdmin('admin/categories_form', [
            'metaTitle' => 'Edit Category',
            'category' => $category,
            'parents' => array_filter($categoryModel->activeTopLevel(), fn($c) => (int)$c['id'] !== $id),
        ]);
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        (new Category())->delete($id);
        flash('success', 'Category deleted.');
        redirect('/admin/categories');
    }

    private function save(Category $categoryModel, ?int $id, ?array $existing = null): void
    {
        $name = trim((string)$this->input('name'));
        $parentId = $this->input('parent_id') ?: null;
        $sortOrder = (int)$this->input('sort_order', 0);
        $isActive = $this->input('is_active') ? 1 : 0;

        if ($name === '') {
            flash('error', 'Category name is required.');
            redirect($id ? "/admin/categories/{$id}/edit" : '/admin/categories/create');
        }

        $slug = slugify($name);
        $imagePath = $existing['image'] ?? null;

        if (!empty($_FILES['image']['name'])) {
            $uploaded = $this->handleImageUpload($_FILES['image']);
            if ($uploaded) $imagePath = $uploaded;
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
            'image' => $imagePath,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ];

        if ($id) {
            $categoryModel->update($id, $data);
            flash('success', 'Category updated.');
        } else {
            $categoryModel->create($data);
            flash('success', 'Category created.');
        }

        redirect('/admin/categories');
    }

    private function handleImageUpload(array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) return null;

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) return null;

        $dir = UPLOAD_PATH . '/categories';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = 'cat_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $destination = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) return null;

        return 'public/uploads/categories/' . $filename;
    }
}
