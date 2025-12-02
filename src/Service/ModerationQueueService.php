<?php
// src/Service/ModerationQueueService.php
namespace App\Service;

use App\Dto\VideoDto;
use App\Entity\ModerationLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ModerationQueueService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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
        return new VideoDto($results[":id"], $dailymotionVideoId);
    }

    // private function createLog()

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
        // TODO create first log on the video
        return $createdVideo;
    }




    public function getVideo(string $moderator) {

    }

}