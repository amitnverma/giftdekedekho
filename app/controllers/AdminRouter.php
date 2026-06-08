<?php
/**
 * Routes /admin/* requests to the appropriate admin controller method.
 * Kept separate from the main router for clarity given the size of the admin module.
 */
class AdminRouter
{
    public function dispatch(array $segments, string $method): void
    {
        $path = '/' . implode('/', $segments);
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $id = isset($segments[1]) && is_numeric($segments[1]) ? (int)$segments[1] : null;

        switch (true) {
            case $path === '/login':
                (new AdminAuthController())->login();
                break;
            case $path === '/logout':
                (new AdminAuthController())->logout();
                break;

            case $path === '/' || $path === '':
            case $path === '/dashboard':
                (new AdminDashboardController())->index();
                break;

            // ---- Products ----
            case $path === '/products':
                (new AdminProductController())->index();
                break;
            case $path === '/products/create':
                (new AdminProductController())->create();
                break;
            case preg_match('#^/products/(\d+)/edit$#', $path, $m) === 1:
                (new AdminProductController())->edit((int)$m[1]);
                break;
            case preg_match('#^/products/(\d+)/delete$#', $path, $m) === 1:
                (new AdminProductController())->delete((int)$m[1]);
                break;
            case $path === '/products/bulk':
                (new AdminProductController())->bulkAction();
                break;
            case preg_match('#^/products/(\d+)/image-delete$#', $path, $m) === 1:
                (new AdminProductController())->deleteImage((int)$m[1]);
                break;

            // ---- Categories ----
            case $path === '/categories':
                (new AdminCategoryController())->index();
                break;
            case $path === '/categories/create':
                (new AdminCategoryController())->create();
                break;
            case preg_match('#^/categories/(\d+)/edit$#', $path, $m) === 1:
                (new AdminCategoryController())->edit((int)$m[1]);
                break;
            case preg_match('#^/categories/(\d+)/delete$#', $path, $m) === 1:
                (new AdminCategoryController())->delete((int)$m[1]);
                break;

            // ---- Orders ----
            case $path === '/orders':
                (new AdminOrderController())->index();
                break;
            case preg_match('#^/orders/(\d+)$#', $path, $m) === 1:
                (new AdminOrderController())->show((int)$m[1]);
                break;
            case preg_match('#^/orders/(\d+)/update-status$#', $path, $m) === 1:
                (new AdminOrderController())->updateStatus((int)$m[1]);
                break;
            case preg_match('#^/orders/(\d+)/tracking$#', $path, $m) === 1:
                (new AdminOrderController())->setTracking((int)$m[1]);
                break;
            case preg_match('#^/orders/(\d+)/invoice$#', $path, $m) === 1:
                (new AdminOrderController())->invoice((int)$m[1]);
                break;
            case preg_match('#^/order-items/(\d+)/upload-video$#', $path, $m) === 1:
                (new AdminOrderController())->uploadVideoPhoto((int)$m[1]);
                break;
            case preg_match('#^/order-items/(\d+)/toggle-video$#', $path, $m) === 1:
                (new AdminOrderController())->toggleVideoPhoto((int)$m[1]);
                break;

            // ---- Coupons ----
            case $path === '/coupons':
                (new AdminCouponController())->index();
                break;
            case $path === '/coupons/create':
                (new AdminCouponController())->create();
                break;
            case preg_match('#^/coupons/(\d+)/edit$#', $path, $m) === 1:
                (new AdminCouponController())->edit((int)$m[1]);
                break;
            case preg_match('#^/coupons/(\d+)/delete$#', $path, $m) === 1:
                (new AdminCouponController())->delete((int)$m[1]);
                break;

            // ---- Customers ----
            case $path === '/customers':
                (new AdminCustomerController())->index();
                break;
            case preg_match('#^/customers/(\d+)$#', $path, $m) === 1:
                (new AdminCustomerController())->show((int)$m[1]);
                break;
            case $path === '/customers/export':
                (new AdminCustomerController())->export();
                break;

            // ---- Reviews ----
            case $path === '/reviews':
                (new AdminReviewController())->index();
                break;
            case preg_match('#^/reviews/(\d+)/approve$#', $path, $m) === 1:
                (new AdminReviewController())->approve((int)$m[1]);
                break;
            case preg_match('#^/reviews/(\d+)/reject$#', $path, $m) === 1:
                (new AdminReviewController())->reject((int)$m[1]);
                break;
            case $path === '/reviews/bulk-approve':
                (new AdminReviewController())->bulkApprove();
                break;

            // ---- Shipping ----
            case $path === '/shipping':
                (new AdminShippingController())->index();
                break;
            case $path === '/shipping/pincode-upload':
                (new AdminShippingController())->uploadPincodes();
                break;

            // ---- Design Editor ----
            case $path === '/design':
                (new AdminDesignController())->index();
                break;
            case $path === '/design/save':
                (new AdminDesignController())->save();
                break;
            case $path === '/design/layout/save':
                (new AdminDesignController())->saveLayout();
                break;

            // ---- Notification settings ----
            case $path === '/notifications':
                (new AdminSettingsController())->notifications();
                break;

            // ---- General settings ----
            case $path === '/settings':
                (new AdminSettingsController())->general();
                break;
            case $path === '/settings/payments':
                (new AdminSettingsController())->payments();
                break;

            default:
                http_response_code(404);
                echo 'Admin page not found.';
        }
    }
}
