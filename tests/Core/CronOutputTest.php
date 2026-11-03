<?php

namespace App\Tests\Core;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Alles, was ein Cronjob auf stdout oder stderr ausgibt, verschickt die Hosting-Oberfläche als Mail, auch bei „nur bei Fehlern“.
 * Läuft die Aufgabe jede Minute, sind das Tausende Mails. Deprecations gehören deshalb in eine Logdatei,
 * echte Fehler müssen weiter im Cron ankommen.
 */
class CronOutputTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/cron-output-' . uniqid();
        mkdir($this->workDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workDir);
    }

    public function testDeprecationsStayOutOfCronOutput(): void
    {
        $process = $this->runConsole();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame('', $process->getErrorOutput(), 'Das würde der Cron als Mail verschicken.');
        self::assertSame('', $process->getOutput(), 'Das würde der Cron als Mail verschicken.');

        // Gegenprobe: Ohne das wäre der Test auch grün, wenn gar keine Meldung ausgelöst würde
        $logs = glob($this->workDir . '/log/deprecation*.log') ?: [];
        self::assertNotEmpty($logs, 'Die Deprecation hätte in der Logdatei landen müssen.');
        $log = implode('', array_map('file_get_contents', $logs));
        self::assertStringContainsString('CRON-PROBE veraltet', $log);
        self::assertStringContainsString('cron_output_probe', $log);
    }

    public function testRealErrorsStillReachCronOutput(): void
    {
        $process = $this->runConsole('fail');

        self::assertNotSame(0, $process->getExitCode());
        self::assertStringContainsString('CRON-PROBE Absturz', $process->getErrorOutput());
    }

    private function runConsole(string ...$arguments): Process
    {
        $process = new Process(
            [PHP_BINARY, dirname(__DIR__) . '/fixtures/cron/console.php', ...$arguments],
            dirname(__DIR__, 2),
            ['APP_ENV' => 'prod', 'APP_DEBUG' => '0', 'SHELL_VERBOSITY' => '0', 'APP_CACHE_DIR' => $this->workDir . '/cache', 'APP_LOG_DIR' => $this->workDir . '/log'],
            null,
            120,
        );
        $process->run();

        return $process;
    }
}
