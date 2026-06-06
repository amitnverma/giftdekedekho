<?php

class AdminCustomerController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $userModel = new User();
        $search = (string)$this->input('search', '');

        $customers = $search !== '' ? $userModel->search($search, 200) : $userModel->search('', 200);

        $this->viewAdmin('admin/customers_index', [
            'metaTitle' => 'Customers',
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function show(int $id): void
    {
        $this->requireAdmin();
        $userModel = new User();
        $customer = $userModel->find($id);
        if (!$customer || $customer['role'] !== 'customer') { flash('error', 'Customer not found.'); redirect('/admin/customers'); }

        $orderModel = new Order();
        $addressModel = new Address();

        $this->viewAdmin('admin/customers_show', [
            'metaTitle' => $customer['name'],
            'customer' => $customer,
            'orders' => $orderModel->userOrders($id, 50),
            'addresses' => $addressModel->forUser($id),
        ]);
    }

    public function export(): void
    {
        $this->requireAdmin();
        $userModel = new User();
        $customers = $userModel->search('', 100000);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="customers-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Joined']);
        foreach ($customers as $c) {
            fputcsv($out, [$c['id'], $c['name'], $c['email'], $c['phone'] ?? '', $c['created_at']]);
        }
        fclose($out);
        exit;
    }
}
