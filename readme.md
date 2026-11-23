# Hermine Bot

Verschickt automatisiert Nachrichten in Channels und Unterhaltungen von Stashcat/Hermine – über einen normalen Benutzer-Account.

* **Geplante Nachrichten** (`/events`): einmalig oder wiederkehrend (täglich bis alle 3 Jahre), pausierbar, mit Platzhaltern wie `{{CompanyActiveUser}}` und Abrufen externer Quellen (REST, CalDAV-Aufgaben)
* **REST-API** für andere Anwendungen (z. B. den StAN-Manager): Nachricht senden, Channels und Mitglieder abfragen, Direktnachricht
* **Konsole**: `hermine:send-message`, `daemon:tick` (Takt für geplante Nachrichten und Hintergrundaufgaben)
* **Log und Hintergrundaufgaben** in der Oberfläche
* Läuft auch auf kleineren, eingeschränkten Webspaces; der Stashcat-Teil (`src/Stashcat`) ist bewusst frameworkfrei, um ihn in andere Software übernehmen zu können
* Zugangsdaten für Hermine/Stashcat samt Passphrase liegen nur auf dem Server (`config/legacy/`, siehe unten)

Technik: Symfony 7.4 LTS, PHP 8.5 (mindestens 8.2), MySQL/MariaDB.

## Lokale Entwicklung
Mit Docker (WSL oder Linux): PHP 8.5, nginx und MySQL 8.4 laufen in Containern, Befehle mit `php8.5 …` werden in den Container geleitet. Einrichtung, Ports und anonymisierte Produktionsdaten (`bin/prod-daten-lokal`): [docs/SETUP.md](docs/SETUP.md). Vor jedem Commit `bin/pruefen`.

> Lokal ohne echte Zugangsdaten in `config/legacy/env.api.php` arbeiten – sonst gehen Nachrichten wirklich raus.

## Installation auf einem Server
Kurzfassung:
* `composer install --no-dev`
* `DATABASE_URL` in `.env.local` an die eigene Datenbank anpassen, dann `composer dump-env prod`
* Kopiere Konfigurationsdateien aus `/config_examples` in `/config/legacy` kopieren und den eigenen Bedürfnissen anpassen.
* Führe `doctrine:migrations:migrate` aus
* Cron-Job einrichten (jede Minute): `php bin/console daemon:tick` (siehe unten).

## Installation für Einsteiger
Die Installation wird in diesem Beispiel auf Debian 12 oder 13 mit eigener MySQL/MariaDB durchgeführt.
### Installation von PHP und Apache
#### Pakete für die Installation des Repo-Keys von PHP installieren
* `sudo apt update`
* `sudo apt install -y lsb-release ca-certificates apt-transport-https software-properties-common gnupg2`

#### PHP Repo-Key und Repo hinzufügen
* `curl -fsSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb`
* `sudo dpkg -i /tmp/debsuryorg-archive-keyring.deb`
* `echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/sury-php.list`

#### PHP und Apache installieren
* `sudo apt update`
* `sudo apt install apache2 php8.5 php8.5-curl php8.5-xml php8.5-mysql php8.5-cli php8.5-mbstring`
> PHP 8.5 wie produktiv; mindestens PHP 8.2 ist nötig.

### Installation der MySQL-DB
* `sudo apt install mariadb-server mariadb-client`

### Installation von sonstigen Paketen
* `sudo apt install git curl unzip`

### Installation des Composer
> Ausführliche Anleitung zu Composer: https://www.digitalocean.com/community/tutorials/how-to-install-and-use-composer-on-debian-11
* `curl -sS https://getcomposer.org/installer -o composer-setup.php`
* `HASH="$(curl -sS https://composer.github.io/installer.sig)"`
* `php -r "if (hash_file('SHA384', 'composer-setup.php') === '$HASH') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"`

* `sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer`
* `rm composer-setup.php`

### Download des HermineBots
* `cd /opt/`
* `sudo git clone https://github.com/sebiw/HermineBot.git`
* `cd HermineBot/`

### HermineBot Configs kopieren und anpassen
* `cp config_examples/*.php config/legacy/`
> Nach Bedarf auch die "config_examples/command_api_endpoints.json" nach config/legacy/ kopieren

#### env.api.php anpassen
* `nano /opt/HermineBot/config/legacy/env.api.php`
* `'baseUrl' => "https://api.thw-messenger.de/"`
* `'userEmail' => "<Mail-Adresse des Hermine-Accounts>",`
* `'userPassword' => "<Passwort des Hermine-Accounts>",`
* `'userPassphrase' => "<Verschlüsselungskennwort des Hermine-Accounts>",`
    
