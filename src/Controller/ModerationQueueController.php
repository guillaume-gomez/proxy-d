<?php
// src/Controller/ModerationQueueController.php
namespace App\Controller;

use App\Service\ModerationQueueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ModerationQueueController extends AbstractController
{
    private ModerationQueueService $moderationQueueService;

    public function __construct(ModerationQueueService $moderationQueueService)
    {
        $this->moderationQueueService = $moderationQueueService;
    }

    // #[Route('/lucky/number', methods: ['GET'])]
    //   public function number(): JsonResponse
    //   {
    //     $result = $this->moderationQueueService->addVideo("xs2m8jpp");
    //     return new JsonResponse([
    //         'video_id' => $result,
    //         'status' => "jkj"
    //     ], 201);
    //   }

    #[Route('/add_video', methods: ['POST'])]
    public function addVideo(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['video_id'])) {
            throw new BadRequestHttpException('Video ID is required');
        }

        $video = $this->moderationQueueService->addVideo($data['video_id']);

        return new JsonResponse([
            'video_id' => $video->dailymotionVideoId,
        ], 201);
    }
}