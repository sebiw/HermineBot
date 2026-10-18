# Changelog

Alle wesentlichen Änderungen am Hermine-Bot werden in dieser Datei festgehalten.

## [Unreleased]

### Geändert
- Der Event-Ausführer startet erst nach einem Klick auf den Button, nicht mehr schon beim Öffnen der Seite.

### Sicherheit
- Speichern, Löschen und Senden von Events, der Event-Ausführer, das Bereinigen der Logs und das Ausloggen lassen sich nicht mehr über präparierte Links oder fremde Webseiten auslösen.
- Bei einer fehlgeschlagenen Anmeldung an der Schnittstelle werden die gesendeten Daten nicht mehr zurückgegeben.
- Fehlermeldungen enthalten keine Inhalte aus Antworten des Messengers mehr.
- Die Schnittstelle gibt bei Fehlern keine technischen Details zum Programmablauf mehr zurück.

### Behoben
- Geplante Nachrichten werden nicht mehr doppelt verschickt, wenn ein Durchlauf länger als eine Minute dauert.
- Ein nicht antwortender Dienst hält den Versand geplanter Nachrichten nicht mehr unbegrenzt auf.
- Nachrichten aus der Warteschlange gehen bei einem Fehler nicht mehr verloren, sondern werden mehrmals erneut versucht und danach aufbewahrt.
- Eine vom Messenger nicht angenommene Nachricht wird nicht mehr als erfolgreich verschickt gemeldet.
- Die Schnittstelle zum Senden von Nachrichten antwortet bei fehlenden Angaben oder Fehlern mit einer Fehlermeldung statt einer Fehlerseite.
- Ein ungültiges Datum beim Speichern eines Events wird abgelehnt, statt einen Serverfehler auszulösen.
- Platzhalter in eingesetzten Inhalten aus externen Quellen lösen keine weiteren Abrufe mehr aus.

## [1.1.0] - 2026-10-18

### Hinzugefügt
- Die Versionsnummer wird oben rechts unter dem Login-Hinweis angezeigt; nach dem Login öffnet ein Klick darauf dieses Changelog.

### Geändert
- Der Login-Hinweis mit dem Link zum Ausloggen steht jetzt oben rechts statt am Seitenende.
- Schlägt das Versenden einer geplanten Nachricht fehl, versucht der Bot es bis zu dreimal.
- Danach wird eine wiederkehrende Nachricht beim nächsten Termin fortgesetzt und eine einmalige rot als fehlgeschlagen markiert, bis sie erneut gespeichert wird.

### Sicherheit
- Geplante Nachrichten werden nur noch an freigegebene Channels verschickt.

### Behoben
- Beim manuellen Starten des Event-Ausführers erscheinen keine technischen Warnhinweise mehr zum Einlesen von Kalender-Aufgaben.
- Geplante Nachrichten werden nach einem Fehler nicht mehr jede Minute erneut verschickt.
- Eine fehlerhafte geplante Nachricht hält die übrigen nicht mehr vom Versand ab.
- Wiederkehrende Nachrichten, deren Versand fehlschlägt, gelten nicht mehr als übermittelt.
- Nicht erlaubte Intervalle werden beim Speichern auch ohne Enddatum abgelehnt.

## [1.0.0] - 2026-10-17

- Ausgangsstand der Versionierung: geplante Nachrichten an Channels und Unterhaltungen mit Wiederholung, Platzhalter aus externen Quellen und Kalender-Aufgaben, Empfang von Nachrichten per REST und Warteschlange, Protokoll.
