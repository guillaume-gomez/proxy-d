<?php
namespace App\Tests\Controller;

use App\Dto\VideoDto;
use App\Service\ModerationQueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ModerationQueueControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private ModerationQueueService $moderationQueueService;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        $this->moderationQueueService = new ModerationQueueService($this->entityManager);
        // Clean tables before each test
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE moderation_logs CASCADE');
        $connection->executeStatement('TRUNCATE TABLE videos CASCADE');

    }

    public function testAddVideoWithValidVideoId()
    {
        $this->client->request(
            'POST',
            '/add_video',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['video_id' => 'xs2m8jpp'])
        );

        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('video_id', $response);
        $this->assertEquals('xs2m8jpp', $response['video_id']);
    }

    public function testAddVideoWithoutVideoId()
    {
        $this->client->request(
            'POST',
            '/add_video',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Video ID is required', $response['message']);
    }

    public function testAddVideoWithInvalidVideoId()
    {
        $this->client->request(
            'POST',
            '/add_video',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['video_id' => 'invalid404'])
        );

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Invalid video id', $response['message']);
    }

    public function testFlagVideoWithValidData()
    {
        // First, create a video and moderation log
        $connection = $this->entityManager->getConnection();
        $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status)',
            ['id' => 'xs2m8jpp', 'status' => 'pending']
        );
        $connection->executeQuery(
            'INSERT INTO moderation_logs (video_id, moderator, status) VALUES (:video_id, :moderator, :status)',
            ['video_id' => 'xs2m8jpp', 'moderator' => 'john.doe', 'status' => 'pending']
        );

        $this->client->request(
            'POST',
            '/flag_video',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => base64_encode('john.doe')
            ],
            json_encode([
                'video_id' => 'xs2m8jpp',
                'status' => VideoDto::STATUS_SPAM
            ])
        );

        $this->assertEquals(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('video_id', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('xs2m8jpp', $response['video_id']);
        $this->assertEquals(VideoDto::STATUS_SPAM, $response['status']);
    }

    public function testFlagVideoWithoutVideoId()
    {
        $this->client->request(
            'POST',
            '/flag_video',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => base64_encode('john.doe')
            ],
            json_encode(['status' => VideoDto::STATUS_SPAM])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Video ID is required', $response['message']);
    }

    public function testFlagVideoWithoutStatus()
    {
        $this->client->request(
            'POST',
            '/flag_video',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => base64_encode('john.doe')
            ],
            json_encode(['video_id' => 'xs2m8jpp'])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Status is required', $response['message']);
    }

    public function testFlagVideoWithInvalidStatus()
    {
        $this->client->request(
            'POST',
            '/flag_video',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => base64_encode('john.doe')
            ],
            json_encode([
                'video_id' => 'xs2m8jpp',
                'status' => 'invalid_status'
            ])
        );

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Invalid status', $response['message']);
    }

    public function testFlagVideoWithInvalidVideoId()
    {
        $this->client->request(
            'POST',
            '/flag_video',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => base64_encode('john.doe')
            ],
            json_encode([
                'video_id' => 'invalid404',
                'status' => VideoDto::STATUS_SPAM
            ])
        );

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Invalid video id', $response['message']);
    }

    public function testFlagVideoWithoutAuthentication()
    {
        $this->client->request(
            'POST',
            '/flag_video',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'video_id' => 'xs2m8jpp',
                'status' => VideoDto::STATUS_SPAM
            ])
        );

        $this->assertGreaterThanOrEqual(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testFlagVideoWithUnauthorizedModerator()
    {
        // Create a video but without a moderation log for this moderator
        $connection = $this->entityManager->getConnection();
        $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status)',
            ['id' => 'xs2m8jpp', 'status' => 'pending']
        );

        $this->client->request(
            'POST',
            '/flag_video',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_Authorization' => base64_encode('unauthorized.moderator')
            ],
            json_encode([
                'video_id' => 'xs2m8jpp',
                'status' => VideoDto::STATUS_SPAM
            ])
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Cannot managed this video', $response['message']);
    }

    public function testGetVideoWithValidModerator()
    {
        // Create a pending video
        $connection = $this->entityManager->getConnection();
        $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status)',
            ['id' => 'xs2m8jpp', 'status' => 'pending']
        );
        $connection->executeQuery(
            'INSERT INTO moderation_logs (video_id) VALUES (:video_id)',
            ['video_id' => 'xs2m8jpp']
        );

        $this->client->request(
            'GET',
            '/get_video',
            [],
            [],
            ['HTTP_Authorization' => base64_encode('john.doe')]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('video_id', $response);
        $this->assertEquals('xs2m8jpp', $response['video_id']);
    }

    public function testGetVideoWithNoVideosInQueue()
    {
        $this->client->request(
            'GET',
            '/get_video',
            [],
            [],
            ['HTTP_Authorization' => base64_encode('john.doe')]
        );

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('No videos in queue', $response['message']);
    }

    public function testGetVideoWithoutAuthentication()
    {
        $this->client->request('GET', '/get_video');

        // The authenticated method throws a JsonResponse exception
        $this->assertGreaterThanOrEqual(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testGetLogsWithExistingVideo()
    {
        $dailymotionVideoId = 'xs2m8jpp';
        $connection = $this->entityManager->getConnection();

        // Create a video
        $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status)',
            ['id' => $dailymotionVideoId, 'status' => 'pending']
        );

        // Create moderation logs
        $connection->executeQuery(
            'INSERT INTO moderation_logs (video_id, moderator, status) VALUES (:video_id, :moderator, :status)',
            ['video_id' => $dailymotionVideoId, 'moderator' => 'john.doe', 'status' => 'pending']
        );
        $connection->executeQuery(
            'INSERT INTO moderation_logs (video_id, moderator, status) VALUES (:video_id, :moderator, :status)',
            ['video_id' => $dailymotionVideoId, 'moderator' => 'john.doe', 'status' => 'spam']
        );

        $this->client->request('GET', '/log_video/' . $dailymotionVideoId);

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertCount(2, $response);
    }

    public function testGetLogsWithNonExistentVideo()
    {
        $this->client->request('GET', '/log_video/nonexistent');

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testGetStatsWithAdminToken()
    {
        // Create videos with different statuses
        $connection = $this->entityManager->getConnection();
        $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status)',
            ['id' => 'pending_1', 'status' => 'pending']
        );
        $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status)',
            ['id' => 'spam_1', 'status' => 'spam']
        );
        $connection->executeQuery(
            'INSERT INTO videos (id, status) VALUES (:id, :status)',
            ['id' => 'not_spam_1', 'status' => 'not_spam']
        );

        $this->client->request(
            'GET',
            '/stats',
            [],
            [],
            ['HTTP_TOKEN' => base64_encode('admin_token_dailymotion')]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertCount(1, $response);
        $this->assertArrayHasKey('total_pending_videos', $response[0]);
        $this->assertArrayHasKey('total_spam_videos', $response[0]);
        $this->assertArrayHasKey('total_not_spam_videos', $response[0]);
    }

    public function testGetStatsWithoutAdminToken()
    {
        $this->client->request('GET', '/stats');

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Not an admin', $response['message']);
    }

    public function testGetStatsWithInvalidToken()
    {
        $this->client->request(
            'GET',
            '/stats',
            [],
            [],
            ['HTTP_Token' => base64_encode('invalidToken')]
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Not an admin', $response['message']);
    }
}

