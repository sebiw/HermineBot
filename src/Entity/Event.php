<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\Table(name : "events")]
class Event implements \JsonSerializable
{

    /**
     * Nach so vielen Fehlversuchen in Folge wird ein wiederkehrendes Event beim nächsten Termin fortgesetzt,
     * ein einmaliges (oder eines mit ungültigem Intervall) bis zum erneuten Speichern nicht mehr verarbeitet.
     */
    const MAX_FAILED_ATTEMPTS = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type : "datetime")]
    private \DateTime $startDateTime;

    #[ORM\Column(type : "datetime")]
    private \DateTime $dueDateTime;

    #[ORM\Column(type : "datetime" , nullable: true )]
    private ?\DateTime $doneDateTime = null;

    #[ORM\Column(type : "datetime",  nullable : true)]
    private ?\DateTime $untilDateTime = null;

    #[ORM\Column(type : "string",  nullable : true)]
    private ?string $dateInterval = null;

    #[ORM\Column(type : "integer",  nullable : false)]
    private int $transmissionsCount = 0;

    #[ORM\Column(type : "integer",  nullable : false, options: [ 'default' => 0 ])]
    private int $failedAttempts = 0;

    #[ORM\Column(type : "string",  nullable : false)]
    private string $channelTarget = "";

    /** @var array<string, mixed>|null z. B. conversationId bei Unterhaltungen */
    #[ORM\Column(type : "json",  nullable : true)]
    private ?array $channelTargetPayload = null;

    #[ORM\Column(type : "text",  nullable : false)]
    private string $text = "";

    /**
     *
     */
    public function __construct()
    {
        $this->setDueDateTime( new \DateTime() );
        $this->setStartDateTime( new \DateTime() );
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return \DateTime
     */
    public function getDueDateTime(): \DateTime
    {
        return $this->dueDateTime;
    }

    /**
     * @param \DateTime $dueDateTime
     */
    public function setDueDateTime(\DateTime $dueDateTime): void
    {
        $this->dueDateTime = $dueDateTime;
    }

    /**
     * @return \DateTime
     */
    public function getDoneDateTime(): ?\DateTime
    {
        return $this->doneDateTime;
    }

    /**
     * @param ?\DateTime $doneDateTime
     */
    public function setDoneDateTime(?\DateTime $doneDateTime): void
    {
        $this->doneDateTime = $doneDateTime;
    }

    /**
     * @return string
     */
    public function getChannelTarget(): string
    {
        return $this->channelTarget;
    }

    /**
     * @param string $channelTarget
     */
    public function setChannelTarget(string $channelTarget): void
    {
        $this->channelTarget = $channelTarget;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return void
     */
    public function setChannelTargetPayload( ?array $payload ): void
    {
        $this->channelTargetPayload = $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getChannelTargetPayload() : ?array {
        return $this->channelTargetPayload;
    }

    /**
     * @return string|null
     */
    public function getConversationIdFromPayload() : ?string {
        return $this->getChannelTargetPayload()['conversationId'] ?? null;
    }

    /**
     * @param string|null $conversationId
     * @return void
     */
    public function setConversationIdToPayload( ?string $conversationId ): void
    {
        $this->setChannelTargetPayload( array_merge( $this->getChannelTargetPayload() ?? [] , [ 'conversationId' => $conversationId ] ) );
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param string $text
     */
    public function setText(string $text): void
    {
        $this->text = $text;
    }

    /**
     * @return string|null
     */
    public function getDateInterval(): ?string
    {
        return $this->dateInterval;
    }

    /**
     * @param string|null $dateInterval
     */
    public function setDateInterval(?string $dateInterval): void
    {
        $this->dateInterval = $dateInterval;
    }

    /**
     * @return \DateTime
     */
    public function getUntilDateTime(): ?\DateTime
    {
        return $this->untilDateTime;
    }

    /**
     * @param ?\DateTime $untilDateTime
     */
    public function setUntilDateTime(?\DateTime $untilDateTime): void
    {
        $this->untilDateTime = $untilDateTime;
    }

    /**
     * @return int
     */
    public function getTransmissionsCount(): int
    {
        return $this->transmissionsCount;
    }

    /**
     * @return void
     */
    public function increaseTransmissionsCount(): void
    {
        $this->transmissionsCount++;
    }

    /**
     * @return int
     */
    public function getFailedAttempts(): int
    {
        return $this->failedAttempts;
    }

    /**
     * @return void
     */
    public function increaseFailedAttempts(): void
    {
        $this->failedAttempts++;
    }

    /**
     * @return void
     */
    public function resetFailedAttempts(): void
    {
        $this->failedAttempts = 0;
    }

    /**
     * @return bool
     */
    public function eventFailed(): bool
    {
        return $this->failedAttempts >= self::MAX_FAILED_ATTEMPTS;
    }

    /**
     * Nächste Fälligkeit nach dem Intervall, verpasste Intervalle werden übersprungen.
     * @param \DateTime $now
     * @return \DateTime|null null bei einmaligen Events
     * @throws \Exception bei ungültigem Intervall
     */
    public function calculateNextDueDateTime( \DateTime $now ): ?\DateTime
    {
        if( empty( $this->getDateInterval() ) ){
            return null;
        }
        $interval = new \DateInterval( $this->getDateInterval() );
        $dueDate = clone $this->getDueDateTime();
        while( $dueDate <= $now ){
            $previousDueDate = clone $dueDate;
            $dueDate->add( $interval );
            // Schutz vor Endlosschleife bei Intervallen ohne Dauer (z. B. PT0S)
            if( $dueDate <= $previousDueDate ){
                throw new \Exception(sprintf('Interval "%s" does not advance the due date!' , $this->getDateInterval() ));
            }
        }
        return $dueDate;
    }

    /**
     * @return \DateTime|null
     */
    public function getStartDateTime(): ?\DateTime
    {
        return $this->startDateTime;
    }

    /**
     * @param \DateTime $startDateTime
     */
    public function setStartDateTime(\DateTime $startDateTime): void
    {
        $this->startDateTime = $startDateTime;
    }

    /**
     * @return bool
     */
    public function eventDone(): bool
    {
        if( $this->getDateInterval() === null ){
            return $this->getDoneDateTime() !== null;
        }
        // Wiederkehrend: fertig, sobald die nächste Fälligkeit hinter dem Enddatum liegt (nicht schon, wenn der letzte Versand davor lag)
        return $this->getUntilDateTime() !== null && $this->getDueDateTime() > $this->getUntilDateTime();
    }

    /**
     * Soll der Daemon das Event jetzt senden? Fällig, nicht fertig (einmalig: noch nie gesendet; wiederkehrend: Fälligkeit
     * spätestens am Enddatum) und nicht nach zu vielen Fehlversuchen gesperrt.
     */
    public function isDue( \DateTimeInterface $now ): bool
    {
        return $this->getDueDateTime() <= $now
            && !$this->eventDone()
            && !$this->eventFailed();
    }


    const STATE_PLANNED = 'planned';
    const STATE_RUNNING = 'running';
    const STATE_DONE = 'done';
    const STATE_FAILED = 'failed';

    /** Stand für die Übersicht (Abzeichen, Farben wie „Stand der Abfrage“ im StAN-Manager) */
    const STATE_LABELS = [
        self::STATE_PLANNED => '🕓 geplant',
        self::STATE_RUNNING => '🔁 läuft',
        self::STATE_DONE => '✔️ erledigt',
        self::STATE_FAILED => '⛔ gesperrt',
    ];

    /**
     * Gesperrt nach Fehlversuchen, erledigt, wiederkehrend aktiv oder einmalig noch offen
     */
    public function getState(): string
    {
        return match( true ){
            $this->eventFailed() => self::STATE_FAILED,
            $this->eventDone() => self::STATE_DONE,
            $this->getDateInterval() !== null => self::STATE_RUNNING,
            default => self::STATE_PLANNED,
        };
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
            'done' => $this->getDoneDateTime()
        ];
    }
}