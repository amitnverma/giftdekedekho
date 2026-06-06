<?php

class WatchController extends BaseController
{
    public function show(string $token): void
    {
        $record = (new OrderVideoPhoto())->findByToken($token);
        $settings = new Settings();
        $logo = $settings->get('logo_path', '/images/GDKD logo.png');
        $siteName = $settings->get('site_name', SITE_NAME);

        if (!$record || !$record['is_active'] || empty($record['admin_video_path'])) {
            renderRaw('store/watch_invalid', ['logo' => $logo, 'siteName' => $siteName]);
            return;
        }

        renderRaw('store/watch_player', [
            'videoUrl' => asset($record['admin_video_path']),
            'logo' => $logo,
            'siteName' => $siteName,
        ]);
    }
}
