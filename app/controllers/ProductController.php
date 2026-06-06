<?php

class ProductController extends BaseController
{
    public function show(string $slug): void
    {
        $productModel = new Product();
        $product = $productModel->findBySlug($slug);
        if (!$product) {
            http_response_code(404);
            (new PageController())->notFound();
            return;
        }

        $images = $productModel->images($product['id']);
        $options = $productModel->customizationOptions($product['id']);
        $related = $productModel->relatedProducts($product['category_id'], $product['id']);
        $reviewModel = new Review();
        $reviews = $reviewModel->approvedForProduct($product['id']);
        $ratingSummary = $reviewModel->ratingSummary($product['id']);
        $inWishlist = isLoggedIn() ? (new Wishlist())->has(currentUserId(), $product['id']) : false;

        $this->view('product', [
            'metaTitle' => $product['meta_title'] ?: ($product['name'] . ' | ' . SITE_NAME),
            'metaDescription' => $product['meta_description'] ?: $product['short_description'],
            'ogImage' => !empty($images) ? asset($images[0]['image_path']) : null,
            'product' => $product,
            'images' => $images,
            'options' => $options,
            'related' => $related,
            'reviews' => $reviews,
            'ratingSummary' => $ratingSummary,
            'inWishlist' => $inWishlist,
        ]);
    }
}
