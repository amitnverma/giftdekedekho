<?php

class AdminCouponController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $this->viewAdmin('admin/coupons_index', [
            'metaTitle' => 'Coupons',
            'coupons' => (new Coupon())->all('id DESC'),
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $this->save(new Coupon(), null);
            return;
        }
        $this->viewAdmin('admin/coupons_form', ['metaTitle' => 'Add Coupon', 'coupon' => null]);
    }

    public function edit(int $id): void
    {
        $this->requireAdmin();
        $couponModel = new Coupon();
        $coupon = $couponModel->find($id);
        if (!$coupon) { flash('error', 'Coupon not found.'); redirect('/admin/coupons'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requireCsrf();
            $this->save($couponModel, $id);
            return;
        }
        $this->viewAdmin('admin/coupons_form', ['metaTitle' => 'Edit Coupon', 'coupon' => $coupon]);
    }

    public function delete(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        (new Coupon())->delete($id);
        flash('success', 'Coupon deleted.');
        redirect('/admin/coupons');
    }

    private function save(Coupon $couponModel, ?int $id): void
    {
        $code = trim((string)$this->input('code'));
        if ($code === '') {
            flash('error', 'Coupon code is required.');
            redirect($id ? "/admin/coupons/{$id}/edit" : '/admin/coupons/create');
        }

        $data = [
            'code' => $code,
            'discount_type' => in_array($this->input('discount_type'), ['flat', 'percent'], true) ? $this->input('discount_type') : 'flat',
            'discount_value' => (float)$this->input('discount_value', 0),
            'min_order_value' => (float)$this->input('min_order_value', 0),
            'max_uses' => $this->input('max_uses') !== '' ? (int)$this->input('max_uses') : null,
            'valid_from' => (string)$this->input('valid_from'),
            'valid_to' => (string)$this->input('valid_to'),
            'is_active' => $this->input('is_active') ? 1 : 0,
        ];

        if ($id) {
            $couponModel->update($id, $data);
            flash('success', 'Coupon updated.');
        } else {
            $data['used_count'] = 0;
            $couponModel->create($data);
            flash('success', 'Coupon created.');
        }

        redirect('/admin/coupons');
    }
}
