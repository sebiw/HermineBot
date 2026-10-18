# Hermine-Bot

Symfony-6.1-Anwendung (PHP ≥ 8.1), die automatisiert Nachrichten in Channels und Unterhaltungen des Messengers Stashcat/Hermine (THW) verschickt – über einen normalen Benutzer-Account. Hobby-Projekt ohne Bezug zur stashcat GmbH.

**Das Repo ist öffentlich** – keine Zugangsdaten, Produktiv-URLs, Hostnamen, Server-Pfade, Channel-Inhalte oder personenbezogenen Daten committen.

## Fachliche Bausteine

- **Events** (`Entity/Event`, Tabelle `events`, `EventsController`, `/events`): geplante Nachrichten mit Ziel (`channelTarget` = Channel-Name oder Unterhaltungs-Platzhalter, Unterhaltungs-ID in `channelTargetPayload.conversationId`), Text, Start (`startDateTime`), Fälligkeit (`dueDateTime`), optionalem Intervall (`dateInterval`, ISO 8601, erlaubte Werte in `allowed_intervals`), Enddatum (`untilDateTime`), `doneDateTime` und `transmissionsCount`.
  - Formular-Aktionen (`POST /events`, Feld `action`): `add`/`save`, `edit`, `delete`, `trigger` (sendet sofort über `hermine:send-message`, ändert die Fälligkeit nicht). Fälligkeit wird nur bei `resetDue=1` auf das Startdatum gesetzt.
  - Speichern nur für Channels aus `allowed_channel` (plus Unterhaltungs-Platzhalter).
- **Unterhaltungen**: Ziel ist der Pseudo-Channel `conversation_channel_placeholder_name` (Standard `[[Konversation]]`, `Core/Config::DEFAULT_CHANNEL_CONVERSATION_PLACEHOLDER`). Er wird von `getAllowedChannelNames()` immer automatisch zugelassen; die eigentliche Unterhaltung kommt über die ID.
- **Daemon** (`daemon:tick`, `Command/Daemon`): verarbeitet fällige Events – Platzhalter ersetzen, senden, nächste Fälligkeit berechnen (verpasste Intervalle werden übersprungen), eigene Nachricht liken. Liest vorher JSON-Dateien (`message`, `channel`, `context`) aus `MESSAGE_FILES_IN` und löscht sie danach (Altlast, Datei-Variante).
  - Fällig: `dueDateTime <= jetzt` und entweder noch nie erledigt, oder mit Intervall und `doneDateTime < untilDateTime` bzw. ohne Enddatum.
  - Bekanntes Verhalten: Schlägt das Senden fehl, wird bei Intervall-Events trotzdem die Fälligkeit weitergeschoben und `transmissionsCount` erhöht; einmalige Events bleiben fällig und werden bei jedem Tick (jede Minute) erneut versucht.
- **Platzhalter** (`Core/MessageDecorator`): Syntax `{{Name}}` für Platzhalter, `{%Name%}` für Befehle (externe Abrufe). Ersetzt werden nur Schlüssel, die im Text vorkommen; Callbacks, die `null` oder Nicht-Skalare liefern, bleiben stehen. Liste der verfügbaren Schlüssel steht im Events-Formular.
  - Eingebaut: `{{CompanyActiveUser}}`, `{{CompanyCreatedUser}}`, `{{CompanyName}}`, `{{ChannelUserCount}}` (nur bei Channels), `{{CompanyGroupsStats}}`, `{{EventInterval}}` (Bezeichnung des Intervalls, nur bei Events).
  - `data/placeholder.json`: Schlüssel `<context>:<key>` → `{ message, message_datetime }`, geschrieben von `HerminePlaceholderHandler` aus `HermineBotCom\Placeholder`.
  - `config/legacy/command_api_endpoints.json`: Schlüssel = Befehlsname, Pflichtfeld `type` (Einträge ohne `type` werden ignoriert – die Vorlage in `config_examples/` hat bei den StAN-Manager-Einträgen keinen):
    - `REST_JSON`: `POST url` mit JSON-`payload` (z. B. `user`/`key` für die StAN-Manager-Reports `/api/open-messages`, `/api/open-files`), Ergebnis aus `content` oder `message`.
    - `ICAL_DOWNLOAD_VTODO`: lädt Aufgaben per CalDAV-Export (optional `authorization_basic`), Feld `events` steuert den Filter: `OPEN` (Standard, unerledigte), `COMPLETED` (erledigt in den letzten 4 Wochen), `COMPLETED_RECENTLY` (erledigt in den letzten ~12 Tagen); jeder andere Wert zeigt alle Aufgaben ungefiltert. Ausgabe sortiert nach Priorität, mit Status-Emojis.
