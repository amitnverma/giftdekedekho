<?php

class AdminShippingController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $shippingModel = new Shipping();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();

            $data = [
                'label' => trim((string)$this->input('label', 'Standard Shipping')),
                'flat_rate' => (float)$this->input('flat_rate', 0),
                'free_above_amount' => $this->input('free_above_amount') !== '' ? (float)$this->input('free_above_amount') : null,
                'is_active' => 1,
            ];

            $rule = $shippingModel->activeRule();
            if ($rule) {
                $shippingModel->update((int)$rule['id'], $data);
            } else {
                $shippingModel->create($data);
            }

            flash('success', 'Shipping settings updated.');
            redirect('/admin/shipping');
        }

        $this->viewAdmin('admin/shipping_index', [
            'metaTitle' => 'Shipping Settings',
            'rule' => $shippingModel->activeRule(),
            'pincodes' => $shippingModel->allPincodes(500),
        ]);
    }

    public function uploadPincodes(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Please choose a CSV file to upload.');
            redirect('/admin/shipping');
        }

        $shippingModel = new Shipping();
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $count = 0;
        $first = true;

        while (($row = fgetcsv($handle)) !== false) {
            if ($first) {
                $first = false;
                if (!is_numeric(trim((string)($row[0] ?? '')))) continue; // skip header row
            }
            $pincode = trim((string)($row[0] ?? ''));
            if (!preg_match('/^\d{6}$/', $pincode)) continue;

            $serviceable = isset($row[1]) ? (int)trim((string)$row[1]) !== 0 : true;
            $days = isset($row[2]) && is_numeric($row[2]) ? (int)$row[2] : 5;

            $shippingModel->upsertPincode($pincode, $serviceable, $days);
            $count++;
        }
        fclose($handle);

        flash('success', "{$count} pincode(s) imported/updated successfully.");
        redirect('/admin/shipping');
    }
}
