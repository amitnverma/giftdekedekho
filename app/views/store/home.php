<?php
// Default section order — this is the canonical order used when no custom order has been saved.
$_defaultSectionOrder = [
    'hero_banner',
    'marquee_strip',
    'why_choose_us',
    'shop_by_category',
    'how_it_works',
    'featured_products_section',
    'signature_feature',
    'trust_badges',
    'testimonials_section',
    'instagram_gallery',
    'newsletter',
];

$_savedOrder = !empty($sectionOrder) ? $sectionOrder : [];

// Merge: saved order first, then append any new sections not yet in saved order
$_renderOrder = $_savedOrder;
foreach ($_defaultSectionOrder as $_key) {
    if (!in_array($_key, $_renderOrder, true)) {
        $_renderOrder[] = $_key;
    }
}

$_sectionPartials = [
    'hero_banner'               => 'store/partials/sections/hero_banner',
    'marquee_strip'             => 'store/partials/sections/marquee_strip',
    'why_choose_us'             => 'store/partials/sections/why_choose_us',
    'shop_by_category'          => 'store/partials/sections/shop_by_category',
    'how_it_works'              => 'store/partials/sections/how_it_works',
    'featured_products_section' => 'store/partials/sections/featured_products_section',
    'signature_feature'         => 'store/partials/sections/signature_feature',
    'trust_badges'              => 'store/partials/sections/trust_badges',
    'testimonials_section'      => 'store/partials/sections/testimonials_section',
    'instagram_gallery'         => 'store/partials/sections/instagram_gallery',
    'newsletter'                => 'store/partials/sections/newsletter',
];

foreach ($_renderOrder as $_sectionKey) {
    if (isset($_sectionPartials[$_sectionKey])) {
        renderRaw($_sectionPartials[$_sectionKey], [
            'sections'    => $sections,
            'categories'  => $categories,
            'featured'    => $featured,
            'wishlistIds' => $wishlistIds,
        ]);
    }
}