- **Eingänge von außen**:
  - REST `POST /api/send-message` (`ApiController`, `ROLE_API`): JSON-Body mit `user` + `key` (Benutzername/Passwort eines DB-Users mit `ROLE_API`, geprüft von `Security/ApiKeyAuthenticator` für alle Routen `api_*`, `Content-Type` muss `json` enthalten) sowie `message` und `channel` **oder** `conversationId` → ruft intern `hermine:send-message --context REST` auf. Antwort `{ content: <Konsolenausgabe> }`; fehlende Felder werfen eine Exception (HTTP 500).
  - Doctrine-Queue (Transport `async`, Verbindung/DB `messages`, `auto_setup=0` – Tabelle wird nicht automatisch angelegt): `HermineBotCom\Message` (nur Channel, keine Unterhaltung) und `HermineBotCom\Placeholder`, verarbeitet von `MessageHandler/*` per `messenger:consume`. `EventListener/WorkerListener` beendet den Worker, sobald die Queue leer ist – so ist `messenger:consume` per Cron auf dem Webspace nutzbar. Erzeugt u. a. vom StAN-Manager.
- **Senden** (`hermine:send-message`, `Command/SendMessage`): Optionen `-x/--context`, `-c/--channel` (Standard: Unterhaltungs-Platzhalter), `--conversationId`, `-m/--message`, `-b/--message-b64`. Prüft das Ziel gegen `allowed_channel`, ersetzt Platzhalter, hängt `auto_append_to_messages` an, liket die eigene Nachricht. Alle anderen Wege (Daemon-Dateien, GUI-Trigger, REST, Queue) laufen über diesen Befehl; nur `daemon:tick` sendet Events direkt über den Mediator.
- **Stashcat-Anbindung** (`src/Stashcat`, bewusst frameworkfrei): `ApiClient` (Login, Auth-Check, Schlüssel, Firmen/Gruppen/Channels/Unterhaltungen, Senden an Channel/Unterhaltung, Nachrichten lesen, Like), `CryptoBox` (Ende-zu-Ende-Verschlüsselung, Hilfsfunktionen in `Core/Crypt`), Entities/Responses. `Core/StashcatMediator` ist die Fassade für Anwendungscode (login → decryptPrivateKey → loadCompanies → Channel/Unterhaltung laden → entschlüsseln → senden/lesen).
  - Die Login-Antwort (Client-Key) wird in `data/credentials/login-data.json` zwischengespeichert und per Auth-Check wiederverwendet – Datei ist ein Geheimnis.
  - Die Firma ist fest auf `'THW'` verdrahtet (`getCompanyByName('THW')` in Daemon, SendMessage, QueryController); `stashcat_event_company` aus der Config wird nicht verwendet.
  - `AppService` baut Mediator/ApiClient/CryptoBox im Konstruktor aus `config/legacy/env.app.php` und `env.api.php` (per `include`) – ohne diese Dateien startet weder Web noch Konsole.
