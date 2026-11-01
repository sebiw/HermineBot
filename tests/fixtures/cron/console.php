<?php

// Startet die Anwendung wie bin/console im Produktivmodus, ohne Netz und ohne Datenbank.
// Cache und Log legt der Test über APP_CACHE_DIR und APP_LOG_DIR in einen eigenen Ordner, siehe CronOutputTest.

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Runtime\Internal\SymfonyErrorHandler;

$projectDir = dirname(__DIR__, 3);
$fail = ($argv[1] ?? '') === 'fail';

require $projectDir . '/vendor/autoload.php';

(new Dotenv())->bootEnv($projectDir . '/.env');
SymfonyErrorHandler::register(false);

$kernel = new App\Kernel('prod', false);

$command = new class($fail) extends Command {
    public function __construct(private bool $fail)
    {
        parent::__construct('cron-output:probe');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Eine Bibliothek mit veralteter Schreibweise meldet sich schon beim Laden, wie php-imap beim Abruf der Mails
        eval('function cron_output_probe(string $value = null) {}');
        trigger_error('CRON-PROBE veraltet', E_USER_DEPRECATED);
        if ($this->fail) {
            throw new RuntimeException('CRON-PROBE Absturz');
        }
        return Command::SUCCESS;
    }
};

$application = new Application($kernel);
$application->setAutoExit(false);
$application->addCommand($command);
exit($application->run(new ArrayInput(['command' => 'cron-output:probe']), new ConsoleOutput()));
