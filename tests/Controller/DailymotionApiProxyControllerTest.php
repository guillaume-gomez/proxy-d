<?php
namespace App\Tests\Controller;

use App\Service\DailymotionApiProxyService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class DailymotionApiProxyControllerTest extends WebTestCase
{
    private DailymotionApiProxyService&MockObject $apiProxyService;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Create a mock of the service
        $this->apiProxyService = $this->createMock(DailymotionApiProxyService::class);
        // Replace the service in the container
        $container = static::getContainer();
        $container->set('App\Service\DailymotionApiProxyService', $this->apiProxyService);
    }

    public function testGetVideoInfoWithValidVideoId()
    {
        $videoId = 'xs2m8jpp';
        $videoInfo = [
            'id' => $videoId,
            'title' => 'Test Video',
            'channel' => 'test',
            'owner' => 'test_user',
            'filmstrip_60_url' => 'https://example.com/filmstrip.jpg',
            'embed_url' => 'https://www.dailymotion.com/embed/video/xs2m8jpp',
        ];

        $this->apiProxyService->expects($this->once())
            ->method('getVideoInfo')
            ->with($videoId)
            ->willReturn([true, $videoInfo]);

        $this->client->request('GET', '/get_video_info/' . $videoId);

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('video_id', $response);
        $this->assertArrayHasKey('info', $response);
        $this->assertEquals($videoId, $response['video_id']);
        $this->assertEquals($videoInfo, $response['info']);
    }

    public function testGetVideoInfoWithInvalidVideoId()
    {
        $invalidVideoId = 'test404';

        // Service should not be called for invalid videoId
        $this->apiProxyService->expects($this->never())
            ->method('getVideoInfo');

        $this->client->request('GET', '/get_video_info/' . $invalidVideoId);

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Invalid video id', $response['message']);
    }

    public function testGetVideoInfoWithServiceError()
    {
        $videoId = 'xs2m8jpp';
        $errorResponse = ['error' => 'API Error: Video not found'];

        $this->apiProxyService->expects($this->once())
            ->method('getVideoInfo')
            ->with($videoId)
            ->willReturn([false, $errorResponse]);

        $this->client->request('GET', '/get_video_info/' . $videoId);

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('API Error: Video not found', $response['error']);
    }

    public function testGetVideoInfoWithMockVideoId()
    {
        $videoId = '123456';
        $videoInfo = [
            'id' => '123456',
            'title' => 'Dailymotion Spirit Movie',
            'channel' => 'creation',
            'owner' => 'dailymotion_user',
            'filmstrip_60_url' => 'https://example.com/filmstrip.jpg',
            'embed_url' => 'https://www.dailymotion.com/embed/video/x2m8jpp',
        ];

        $this->apiProxyService->expects($this->once())
            ->method('getVideoInfo')
            ->with($videoId)
            ->willReturn([true, $videoInfo]);

        $this->client->request('GET', '/get_video_info/' . $videoId);

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('video_id', $response);
        $this->assertArrayHasKey('info', $response);
        $this->assertEquals($videoId, $response['video_id']);
        $this->assertEquals($videoInfo, $response['info']);
    }
}

