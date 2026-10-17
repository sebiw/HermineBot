# Hermine Bot

* Automatisches verschicken von Nachrichten mittels API von Stashcat
* Die Credentials für Hermine/Stashcat sowie die Passphrase muss auf dem Server hinterlegt werden! (Siehe config files)
* Entwickelt um auch auf kleineren, eingeschränkteren Webspaces lauffähig zu sein, zudem ist der Stashcat-Api part losgelöst von Frameworks entwickelt um ggf. einfacher in anderer Software integriert werden zu können.

## Installation
Nebst dem üblichen `composer install`
* Start `composer dump-env prod`
* `DATABASE_URL` an die eigene Datenbank anpassen
* Kopiere Konfigurationsdateien aus `/config_examples` in `/config/legacy` kopieren und den eigenen Bedürfnissen anpassen.
* Führe `doctrine:migrations:migrate` aus
* Cron-Job einrichten der `private/runner.php` (am besten jede Minute) aufruft.

## Installation für Einsteiger
Die Installation wird in diesem Beispiel auf einem Debian 12 und eigener MySQL-SB durchgeführt.
### Installation von PHP und Apache
#### Pakete für die Installation des Repo-Keys von PHP installieren
* `sudo apt update`
* `sudo apt install -y lsb-release ca-certificates apt-transport-https software-properties-common gnupg2`

#### PHP Repo hinzufügen
* `echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/sury-php.list`

#### PHP Repo-Key hinzufügen
* `curl -fsSL  https://packages.sury.org/php/apt.gpg| sudo gpg --dearmor -o /etc/apt/trusted.gpg.d/sury-keyring.gpg`

#### PHP und Apache installieren
* `sudo apt update`
* `sudo apt install apache2 php8.1 php8.1-curl php8.1-xml php8.1-mysql php8.1-cli php8.1-mbstring`

### Installation der MySQL-DB
* `sudo apt install mariadb-server mariadb-client`

### Installation von sonstigen Paketen
* `sudo apt install doctrine git curl git unzip`

### Installation des Composer
> Ausführliche Anleitung zu Composer: https://www.digitalocean.com/community/tutorials/how-to-install-and-use-composer-on-debian-11
* `curl -sS https://getcomposer.org/installer -o composer-setup.php`
* `HASH="curl -sS https://composer.github.io/installer.sig"`
* `php -r "if (hash_file('SHA384', 'composer-setup.php') === '$HASH') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"`

* `sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer`
* `rm composer-setup.php`

### Download des HermineBots
* `cd /opt/`
* `sudo git clone --recurse-submodules https://github.com/sebiw/HermineBot.git`
* `cd HermineBot/`

### HermineBot Configs kopieren und anpassen
* `cp config_examples/*.php config/legacy/`
> Nach Bedarf auch die "config_examples/command_api_endpoints.json" nach config/legacy/ kopieren

#### env.api.php anpassen
* `ǹano /opt/HermineBot/config/legacy/env.api.php`
* `'baseUrl' => "https://api.thw-messenger.de/"`
* `'userEmail' => "<Mail-Adresse des Hermine-Accounts>",`
* `'userPassword' => "<Passwort des Hermine-Accounts>",`
* `'userPassphrase' => "<Verschlüsselungskennwort des Hermine-Accounts>",`
    
#### env.app.php anpassen
* `ǹano /opt/HermineBot/config/legacy/env.app.php`
* `"stashcat_event_company" => "THW OV <Ortschaft>"`
* `"allowed_channel" => [ "<T_OV_Testchannel>","<T_OV_Testchannel2>" ]` #Channels eintragen, in die der Bot Nachrichten senden darf
* `"base_url" => "http://localhost:80/"` #DNS-Name oder IP bei Bedarf an den Server anpassen

### Apache Modul aktivieren
* `sudo a2enmod rewrite`
* `sudo systemctl restart apache2.service`

### Apache-Config anpassen
* `sudo nano /etc/apache2/sites-enabled/000-default.conf`
* Folgenden Block in die Config einfügen/anpassen:\
        `DocumentRoot /opt/HermineBot/public`\
        `<Directory /opt/HermineBot/public>`\
                `Options Indexes FollowSymLinks`\
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
* `nano .env`
* Config bearbeiten:\
`DATABASE_URL="mysql://<DB-Benutzernamen>:<Passwort>@127.0.0.1:3306/hermine_bot?charset=utf8mb4"` #DB-Benutzername und Passwort eintragen und die server_Version rausnehmen\
`DATABASE_MESSAGE_URL="mysql://<DB-Usernamen>:<Passwort>@127.0.0.1:3306/messages?charset=utf8mb4"` #DB-Benutzername und Passwort eintragen und die server_Version rausnehmen

### Composer erforderliche Pakete installieren lassen
`composer install`

### Config-Äenderungen (.env) in den Produktivbetrieb übernehmen
`composer dump-env prod`

### DB-Grundstruktur importieren
`php bin/console doctrine:migrations:migrate`

### Berechtigung an Webserver übergeben
`sudo chown www-data:www-data -R /opt/HermineBot/`

## Web-Benutzer anlegen
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

## Disclaimer
Ich arbeite nicht für StashCat und habe auch ansonsten keinen Bezug zur `stashcat GmbH`. Es war ein Hobby-Projekt um die Funktionen für den im THW eingesetzten Stashcat-Brand "Hermine" zu erweitern.
Hintergrund: Ich wollte automatisiert z.B. auf anstehende reguläre Dienste hinweisen und unter anderem die HU/AU/SP-Termine der OV-Fahrzeugflotte einpflegen damit wir rechtzeitig dran denken.

Liebe `stashcat GmbH` - bitte nicht hauen, wenn das so nicht geplant ist von euch. Will mir nur das Leben im THW nur ein wenig einfacher machen 😘
