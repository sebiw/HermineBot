<?php

namespace App\Core\Job;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Hintergrundaufgabe, ausgeführt von daemon:tick (Cron jede Minute), sobald ihr Takt fällig ist.
 * An/aus, Takt und die eigene Einstellung stellen Admins unter /jobs ein. Neue Aufgabe = Klasse mit diesem Interface.
 */
#[AutoconfigureTag( self::TAG )]
interface BackgroundJob
{
    const TAG = 'app.background_job';

    /**
     * Fester Schlüssel (Einstellungen hängen daran), z. B. „log-cleanup“
     */
    public function getName(): string;

    public function getLabel(): string;

    public function getDescription(): string;

    public function getDefaultIntervalMinutes(): int;

    public function isEnabledByDefault(): bool;

    /**
     * Eine eigene Zahl als Einstellung, z. B. Aufbewahrung in Tagen
     * @return array{label: string, default: int, min: int, max: int}|null null = keine
     */
    public function getSetting(): ?array;

    /**
     * Fehler dürfen als Exception herausfliegen – der Scheduler hält sie fest
     * @param int|null $setting aktueller Wert der eigenen Einstellung
     * @param bool $interactive true = jemand wartet im Browser, nur kurz arbeiten und den Rest dem Takt lassen
     */
    public function run( ?int $setting , bool $interactive = false ): JobResult;
}
