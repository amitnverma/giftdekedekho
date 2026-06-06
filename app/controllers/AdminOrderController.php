<?php

class AdminOrderController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        $orderModel = new Order();

        $filters = [
            'status' => $this->input('status', ''),
            'payment_gateway' => $this->input('payment_gateway', ''),
            'search' => $this->input('search', ''),
            'date_from' => $this->input('date_from', ''),
            'date_to' => $this->input('date_to', ''),
        ];
        $page = max(1, (int)$this->input('page', 1));
        $perPage = 20;

        $total = $orderModel->adminCount($filters);
        $pagination = paginate($total, $perPage, $page);
        $orders = $orderModel->adminList($filters, $perPage, $pagination['offset']);

        $this->viewAdmin('admin/orders_index', [
            'metaTitle' => 'Orders',
            'orders' => $orders,
            'filters' => $filters,
            'pagination' => $pagination,
        ]);
    }

    public function show(int $id): void
    {
        $this->requireAdmin();
        $orderModel = new Order();
        $order = $orderModel->findWithItems($id);
        if (!$order) { flash('error', 'Order not found.'); redirect('/admin/orders'); }

        $this->viewAdmin('admin/orders_show', [
            'metaTitle' => 'Order #' . $id,
            'order' => $order,
            'address' => json_decode($order['address_snapshot_json'], true) ?: [],
        ]);
    }

    public function updateStatus(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $orderModel = new Order();
        $order = $orderModel->find($id);
        if (!$order) { flash('error', 'Order not found.'); redirect('/admin/orders'); }

        $status = (string)$this->input('order_status');
        $allowed = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
        if (!in_array($status, $allowed, true)) {
            flash('error', 'Invalid status.');
            redirect("/admin/orders/{$id}");
        }

        $orderModel->updateStatus($id, $status);

        $notifier = new NotificationService();
        try {
            if ($status === 'confirmed') $notifier->sendOrderConfirmed($id);
            if ($status === 'shipped') $notifier->sendOrderShipped($id);
            if ($status === 'delivered') $notifier->sendOrderDelivered($id);
        } catch (Throwable $e) {
            error_log('Notification error: ' . $e->getMessage());
        }

        if ($status === 'confirmed' && empty($order['shiprocket_order_id'])) {
            try { (new ShiprocketService())->createShipmentForOrder($id); } catch (Throwable $e) { error_log('Shiprocket error: ' . $e->getMessage()); }
        }

        flash('success', 'Order status updated to "' . $status . '".');
        redirect("/admin/orders/{$id}");
    }

    public function setTracking(int $id): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $trackingNumber = trim((string)$this->input('tracking_number', ''));
        $trackingUrl = trim((string)$this->input('tracking_url', '')) ?: null;

        (new Order())->setTracking($id, $trackingNumber, $trackingUrl);
        flash('success', 'Tracking information updated.');
        redirect("/admin/orders/{$id}");
    }

    public function invoice(int $id): void
    {
        $this->requireAdmin();
        $orderModel = new Order();
        $order = $orderModel->findWithItems($id);
        if (!$order) { flash('error', 'Order not found.'); redirect('/admin/orders'); }

        $address = json_decode($order['address_snapshot_json'], true) ?: [];

        $fpdfLib = BASE_PATH . '/libs/fpdf/fpdf.php';
        if (is_file($fpdfLib)) {
            require_once $fpdfLib;
            $this->renderPdfInvoice($order, $address);
            return;
        }

        // Fallback: printable HTML invoice if FPDF library isn't installed
        renderRaw('admin/invoice_html', [
            'order' => $order,
            'address' => $address,
            'metaTitle' => 'Invoice #' . $id,
        ]);
    }

    private function renderPdfInvoice(array $order, array $address): void
    {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, siteSetting('site_name', SITE_NAME), 0, 1);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 7, 'Tax Invoice', 0, 1);
        $pdf->Ln(4);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, 'Order #' . $order['id'] . '   |   Date: ' . date('d M Y', strtotime($order['created_at'])), 0, 1);
        $pdf->Ln(2);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, 'Shipping Address:', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 5, ($address['full_name'] ?? '') . "\n" . ($address['address_line1'] ?? '') . ' ' . ($address['address_line2'] ?? '') . "\n" . ($address['city'] ?? '') . ', ' . ($address['state'] ?? '') . ' - ' . ($address['pincode'] ?? '') . "\nPhone: " . ($address['phone'] ?? ''));
        $pdf->Ln(4);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(90, 7, 'Item', 1);
        $pdf->Cell(25, 7, 'Qty', 1, 0, 'C');
        $pdf->Cell(35, 7, 'Unit Price', 1, 0, 'R');
        $pdf->Cell(40, 7, 'Total', 1, 1, 'R');

        $pdf->SetFont('Arial', '', 10);
        foreach ($order['items'] as $item) {
            $lineTotal = (float)$item['unit_price'] * (int)$item['quantity'];
            $pdf->Cell(90, 7, $item['product_name_snapshot'], 1);
            $pdf->Cell(25, 7, (string)$item['quantity'], 1, 0, 'C');
            $pdf->Cell(35, 7, CURRENCY_SYMBOL . number_format((float)$item['unit_price'], 2), 1, 0, 'R');
            $pdf->Cell(40, 7, CURRENCY_SYMBOL . number_format($lineTotal, 2), 1, 1, 'R');
        }

        $pdf->Ln(2);
        $pdf->Cell(150, 7, 'Subtotal', 0, 0, 'R');
        $pdf->Cell(40, 7, CURRENCY_SYMBOL . number_format((float)$order['subtotal'], 2), 0, 1, 'R');
        $pdf->Cell(150, 7, 'Discount', 0, 0, 'R');
        $pdf->Cell(40, 7, '- ' . CURRENCY_SYMBOL . number_format((float)$order['discount'], 2), 0, 1, 'R');
        $pdf->Cell(150, 7, 'Shipping', 0, 0, 'R');
        $pdf->Cell(40, 7, CURRENCY_SYMBOL . number_format((float)$order['shipping_charge'], 2), 0, 1, 'R');
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(150, 8, 'Grand Total', 0, 0, 'R');
        $pdf->Cell(40, 8, CURRENCY_SYMBOL . number_format((float)$order['total'], 2), 0, 1, 'R');

        $pdf->Ln(6);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, 'Payment Method: ' . strtoupper($order['payment_gateway']) . '   |   Payment Status: ' . ucfirst($order['payment_status']), 0, 1);
        $pdf->Cell(0, 5, 'Thank you for shopping with ' . siteSetting('site_name', SITE_NAME) . '!', 0, 1);

        $pdf->Output('I', 'invoice-' . $order['id'] . '.pdf');
        exit;
    }

    public function uploadVideoPhoto(int $orderItemId): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $orderModel = new Order();
        $stmt = Database::getInstance()->prepare('SELECT * FROM order_items WHERE id = ?');
        $stmt->execute([$orderItemId]);
        $orderItem = $stmt->fetch();
        if (!$orderItem) { flash('error', 'Order item not found.'); redirect('/admin/orders'); }

        if (empty($_FILES['video']['name'])) {
            flash('error', 'Please choose a video file to upload.');
            redirect("/admin/orders/{$orderItem['order_id']}");
        }

        $file = $_FILES['video'];
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 100 * 1024 * 1024) {
            flash('error', 'Video upload failed. Maximum size is 100MB.');
            redirect("/admin/orders/{$orderItem['order_id']}");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'];
        if (!isset($allowed[$mime])) {
            flash('error', 'Unsupported video format. Please upload MP4, MOV or WebM.');
            redirect("/admin/orders/{$orderItem['order_id']}");
        }

        $dir = UPLOAD_PATH . '/videos';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = 'video_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            flash('error', 'Could not save the uploaded video.');
            redirect("/admin/orders/{$orderItem['order_id']}");
        }
        $videoPath = 'videos/' . $filename;

        $videoPhotoModel = new OrderVideoPhoto();
        $existing = $videoPhotoModel->findByOrderItem($orderItemId);

        $token = $existing['token'] ?? $videoPhotoModel->generateUniqueToken();
        $scanUrl = rtrim(SITE_URL, '/') . '/watch/' . $token;

        $qrDir = UPLOAD_PATH . '/qrcodes';
        if (!is_dir($qrDir)) mkdir($qrDir, 0755, true);
        $qrFilename = 'qr_' . $token . '.png';
        $qrDestination = $qrDir . '/' . $qrFilename;
        (new QrCodeService())->generate($scanUrl, $qrDestination);
        $qrPath = 'qrcodes/' . $qrFilename;

        if ($existing) {
            $db = Database::getInstance();
            $db->prepare('UPDATE order_video_photos SET admin_video_path = ?, qr_code_path = ?, scan_url = ?, is_active = 1 WHERE id = ?')
               ->execute([$videoPath, $qrPath, $scanUrl, $existing['id']]);
        } else {
            $videoPhotoModel->create($orderItemId, $token, $videoPath, $qrPath, $scanUrl);
        }

        flash('success', 'Video uploaded and QR code generated successfully.');
        redirect("/admin/orders/{$orderItem['order_id']}");
    }

    public function toggleVideoPhoto(int $orderItemId): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $videoPhotoModel = new OrderVideoPhoto();
        $record = $videoPhotoModel->findByOrderItem($orderItemId);
        if (!$record) jsonResponse(['ok' => false, 'message' => 'No upload found.'], 404);

        $videoPhotoModel->setActive((int)$record['id'], !$record['is_active']);
        jsonResponse(['ok' => true, 'is_active' => !$record['is_active']]);
    }
}
