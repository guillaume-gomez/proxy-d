<?php
namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class VideoDto
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SPAM = 'spam';
    public const STATUS_NOT_SPAM = 'not_spam';


    public function __construct(
        #[Assert\NotBlank(message: "Video ID is required")]
        public readonly ?string $id = null,

        #[Assert\Choice(
            choices: ['pending', 'spam', 'not_spam'],
            message: "Invalid video status"
        )]
        public string $status = self::STATUS_PENDING,

        #[Assert\NotNull(message: "Created timestamp is required")]
        //private ?\DateTimeImmutable $createdAt = null,
        public $createdAt = null,

        #[Assert\NotNull(message: "Updated timestamp is required")]
        //private ?\DateTimeImmutable $updatedAt = null,
        public $updatedAt = null,
    ) {
        $this->createdAt = new \DateTimeImmutable($createdAt ? $createdAt : "");
        $this->updatedAt = new \DateTimeImmutable($updatedAt ? $updatedAt : "");
    }
}