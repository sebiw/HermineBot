# Lokales Setup mit Docker (WSL)

Verlinkt aus `AGENTS.md`. Die Einrichtung auf einem Server steht weiterhin in `readme.md`.

PHP 8.5, Webserver und Datenbank laufen in Containern (`compose.yaml`). Alle Befehle mit `php8.5 …` – auch in `bin/pruefen` – funktionieren unverändert: `tools/docker/php8.5` leitet sie in den Container `hermine-bot-php` weiter. Der Container teilt das Netz mit WSL und sieht `$HOME` und `/tmp` unter denselben Pfaden, deshalb gelten `127.0.0.1`, Ports, Pfade und Umgebungsvariablen drinnen wie draußen.

- **Voraussetzungen in WSL:** Docker Engine (Benutzer in der Gruppe `docker`), Git. Kein PHP, kein MariaDB, kein Webserver.
- **Einrichten (einmalig):**
  ```bash
  cd ~/projects/HermineBot
  docker compose up -d                                              # baut das PHP-Image beim ersten Mal
  ln -s ~/projects/HermineBot/tools/docker/php8.5 ~/.local/bin/php8.5   # nur, wenn es die Verknüpfung noch nicht gibt (eine reicht für alle Projekte)
  curl -fsSLo ~/.local/bin/composer https://getcomposer.org/download/latest-stable/composer.phar
  php8.5 ~/.local/bin/composer install
  php8.5 bin/console doctrine:migrations:migrate
  ```
- **`.env.local`:** `DATABASE_URL="mysql://bot:bot@127.0.0.1:3317/hermine_bot?serverVersion=8.4.0&charset=utf8mb4"`, dazu `APP_SECRET` (z. B. `openssl rand -hex 16`).
- **`config/legacy/`:** aus `config_examples/` kopieren. Lokal ohne echte Zugangsdaten und mit `baseUrl` auf eine Adresse ohne Server (z. B. `http://127.0.0.1:9/`): Dann kann nichts an Hermine gehen (siehe `AGENTS.md`, Achtung beim Senden).
- **Ersten Benutzer anlegen:** Hash mit `php8.5 bin/console security:hash-password`, dann `docker exec -it hermine-bot-mysql mysql -uroot -proot hermine_bot` und das `INSERT` aus `readme.md`.
- **Dienste:** App http://hermine-bot.localhost:8081 (nginx + php-fpm; mit dem Repo `dev-umgebung` auch ohne Port unter http://hermine-bot.localhost und in der Übersicht unter http://localhost, angemeldet über die Labels in `compose.yaml`; `*.localhost` zeigt immer auf den eigenen Rechner), MySQL 8.4 auf `127.0.0.1:3317` (Benutzer `bot`/`bot`, darf alle Datenbanken `hermine_bot*` nutzen, also auch `hermine_bot_test`). Die Ports sind so gewählt, dass andere Projekte gleichzeitig laufen können; ändern über `HERMINE_BOT_WEB_PORT`, `HERMINE_BOT_FPM_PORT`, `HERMINE_BOT_DB_PORT` vor `docker compose up -d`.
- **Daten aus der Produktion:** `bin/prod-daten-lokal --konto=<benutzer>` holt die Produktion über eine SSH-Verbindung (nur lesend), anonymisiert sie (Benutzer, Unterhaltungen, Log) und ersetzt die lokale Datenbank; die bisherige landet vorher in `data/backup/`. Das eigene Konto wird mit `--konto` unverändert übernommen. Braucht `PROD_SSH`, `PROD_APP_DIR`, `PROD_PHP` in `.env.local` (Werte in der privaten Betriebsdoku) und einen SSH-Key auf dem Server.
- **PHP-Version oder Erweiterungen ändern:** `docker/php/Dockerfile`, danach `docker compose up -d --build`.
- **Code und Container:** Die Container enthalten keinen Code, nur PHP, nginx und MySQL. Der Projektordner ist eingebunden (keine Kopie): Es läuft immer, was gerade ausgecheckt ist, Branch-Wechsel und Änderungen wirken sofort. Nach `git pull` wie gewohnt bei Bedarf `php8.5 ~/.local/bin/composer install`, `php8.5 bin/console doctrine:migrations:migrate`. Neu bauen (`docker compose up -d --build`) nur, wenn sich `docker/php/Dockerfile` ändert. In Worktrees läuft `php8.5` im selben Container mit dem Code des Worktrees; nginx zeigt immer den Hauptordner.
- **Daten:** Die Datenbank liegt im Docker-Volume `hermine-bot_mysql-data` und bleibt bei Branch-Wechsel und `docker compose down` erhalten; gelöscht wird sie nur mit `docker compose down -v`.
- **Zurück zu nativem PHP:** `rm ~/.local/bin/php8.5`, `docker compose down`.
