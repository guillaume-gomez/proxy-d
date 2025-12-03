<?php
// src/Controller/BaseController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class BaseController extends AbstractController {

    public function isVideoIdValid(string $dailymotionVideoId): bool {
       $pattern = '/404$/';

        return preg_match($pattern, $dailymotionVideoId);
    }
}