- **Nachrichten lesen**: `getMessagesFromChannel()` / `getMessagesFromConversation()` funktionieren, werden aber nur im unfertigen Prototyp `QueryController` (`/query`, fest auf Test-Channel `T_OWBH_TEST`, endet mit `dd()`) genutzt. `THWStashcatConventions::extractNameParts()` zerlegt Nachnamen in Nachname + Dienststelle. Ideen dort und in `EventsController`: „Question Bot“ (Rückmeldungen per Emoji/Stichwort auswerten), Merken bereits verarbeiteter Nachrichten, Mail/iCal → Hermine.
- **Kennzeichnung**: An jede gesendete Nachricht wird `auto_append_to_messages` angehängt (z. B. „🤖 AUTOMATISCH ERZEUGTE NACHRICHT 🤖“). Wichtig für alles, was künftig Nachrichten liest: Der Bot schreibt über einen persönlichen Account – eigene Nachrichten an dieser Kennung erkennen, sonst entstehen Schleifen.
- **Logging**: `Core/DatabaseLogger` (PSR-3) in die Tabelle `log`, Kanäle `logger.app` (Standard für `DatabaseLogger`), `logger.events`, `logger.stashcat` (ohne debug). Aufrufkontext steht im Log (`Console`, `Manual`, `REST`, `GUI-TRIGGER` bzw. Kontext der Queue-Nachricht). Ansicht unter `/events/log` (100 pro Seite), `/events/log/truncate` löscht Einträge älter als eine Woche.
- **Web-Oberfläche**: `/` Login (Form-Login mit CSRF, Benutzer in Tabelle `user`), `/events` (`ROLE_ADMIN`), `/events/execute` startet `daemon:tick --context Manual` und zeigt die Ausgabe, `/events/log`, `/changelog`. Twig-Globals `title`, `ov` (aus `ORGANIZATION_UNIT`), `app_version`; `Twig/AppExtension` überschreibt den Filter `date` mit dem Format aus `env.app.php`. Assets: jQuery, jQuery-UI, timepicker über asset-packagist nach `public/assets/npm-asset/` (gitignored), eigene Dateien `public/assets/main.css`, `app.js`.

## Struktur

- `src/Stashcat` – Stashcat-API-Client, Krypto, Entities, Responses
- `src/Core` – Mediator, MessageDecorator, Config (`ArrayDataCollection`), RestClient (cURL), Crypt, DatabaseLogger, ChangelogRenderer, `THWStashcatConventions` (Namensschema der THW-Accounts: Nachname + Dienststelle)
- `src/Command` – `daemon:tick`, `hermine:send-message`
- `src/Controller` – Events, Login, API, Changelog, Query (Prototyp)
- `src/MessageHandler` – Handler für die `HermineBotCom`-Nachrichten
- `src/Security` – `ApiKeyAuthenticator`; `src/Service/AppService` – zentraler Einstieg zu Config und Mediator
- `src/Entity` – `Event`, `LogEntry`, `User`; `src/Repository/TestRepository` ist ein Überbleibsel (verweist auf nicht existierendes `App\Entity\Test`)
- `bootstrap.php` – definiert die Konstante `BASE_PATH` (Projektwurzel), von `public/index.php` und `bin/console` geladen; Pfade in `services.yaml` (`app.message_*`) sind relativ dazu
- `submodule/HermineBotCom` – Git-Submodule (github.com/sebiw/HermineBotCom), Nachrichtenklassen (`AbstractMessage`, `Message`, `Placeholder`), die auch der StAN-Manager nutzt; per PSR-4 in `composer.json` eingebunden. Beide Projekte sollten denselben Commit pinnen (aktuell auf `main`).
- `config/legacy/` – echte Konfiguration (gitignored), Vorlagen in `config_examples/`
- `data/` – Laufzeitdaten (gitignored): `placeholder.json`, `credentials/login-data.json`, Nachrichtendateien
- `migrations/` – Tabellen `events`, `user`, `log` (DB `hermine_bot`)
- `tests/` – derzeit keine Tests (nur `bootstrap.php`, PHPUnit 9 ist eingerichtet)

## Konfiguration

- `.env` (eingecheckt, Platzhalter) / `.env.local` (nicht committen): `DATABASE_URL`, `DATABASE_MESSAGE_URL`, `MESSENGER_TRANSPORT_DSN` (`doctrine://messages?auto_setup=0`), `MESSAGE_FILES_IN` (relativ zu `BASE_PATH`, leer = deaktiviert), `ORGANIZATION_UNIT` (Anzeige im Titel), `APP_SECRET`.
- `config/legacy/env.api.php` – Stashcat-Zugang: `baseUrl`, `version`, `appNamePattern`, `deviceId` (32 Zeichen), `userEmail`, `userPassword`, `userPassphrase` (für den privaten Schlüssel), Krypto-Parameter.
- `config/legacy/env.app.php` – `data_path`, `auto_append_to_messages`, `allowed_channel` (Freigabeliste), `conversation_channel_placeholder_name` (optional), `allowed_intervals` (ISO-Intervall → Bezeichnung), `date_format`, `time_format`. Vorhanden, aber derzeit ungenutzt: `dev_mode`, `stashcat_event_company`, `base_url`.
- `config/legacy/command_api_endpoints.json` – externe Datenquellen für Befehle (enthält API-Keys/Passwörter).
- `config/services.yaml` – `app.version`, Pfade `app.message_files_in`, `app.message_placeholder`, `app.message_command_api_endpoints`, Logger-Services.
- Rollen (`config/packages/security.yaml`): `ROLE_ADMIN` (enthält `ROLE_USER`, derzeit gleichbedeutend), `ROLE_API` für `/api/*`. Benutzer anlegen: Hash mit `php bin/console security:hash-password`, dann per SQL in `user` eintragen (siehe `readme.md`); es gibt keinen Befehl/keine Oberfläche dafür.

