<?php

namespace App\Controller;

use App\Core\Job\JobScheduler;
use App\Entity\JobState;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Hintergrundaufgaben: an/aus, Takt, eigene Einstellung, letzter Lauf und „jetzt ausführen“. Ausgeführt von daemon:tick (Cron).
 */
#[IsGranted('ROLE_ADMIN')]
class JobController extends AbstractController
{
    use CsrfTokenCheck;

    /** So lange ohne Takt gilt der Cron als ausgefallen */
    const TICK_WARNING_MINUTES = 15;

    #[Route('/jobs', name: 'jobs', methods: [ 'GET' ])]
    public function index( JobScheduler $scheduler ): Response
    {
        $jobs = [];
        foreach( $scheduler->getJobs() AS $name => $job ){
            $jobs[] = [
                'job' => $job,
                'enabled' => $scheduler->isEnabled( $job ),
                'interval' => $scheduler->getIntervalMinutes( $job ),
                'setting' => $job->getSetting(),
                'settingValue' => $scheduler->getSettingValue( $job ),
                'state' => $scheduler->getState( $name ),
                'nextRunAt' => $scheduler->getNextRunAt( $job ),
                'running' => $scheduler->isRunning( $job ),
            ];
        }
        $lastTick = $scheduler->getState( JobScheduler::TICK )?->getLastStartedAt();
        return $this->render('default/jobs.html.twig' , [
            'jobs' => $jobs,
            'lastTick' => $lastTick,
            'tickLate' => $lastTick === null || $lastTick < new \DateTimeImmutable( sprintf( '-%d minutes' , self::TICK_WARNING_MINUTES ) ),
            'statusLabels' => [ JobState::STATUS_DONE => 'erledigt' , JobState::STATUS_IDLE => 'nichts zu tun' , JobState::STATUS_ERROR => 'Fehler' ],
            'minInterval' => JobScheduler::MIN_INTERVAL,
            'maxInterval' => JobScheduler::MAX_INTERVAL,
        ]);
    }

    #[Route('/jobs/{name}/configure', name: 'jobs_configure', requirements: [ 'name' => '[a-z0-9-]+' ], methods: [ 'POST' ])]
    public function configure( string $name , Request $request , JobScheduler $scheduler ): Response
    {
        $job = $scheduler->getJob( $name ) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessCsrfTokenValid( 'job-' . $name , $request );
        $interval = $request->request->getString('interval');
        $setting = $request->request->getString('setting');
        try {
            $scheduler->configure( $job , $request->request->getBoolean('enabled') , ctype_digit( $interval ) ? (int) $interval : 0 , ctype_digit( $setting ) ? (int) $setting : null );
            $this->addFlash( 'success' , sprintf( '„%s“ gespeichert.' , $job->getLabel() ) );
        } catch( \InvalidArgumentException $exception ){
            $this->addFlash( 'error' , $exception->getMessage() );
        }
        return $this->redirectToRoute('jobs');
    }

    #[Route('/jobs/{name}/run', name: 'jobs_run', requirements: [ 'name' => '[a-z0-9-]+' ], methods: [ 'POST' ])]
    public function run( string $name , Request $request , JobScheduler $scheduler ): Response
    {
        $job = $scheduler->getJob( $name ) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessCsrfTokenValid( 'job-' . $name , $request );
        $result = $scheduler->run( $job , true );
        if( $result === null ){
            $this->addFlash( 'error' , sprintf( '„%s“ läuft gerade schon.' , $job->getLabel() ) );
        } elseif( $result->status === JobState::STATUS_ERROR ){
            $this->addFlash( 'error' , sprintf( '„%s“ fehlgeschlagen: %s' , $job->getLabel() , $result->message ) );
        } else {
            $this->addFlash( 'success' , sprintf( '„%s“ ausgeführt%s' , $job->getLabel() , $result->message === null ? '.' : ': ' . $result->message ) );
        }
        return $this->redirectToRoute('jobs');
    }
}
