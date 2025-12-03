<?php
// src/Controller/BaseController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\ValidatorService;

class BaseController extends AbstractController {

    public function isVideoIdValid(string $dailymotionVideoId): bool {
       return ValidatorService::isVideoIdValid($dailymotionVideoId);
    }
}