## Lokales Setup (WSL)

- Gleiche Umgebung wie der StAN-Manager: nginx + PHP-FPM 8.1, MariaDB auf `127.0.0.1:3306`. vHost **http://hermine-bot.localhost/** (Root `public/`, Eintrag in der Windows-`hosts`-Datei). Datenbanken `hermine_bot` und `messages` (gemeinsame Queue mit dem StAN-Manager).
- Seiten: `/` Login, `/events` geplante Nachrichten, `/events/log` Log.
- Der erste Aufruf nach längerer Pause kann mit **504** abbrechen (Cache-Aufbau unter `/mnt/c` ist langsam) – einfach neu laden.
- Konsole:
  ```
  php bin/console daemon:tick           # fällige Events verarbeiten
  php bin/console messenger:consume -q  # Nachrichten aus der Queue verarbeiten
  php bin/console hermine:send-message -c <Channel> -m "<Text>"
  ```
- **Achtung – lokal gibt es kein Test-Postfach wie Mailpit:** Stehen in `config/legacy/env.api.php` echte Zugangsdaten, verschicken `daemon:tick`, `/events/execute`, der „Trigger“-Button in `/events`, `messenger:consume`, `hermine:send-message` und `/api/send-message` **echte Nachrichten in echte Hermine-Channels** über den persönlichen Account. Auch `/query` meldet sich bei Hermine an und liest echte Nachrichten.
  - **Einzig die Gruppe `T_OWBH_TEST` darf ohne Rückfrage beschrieben werden.** Alle anderen Channels, Gruppen und Unterhaltungen sind echt – niemals dorthin senden, auch nicht zum Testen.
  - Lokal `allowed_channel` auf `T_OWBH_TEST` einschränken und vor jedem Lauf prüfen, dass keine fälligen Events bzw. Queue-Nachrichten oder Dateien in `MESSAGE_FILES_IN` für andere Ziele anstehen (`daemon:tick` und `messenger:consume` verarbeiten *alles*, was fällig ist). Achtung: Unterhaltungen sind über den Platzhalter immer erlaubt.
  - Agenten führen sendende Befehle nur nach ausdrücklicher Freigabe aus.
  - Gefahrlos ohne Hermine-Zugriff: `php bin/console lint:twig templates`, `lint:container`, `debug:router`, `doctrine:schema:validate`, `php -l`.
- In WSL muss `core.autocrlf=true` gesetzt sein (auch im Submodule), sonst erscheinen alle Dateien als geändert. Dateien sind im Repo mit LF, im Arbeitsverzeichnis mit CRLF gespeichert.
- Migrationen: `php bin/console doctrine:migrations:migrate`

## Deployment

- Produktiv auf einem Webspace (Plesk) zusammen mit dem StAN-Manager. URL, Server-Pfade und Zugangsdaten stehen nur in der internen Doku (privates StAN-Manager-Repo) – nicht hier eintragen.
- Cronjobs (Plesk „Geplante Aufgaben“), jeweils jede Minute: `php <Projektverzeichnis>/bin/console messenger:consume -q` und `php <Projektverzeichnis>/bin/console daemon:tick`.
- Der Hinweis im `readme.md` auf `private/runner.php` ist veraltet.
- Bereitstellungsweg (Plesk-Git oder manuell) ist hier noch nicht dokumentiert – beim nächsten Deployment ergänzen.

## Zusammenspiel mit dem StAN-Manager

