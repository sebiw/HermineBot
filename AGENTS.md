# Hermine-Bot

Symfony-7.4-LTS-Anwendung (PHP 8.5, Abhängigkeiten kompatibel ab PHP 8.2), die automatisiert Nachrichten in Channels und Unterhaltungen des Messengers Stashcat/Hermine (THW) verschickt – über einen normalen Benutzer-Account. Hobby-Projekt ohne Bezug zur stashcat GmbH.

**Das Repo ist öffentlich** – keine Zugangsdaten, Produktiv-URLs, Hostnamen, Server-Pfade, Channel-Inhalte oder personenbezogenen Daten committen.

## Fachliche Bausteine

- **Events** (`Entity/Event`, Tabelle `events`, `EventsController`, `/events`): geplante Nachrichten mit Ziel (`channelTarget` = Channel-Name oder Unterhaltungs-Platzhalter, Unterhaltungs-ID in `channelTargetPayload.conversationId`), Text, Start (`startDateTime`), Fälligkeit (`dueDateTime`), optionalem Intervall (`dateInterval`, ISO 8601, erlaubte Werte in `allowed_intervals`), Enddatum (`untilDateTime`), `doneDateTime` und `transmissionsCount`.
  - Formular-Aktionen (`POST /events`, Feld `action`): `add`/`save`, `edit`, `delete`, `trigger` (sendet sofort über `hermine:send-message`, ändert die Fälligkeit nicht). Fälligkeit wird nur bei `resetDue=1` auf das Startdatum gesetzt.
  - Speichern nur für Channels aus `allowed_channel` (plus Unterhaltungs-Platzhalter); beim Platzhalter ist die Unterhaltungs-ID Pflicht.
  - Unterhaltung wählen: Ist der Platzhalter gewählt, lädt das Formular per AJAX `GET /events/conversations` (nur lesend, JSON `{status, conversations: [{id, label}]}`, Label = Mitgliedernamen ohne eigenen Account über `StashcatMediator::getConversationLabel()`), nur offene Unterhaltungen (`message/conversations`, `archive=0`). Ein Klick öffnet die Liste mit Filterfeld (eigenes Aufklappmenü, Mitgliedernamen tolerant über `User::getDisplayName()`, gelöschte Accounts ohne Namen); die Auswahl füllt nur das ID-Feld, bei Ladefehlern zeigt das Formular die Fehlermeldung; gespeichert wird immer das ID-Feld, so bleiben IDs nicht gelisteter (z. B. archivierter) Unterhaltungen erhalten. Senden an archivierte Unterhaltungen scheitert derzeit, weil `daemon:tick`/`hermine:send-message` die Unterhaltung nur in dieser Liste suchen.
- **Unterhaltungen**: Ziel ist der Pseudo-Channel `conversation_channel_placeholder_name` (Standard `[[Konversation]]`, `Core/Config::DEFAULT_CHANNEL_CONVERSATION_PLACEHOLDER`). Er wird von `getAllowedChannelNames()` immer automatisch zugelassen; die eigentliche Unterhaltung kommt über die ID.
- **Daemon** (`daemon:tick`, `Command/Daemon`): verarbeitet fällige Events – Platzhalter ersetzen, senden, nächste Fälligkeit berechnen (verpasste Intervalle werden übersprungen), eigene Nachricht liken. Liest vorher JSON-Dateien (`message`, `channel`, `context`) aus `MESSAGE_FILES_IN` (Altlast, Datei-Variante): gesendet → gelöscht, fehlerhaft oder fehlgeschlagen → in `*.json.failed` umbenannt (wird nicht erneut verarbeitet); Fehler dort brechen den Tick nicht ab.
  - Fällig: `dueDateTime <= jetzt` und entweder noch nie erledigt, oder mit Intervall und `doneDateTime < untilDateTime` bzw. ohne Enddatum – jeweils nur mit `failedAttempts < Event::MAX_FAILED_ATTEMPTS` (3).
  - Ablauf pro Event: erst vorbereiten (nächste Fälligkeit über `Event::calculateNextDueDateTime()`, Ziel laden, Platzhalter ersetzen), dann senden, dann sofort speichern, zuletzt liken (Fehler dort nur geloggt). Fehler werden pro Event abgefangen und blockieren die übrigen nicht.
  - Fehlversuche (`registerFailedAttempt()`): Zähler `failedAttempts` erhöhen, beim nächsten Tick erneut versuchen. Beim dritten Fehlversuch wird ein Intervall-Event auf den nächsten Termin geschoben (Zähler zurück auf 0), ein einmaliges Event bzw. eines mit ungültigem Intervall bleibt gesperrt (rot in `/events`), bis es erneut gespeichert wird. Erfolg setzt den Zähler zurück.
  - Parallele Läufe sperrt `flock` auf `var/daemon-tick.lock` (gilt auch für `/events/execute`); ein zweiter Tick beendet sich sofort mit Status `LOCKED`. `Core/RestClient` bricht nach 10 s Verbindungsaufbau bzw. 30 s Gesamtdauer ab.
  - Restrisiko: Scheitert das Speichern direkt nach erfolgreichem Senden (DB weg) oder läuft das Senden in den Timeout, obwohl Hermine die Nachricht angenommen hat, wird beim nächsten Tick erneut gesendet.
