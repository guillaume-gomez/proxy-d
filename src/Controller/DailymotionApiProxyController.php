<?php
// src/Controller/DailymotionApiProxyController
namespace App\Controller;

use App\Service\DailymotionApiProxyService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class DailymotionApiProxyController extends BaseController
{
    private DailymotionApiProxyService $apiProxyService;

    public function __construct(DailymotionApiProxyService $apiProxyService)
    {
        $this->apiProxyService = $apiProxyService;
    }

    #[Route('/get_video_info/{videoId}', methods: ['GET'])]
    public function getVideoInfo(string $videoId): JsonResponse
    {
        if($this->isVideoIdValid($videoId)) {
            return new JsonResponse(['message' => 'Invalid video id'], 422);
        }

        // Retrieve video information
        [$status, $result] = $this->apiProxyService->getVideoInfo($videoId);

        if (!$status) {
            return new JsonResponse($result, 422);
        }

        return new JsonResponse([
            'video_id' => $videoId,
            'info' => $result,
        ], 200);
    }
}