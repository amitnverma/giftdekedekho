<?php

class CategoryController extends BaseController
{
    public function show(string $slug): void
    {
        $categoryModel = new Category();
        $productModel = new Product();
        $category = null;
        $categoryId = null;

        if ($slug !== 'all') {
            $category = $categoryModel->findBySlug($slug);
            if (!$category) {
                http_response_code(404);
                (new PageController())->notFound();
                return;
            }
            $categoryId = (int)$category['id'];
        }

        $filters = [
            'min_price' => $this->input('min_price'),
            'max_price' => $this->input('max_price'),
            'q' => trim((string)$this->input('q', '')),
        ];
        $sort = $this->input('sort', 'popularity');
        $page = max(1, (int)$this->input('page', 1));
        $perPage = 12;

        $total = $productModel->countByCategory($categoryId, $filters);
        $pagination = paginate($total, $perPage, $page);
        $products = $productModel->listByCategory($categoryId, $filters, $sort, $perPage, $pagination['offset']);

        $wishlistIds = isLoggedIn() ? (new Wishlist())->userIdsForProduct(currentUserId()) : [];
        $subCategories = $category ? $categoryModel->children($categoryId) : [];

        $title = $category ? $category['name'] : 'All Gifts';
        $this->view('category', [
            'metaTitle' => $category['meta_title'] ?? ($title . ' | ' . SITE_NAME),
            'metaDescription' => $category['meta_description'] ?? ('Shop ' . $title . ' — personalized gifts online in India.'),
            'category' => $category,
            'subCategories' => $subCategories,
            'products' => $products,
            'pagination' => $pagination,
            'sort' => $sort,
            'filters' => $filters,
            'wishlistIds' => $wishlistIds,
            'title' => $title,
        ]);
    }
}
