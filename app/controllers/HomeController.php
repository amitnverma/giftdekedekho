<?php

class HomeController extends BaseController
{
    public function index(): void
    {
        $settings = new Settings();
        $sections = $this->loadSections();
        $categories = (new Category())->activeTopLevel();
        $featured = (new Product())->featured(8);
        $wishlistIds = isLoggedIn() ? (new Wishlist())->userIdsForProduct(currentUserId()) : [];

        $rawOrder = $settings->get('homepage_section_order', '');
        $sectionOrder = $rawOrder ? (json_decode($rawOrder, true) ?: []) : [];

        $this->view('home', [
            'metaTitle' => $settings->get('site_name', SITE_NAME) . ' — ' . $settings->get('site_tagline', 'Personalized Gifts for Every Occasion'),
            'metaDescription' => $settings->get('site_tagline', ''),
            'sections' => $sections,
            'categories' => $categories,
            'featured' => $featured,
            'wishlistIds' => $wishlistIds,
            'sectionOrder' => $sectionOrder,
        ]);
    }

    private function loadSections(): array
    {
        $db = Database::getInstance();
        $rows = $db->query('SELECT section_key, content_json FROM site_sections')->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[$row['section_key']] = json_decode($row['content_json'], true) ?: [];
        }
        return $out;
    }
}
