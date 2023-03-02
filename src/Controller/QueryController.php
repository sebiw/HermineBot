<?php

namespace App\Controller;

use App\Core\DatabaseLogger;
use App\Core\THWStashcatConventions;
use App\Entity\Event;
use App\Entity\LogEntry;
use App\Entity\User;
use App\Service\AppService;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LogLevel;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[IsGranted('ROLE_ADMIN')]
class QueryController extends AbstractController
{

    #[Route('/query', name: 'query', methods: [ 'GET' ])]
    public function main( AppService $app , ManagerRegistry $doctrine , Request $request, #[CurrentUser] ?User $user ): Response
    {
        // @TODO: Hermine Bot?
        // Keywords trigger messages? => Kombination mit "Question Bot"

        $stashcatMediator = $app->getMediator();
        $stashcatMediator->login();
        $stashcatMediator->decryptPrivateKey();
        $stashcatMediator->loadCompanies();

        $THWCompany = $stashcatMediator->getCompanyMembers()->getCompanyByName('THW');
        $stashcatMediator->loadChannelSubscripted( $THWCompany );

        // Lade ID der zusetzt bekannten Nachricht, sowie das Datum => Beides nutzen! Nachricht könnte ja gelöscht sein?!

        $THWChannel = $stashcatMediator->getChannelOfCompany( 'T_OWBH_TEST' , $THWCompany );
        $stashcatMediator->decryptChannelPrivateKey( $THWChannel );

        // Datenbank:
        // query_head [ begin time , end time , channel , last sent , next send , title , message?? , repeat request interval??? ] => EVENT NUTZEN???
        // Message aus Config erzeugen nebst freitext!
        // query_config => Groups / Answer options
        // BSP:
        // query_1 | config_type_group | HEUTE => "Schreibe 1 ..."
        // query_1 | config_type_group | MORGEN => "Schreibe 2 ..."
        // query_1 | config_type_group | ÜBERMORGEN => "Schreibe 3 ..."
        // query_1 | config_type_answer_option | YES / 👍 / OK / ✅ / 🆗 ...
        // query_1 | config_type_answer_option | NO / NEIN / KANN NICHT / 👎 / ❌ ...

        // query_messages [ id , timestamp ] => Known data
        // query_result [ head , Person , grouping criteria (multiple slots available) , answer (yes / no), comment ]

        // Beginne das Laden der Nachrichten BIS zu dem genannten Zeitpunkt -> TTL to Prevent Bugs???
        $messages = $stashcatMediator->getMessagesFromChannel( $THWChannel , 30 , 0 );
        $messages = array_reverse( $messages ); // Beginne mit der NEUESTEN Nachricht, nicht der Ältesten
        foreach( $messages AS $message ){
            if( $message->getId()  ){

                $nameParts = THWStashcatConventions::extractNameParts( $message->getSenderLastName() );
                $lastName = $nameParts['last_name'];
                $firstName = $message->getSenderFirstName();
                $department = $nameParts['department'];

                dd( $message->getText() , $message->getId() , $message->getTimeAsDateTime() , $firstName ,  $lastName , $department  );
            }
        }


        // @TODO: Question Bot?
        // Umfragen / Abfragen vereinfachen: Rückmeldungen Lesen unter Bereitstellen um Übersichten zu planen.
        // Kombinieren mit der Helferdislozierungsliste aus dem THWin könnte man Excel-Dateien in Software gießen.
        // Antwort-Optionen durch Emojies? (JA / NEIN)
        // Mehrere Termine: unterschiedliche Emojies / Keywords?
        // => DENNOCH Manuelles Editieren muss möglich sein!!!
        // PRIVATE Nachrichten entschlüsseln?


        // System bauen das:
        // Ab einem Zeitpunkt sich nachrichten merkt die Gelesen und verarbeitet wurden.
        // Last??? Nicht jede Minute triggern => Eigener Trigger-Interval?
        // Sockets wird nicht klappen auf Webspaces...
    }

    // @TODO: Mail to Hermine?
    // Materialanforderungen / Materialeingang automatisch in Hermine Posten?
    // Tracking von Material: Was für Anforderungen wurden erfasst / Welche sind noch offen?

    // @TODO: iCal to Hermine?
    // Trigger events by Kalender entries



}