# Hermine-Bot

Symfony-6.1-Anwendung (PHP ≥ 8.1), die automatisiert Nachrichten in Channels und Unterhaltungen des Messengers Stashcat/Hermine (THW) verschickt – über einen normalen Benutzer-Account. Hobby-Projekt ohne Bezug zur stashcat GmbH.

**Das Repo ist öffentlich** – keine Zugangsdaten, Produktiv-URLs, Hostnamen, Server-Pfade, Channel-Inhalte oder personenbezogenen Daten committen.

## Fachliche Bausteine

- **Events** (`Entity/Event`, `EventsController`, `/events`): geplante Nachrichten mit Ziel (Channel-Name oder Unterhaltung), Text, Fälligkeit (`dueDateTime`), optionalem Intervall (`dateInterval`, ISO 8601, erlaubte Werte in `allowed_intervals`) und Enddatum. Speichern nur für Channels aus `allowed_channel`.
- **Daemon** (`daemon:tick`): verarbeitet fällige Events – Platzhalter ersetzen, senden, nächste Fälligkeit berechnen (verpasste Intervalle werden übersprungen), eigene Nachricht liken. Liest außerdem JSON-Nachrichtendateien aus `MESSAGE_FILES_IN` (Altlast, Datei-Variante).
- **Platzhalter** (`Core/MessageDecorator`): `{{…}}`-Platzhalter und Befehle im Text. Quellen: `data/placeholder.json` (befüllt u. a. über `HermineBotCom\Placeholder` aus der Queue), `config/legacy/command_api_endpoints.json` mit Typen `REST_JSON` (z. B. Reports `/api/open-messages`, `/api/open-files` des StAN-Managers) und `ICAL_DOWNLOAD_VTODO` (Aufgaben aus CalDAV, z. B. Hofdienst; Modi wie `OPEN`, `COMPLETED`, `COMPLETED_SHORT`, `COMPLETED_RECENTLY`).
- **Eingänge von außen**:
  - REST `POST /api/send-message` (`ROLE_API`, `ApiKeyAuthenticator`) mit `message` und `channel` oder `conversationId` → ruft intern `hermine:send-message` auf.
  - Doctrine-Queue (Transport `async`, DB `messages`): `HermineBotCom\Message` und `HermineBotCom\Placeholder`, verarbeitet von `MessageHandler/*` per `messenger:consume`. `EventListener/WorkerListener` beendet den Worker, sobald die Queue leer ist – so ist `messenger:consume` per Cron auf dem Webspace nutzbar. Erzeugt u. a. vom StAN-Manager.
- **Stashcat-Anbindung** (`src/Stashcat`, bewusst frameworkfrei): `ApiClient` (Login, Schlüssel, Firmen/Gruppen/Channels, Senden an Channel/Unterhaltung, Nachrichten lesen, Like), `CryptoBox` (Ende-zu-Ende-Verschlüsselung), Entities/Responses. `Core/StashcatMediator` ist die Fassade für Anwendungscode (login → decryptPrivateKey → loadCompanies → Channel/Unterhaltung laden → entschlüsseln → senden/lesen).
- **Nachrichten lesen**: `getMessagesFromChannel()` / `getMessagesFromConversation()` funktionieren, werden aber nur im unfertigen Prototyp `QueryController` (`/query`, fest auf Test-Channel `T_OWBH_TEST`, endet mit `dd()`) genutzt. Ideen dort: „Question Bot“ (Rückmeldungen per Emoji/Stichwort auswerten), Merken bereits verarbeiteter Nachrichten.
- **Kennzeichnung**: An jede gesendete Nachricht wird `auto_append_to_messages` angehängt (z. B. „🤖 AUTOMATISCH ERZEUGTE NACHRICHT 🤖“). Wichtig für alles, was künftig Nachrichten liest: Der Bot schreibt über einen persönlichen Account – eigene Nachrichten an dieser Kennung erkennen, sonst entstehen Schleifen.
- **Logging**: `Core/DatabaseLogger` in die DB, Kanäle `logger.app`, `logger.events`, `logger.stashcat` (ohne debug). Ansicht unter `/events/log`.

## Struktur

- `src/Stashcat` – Stashcat-API-Client, Krypto, Entities, Responses
- `src/Core` – Mediator, MessageDecorator, Config, RestClient, Crypt, Logger, `THWStashcatConventions` (Namensschema der THW-Accounts: Nachname + Dienststelle)
- `src/Command` – `daemon:tick`, `hermine:send-message`
- `src/Controller` – Events, Login, API, Query (Prototyp)
- `src/MessageHandler` – Handler für die `HermineBotCom`-Nachrichten
- `submodule/HermineBotCom` – Git-Submodule (github.com/sebiw/HermineBotCom), Nachrichtenklassen, die auch der StAN-Manager nutzt. Beide Projekte sollten denselben Commit pinnen (aktuell `main`).
- `config/legacy/` – echte Konfiguration (gitignored), Vorlagen in `config_examples/`
- `data/` – Laufzeitdaten (gitignored), u. a. `placeholder.json`
- `tests/` – derzeit keine Tests

