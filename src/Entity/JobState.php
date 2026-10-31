<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Einstellungen und letzter Lauf einer Hintergrundaufgabe. Leere Einstellung = Standard der Aufgabe.
 * Der Eintrag „daemon:tick“ hält nur den letzten Takt fest (zeigt, ob der Cron läuft).
 */
#[ORM\Entity()]
#[ORM\Table(name : "job_state")]
class JobState
{
    public const STATUS_DONE = 'done';
    public const STATUS_IDLE = 'idle';
    public const STATUS_ERROR = 'error';

    #[ORM\Id]
    #[ORM\Column(type : Types::STRING, length: 100)]
    private string $name;

    #[ORM\Column(type : Types::BOOLEAN, nullable : true)]
    private ?bool $enabled = null;

    #[ORM\Column(name : "interval_minutes", type : Types::INTEGER, nullable : true)]
    private ?int $intervalMinutes = null;

    #[ORM\Column(name : "setting_value", type : Types::INTEGER, nullable : true)]
    private ?int $settingValue = null;

    #[ORM\Column(type : Types::DATETIME_IMMUTABLE, nullable : true)]
    private ?\DateTimeImmutable $lastStartedAt = null;

    #[ORM\Column(type : Types::STRING, length: 10, nullable : true)]
    private ?string $lastStatus = null;

    #[ORM\Column(type : Types::STRING, length: 500, nullable : true)]
    private ?string $lastMessage = null;

    #[ORM\Column(type : Types::INTEGER, nullable : true)]
    private ?int $lastDurationMs = null;

    public function __construct( string $name )
    {
        $this->name = $name;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function getIntervalMinutes(): ?int
    {
        return $this->intervalMinutes;
    }

    public function getSettingValue(): ?int
    {
        return $this->settingValue;
    }

    public function configure( ?bool $enabled , ?int $intervalMinutes , ?int $settingValue ): void
    {
        $this->enabled = $enabled;
        $this->intervalMinutes = $intervalMinutes;
        $this->settingValue = $settingValue;
    }

    public function getLastStartedAt(): ?\DateTimeImmutable
    {
        return $this->lastStartedAt;
    }

    public function getLastStatus(): ?string
    {
        return $this->lastStatus;
    }

    public function getLastMessage(): ?string
    {
        return $this->lastMessage;
    }

    public function getLastDurationMs(): ?int
    {
        return $this->lastDurationMs;
    }

    public function start( \DateTimeImmutable $now ): void
    {
        $this->lastStartedAt = $now;
        $this->lastStatus = null;
        $this->lastMessage = null;
        $this->lastDurationMs = null;
    }

    public function finish( string $status , ?string $message , int $durationMs ): void
    {
        $this->lastStatus = $status;
        $this->lastMessage = $message === null ? null : mb_substr( $message , 0 , 500 );
        $this->lastDurationMs = $durationMs;
    }
}
