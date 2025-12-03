<?php
// src/Service/ModerationQueueService.php
namespace App\Service;

use App\Dto\VideoDto;
use App\Dto\ModerationLogDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Connection;

class ModerationQueueService
{
    private EntityManagerInterface $entityManager;
    private Connection $connexion;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->connexion = $this->entityManager->getConnection();
    }

    /**
     * Add a new video to the moderation queue
     */
    public function addVideo(string $videoId)
    {
        $foundVideo = $this->findVideo($videoId);
        if($foundVideo) {
            return $foundVideo;
        }

        $createdVideo = $this->createVideo($videoId);
        $this->createModerationLog($createdVideo->id);
        return $createdVideo;
    }

    public function getDailymotionVideoId(string $moderator): ?string  {
        $dailymotionVideoId = $this->getVideoId($moderator);
        if(!$dailymotionVideoId) {
            return null;
        }
        $this->createModerationLog($dailymotionVideoId, $moderator);
        return $dailymotionVideoId;
    }

    public function getVideoLogs(string $dailymotionVideoId): array {
        $sql = 'SELECT moderation_logs.created_at as "date", moderation_logs.status, moderation_logs.moderator
                FROM moderation_logs
                WHERE moderation_logs.video_id = :dailymotion_video_id';
        $results = $this->connexion->executeQuery(
            $sql, ['dailymotion_video_id' => $dailymotionVideoId]
        )->fetchAllAssociative();

        if (!$results) {
            return [];
        }
        return $results;
    }

    // TODO OPTIMISE
    public function getStats() : array {
        $sql = "SELECT
                COUNT(CASE WHEN status = 'pending' THEN 1 END) AS total_pending_videos,
                COUNT(CASE WHEN status = 'spam' THEN 1 END) AS total_spam_videos,
                COUNT(CASE WHEN status = 'not_spam' THEN 1 END) AS total_not_spam_videos
                FROM videos;
            ";
        $results = $this->connexion->executeQuery($sql)->fetchAllAssociative();
        return $results;
    }

    public function flagVideo(string $dailymotionVideoId, string $status, string $moderator): ?VideoDto {
        // check already done in controller, but this service could be use elsewhere
        if(!in_array($status, [VideoDto::STATUS_SPAM, VideoDto::STATUS_NOT_SPAM]) ) {
            return null;
        }

        if(!$this->canModeratorManage($dailymotionVideoId, $moderator)) {
            return null;
        }
        $video = $this->updateStatusVideo($dailymotionVideoId, $status);
        if($video->status !== VideoDto::STATUS_PENDING) {
            return null;
        }

        $this->createModerationLog($video->id, $moderator, $status);

        return $video;
    }

    private function canModeratorManage(string $dailymotionVideoId, string $moderator): bool {
        $sql = 'SELECT video_id
                FROM moderation_logs
                WHERE moderator = :moderator and video_id = :dailymotion_video_id';
        $result = $this->connexion->executeQuery(
            $sql,
            ['moderator' => $moderator, 'dailymotion_video_id' => $dailymotionVideoId]
        )->fetchOne();
        return (bool) $result;
    }

    private function findVideo(string $dailymotionVideoId): ?VideoDto {
        $connexion = $this->entityManager->getConnection();
        $sql = 'SELECT id, status, created_at as "createdAt", updated_at as "updatedAt"
        FROM videos WHERE videos.id = :dailymotion_video_id
        ';
        $results = $connexion->executeQuery(
            $sql,
            ['dailymotion_video_id' => $dailymotionVideoId]
        )->fetchAssociative();

        if (!$results) {
            return null;
        }
        return new VideoDto(...$results);
    }

    private function createVideo(string $dailymotionVideoId): VideoDto {
        $sql = 'INSERT INTO videos (id) VALUES (:dailymotion_video_id) RETURNING id';
        $results = $this->connexion->executeQuery(
            $sql,
            ['dailymotion_video_id' => $dailymotionVideoId]
        )->fetchAssociative();
        return new VideoDto($results["id"]);
    }

    private function updateStatusVideo(string $dailymotionVideoId, string $status): ?VideoDto {
        $sql = 'UPDATE videos
                SET status = :status
                WHERE id = :dailymotion_video_id
                returning id, status, created_at as "createdAt"
                ';
        $results = $this->connexion->executeQuery(
            $sql,
            ['dailymotion_video_id' => $dailymotionVideoId, 'status' => $status]
        )->fetchAssociative();
        return new VideoDto(...$results);
    }

    private function createModerationLog(string $videoId, ?string $moderator = null, string $status = VideoDto::STATUS_PENDING) {
        $sql = 'INSERT INTO moderation_logs (video_id, moderator, status) VALUES (:video_id, :moderator, :status) RETURNING id';
        $results = $this->connexion->executeQuery(
            $sql,
            [
                'video_id' => $videoId,
                'moderator' => $moderator,
                'status' => $status
            ]
        )->fetchAssociative();
        return new ModerationLogDto($results["id"], $videoId, $moderator, $status);
    }

    private function getVideoId(string $moderator): ?string {
        $sql = "SELECT videos.id
                FROM moderation_logs INNER JOIN videos on moderation_logs.video_id = videos.id
                WHERE (videos.status='pending' and moderator = :moderator) OR (videos.status = 'pending' and moderator is null)
                ORDER BY videos.created_at DESC
                LIMIT 1
            ";
        return $this->connexion->executeQuery($sql, ['moderator' => $moderator])->fetchOne();
    }

}