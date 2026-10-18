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
     * @param array|null $payload
     * @return void
     */
    public function setChannelTargetPayload( ?array $payload ): void
    {
        $this->channelTargetPayload = $payload;
    }

    /**
     * @return array|null
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
     * @param \DateTime|null $startDateTime
     */
    public function setStartDateTime(?\DateTime $startDateTime): void
    {
        $this->startDateTime = $startDateTime;
    }

    /**
     * @return bool
     */
    public function eventDone(): bool
    {
        $simpleDone =  ( $this->getDateInterval() === null && $this->getDoneDateTime() !== null );
        $intervalDone = ( $this->getDateInterval() !== null && $this->getDoneDateTime() !== null && $this->getDoneDateTime() >= $this->getUntilDateTime() && $this->getUntilDateTime() !== null  );
        return $simpleDone || $intervalDone;
    }


    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
            'done' => $this->getDoneDateTime()
        ];
    }
}