- StAN-Manager → Bot: Nachrichten und Platzhalter über die Doctrine-Queue (`HermineBotCom`), ggf. Shell-Command bzw. Dateien (Altlast).
- Bot → StAN-Manager: REST-Abrufe der Reports über `command_api_endpoints.json` (`REST_JSON`).
- Geplant: Inventarnummern in Hermine-Channels automatisch auflösen (StAN-Manager-Issues #16 Lookup-Endpunkt, #17 Hermine-Auskunft). Dafür braucht der Bot: Channels lesen, verarbeitete Nachrichten merken, klaren Auslöser erkennen, eigene Nachrichten ignorieren.

## Versionierung & Changelog

- Version nach [SemVer](https://semver.org/lang/de/), einzige Quelle ist der Parameter `app.version` in `config/services.yaml`. Er wird als Twig-Global `app_version` oben rechts unter dem Login-Hinweis angezeigt (Block `topnav`) (`templates/base.html.twig`, Styles in `public/assets/main.css`); nach dem Login lädt ein Klick `/changelog` (`ChangelogController`, `Core/ChangelogRenderer`, ohne Login 403) per AJAX in einen jQuery-UI-Dialog (`public/assets/app.js`). jQuery und jQuery-UI werden dafür in `base.html.twig` auf allen Seiten geladen.
- Änderungen stehen in `CHANGELOG.md` (Format „Keep a Changelog“, Deutsch). Gleiche Regeln wie im StAN-Manager:

**Anweisung für Agenten – bei jeder Änderung automatisch beachten:**

1. **Bei jedem Commit mit wesentlicher Änderung** einen Eintrag unter `## [Unreleased]` in `CHANGELOG.md` ergänzen – im selben Commit, ohne Rückfrage. Das Changelog richtet sich **nur an Endnutzer** und ist in der App sichtbar: neue Funktionen, behobene Fehler, geändertes Verhalten, Sicherheitsfixes. Nicht eintragen: Betrieb/Deployment, Refactorings ohne Verhaltensänderung, Formatierung, Kommentare, Entwickler-Doku, Tests, Tooling.
2. **Rubriken** (nur verwenden, was vorkommt): `### Hinzugefügt`, `### Geändert`, `### Behoben`, `### Entfernt`, `### Sicherheit`.
3. **Formulierung**: ein Satz pro Eintrag, auf Deutsch, aus Sicht der Anwender, ohne Klassennamen, Dateipfade oder Commit-Hashes. Kein Inline-Code mit Backticks (der Renderer unterstützt nur Überschriften, Listen, Absätze, Links und Fettschrift). **Repo ist öffentlich** – keine URLs, Hostnamen oder internen Details.
4. **Release** nur auf ausdrücklichen Wunsch: Versionssprung aus `[Unreleased]` ableiten (MAJOR: inkompatibel, z. B. geänderte `/api/*` oder `HermineBotCom`-Nachrichten, manuelle Deployment-Schritte; MINOR: neue Funktionen; PATCH: Fehlerbehebungen), `## [Unreleased]` in `## [X.Y.Z] - JJJJ-MM-TT` umbenennen, neuen leeren `[Unreleased]`-Abschnitt anlegen, `app.version` anpassen, Commit `MOD Release vX.Y.Z` und annotierten Tag `vX.Y.Z`; pushen mit `git push origin main --follow-tags`, sofern gewünscht.

## Hinweise

- Ändern sich Klassen in `HermineBotCom`, müssen StAN-Manager und Bot beide aktualisiert und deployt werden (Queue-Nachrichten werden serialisiert – alte Nachrichten in der Queue müssen noch deserialisierbar sein).
- Gleicher Technik-Stand wie der StAN-Manager (Symfony 6.1, PHP 8.1, `sensio/framework-extra-bundle` für `#[IsGranted]`, Doctrine-Annotations/Attribute): Updates beider Projekte gemeinsam planen (StAN-Manager-Issue #8).
- Code-Stil: Leerzeichen innerhalb von Klammern (`foo( $a , $b )`), Getter/Setter mit PHPDoc, Konstruktor-Injection mit `#[Autowire(service: 'logger.…')]` für die DB-Logger. Konsolenbefehle werden aus Controllern/Handlern über `new Application( $kernel )` + `ArrayInput` aufgerufen.
- Commit-Messages bisher im Muster `* ADD …`, `* MOD …`; künftig wie im StAN-Manager `ADD …`, `FIX …`, `MOD …`.
- Sprache der UI und Kommentare: Deutsch.