#### env.app.php anpassen
* `nano /opt/HermineBot/config/legacy/env.app.php`
* `"stashcat_event_company" => "THW OV <Ortschaft>"`
* `"allowed_channel" => [ "<T_OV_Testchannel>","<T_OV_Testchannel2>" ]` #Channels eintragen, in die der Bot Nachrichten senden darf

### Apache Modul aktivieren
* `sudo a2enmod rewrite`
* `sudo systemctl restart apache2.service`

### Apache-Config anpassen
* `sudo nano /etc/apache2/sites-enabled/000-default.conf`
* Folgenden Block in die Config einfügen/anpassen:\
        `DocumentRoot /opt/HermineBot/public`\
        `<Directory /opt/HermineBot/public>`\
                `Options -Indexes +FollowSymLinks`\
                `AllowOverride All`\
                `Require all granted`\
        `</Directory>`
* Danach den Apache neustarten:
`sudo systemctl restart apache2.service`

### DB erstellen und Benutzer anlegen
* Zu root werden\
`sudo su -`
* MySQL-Client starten\
`mysql`
* DB anlegen\
`create database hermine_bot;`
* DB-User anlegen\
`CREATE USER '<DB-Benutzernamen>'@localhost IDENTIFIED BY '<Passwort>';`
* DB-User Berechtigungen vergeben\
`GRANT ALL PRIVILEGES ON hermine_bot.* TO '<DB-Benutzernamen>'@'localhost';`
* MySQL-Client beenden\
`quit;`
* Aus Root-Kontext raus\
`exit`

### DB-User im Hermine-Bot eintragen
* `nano .env.local`
> Zugangsdaten nie in die eingecheckte `.env` eintragen, sondern nur in `.env.local` (wird von Git ignoriert).
* Config bearbeiten:\
`DATABASE_URL="mysql://<DB-Benutzernamen>:<Passwort>@127.0.0.1:3306/hermine_bot?charset=utf8mb4"` #DB-Benutzername und Passwort eintragen und die server_Version rausnehmen\

### Composer erforderliche Pakete installieren lassen
`composer install --no-dev`

### Config-Änderungen (.env.local) in den Produktivbetrieb übernehmen
`composer dump-env prod`

### DB-Grundstruktur importieren
`php bin/console doctrine:migrations:migrate`

### Berechtigung an Webserver übergeben
`sudo chown www-data:www-data -R /opt/HermineBot/`

## Cron-Job einrichten
Ohne Cron-Job verschickt der Bot keine geplanten Nachrichten. Als Webserver-Benutzer einrichten (`sudo crontab -u www-data -e`):\
`* * * * * php /opt/HermineBot/bin/console daemon:tick`

## Ersten Web-Benutzer anlegen
Nur für den ersten Administrator nötig; weitere Nutzer (auch API-Nutzer) danach in der Oberfläche unter „Nutzer“.

### Passwort-Hash erstellen
`php bin/console security:hash-password`

### User in DB anlegen
* Zu root werden\
`sudo su -`
* MySQL-Client starten\
`mysql`
* Datenbank wählen\
`use hermine_bot;`
* Benutzer in DB anlegen\
`INSERT INTO user (id, username, roles, password) VALUES (1, '<Benutzername>', '["ROLE_ADMIN"]', '<Generierter Passwort-Hash>');`
* MySQL-Client beenden\
`quit;`
* Aus Root-Kontext raus\
`exit`

## Web-Zugriff
* Webseite, wie in env.app.php konfiguriert, aufrufen.
* Mit den Anmeldedaten, die per MySQL-Command angelegt wurden, anmelden

## Nachricht per Bash-Console / -Script verschicken
`php /opt/HermineBot/bin/console hermine:send-message -c <Hermine-Channel> -m "<Nachricht>"`
> Achtung: Die Nachricht geht sofort und echt an den Channel – zum Testen nur einen Test-Channel aus `allowed_channel` verwenden.

## Disclaimer
Ich arbeite nicht für StashCat und habe auch ansonsten keinen Bezug zur `stashcat GmbH`. Es war ein Hobby-Projekt um die Funktionen für den im THW eingesetzten Stashcat-Brand "Hermine" zu erweitern.
Hintergrund: Ich wollte automatisiert z.B. auf anstehende reguläre Dienste hinweisen und unter anderem die HU/AU/SP-Termine der OV-Fahrzeugflotte einpflegen damit wir rechtzeitig dran denken.

Liebe `stashcat GmbH` - bitte nicht hauen, wenn das so nicht geplant ist von euch. Will mir nur das Leben im THW nur ein wenig einfacher machen 😘
