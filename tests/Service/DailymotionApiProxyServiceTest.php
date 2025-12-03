<?php
namespace App\Tests\Service;

use App\Service\DailymotionApiProxyService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DailymotionApiProxyServiceTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private CacheItemPoolInterface&MockObject $cache;
    private DailymotionApiProxyService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->service = new DailymotionApiProxyService($this->httpClient, $this->cache);
    }

    public function testGetVideoInfoWithInvalidVideoId()
    {
        // A videoId ending with 404 is considered invalid
        $invalidVideoId = 'test404';

        $result = $this->service->getVideoInfo($invalidVideoId);

        $this->assertNull($result);
    }

    public function testGetVideoInfoWithVvalidVideoId()
    {
        // A videoId ending with 404 is considered invalid
        $invalidVideoId = 'test4040';

        $result = $this->service->getVideoInfo($invalidVideoId);

        $this->assertNotNull($result);
    }

    public function testGetVideoInfoWithCacheHit()
    {
        $videoId = 'xs2m8jpp';
        $cachedData = [
            'id' => $videoId,
            'title' => 'Cached Video',
            'channel' => 'test',
            'owner' => 'test_user',
            'filmstrip_60_url' => 'https://example.com/filmstrip.jpg',
            'embed_url' => 'https://www.dailymotion.com/embed/video/xs2m8jpp',
        ];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($cachedData);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with("dailymotion_video_{$videoId}")
            ->willReturn($cacheItem);

        // HTTP client should not be called if cache is hit
        $this->httpClient->expects($this->never())
            ->method('request');

        $result = $this->service->getVideoInfo($videoId);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertTrue($result[0]);
        $this->assertEquals($cachedData, $result[1]);
    }

    public function testGetVideoInfoWithCacheMissAndMockVideoId()
    {
        $videoId = '123456';
        $expectedData = [
            'id' => '123456',
            'title' => 'Dailymotion Spirit Movie',
            'channel' => 'creation',
            'owner' => 'dailymotion_user',
            'filmstrip_60_url' => 'https://example.com/filmstrip.jpg',
            'embed_url' => 'https://www.dailymotion.com/embed/video/x2m8jpp',
        ];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $cacheItem->expects($this->once())
            ->method('set')
            ->with($expectedData)
            ->willReturnSelf();
        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
            ->willReturnSelf();

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with("dailymotion_video_{$videoId}")
            ->willReturn($cacheItem);
        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheItem)
            ->willReturn(true);

        // HTTP client should not be called for the mock videoId
        $this->httpClient->expects($this->never())
            ->method('request');

        $result = $this->service->getVideoInfo($videoId);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertTrue($result[0]);
        $this->assertEquals($expectedData, $result[1]);
    }

    public function testGetVideoInfoWithCacheMissAndRealApiCall()
    {
        $videoId = 'xs2m8jpp';
        $apiResponse = [
            'id' => $videoId,
            'title' => 'Real Video',
            'channel' => 'real_channel',
            'owner' => 'real_owner',
            'filmstrip_60_url' => 'https://example.com/filmstrip.jpg',
            'embed_url' => 'https://www.dailymotion.com/embed/video/xs2m8jpp',
        ];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $cacheItem->expects($this->once())
            ->method('set')
            ->with($apiResponse)
            ->willReturnSelf();
        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
            ->willReturnSelf();

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($apiResponse);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with("dailymotion_video_{$videoId}")
            ->willReturn($cacheItem);
        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheItem)
            ->willReturn(true);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.dailymotion.com/video/' . $videoId . '?fields=id,title,channel,owner,filmstrip_60_url,embed_url'
            )
            ->willReturn($response);

        $result = $this->service->getVideoInfo($videoId);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertTrue($result[0]);
        $this->assertEquals($apiResponse, $result[1]);
    }

    public function testGetVideoInfoWithApiError()
    {
        $videoId = 'xs2m8jpp';
        $errorMessage = 'API Error: Video not found';

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with("dailymotion_video_{$videoId}")
            ->willReturn($cacheItem);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception($errorMessage));

        // Cache should not be saved in case of error
        $this->cache->expects($this->never())
            ->method('save');

        $result = $this->service->getVideoInfo($videoId);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertFalse($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertArrayHasKey('error', $result[1]);
        $this->assertEquals($errorMessage, $result[1]['error']);
    }

    public function testGetVideoInfoWithValidVideoIdNotEndingWith404()
    {
        $videoId = 'xs2m8jpp';
        $apiResponse = [
            'id' => $videoId,
            'title' => 'Test Video',
            'channel' => 'test',
            'owner' => 'test_user',
            'filmstrip_60_url' => 'https://example.com/filmstrip.jpg',
            'embed_url' => 'https://www.dailymotion.com/embed/video/xs2m8jpp',
        ];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $cacheItem->expects($this->once())
            ->method('set')
            ->with($apiResponse)
            ->willReturnSelf();
        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
            ->willReturnSelf();

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('toArray')
            ->willReturn($apiResponse);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with("dailymotion_video_{$videoId}")
            ->willReturn($cacheItem);
        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheItem)
            ->willReturn(true);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.dailymotion.com/video/' . $videoId . '?fields=id,title,channel,owner,filmstrip_60_url,embed_url'
            )
            ->willReturn($response);

        $result = $this->service->getVideoInfo($videoId);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertTrue($result[0]);
        $this->assertEquals($apiResponse, $result[1]);
    }
}

