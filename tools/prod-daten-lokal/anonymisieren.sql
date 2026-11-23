-- Anonymisierung einer Kopie der Produktion für die lokale Entwicklung (bin/prod-daten-lokal). Läuft im Wegwerf-Container auf einer
-- Kopie, nie auf der Produktion. Danach steht kein Benutzername, kein Empfänger einer Unterhaltung und kein Text an eine Unterhaltung
-- mehr in der Datenbank (dort können PINs stehen, siehe ApiController); das Log ist leer. Nachrichten an Channels bleiben.
SET FOREIGN_KEY_CHECKS = 0;

UPDATE user SET username = CONCAT( 'benutzer' , id ), password = '!';

-- Unterhaltungen: Empfänger, Kennung und Text (Personen, PINs)
UPDATE events SET
    channel_target = CONCAT( 'Unterhaltung ' , id ),
    channel_target_payload = JSON_OBJECT( 'conversationId' , CONCAT( 'anonym-' , id ) ),
    text = CONCAT( 'Nachricht ' , id , ' an eine Unterhaltung (anonymisiert)' )
-- Unterhaltung = conversationId gesetzt; bei Channels steht dort null
WHERE JSON_TYPE( JSON_EXTRACT( channel_target_payload , '$.conversationId' ) ) IS NOT NULL
  AND JSON_TYPE( JSON_EXTRACT( channel_target_payload , '$.conversationId' ) ) <> 'NULL';

DELETE FROM log;
UPDATE job_state SET last_message = NULL;

SET FOREIGN_KEY_CHECKS = 1;
