<?php
namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ModerationLogDto
{
    public function __construct(
        public readonly ?int $id = null,

        #[Assert\NotBlank(message: "Video ID is required")]
        public readonly int $videoId,

        #[Assert\Choice(
            choices: ['pending', 'spam', 'not_spam'],
            message: "Invalid video status"
        )]
        public string $status = VideoDto::STATUS_PENDING,

        #[Assert\NotNull(message: "Created timestamp is required")]
        //private ?\DateTimeImmutable $createdAt = null,
        public $createdAt = null,
    ) {
        $this->createdAt = new \DateTimeImmutable($createdAt ? $createdAt : "");
    }
}