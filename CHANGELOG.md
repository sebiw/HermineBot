# Changelog

Alle wesentlichen Änderungen am Hermine-Bot. Format nach [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), Versionierung nach [SemVer](https://semver.org/lang/de/).

## [Unreleased]

### Behoben

- Geplante Nachrichten werden nicht mehr doppelt verschickt, wenn ein Durchlauf länger als eine Minute dauert, und ein nicht antwortender Dienst hält den Versand nicht mehr unbegrenzt auf.

## [1.1.0] - 2026-10-18

### Hinzugefügt

- Die Versionsnummer wird oben rechts unter dem Login-Hinweis angezeigt; nach dem Login öffnet ein Klick darauf dieses Changelog.

### Geändert

- Der Login-Hinweis mit dem Link zum Ausloggen steht jetzt oben rechts statt am Seitenende.
- Schlägt das Versenden einer geplanten Nachricht fehl, versucht der Bot es bis zu dreimal; danach wird eine wiederkehrende Nachricht beim nächsten Termin fortgesetzt und eine einmalige rot als fehlgeschlagen markiert, bis sie erneut gespeichert wird.

### Behoben

- Beim manuellen Starten des Event-Ausführers erscheinen keine technischen Warnhinweise mehr zum Einlesen von Kalender-Aufgaben.
- Geplante Nachrichten werden nach einem Fehler nicht mehr jede Minute erneut verschickt.
- Eine fehlerhafte geplante Nachricht hält die übrigen nicht mehr vom Versand ab.
- Wiederkehrende Nachrichten, deren Versand fehlschlägt, gelten nicht mehr als übermittelt.
- Nicht erlaubte Intervalle werden beim Speichern auch ohne Enddatum abgelehnt.

### Sicherheit

- Geplante Nachrichten werden nur noch an freigegebene Channels verschickt.

## [1.0.0] - 2026-10-17

- Ausgangsstand der Versionierung: geplante Nachrichten an Channels und Unterhaltungen mit Wiederholung, Platzhalter aus externen Quellen und Kalender-Aufgaben, Empfang von Nachrichten per REST und Warteschlange, Protokoll.
