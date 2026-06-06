<?php

class AdminReviewController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $reviewModel = new Review();
        $filter = (string)$this->input('filter', 'pending');

        $reviews = $filter === 'all' ? $reviewModel->all_() : $reviewModel->pending();

        $this->viewAdmin('admin/reviews_index', [
            'metaTitle' => 'Reviews',
            'reviews' => $reviews,
            'filter' => $filter,
        ]);
    }

    public function approve(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        (new Review())->approve($id);
        flash('success', 'Review approved.');
        redirect('/admin/reviews');
    }

    public function reject(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        (new Review())->reject($id);
        flash('success', 'Review rejected and removed.');
        redirect('/admin/reviews');
    }

    public function bulkApprove(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        (new Review())->bulkApprove($ids);
        flash('success', count($ids) . ' review(s) approved.');
        redirect('/admin/reviews');
    }
}
