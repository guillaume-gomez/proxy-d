<?php
namespace App\Tests\Service;

use App\Dto\VideoDto;
use App\Service\ModerationQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ModerationQueueServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ModerationQueueService $service;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        $this->service = new ModerationQueueService($this->entityManager);

        // Clean tables before each test
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE moderation_logs CASCADE');
        $connection->executeStatement('TRUNCATE TABLE videos CASCADE');
    }

    public function testAddVideoWithNewVideo()
    {
        $videoId = 'xs2m8jpp';

        $video = $this->service->addVideo($videoId);

        $this->assertInstanceOf(VideoDto::class, $video);
        $this->assertEquals($videoId, $video->id);

        // Verify that the video was created in the database
        $connection = $this->entityManager->getConnection();
        $result = $connection->executeQuery(
            'SELECT * FROM videos WHERE id = :id',
            ['id' => $videoId]
        )->fetchAssociative();

       $this->assertNotFalse($result);
       $this->assertEquals($videoId, $result['id']);

        //Verify that a moderation log was created
        $logResult = $connection->executeQuery(
            'SELECT * FROM moderation_logs WHERE video_id = :id',
            ['id' => $video->id]
        )->fetchAssociative();

        $this->assertNotFalse($logResult);
    }

    public function testAddVideoWithExistingVideo()
    {
        $videoId = 'xs2m8jpp';

        // Create an existing video
        $connection = $this->entityManager->getConnection();
        $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status)',
            ['id' => $videoId, 'status' => 'pending']
        );

        $video = $this->service->addVideo($videoId);

        $this->assertInstanceOf(VideoDto::class, $video);
        $this->assertEquals($videoId, $video->id);

        // Verify that no new video was created
        $count = $connection->executeQuery('SELECT COUNT(*) FROM videos WHERE id = :id', ['id' => $videoId])
            ->fetchOne();
        $this->assertEquals(1, $count);
    }

    public function testGetDailymotionVideoId()
    {
        $moderator = 'john.doe';
        $expectedVideoId = 'xs2m8jpp';

        // Create a pending video and its log
        $connection = $this->entityManager->getConnection();
        $result = $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status) RETURNING id',
            ['id' => $expectedVideoId, 'status' => 'pending']
        )->fetchAssociative();
        //
        $connection->executeQuery(
            'INSERT INTO moderation_logs (video_id) VALUES (:video_id)',
            ['video_id' => $expectedVideoId]
        )->fetchAssociative();
        $videoId = $result['id'];

        $dailymotionVideoId = $this->service->getDailymotionVideoId($moderator);

        $this->assertEquals($expectedVideoId, $dailymotionVideoId);

        // Verify that a log was created with the moderator
        $logResult = $connection->executeQuery(
            'SELECT * FROM moderation_logs WHERE video_id = :id AND moderator = :moderator',
            ['id' => $videoId, 'moderator' => $moderator]
        )->fetchAssociative();

        $this->assertNotFalse($logResult);
    }

    public function testGetDailymotionVideoIdWithNoVideo()
    {
        $moderator = 'john.doe';

        // Do not create any video

        $videoId = $this->service->getDailymotionVideoId($moderator);

        $this->assertNull($videoId);
    }

    public function testGetVideoLogs()
    {
        $dailymotionVideoId = 'xs2m8jpp';
        $moderator = 'john.doe';

        // Create a video and logs
        $connection = $this->entityManager->getConnection();
        $result = $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status) RETURNING id',
            ['id' => $dailymotionVideoId, 'status' => 'pending']
        )->fetchAssociative();
        $videoId = $result['id'];

        // Create two logs
        $connection->executeQuery(
            'INSERT INTO moderation_logs (video_id, moderator, status) VALUES (:video_id, :moderator, :status)',
            ['video_id' => $videoId, 'moderator' => $moderator, 'status' => 'pending']
        );
        $connection->executeQuery(
            'INSERT INTO moderation_logs (video_id, moderator, status) VALUES (:video_id, :moderator, :status)',
            ['video_id' => $videoId, 'moderator' => $moderator, 'status' => 'spam']
        );

        $logs = $this->service->getVideoLogs($dailymotionVideoId);

        $this->assertCount(2, $logs);
        $this->assertEquals('pending', $logs[0]['status']);
        $this->assertEquals('spam', $logs[1]['status']);
        $this->assertEquals($moderator, $logs[0]['moderator']);
    }

    public function testGetVideoLogsWithNoLogs()
    {
        $dailymotionVideoId = 'xs2m8jpp';

        // Create a video without logs
        $connection = $this->entityManager->getConnection();
        $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status)',
            ['id' => $dailymotionVideoId, 'status' => 'pending']
        );

        $logs = $this->service->getVideoLogs($dailymotionVideoId);

        $this->assertEquals([], $logs);
    }

    public function testGetStats()
    {
        // Create videos with different statuses
        $connection = $this->entityManager->getConnection();

        // 3 pending videos
        for ($i = 0; $i < 3; $i++) {
            $connection->executeQuery(
                'INSERT INTO videos (id, status) VALUES (:id, :status)',
                ['id' => 'pending_' . $i, 'status' => 'pending']
            );
        }

        // 2 spam videos
        for ($i = 0; $i < 2; $i++) {
            $connection->executeQuery(
                'INSERT INTO videos (id, status) VALUES (:id, :status)',
                ['id' => 'spam_' . $i, 'status' => 'spam']
            );
        }

        // 1 not_spam video
        $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status)',
            ['id' => 'not_spam_0', 'status' => 'not_spam']
        );

        $stats = $this->service->getStats();

        $this->assertCount(1, $stats);
        $this->assertEquals(3, $stats[0]['total_pending_videos']);
        $this->assertEquals(2, $stats[0]['total_spam_videos']);
        $this->assertEquals(1, $stats[0]['total_not_spam_videos']);
    }

    public function testFlagVideo()
    {
        $dailymotionVideoId = 'xs2m8jpp';
        $status = 'spam';
        $moderator = 'john.doe';

        // Create a video and a moderation log for the moderator
        $connection = $this->entityManager->getConnection();
        $result = $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status) RETURNING id',
            ['id' => $dailymotionVideoId, 'status' => 'pending']
        )->fetchAssociative();
        $videoId = $result['id'];

        // Create a moderation log to allow the moderator to manage the video
        $connection->executeQuery(
            'INSERT INTO moderation_logs (video_id, moderator, status) VALUES (:video_id, :moderator, :status)',
            ['video_id' => $videoId, 'moderator' => $moderator, 'status' => 'pending']
        );

        $video = $this->service->flagVideo($dailymotionVideoId, $status, $moderator);

        $this->assertInstanceOf(VideoDto::class, $video);
        $this->assertEquals($dailymotionVideoId, $video->id);
        $this->assertEquals($status, $video->status);

        // Verify that the status was updated in the database
        $updatedVideo = $connection->executeQuery(
            'SELECT * FROM videos WHERE id = :id',
            ['id' => $dailymotionVideoId]
        )->fetchAssociative();

        $this->assertEquals($status, $updatedVideo['status']);

        // Verify that a new log was created
        $logs = $connection->executeQuery(
            'SELECT * FROM moderation_logs WHERE video_id = :id AND status = :status',
            ['id' => $videoId, 'status' => $status]
        )->fetchAllAssociative();

        $this->assertNotEmpty($logs);
    }

    public function testFlagVideoWithInvalidModerator()
    {
        $dailymotionVideoId = 'xs2m8jpp';
        $status = 'spam';
        $moderator = 'invalid.moderator';

        // Create a video but without a moderation log for this moderator
        $connection = $this->entityManager->getConnection();
        $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status)',
            ['id' => $dailymotionVideoId, 'status' => 'pending']
        );

        $result = $this->service->flagVideo($dailymotionVideoId, $status, $moderator);

        $this->assertNull($result);

        // Verify that the status was not modified
        $video = $connection->executeQuery(
            'SELECT * FROM videos WHERE id = :id',
            ['id' => $dailymotionVideoId]
        )->fetchAssociative();

        $this->assertEquals('pending', $video['status']);
    }
}