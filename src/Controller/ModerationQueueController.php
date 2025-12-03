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

    #[Route('/lucky/number', methods: ['GET'])]
      public function number(): JsonResponse
      {
        $result = $this->moderationQueueService->addVideo("xs2m8jpp");
        return new JsonResponse([
            'video_id' => $result,
        ], 201);
      }

    #[Route('/add_video', methods: ['POST'])]
    public function addVideo(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['video_id'])) {
            throw new BadRequestHttpException('Video ID is required');
        }

        $video = $this->moderationQueueService->addVideo($data['video_id']);

        return new JsonResponse([
            'video_id' => $video->id,
        ], 201);
    }

    #[Route('/flag_video', methods: ['POST'])]
    public function flagVideo(Request $request): JsonResponse
    {
        $moderatorName = $this->authenticated($request);
        $data = json_decode($request->getContent(), true);

        if (!isset($data['video_id'])) {
            return new JsonResponse (['message' => 'Video ID is required'], 400);
        }

        if (!isset($data['status'])) {
            return new JsonResponse (['message' => 'status is required'], 400);
        }

        $video = $this->moderationQueueService->flagVideo($data['video_id'], $data['status'], $moderatorName);

        return new JsonResponse([
            'video_id' => $video->id,
            'status' => $video->status
        ], 201);
    }

    #[Route('/get_video', methods: ['GET'])]
    public function getVideo(Request $request): JsonResponse
    {
        $moderatorName = $this->authenticated($request);

        $dailymotionVideoId = $this->moderationQueueService->getDailymotionVideoId($moderatorName);

         if (!$dailymotionVideoId) {
            return new JsonResponse(['message' => 'No videos in queue'], 422);
        }

        return new JsonResponse([
            'video_id' => $dailymotionVideoId,
        ], 200);
    }

    #[Route('/log_video/{dailymotionVideoId}', methods: ['GET'])]
    public function getLogs(string $dailymotionVideoId): JsonResponse
    {
        return new JsonResponse($this->moderationQueueService->getVideoLogs($dailymotionVideoId), 200);
    }

    #[Route('/stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        return new JsonResponse($this->moderationQueueService->getStats(), 200);
    }

    private function authenticated(Request $request) {
        // remove or condition
        $authHeader = $request->headers->get('Authorization', '') ?: "am9obi5kb2U=";
        $moderatorName = base64_decode($authHeader);

        if(!isset($moderatorName)) {
            throw new BadRequestHttpException('Not authenticated');
        }

        return $moderatorName;
    }
}