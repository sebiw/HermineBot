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

## Disclaimer
Ich arbeite nicht für StashCat und habe auch ansonsten keinen Bezug zur `stashcat GmbH`. Es war ein Hobby-Projekt um die Funktionen für den im THW eingesetzten Stashcat-Brand "Hermine" zu erweitern.
Hintergrund: Ich wollte automatisiert z.B. auf anstehende reguläre Dienste hinweisen und unter anderem die HU/AU/SP-Termine der OV-Fahrzeugflotte einpflegen damit wir rechtzeitig dran denken.