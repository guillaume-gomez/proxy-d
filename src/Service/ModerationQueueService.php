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
        $connexion = $this->entityManager->getConnection();
        $statement = $connexion->prepare($sql);
        $statement->bindValue('dailymotion_video_id', $dailymotionVideoId);
        $results = $statement->executeQuery()->fetchAllAssociative();

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
        $connexion = $this->entityManager->getConnection();
        $statement = $connexion->prepare($sql);
        $results = $statement->executeQuery()->fetchAllAssociative();
        return $results;
    }

    public function flagVideo(string $dailymotionVideoId, string $status, string $moderator): ?VideoDto {
        if(!$this->canModeratorManage($dailymotionVideoId, $moderator)) {
            return null;
        }
        //TODO check if status value is valid
        $video = $this->updateStatusVideo($dailymotionVideoId, $status);
        $this->createModerationLog($video->id, $moderator, $status);

        return $video;
    }

    private function canModeratorManage(string $dailymotionVideoId, string $moderator): bool {
        $sql = 'SELECT video_id
                FROM moderation_logs
                WHERE moderator = :moderator and video_id = :dailymotion_video_id';
        $connexion = $this->entityManager->getConnection();
        $statement = $connexion->prepare($sql);
        $statement->bindValue('moderator', $moderator);
        $statement->bindValue('dailymotion_video_id', $dailymotionVideoId);
        $result = $statement->executeQuery()->fetchOne();
        return $result;
    }

    private function findVideo(string $dailymotionVideoId): ?VideoDto {
        $sql = 'SELECT id, status, created_at as "createdAt", updated_at as "updatedAt"
        FROM videos WHERE videos.id = :dailymotion_video_id
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
        $sql = 'INSERT INTO videos (id) VALUES (:dailymotion_video_id) RETURNING id';
        $connexion = $this->entityManager->getConnection();
        $statement = $connexion->prepare($sql);
        $statement->bindValue('dailymotion_video_id', $dailymotionVideoId);
        $results = $statement->executeQuery()->fetchAssociative();
        return new VideoDto($results["id"]);
    }

    private function updateStatusVideo(string $dailymotionVideoId, string $status): ?VideoDto {
        $sql = 'UPDATE videos
                SET status = :status
                WHERE id = :dailymotion_video_id
                returning id, status, created_at as "createdAt"
                ';
        $connexion = $this->entityManager->getConnection();
        $statement = $connexion->prepare($sql);
        $statement->bindValue('dailymotion_video_id', $dailymotionVideoId);
        $statement->bindValue('status', $status);
        $results = $statement->executeQuery()->fetchAssociative();
        return new VideoDto(...$results);
    }

    private function createModerationLog(string $videoId, ?string $moderator = null, string $status = VideoDto::STATUS_PENDING) {
        $sql = 'INSERT INTO moderation_logs (video_id, moderator, status) VALUES (:video_id, :moderator, :status) RETURNING id';
        $connexion = $this->entityManager->getConnection();
        $statement = $connexion->prepare($sql);
        $statement->bindValue('video_id', $videoId);
        $statement->bindValue('moderator', $moderator);
        $statement->bindValue('status', $status);
        $results = $statement->executeQuery()->fetchAssociative();
        return new ModerationLogDto($results["id"], $videoId, $moderator, $status);
    }

    private function getVideoId(string $moderator): ?string {
        $sql = "SELECT videos.id
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