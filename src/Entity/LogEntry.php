<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\Table(name : "log")]
class LogEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type : Types::STRING,  nullable : false)]
    private string $channel;

    #[ORM\Column(type : Types::STRING,  nullable : false)]
    private string $level;

    #[ORM\Column(type : Types::TEXT,  nullable : false)]
    private string $message;

    #[ORM\Column(type : Types::ARRAY,  nullable : true)]
    private array $context;

    #[ORM\Column(type : Types::DATETIME_IMMUTABLE , nullable: false )]
    private \DateTimeImmutable $createdDateTime;

    public function __construct( string $channel , string $level , string $message , array $context )
    {
        $this->channel = $channel;
        $this->level = $level;
        $this->message = $message;
        $this->context = $context;
        $this->createdDateTime = new \DateTimeImmutable();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return string
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * @return string
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getCreatedDateTime(): \DateTimeImmutable
    {
        return $this->createdDateTime;
    }


}