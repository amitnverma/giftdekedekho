<?php
/**
 * Generates an XML sitemap of public storefront URLs.
 * Included by index.php when /sitemap.xml is requested.
 */

header('Content-Type: application/xml; charset=utf-8');

$db = Database::getInstance();
$baseUrl = rtrim(SITE_URL, '/');

$urls = [];
$urls[] = ['loc' => $baseUrl . '/', 'changefreq' => 'daily', 'priority' => '1.0'];
$urls[] = ['loc' => $baseUrl . '/contact', 'changefreq' => 'monthly', 'priority' => '0.4'];
$urls[] = ['loc' => $baseUrl . '/about', 'changefreq' => 'monthly', 'priority' => '0.4'];

foreach ($db->query('SELECT slug FROM categories WHERE is_active = 1')->fetchAll() as $cat) {
    $urls[] = ['loc' => $baseUrl . '/category/' . $cat['slug'], 'changefreq' => 'weekly', 'priority' => '0.7'];
}

foreach ($db->query('SELECT slug, created_at FROM products WHERE is_active = 1')->fetchAll() as $product) {
    $urls[] = [
        'loc' => $baseUrl . '/product/' . $product['slug'],
        'lastmod' => date('Y-m-d', strtotime($product['created_at'])),
        'changefreq' => 'weekly',
        'priority' => '0.8',
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($url['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8') . "</loc>\n";
    if (!empty($url['lastmod'])) echo '    <lastmod>' . $url['lastmod'] . "</lastmod>\n";
    if (!empty($url['changefreq'])) echo '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
    if (!empty($url['priority'])) echo '    <priority>' . $url['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
exit;
