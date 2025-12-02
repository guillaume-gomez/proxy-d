<?php

namespace App\Tests\Controller;

use App\Controller\ModerationQueueController;
use App\Service\ModerationQueueService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ModerationQueueControllerTest extends TestCase
{
    private $moderationQueueService;
    private $controller;

    protected function setUp(): void
    {
        $this->moderationQueueService = $this->createMock(ModerationQueueService::class);
        $this->controller = new ModerationQueueController($this->moderationQueueService);
    }

    public function testAddVideoWithValidData()
    {
        $request = new Request([], [], [], [], [], [], json_encode(['video_id' => 'xs2m8jpp']));

        $videoMock = new class {
            public $dailymotionVideoId = 'xs2m8jpp';
        };

        $this->moderationQueueService
            ->method('addVideo')
            ->willReturn($videoMock);

        $response = $this->controller->addVideo($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            json_encode(['video_id' => 'xs2m8jpp']),
            $response->getContent()
        );
    }

    public function testAddVideoWithoutVideoId()
    {
        $request = new Request([], [], [], [], [], [], json_encode([]));

        $this->expectException(BadRequestHttpException::class);
        $this->controller->addVideo($request);
    }
}