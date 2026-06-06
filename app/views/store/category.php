<?php
$qs = $_GET;
function gddSortUrl($sort, $qs) { $qs['sort'] = $sort; unset($qs['page']); return '?' . http_build_query($qs); }
function gddPageUrl($page, $qs) { $qs['page'] = $page; return '?' . http_build_query($qs); }
?>
<div class="container">
  <div class="breadcrumbs"><a href="<?= url('/') ?>">Home</a> / <?= e($title) ?></div>
  <h1 class="page-title"><?= e($title) ?></h1>
  <?php if (!empty($filters['q'])): ?>
    <p style="color:var(--color-muted);margin:-8px 0 18px">Showing results for &ldquo;<strong><?= e($filters['q']) ?></strong>&rdquo; — <?= (int)$pagination['totalItems'] ?> match<?= (int)$pagination['totalItems'] === 1 ? '' : 'es' ?></p>
  <?php endif; ?>

  <?php if (!empty($subCategories)): ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px">
      <?php foreach ($subCategories as $sc): ?>
        <a href="<?= url('/category/' . $sc['slug']) ?>" class="btn btn-outline btn-sm"><?= e($sc['name']) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="listing-layout">
    <aside class="filter-sidebar">
      <h4>Filter by Price</h4>
      <form method="get" id="filterForm">
        <?php foreach ($qs as $k => $v) { if (!in_array($k, ['min_price','max_price','page'])) echo '<input type="hidden" name="'.e($k).'" value="'.e($v).'">'; } ?>
        <div class="filter-group">
          <label>Min Price (₹)</label>
          <input type="number" name="min_price" value="<?= e($filters['min_price'] ?? '') ?>" min="0" style="width:100%;padding:8px;border:1px solid var(--color-border);border-radius:6px">
        </div>
        <div class="filter-group">
          <label>Max Price (₹)</label>
          <input type="number" name="max_price" value="<?= e($filters['max_price'] ?? '') ?>" min="0" style="width:100%;padding:8px;border:1px solid var(--color-border);border-radius:6px">
        </div>
        <button type="submit" class="btn btn-primary btn-sm btn-block">Apply Filters</button>
      </form>
    </aside>

    <div>
      <div class="listing-toolbar">
        <span><?= (int)$pagination['totalItems'] ?> products found</span>
        <select class="sort-select" onchange="window.location.href=this.value">
          <option value="<?= e(gddSortUrl('popularity', $qs)) ?>" <?= $sort === 'popularity' ? 'selected' : '' ?>>Sort: Popularity</option>
          <option value="<?= e(gddSortUrl('newest', $qs)) ?>" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
          <option value="<?= e(gddSortUrl('price_asc', $qs)) ?>" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
          <option value="<?= e(gddSortUrl('price_desc', $qs)) ?>" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
          <option value="<?= e(gddSortUrl('rating', $qs)) ?>" <?= $sort === 'rating' ? 'selected' : '' ?>>Customer Rating</option>
        </select>
      </div>

      <?php if (empty($products)): ?>
        <div class="empty-state">
          <div class="icon">🔍</div>
          <h3>No products found</h3>
          <p>Try adjusting your filters or browse other categories.</p>
        </div>
      <?php else: ?>
        <div class="product-grid">
          <?php foreach ($products as $p): ?>
            <?php renderRaw('store/partials/product_card', ['p' => $p, 'inWishlist' => in_array($p['id'], $wishlistIds)]); ?>
          <?php endforeach; ?>
        </div>

        <?php if ($pagination['totalPages'] > 1): ?>
          <div class="pagination">
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
              <a href="<?= e(gddPageUrl($i, $qs)) ?>" class="<?= $i === $pagination['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