- **Platzhalter** (`Core/MessageDecorator`): Syntax `{{Name}}` für Platzhalter, `{%Name%}` für Befehle (externe Abrufe). Ersetzt werden nur Schlüssel, die im Originaltext vorkommen, in einem Durchgang (eingesetzte Ergebnisse werden nicht erneut ausgewertet); Callbacks, die `null` oder Nicht-Skalare liefern, bleiben stehen. Liste der verfügbaren Schlüssel steht im Events-Formular.
  - Eingebaut: `{{CompanyActiveUser}}`, `{{CompanyCreatedUser}}`, `{{CompanyName}}`, `{{ChannelUserCount}}` (nur bei Channels), `{{CompanyGroupsStats}}`, `{{EventInterval}}` (Bezeichnung des Intervalls, nur bei Events).
  - `data/placeholder.json`: Schlüssel `<context>:<key>` → `{ message, message_datetime }`, geschrieben von `HerminePlaceholderHandler` aus `HermineBotCom\Placeholder`.
  - `config/legacy/command_api_endpoints.json`: Schlüssel = Befehlsname, Pflichtfeld `type` (Einträge ohne `type` werden ignoriert):
    - `REST_JSON`: `POST url` mit JSON-`payload` (z. B. `user`/`key` für die StAN-Manager-Reports `/api/open-messages`, `/api/open-files`), Ergebnis aus `content` oder `message`.
    - `ICAL_DOWNLOAD_VTODO`: lädt Aufgaben per CalDAV-Export (optional `authorization_basic`), Feld `events` steuert den Filter: `OPEN` (Standard, unerledigte), `COMPLETED` (erledigt in den letzten 4 Wochen), `COMPLETED_RECENTLY` (erledigt in den letzten ~12 Tagen); jeder andere Wert zeigt alle Aufgaben ungefiltert. Ausgabe sortiert nach Priorität, mit Status-Emojis.
- **Eingänge von außen**:
  - REST `POST /api/send-message` (`ApiController`, `ROLE_API`): JSON-Body mit `user` + `key` (Benutzername/Passwort eines DB-Users mit `ROLE_API`, geprüft von `Security/ApiKeyAuthenticator` für alle Routen `api_*`, `Content-Type` muss `json` enthalten) sowie `message` und `channel` **oder** `conversationId` → ruft intern `hermine:send-message --context REST` auf. Antworten immer als JSON mit `status` (`OK`/`ERROR`): Erfolg 200 mit `content` (Konsolenausgabe), fehlende Felder 400 mit `message`, Fehler beim Senden 500 mit `content`, Anmeldefehler 401 mit `message` (ohne Request-Inhalt).
  - REST `POST /api/channel-members` (`ApiController`, `ROLE_API`, nur lesend): JSON-Body mit `user` + `key` und `channel` (Name, nur aus `allowed_channel`, nicht der Unterhaltungs-Platzhalter – sonst 403) → meldet sich bei Hermine an und liefert `{status, channel: {id, name}, ownUserId, members: [{id, first_name, last_name, department, display_name}]}` (`last_name` ohne „(Dienststelle)“, `User::getNameParts()`; `ownUserId` = Account des Bots, der selbst Mitglied ist). Genutzt vom StAN-Manager für die Personen der Dienst-Abfragen. Fehler beim Laden: 500 ohne Details (Details im Log), unbekannter Channel 404.
  - Doctrine-Queue (Transport `async`, Verbindung/DB `messages`, `auto_setup=0` – Tabelle wird nicht automatisch angelegt): `HermineBotCom\Message` (nur Channel, keine Unterhaltung) und `HermineBotCom\Placeholder`, verarbeitet von `MessageHandler/*` per `messenger:consume`. `EventListener/WorkerListener` beendet den Worker, sobald die Queue leer ist – so ist `messenger:consume` auch per Cron nutzbar. Erzeugt u. a. vom StAN-Manager.
    - Fehler: `HermineMessageHandler` wirft eine Exception, Messenger versucht es erneut nach 1, 2 und 4 Minuten (`retry_strategy`, die Wiederholungen werden mit späterem `available_at` in die Queue zurückgestellt und vom nächsten Cron-Lauf abgeholt). Danach landet die Nachricht im Failure-Transport `failed` (Tabelle `messenger_messages` in der **Bot-DB**, `queue_name = failed`): `messenger:failed:show`, `messenger:failed:retry`, `messenger:failed:remove`. `EventListener/WorkerMessageFailedListener` loggt Wiederholungen (`warning`) und den endgültigen Fehlschlag (`critical`).
