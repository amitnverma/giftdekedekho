<?php

class AdminDashboardController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();

        $orderModel = new Order();
        $userModel = new User();
        $productModel = new Product();
        $reviewModel = new Review();

        $range = $_GET['range'] ?? 'daily';
        $range = in_array($range, ['daily', 'weekly', 'monthly'], true) ? $range : 'daily';

        $this->viewAdmin('admin/dashboard', [
            'metaTitle' => 'Dashboard',
            'todaysRevenue' => $orderModel->todaysRevenue(),
            'todaysOrders' => $orderModel->todaysOrderCount(),
            'totalCustomers' => $userModel->customerCount(),
            'newCustomersToday' => $userModel->newCustomersToday(),
            'lowStockProducts' => $productModel->lowStock((int)siteSetting('low_stock_threshold', 5)),
            'pendingReviews' => count($reviewModel->pending()),
            'recentOrders' => $orderModel->recentOrders(8),
            'topProducts' => $productModel->topSelling(5),
            'chartRange' => $range,
            'chartData' => $orderModel->revenueChartData($range),
        ]);
    }
}
