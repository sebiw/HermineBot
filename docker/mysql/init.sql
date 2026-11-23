-- Läuft nur beim ersten Start mit leerem Volume. Die Test-Umgebung hängt „_test“ an den Namen der Datenbank (doctrine.yaml):
-- Der Benutzer darf deshalb alle Datenbanken hermine_bot* anlegen und nutzen.
GRANT ALL PRIVILEGES ON `hermine\_bot%`.* TO 'bot'@'%';