- **Senden** (`hermine:send-message`, `Command/SendMessage`): Optionen `-x/--context`, `-c/--channel` (Standard: Unterhaltungs-Platzhalter), `--conversationId`, `-m/--message`, `-b/--message-b64`. Prüft das Ziel gegen `allowed_channel`, ersetzt Platzhalter, hängt `auto_append_to_messages` an, liket die eigene Nachricht. Nimmt Hermine die Nachricht nicht an (Status ≠ `OK`), endet der Befehl mit Fehler; ein Fehler beim Liken nach erfolgreichem Senden wird nur geloggt (sonst würde die Queue erneut senden). Alle anderen Wege (Daemon-Dateien, GUI-Trigger, REST, Queue) laufen über diesen Befehl; nur `daemon:tick` sendet Events direkt über den Mediator.
- **Stashcat-Anbindung** (`src/Stashcat`, bewusst frameworkfrei): `ApiClient` (Login, Auth-Check, Schlüssel, Firmen/Gruppen/Channels/Unterhaltungen, Senden an Channel/Unterhaltung, Nachrichten lesen, Like; nur lesend außerdem Channel-Mitglieder `channels/members`, Channel-Details `channels/infos`, Personensuche `users/listing`, Personendetails `users/info`, einzelne Unterhaltung `message/conversation`, „gesehen von“ `message/list_message_seen_users` – laut Erfahrung unzuverlässig), `CryptoBox` (Ende-zu-Ende-Verschlüsselung, Hilfsfunktionen in `Core/Crypt`), Entities/Responses. `Core/StashcatMediator` ist die Fassade für Anwendungscode (login → decryptPrivateKey → loadCompanies → Channel/Unterhaltung laden → entschlüsseln → senden/lesen).
  - Inoffizielle API: Endpunkte sind nachgebaut bzw. aus Mitschnitten des Web-Clients ermittelt. Neue Endpunkte nur lesend und mit wenigen Testaufrufen im Test-Channel ausprobieren; Nachrichtenfelder, die nicht immer geliefert werden (Reaktionen, Thread, gesehen), über tolerante Getter lesen. Neue Unterhaltungen anlegen: `StashcatMediator::createConversation()` (`message/createEncryptedConversation`, schreibend) – zufälliger Schlüssel, je Mitglied per RSA-OAEP verschlüsselt und mit dem privaten Signaturschlüssel signiert (RSASSA-PKCS1-v1_5/SHA-256; Schlüssel über `security/get_private_key` mit `type=signing`, `format=jwk`, entschlüsselt mit dem Hauptschlüssel). Empfänger-Schlüssel werden geprüft, die Server-Prüfung nur mit `serverPublicKey` in `env.api.php`; ungeprüfte Empfänger nur mit `$allowUnverified`. Bisher nicht gegen Hermine getestet und an keiner Oberfläche/keinem Befehl angebunden.
  - Die Login-Antwort (Client-Key) wird in `data/credentials/login-data.json` zwischengespeichert und per Auth-Check wiederverwendet – Datei ist ein Geheimnis.
  - Die Stashcat-Firma kommt aus `stashcat_event_company` (Daemon, SendMessage, QueryController).
  - `AppService` baut Mediator/ApiClient/CryptoBox im Konstruktor aus `config/legacy/env.app.php` und `env.api.php` (per `include`) – ohne diese Dateien startet weder Web noch Konsole.
