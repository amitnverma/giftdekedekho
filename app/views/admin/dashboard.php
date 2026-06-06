<div class="admin-grid admin-grid-4">
    <div class="admin-card">
        <p class="admin-kpi-label">Today's Revenue</p>
        <p class="admin-kpi-value"><?= formatPrice($todaysRevenue) ?></p>
        <p class="admin-kpi-sub">From paid orders today</p>
    </div>
    <div class="admin-card">
        <p class="admin-kpi-label">Today's Orders</p>
        <p class="admin-kpi-value"><?= (int)$todaysOrders ?></p>
        <p class="admin-kpi-sub">Orders placed today</p>
    </div>
    <div class="admin-card">
        <p class="admin-kpi-label">Total Customers</p>
        <p class="admin-kpi-value"><?= (int)$totalCustomers ?></p>
        <p class="admin-kpi-sub">+<?= (int)$newCustomersToday ?> new today</p>
    </div>
    <div class="admin-card">
        <p class="admin-kpi-label">Pending Reviews</p>
        <p class="admin-kpi-value"><?= (int)$pendingReviews ?></p>
        <p class="admin-kpi-sub"><a href="<?= url('/admin/reviews') ?>">Moderate now &rarr;</a></p>
    </div>
</div>

<div class="admin-card admin-mt">
    <div class="admin-flex-between">
        <h2 class="admin-card-title">Revenue Overview</h2>
        <div class="admin-filters" style="margin:0;">
            <a class="admin-btn admin-btn-sm <?= $chartRange === 'daily' ? 'admin-btn-primary' : '' ?>" href="?range=daily">Daily</a>
            <a class="admin-btn admin-btn-sm <?= $chartRange === 'weekly' ? 'admin-btn-primary' : '' ?>" href="?range=weekly">Weekly</a>
            <a class="admin-btn admin-btn-sm <?= $chartRange === 'monthly' ? 'admin-btn-primary' : '' ?>" href="?range=monthly">Monthly</a>
        </div>
    </div>
    <canvas id="revenueChart" height="90"></canvas>
</div>

<div class="admin-grid admin-grid-2 admin-mt">
    <div class="admin-card">
        <h2 class="admin-card-title">Recent Orders</h2>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>#</th><th>Customer</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recentOrders as $o): ?>
                    <tr>
                        <td>#<?= (int)$o['id'] ?></td>
                        <td><?= e($o['customer_name']) ?></td>
                        <td><?= formatPrice($o['total']) ?></td>
                        <td><span class="admin-badge admin-badge-blue"><?= e($o['order_status']) ?></span></td>
                        <td><a href="<?= url('/admin/orders/' . $o['id']) ?>" class="admin-btn admin-btn-sm">View</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="5" class="admin-muted">No orders yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card">
        <h2 class="admin-card-title">Top Selling Products</h2>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Units Sold</th></tr></thead>
                <tbody>
                <?php foreach ($topProducts as $p): ?>
                    <tr>
                        <td><?= e($p['name']) ?></td>
                        <td><?= (int)$p['total_sold'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($topProducts)): ?>
                    <tr><td colspan="2" class="admin-muted">No sales data yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h2 class="admin-card-title admin-mt">Low Stock Alerts</h2>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Stock</th></tr></thead>
                <tbody>
                <?php foreach ($lowStockProducts as $p): ?>
                    <tr>
                        <td><?= e($p['name']) ?></td>
                        <td><span class="admin-badge admin-badge-red"><?= (int)$p['stock_qty'] ?> left</span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($lowStockProducts)): ?>
                    <tr><td colspan="2" class="admin-muted">All products well stocked.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>window.GDD_CHART_DATA = <?= json_encode($chartData) ?>;</script>
