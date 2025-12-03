<?php
// src/Service/ModerationQueueService.php
namespace App\Service;

use App\Dto\VideoDto;
use App\Dto\ModerationLogDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ModerationQueueService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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
        $this->createModerationLog(4, $moderator);
        return $dailymotionVideoId;
    }

    public function getVideoLogs(string $dailymotionVideoId): array {
        $sql = 'SELECT moderation_logs.created_at as "date", moderation_logs.status, moderation_logs.moderator
                FROM moderation_logs INNER JOIN videos on moderation_logs.video_id = videos.id
                WHERE videos.dailymotion_video_id = :dailymotion_video_id';
        $connexion = $this->entityManager->getConnection();
        $statement = $connexion->prepare($sql);
        $statement->bindValue('dailymotion_video_id', $dailymotionVideoId);
        $results = $statement->executeQuery()->fetchAllAssociative();

        if (!$results) {
            return [];
        }
        return $results;
    }

    private function findVideo(string $dailymotionVideoId): ?VideoDto {
        $sql = 'SELECT id, dailymotion_video_id as "dailymotionVideoId", status, created_at as "createdAt", updated_at as "updatedAt"
        FROM videos WHERE videos.dailymotion_video_id = :dailymotion_video_id
        ';
        $connexion = $this->entityManager->getConnection();
        $statement = $connexion->prepare($sql);
        $statement->bindValue('dailymotion_video_id', $dailymotionVideoId);
        $results = $statement->executeQuery()->fetchAssociative();

        if (!$results) {
            return null;
        }

        return new VideoDto(...$results);
    }

    private function createVideo(string $dailymotionVideoId): VideoDto {
        $sql = 'INSERT INTO videos (dailymotion_video_id) VALUES (:dailymotion_video_id) RETURNING id';
        $connexion = $this->entityManager->getConnection();
        $statement = $connexion->prepare($sql);
        $statement->bindValue('dailymotion_video_id', $dailymotionVideoId);
        $results = $statement->executeQuery()->fetchAssociative();
        return new VideoDto($results["id"], $dailymotionVideoId);
    }

    private function createModerationLog(int $videoId, string $moderator = null) {
        $sql = 'INSERT INTO moderation_logs (video_id, moderator) VALUES (:video_id, :moderator) RETURNING id';
        $connexion = $this->entityManager->getConnection();
        $statement = $connexion->prepare($sql);
        $statement->bindValue('video_id', $videoId);
        $statement->bindValue('moderator', $moderator);
        $results = $statement->executeQuery()->fetchAssociative();
        return new ModerationLogDto($results["id"], $videoId, $moderator);
    }

    private function getVideoId(string $moderator): ?string {
        $sql = "SELECT videos.dailymotion_video_id
                FROM moderation_logs INNER JOIN videos on moderation_logs.video_id = videos.id
                WHERE (videos.status='pending' and moderator = :moderator) OR (videos.status = 'pending' and moderator is null)
                ORDER BY videos.created_at DESC
                LIMIT 1
            ";
        $connexion = $this->entityManager->getConnection();
        $statement = $connexion->prepare($sql);
        $statement->bindValue('moderator', $moderator);
        return $statement->executeQuery()->fetchOne();
    }

}