- **Nachrichten lesen**: `getMessagesFromChannel()` / `getMessagesFromConversation()` funktionieren, werden aber nur im unfertigen Prototyp `QueryController` (`/query`, liest den Test-Channel aus `test_channel`, endet mit `dd()`) genutzt. `THWStashcatConventions::extractNameParts()` zerlegt Nachnamen in Nachname + Dienststelle. Ideen dort und in `EventsController`: „Question Bot“ (Rückmeldungen per Emoji/Stichwort auswerten), Merken bereits verarbeiteter Nachrichten, Mail/iCal → Hermine.
  - Geplant: Inventarnummern in Hermine-Channels automatisch auflösen (StAN-Manager-Issues #16 Lookup-Endpunkt, #17 Hermine-Auskunft). Dafür braucht der Bot: Channels lesen, verarbeitete Nachrichten merken, klaren Auslöser erkennen, eigene Nachrichten ignorieren.
- **Kennzeichnung**: An jede gesendete Nachricht wird `auto_append_to_messages` angehängt (z. B. „🤖 AUTOMATISCH ERZEUGTE NACHRICHT 🤖“). Wichtig für alles, was künftig Nachrichten liest: Der Bot schreibt über einen persönlichen Account – eigene Nachrichten an dieser Kennung erkennen, sonst entstehen Schleifen.
- **Logging**: `Core/DatabaseLogger` (PSR-3) in die Tabelle `log`, Kanäle `logger.app` (Standard für `DatabaseLogger`), `logger.events`, `logger.stashcat` (ohne debug). Aufrufkontext steht im Log (`Console`, `Manual`, `REST`, `GUI-TRIGGER` bzw. Kontext der Queue-Nachricht). Ansicht unter `/events/log` (100 pro Seite), `POST /events/log/truncate` löscht Einträge älter als eine Woche.
- **Benutzer** (`UserController`, `/users`, `ROLE_ADMIN`; Logik in `Core/UserManager`): Administratoren (`ROLE_ADMIN`, Passwort ab 10 Zeichen) und API-Benutzer (`ROLE_API`, „Key“ = Passwort, erzeugt mit 32 Zufallsbytes URL-sicher, nur einmal angezeigt über den Flash `key`, „Neuen Schlüssel erzeugen“ macht den alten sofort ungültig). Gespeichert ist nur der Hash. Niemand löscht sich selbst oder nimmt sich die Administration, mindestens ein Administrator bleibt. Aktionen landen im Log.
- **Web-Oberfläche**: `/` Login (Form-Login mit CSRF, Benutzer in Tabelle `user`), `/events` (`ROLE_ADMIN`), `/users`, `/events/conversations` (JSON für die Unterhaltungsauswahl, meldet sich bei Hermine an), `/events/execute` (GET zeigt nur einen Button, erst POST startet `daemon:tick --context Manual` und zeigt die Ausgabe), `/events/log`, `/changelog`. Alle verändernden Aktionen sind POST mit CSRF-Token (`_token`, geprüft über `denyAccessUnlessCsrfTokenValid()` im `EventsController`: Token-IDs `events`, `execute_events`, `events_log_truncate`); Logout nur mit Token über `logout_path()`. Twig-Globals `title`, `ov` (aus `ORGANIZATION_UNIT`), `app_version`; `Twig/AppExtension` überschreibt den Filter `date` mit dem Format aus `env.app.php`. Assets: jQuery, jQuery-UI, timepicker über asset-packagist nach `public/assets/npm-asset/` (gitignored), eigene Dateien `public/assets/main.css`, `app.js`. **Design wie im StAN-Manager**: `main.css` ist aus dessen `assets/styles/app.scss` übernommen (allgemeine Teile, einmal mit Sass übersetzt – kein Frontend-Build im Bot, Änderungen direkt im CSS), Bot-eigenes am Ende. Gleiche Bausteine: Kopf mit Menü-Knopf `.nav-toggle` (≤ 800px), `nav.primary` mit Reitern und `nav.secondary` dahinter, `.form-page`/`.report-header`/`.form-section`/`.form-grid`/`.form-field`/`.action-bar`, Knöpfe `.button-primary`/`.button-secondary`/`.button-danger`, Tabellen am Handy als Karten (`app.js` setzt `data-label`, ausnehmen mit `.table-keep` – so die breite Events-Tabelle), Kopieren per `data-copy`. Meldungen nach Aktionen als Flash (`success`/`warning`/`error`) oben im Inhalt.

## Projektverbund

Dieses Projekt ist Teil eines Verbunds aus drei Repositories, die zusammen betrieben werden. Änderungen an einer Schnittstelle wirken sich auf die anderen aus.

| Projekt | Rolle |
|---|---|
| [StAN-Manager](https://github.com/sebiw/StAN-Manager) (privat) | Material- und Vorgangsverwaltung. **Producer**: stellt Statusänderungen als `HermineBotCom\Message` in die Queue. **API**: liefert Reports unter `/api/open-messages` und `/api/open-files` (JSON-POST mit API-Benutzer und Key). |
| [HermineBot](https://github.com/sebiw/HermineBot) | Bot für den Messenger Hermine (Stashcat). **Consumer**: verarbeitet die Queue (`messenger:consume`) und sendet an Hermine; ruft die Reports des StAN-Managers per REST als Platzhalter ab. |
| [HermineBotCom](https://github.com/sebiw/HermineBotCom) | Gemeinsame Nachrichtenklassen (`Message`, `Placeholder`, `AbstractMessage`), in beiden Anwendungen als Submodule `submodule/HermineBotCom` eingebunden. |

**Schnittstellen**

- **Queue**: Symfony Messenger, Doctrine-Transport auf eine gemeinsame Datenbank `messages` (`auto_setup=0`). Nachrichten werden mit PHP `serialize()` übertragen – Klassen- und Property-Namen von HermineBotCom bestimmen das Format.
- **REST**: Der Bot ruft die Report-Endpunkte des StAN-Managers auf; die Antwort (`content`) wird als Platzhaltertext in Bot-Nachrichten eingesetzt.
- **Submodule**: Lokal gilt der im jeweiligen Projekt gepinnte Commit. Produktiv werden die drei Repos getrennt bereitgestellt; dort zählt, was zuletzt von `main` bereitgestellt wurde – Submodule werden nicht automatisch mitgezogen.

**Wer hier etwas ändert, prüft dort**

| Änderung an … | Auswirkung / prüfen |
|---|---|
| HermineBotCom-Klassen | Abwärtskompatibel halten (alte Nachrichten in der Queue). Submodule in StAN-Manager **und** HermineBot aktualisieren; Messenger-Routing (StAN-Manager) und Handler (HermineBot) anpassen; beide produktiv bereitstellen. |
| Neuer Nachrichtentyp | Klasse in HermineBotCom, Routing im StAN-Manager, `#[AsMessageHandler]` im HermineBot. |
| `/api/*` im StAN-Manager | Platzhalter-Konfiguration des Bots (REST-Endpunkte) und erwartetes Antwortformat (`content`) prüfen; Breaking Change = MAJOR. |
| Workflow/Status im StAN-Manager | Texte, die über die Queue an Hermine gehen, ggf. anpassen. |
| Queue-Konfiguration (Transport, DB `messages`) | In beiden Anwendungen identisch halten. |
| PHP-/Symfony-Version | Nur gemeinsam für alle drei Projekte anheben (HermineBotCom: keine Features über die gemeinsame PHP-Version hinaus). |

**Interne Informationen**

Hosting-Anbieter, Hostnamen, Produktiv-URLs, Server-Pfade, Benutzer- und Kanalnamen, Zugangsdaten und Momentaufnahmen des Live-Stands gehören **nicht** in dieses Repo (auch nicht in Commits, Issues oder PRs). Sie werden außerhalb von Git in einer privaten Betriebsdoku gepflegt, die lokal über `CLAUDE.local.md` geladen wird. Ein lokaler Pre-commit-Hook prüft Commits auf solche Angaben.

**Commit, Tag und Push**

Agenten committen, taggen und pushen in keinem der drei Repos eigenständig – auch nicht bei Changelog-Einträgen oder Releases. Änderungen fertig vorbereiten, Aufteilung und Commit-Message vorschlagen und auf ausdrückliche Freigabe des Nutzers warten. Eine Freigabe gilt nur für den besprochenen Umfang; Push braucht eine eigene Freigabe.

## Struktur

- `src/Stashcat` – Stashcat-API-Client, Krypto, Entities, Responses
- `src/Core` – Mediator, MessageDecorator, Config (`ArrayDataCollection`), RestClient (cURL), Crypt, DatabaseLogger, ChangelogRenderer, `THWStashcatConventions` (Namensschema der THW-Accounts: Nachname + Dienststelle)
- `src/Command` – `daemon:tick`, `hermine:send-message`
- `src/Controller` – Events, Login, API, Changelog, Query (Prototyp)
- `src/MessageHandler` – Handler für die `HermineBotCom`-Nachrichten
- `src/Security` – `ApiKeyAuthenticator`; `src/Service/AppService` – zentraler Einstieg zu Config und Mediator
- `src/Entity` – `Event`, `LogEntry`, `User`
- `bootstrap.php` – definiert die Konstante `BASE_PATH` (Projektwurzel), von `public/index.php` und `bin/console` geladen; Pfade in `services.yaml` (`app.message_*`) sind relativ dazu
- `submodule/HermineBotCom` – Git-Submodule (github.com/sebiw/HermineBotCom), Nachrichtenklassen (`AbstractMessage`, `Message`, `Placeholder`), die auch der StAN-Manager nutzt; per PSR-4 in `composer.json` eingebunden (siehe Projektverbund).
- `config/legacy/` – echte Konfiguration (gitignored), Vorlagen in `config_examples/`
- `data/` – Laufzeitdaten (gitignored): `placeholder.json`, `credentials/login-data.json`, Nachrichtendateien
- `migrations/` – Tabellen `events`, `user`, `log` (DB `hermine_bot`)
- `tests/` – derzeit keine Tests (nur `bootstrap.php`, PHPUnit 9 ist eingerichtet)

## Konfiguration

- `.env` (eingecheckt, Platzhalter) / `.env.local` (nicht committen): `DATABASE_URL`, `DATABASE_MESSAGE_URL`, `MESSENGER_TRANSPORT_DSN` (`doctrine://messages?auto_setup=0`), `MESSAGE_FILES_IN` (relativ zu `BASE_PATH`, leer = deaktiviert), `ORGANIZATION_UNIT` (Anzeige im Titel), `APP_SECRET`.
- `config/legacy/env.api.php` – Stashcat-Zugang: `baseUrl`, `version`, `appNamePattern`, `deviceId` (32 Zeichen), `userEmail`, `userPassword`, `userPassphrase` (für den privaten Schlüssel), Krypto-Parameter.
- `config/legacy/env.app.php` – `data_path`, `stashcat_event_company` (Name der Stashcat-Firma), `auto_append_to_messages`, `allowed_channel` (Freigabeliste), `test_channel` (Channel für Tests/Prototypen), `conversation_channel_placeholder_name` (optional), `allowed_intervals` (ISO-Intervall → Bezeichnung), `date_format`, `time_format`. Vorhanden, aber derzeit ungenutzt: `dev_mode`, `base_url`.
- `config/legacy/command_api_endpoints.json` – externe Datenquellen für Befehle (enthält API-Keys/Passwörter).
- `config/services.yaml` – `app.version`, Pfade `app.message_files_in`, `app.message_placeholder`, `app.message_command_api_endpoints`, Logger-Services.
- Rollen (`config/packages/security.yaml`): `ROLE_ADMIN` (enthält `ROLE_USER`, derzeit gleichbedeutend), `ROLE_API` für `/api/*`. Benutzer anlegen: Hash mit `php bin/console security:hash-password`, dann per SQL in `user` eintragen (siehe `readme.md`); es gibt keinen Befehl/keine Oberfläche dafür.

## Lokale Entwicklung

- Benötigt PHP 8.5 (mindestens 8.2) mit Webserver (Document-Root `public/`) und MySQL/MariaDB mit den Datenbanken `hermine_bot` und `messages` (Queue, ggf. gemeinsam mit dem StAN-Manager). Einrichtung siehe `readme.md`.
- Seiten: `/` Login, `/events` geplante Nachrichten, `/events/log` Log.
- Konsole:
  ```
  php8.5 bin/console daemon:tick           # fällige Events verarbeiten
  php8.5 bin/console messenger:consume async -q  # Nachrichten aus der Queue verarbeiten
  php8.5 bin/console hermine:send-message -c <Channel> -m "<Text>"
  ```
- **Achtung – lokal gibt es kein Test-Postfach wie Mailpit:** Stehen in `config/legacy/env.api.php` echte Zugangsdaten, verschicken `daemon:tick`, `/events/execute`, der „Trigger“-Button in `/events`, `messenger:consume`, `hermine:send-message` und `/api/send-message` **echte Nachrichten in echte Hermine-Channels** über den persönlichen Account. Auch `/query` meldet sich bei Hermine an und liest echte Nachrichten.
  - **Einzig der in der privaten Betriebsdoku genannte Test-Channel (lokal in `test_channel` hinterlegt) darf ohne Rückfrage beschrieben werden.** Kanalnamen gehören in die Konfiguration, nicht in Code oder Repo. Alle anderen Channels, Gruppen und Unterhaltungen sind echt – niemals dorthin senden, auch nicht zum Testen.
  - Lokal `allowed_channel` auf diesen Test-Channel einschränken und vor jedem Lauf prüfen, dass keine fälligen Events bzw. Queue-Nachrichten oder Dateien in `MESSAGE_FILES_IN` für andere Ziele anstehen (`daemon:tick` und `messenger:consume` verarbeiten *alles*, was fällig ist). Achtung: Unterhaltungen sind über den Platzhalter immer erlaubt.
  - Agenten führen sendende Befehle nur nach ausdrücklicher Freigabe aus.
  - Gefahrlos ohne Hermine-Zugriff: `php8.5 bin/console lint:twig templates`, `lint:container`, `debug:router`, `doctrine:schema:validate`, `debug:container --deprecations`, `php8.5 -l`.
  - Lokal sind PHP 8.1 und 8.5 installiert; `php` kann noch auf 8.1 zeigen – deshalb `php8.5` ausdrücklich aufrufen.
- Dateien sind im Repo mit LF gespeichert.
- Migrationen: `php8.5 bin/console doctrine:migrations:migrate`

## Betrieb

- Läuft auch auf einfachem Webspace ohne dauerhafte Prozesse: zwei Cronjobs, jeweils jede Minute, `php <Projektverzeichnis>/bin/console messenger:consume async -q` (Transport `async` angeben – es gibt zusätzlich `failed`, ohne Angabe bricht der Befehl mit `-q` ab) und `php <Projektverzeichnis>/bin/console daemon:tick`.
- Bei jedem Update: `composer install`, `bin/console cache:clear`, (alles mit dem PHP-8.5-Binary; beim Sprung von Symfony 6.1 auf 7.4 vorher `var/cache/prod` löschen, weil der alte kompilierte Container mit 7.4 nicht mehr lädt), `bin/console doctrine:migrations:migrate`; das Submodule `HermineBotCom` mit aktualisieren. Migrationen abwärtskompatibel halten – Cronjobs können zwischen Code-Update und Migration laufen.
- Konkrete Umgebungen (Server, URLs, Pfade, Zugangsdaten, Bereitstellungsweg) gehören nicht in dieses öffentliche Repo.
- Agenten committen, taggen und pushen nur nach ausdrücklicher Freigabe (siehe „Commit, Tag und Push“) und lösen keine Deployments aus.
- Der Hinweis im `readme.md` auf `private/runner.php` ist veraltet.
## Versionierung & Changelog

- Version nach [SemVer](https://semver.org/lang/de/), einzige Quelle ist der Parameter `app.version` in `config/services.yaml`. Er wird als Twig-Global `app_version` oben rechts unter dem Login-Hinweis angezeigt (Block `topnav`) (`templates/base.html.twig`, Styles in `public/assets/main.css`); nach dem Login lädt ein Klick `/changelog` (`ChangelogController`, `Core/ChangelogRenderer`, ohne Login 403) per AJAX in einen jQuery-UI-Dialog (`public/assets/app.js`). jQuery und jQuery-UI werden dafür in `base.html.twig` auf allen Seiten geladen.
- Änderungen stehen in `CHANGELOG.md` (Format „Keep a Changelog“, Deutsch). Gleiche Regeln und gleiche Form wie im StAN-Manager.

**Anweisung für Agenten – bei jeder Änderung automatisch beachten:**

1. **Bei jedem Commit mit wesentlicher Änderung** einen Eintrag unter `## [Unreleased]` in `CHANGELOG.md` ergänzen – ohne Rückfrage; er gehört in denselben Commit wie die Änderung (committet wird nur nach Freigabe, siehe „Commit, Tag und Push“).
   - Das Changelog richtet sich **nur an Endnutzer** (Anwender der Software) und ist in der App sichtbar. Wesentlich = für Anwender spürbar: neue Funktionen, behobene Fehler, geändertes Verhalten, Sicherheitsfixes.
   - Nicht eintragen: alles rund um Betrieb und Deployment (Konfiguration, Migrationen, Cronjobs, Server-Schritte – das gehört in Commit-Message bzw. `AGENTS.md`), reine Refactorings ohne Verhaltensänderung, Code-Formatierung, Kommentare, Entwickler-Doku, Tests, Tooling.
2. **Rubriken** (nur verwenden, was vorkommt, in dieser Reihenfolge): `### Hinzugefügt`, `### Geändert`, `### Entfernt`, `### Sicherheit`, `### Behoben`. Die Liste steht direkt unter der Rubrik (ohne Leerzeile).
3. **Formulierung**: ein Satz pro Eintrag, auf Deutsch, aus Sicht der Anwender („Geplante Nachrichten werden nicht mehr doppelt verschickt, wenn ein Durchlauf länger als eine Minute dauert.“), keine Fachbegriffe aus der Technik, keine Klassennamen, Dateipfade oder Commit-Hashes. Kein Inline-Code mit Backticks (der Changelog-Renderer unterstützt nur Überschriften, Listen, Absätze, Links und Fettschrift). **Repo ist öffentlich** – keine URLs, Hostnamen, Kanalnamen oder internen Details.
4. **Release** nur auf ausdrücklichen Wunsch („Release machen“, „Version erhöhen“):
   - Versionssprung aus den Einträgen unter `[Unreleased]` ableiten:
     - **MAJOR**: Inkompatible Änderungen – entfernte Funktionen, geänderte API (`/api/*`) oder `HermineBotCom`-Nachrichten, oder ein Deployment, das manuelle Eingriffe erfordert (neuer Pflicht-Konfigurationswert, manuelle Datenmigration).
     - **MINOR**: neue Funktionen, abwärtskompatibel.
     - **PATCH**: nur Fehlerbehebungen und kleine Anpassungen.
   - `## [Unreleased]` in `## [X.Y.Z] - JJJJ-MM-TT` umbenennen und darüber einen neuen, leeren `## [Unreleased]`-Abschnitt anlegen (leere Abschnitte blendet der Renderer in der App aus).
   - `app.version` in `config/services.yaml` auf `X.Y.Z` setzen.
   - Commit `MOD Release vX.Y.Z` und Git-Tag `vX.Y.Z` (annotiert) vorschlagen und erst nach Freigabe anlegen; Tag zusammen mit dem Commit pushen (`git push origin main --follow-tags`), nur nach eigener Freigabe.
   - Den Vorschlag für die neue Versionsnummer vorher kurz nennen, wenn die Einordnung nicht eindeutig ist.

## Hinweise

- Technik-Stand: Symfony 7.4 LTS, PHP 8.5, Doctrine ORM 3 / DBAL 4, `#[IsGranted]` von Symfony (Security-Attribut), Doctrine-Attribute. Request-Parameter über `$request->query`/`request`/`attributes` lesen (`$request->get()` ist abgekündigt), Sortierung mit `\SortDirection`. Ziel: keine Deprecations. Updates gemeinsam mit dem Verbund planen.
- Code-Stil: Leerzeichen innerhalb von Klammern (`foo( $a , $b )`), Getter/Setter mit PHPDoc, Konstruktor-Injection mit `#[Autowire(service: 'logger.…')]` für die DB-Logger. Konsolenbefehle werden aus Controllern/Handlern über `new Application( $kernel )` + `ArrayInput` aufgerufen.
- Commit-Messages bisher im Muster `* ADD …`, `* MOD …`; künftig wie im StAN-Manager `ADD …`, `FIX …`, `MOD …`.
- Sprache der UI und Kommentare: Deutsch.