## Konfiguration

- `.env` (eingecheckt, Platzhalter) / `.env.local` (nicht committen): `DATABASE_URL`, `DATABASE_MESSAGE_URL`, `MESSENGER_TRANSPORT_DSN` (`doctrine://messages?auto_setup=0`), `MESSAGE_FILES_IN`, `ORGANIZATION_UNIT`, `APP_SECRET`.
- `config/legacy/env.api.php` – Stashcat-Zugang: `baseUrl`, `userEmail`, `userPassword`, `userPassphrase` (für den privaten Schlüssel), `deviceId`, Krypto-Parameter.
- `config/legacy/env.app.php` – `dev_mode`, `stashcat_event_company`, `auto_append_to_messages`, `allowed_channel` (Freigabeliste), `conversation_channel_placeholder_name`, `allowed_intervals`, Datums-/Zeitformat, `base_url`.
- `config/legacy/command_api_endpoints.json` – externe Datenquellen für Platzhalter (enthält API-Keys/Passwörter).
- Rollen (`config/packages/security.yaml`): `ROLE_ADMIN` (enthält `ROLE_USER`, derzeit gleichbedeutend), `ROLE_API` für `/api/*`.

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
- **Achtung – lokal gibt es kein Test-Postfach wie Mailpit:** Stehen in `config/legacy/env.api.php` echte Zugangsdaten, verschicken `daemon:tick`, `/events/execute`, `messenger:consume`, `hermine:send-message` und `/api/send-message` **echte Nachrichten in echte Hermine-Channels** über den persönlichen Account.
  - **Einzig die Gruppe `T_OWBH_TEST` darf ohne Rückfrage beschrieben werden.** Alle anderen Channels, Gruppen und Unterhaltungen sind echt – niemals dorthin senden, auch nicht zum Testen.
  - Lokal `allowed_channel` auf `T_OWBH_TEST` einschränken und vor jedem Lauf prüfen, dass keine fälligen Events bzw. Queue-Nachrichten für andere Ziele anstehen (`daemon:tick` und `messenger:consume` verarbeiten *alles*, was fällig ist).
  - Agenten führen sendende Befehle nur nach ausdrücklicher Freigabe aus.
- In WSL muss `core.autocrlf=true` gesetzt sein (auch im Submodule), sonst erscheinen alle Dateien als geändert. Dateien sind im Repo mit LF, im Arbeitsverzeichnis mit CRLF gespeichert.
- Migrationen: `php bin/console doctrine:migrations:migrate`

## Deployment

- Produktiv auf einem Webspace (Plesk) zusammen mit dem StAN-Manager. URL, Server-Pfade und Zugangsdaten stehen nur in der internen Doku (privates StAN-Manager-Repo) – nicht hier eintragen.
- Cronjobs (Plesk „Geplante Aufgaben“), jeweils jede Minute: `php <Projektverzeichnis>/bin/console messenger:consume -q` und `php <Projektverzeichnis>/bin/console daemon:tick`.
- Der Hinweis im `readme.md` auf `private/runner.php` ist veraltet.
- Bereitstellungsweg (Plesk-Git oder manuell) ist hier noch nicht dokumentiert – beim nächsten Deployment ergänzen.

## Zusammenspiel mit dem StAN-Manager

- StAN-Manager → Bot: Nachrichten und Platzhalter über die Doctrine-Queue (`HermineBotCom`), ggf. Shell-Command bzw. Dateien (Altlast).
- Bot → StAN-Manager: REST-Abrufe der Reports über `command_api_endpoints.json`.
- Geplant: Inventarnummern in Hermine-Channels automatisch auflösen (StAN-Manager-Issues #16 Lookup-Endpunkt, #17 Hermine-Auskunft). Dafür braucht der Bot: Channels lesen, verarbeitete Nachrichten merken, klaren Auslöser erkennen, eigene Nachrichten ignorieren.

## Hinweise

- Ändern sich Klassen in `HermineBotCom`, müssen StAN-Manager und Bot beide aktualisiert und deployt werden.
- Gleicher Technik-Stand wie der StAN-Manager (Symfony 6.1, PHP 8.1, `sensio/framework-extra-bundle`): Updates beider Projekte gemeinsam planen (StAN-Manager-Issue #8).
- Commit-Messages bisher im Muster `* ADD …`, `* MOD …`; künftig wie im StAN-Manager `ADD …`, `FIX …`, `MOD …`.
- Sprache der UI und Kommentare: Deutsch.
