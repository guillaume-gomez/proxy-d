<?php
// src/Service/DailymotionApiProxyService.php
namespace App\Service;

class ValidatorService {
    public static function isVideoIdValid(string $dailymotionVideoId): bool {
        $pattern = '/404$/';

        return preg_match($pattern, $dailymotionVideoId);
    }
}
