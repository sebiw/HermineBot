# Changelog

Alle wesentlichen Änderungen am Hermine-Bot werden in dieser Datei festgehalten.

## [Unreleased]

### Sicherheit
- Mehrere Sicherheitslücken in den verwendeten Programmbausteinen sind geschlossen.

## [4.0.3] - 2026-11-06

Keine für Anwender sichtbaren Änderungen.

## [4.0.2] - 2026-11-03

Keine für Anwender sichtbaren Änderungen.

## [4.0.1] - 2026-11-03

### Behoben
- Auf schmalen Handys stehen Beschriftungen in Formularen über dem Eingabefeld, und nichts ragt mehr aus dem Rahmen.

## [4.0.0] - 2026-11-03

### Geändert
- Die Events-Seite ist neu aufgebaut wie die Dienste im StAN-Manager: eine Übersicht mit Stand und ganzem Text, Anlegen und Bearbeiten auf einer eigenen Seite mit Vorschau wie in Hermine und Platzhaltern zum Anklicken.
- Die Anmeldung ist durchgehend auf Deutsch, auch Knopf und Meldungen, und Passwortmanager erkennen Benutzername und Passwort jetzt zuverlässig.
- Die Oberfläche übernimmt Aussehen und Bedienung des StAN-Managers: Formulare mit Knöpfen im gemeinsamen grauen Rahmen, Navigation, Tabellen und Abzeichen.
- Meldungen nach dem Speichern erscheinen oben rechts und verschwinden von selbst, Rückfragen wie „Löschen?“ öffnen ein eigenes Fenster statt der Abfrage des Browsers.
- Die Hintergrundaufgaben zeigen den Plan in Worten und haben Speichern und Jetzt ausführen nebeneinander wie im StAN-Manager.
- Zeiten stehen ohne Bindestrich zwischen Datum und Uhrzeit.
- Das Enddatum wiederkehrender Nachrichten gilt einschließlich des ganzen Tages.

### Sicherheit
- Nach fünf falschen Anmeldungen mit demselben Namen wird die Anmeldung von dort für 15 Minuten gesperrt, auch für die Schnittstelle zum StAN-Manager.
- Über die Schnittstelle zum StAN-Manager gehen Nachrichten nur noch an freigegebene Channels, nicht mehr in private Unterhaltungen, und der Text wird wörtlich gesendet, ohne Abfragen externer Quellen auszulösen.

### Behoben
- Lehnt Hermine die Anmeldung einmal ab, sendet der Bot wieder, sobald Hermine erreichbar ist, statt dauerhaft stehen zu bleiben; nach einer Ablehnung wartet er zehn Minuten, damit der Zugang nicht gesperrt wird.
- Liefert eine externe Quelle für einen Platzhalter einen Fehler, wird die Nachricht später erneut versucht, statt mit Fehlertext oder unersetztem Platzhalter gesendet zu werden.
- Wiederkehrende Nachrichten werden nach dem Enddatum nicht mehr ein weiteres Mal gesendet.
- Die Gruppenstatistik zeigt wieder alle Gruppen, auch wenn mehrere gleich viele Mitglieder haben.
- Aufgaben aus der Nextcloud ohne festgelegte Priorität erscheinen nicht mehr als „hoch“ und nicht mehr oben.
- Fehlt der Schlüssel eines Channels oder einer Unterhaltung, steht im Log jetzt eine verständliche Fehlermeldung statt eines Absturzes.

## [3.1.0] - 2026-11-01

### Geändert
- Die Nutzer-Seite ist aufgeräumt: Die Übersicht zeigt nur noch die Liste, neue Nutzer legt man unter „Neuer Nutzer“ an, und auf der Bearbeiten-Seite stehen Schlüssel und Löschen in eigenen Abschnitten.
- Die Logs lassen sich nach Zeitraum, Bereich und Art filtern und frei durchsuchen; einzelne Aufrufe an Hermine sind als Routine ausgeblendet, der Knopf „Logs bereinigen“ entfällt, alte Einträge werden weiter automatisch gelöscht.
- Alle Tabellen sehen jetzt aus wie im StAN-Manager: weiße Fläche mit weichem Rand und feinen Zeilenlinien.
- In den Logs blättert man jetzt mit klaren Knöpfen für Zurück und Weiter, erste und letzte Seite statt mit vielen einzelnen Seitenzahlen.
- Die Versionsnummer steht jetzt unten rechts in der blauen Kopfleiste, die Anmeldezeile am Rechner auf Höhe der Navigation; so bleibt mehr Platz für den Inhalt.

## [3.0.0] - 2026-10-31

### Hinzugefügt
- Alte Log-Einträge werden automatisch gelöscht; auf der neuen Seite „Hintergrundaufgaben“ lassen sich Takt und Aufbewahrung einstellen.

## [2.3.2] - 2026-10-30

### Geändert
- Unerwartete Fehler der Weboberfläche und der Schnittstelle stehen jetzt im Log, mit Seite und Benutzer.

## [2.3.1] - 2026-10-29

### Geändert
- Die Navigationsleiste mit den Unterpunkten reicht jetzt von Rand zu Rand des Fensters.

## [2.3.0] - 2026-10-29

### Hinzugefügt
- Der StAN-Manager kann beim Bot abfragen, welche Hermine-Channels für Dienst-Abfragen zur Auswahl stehen.

## [2.2.2] - 2026-10-27

### Geändert
- Der Bot setzt unter seine eigenen Nachrichten kein automatisches Daumen-hoch mehr.

## [2.2.1] - 2026-10-27

### Behoben
- Persönliche Nachrichten aus dem StAN-Manager (z. B. die PIN für Dienst-Abfragen) gehen wieder in die bestehende Unterhaltung mit der Person und lassen sich dort lesen, statt „Inhalt konnte nicht entschlüsselt werden“ anzuzeigen.

## [2.2.0] - 2026-10-27

### Hinzugefügt
- Auf der Seite „Benutzer“ steht, wann ein Administrator oder API-Zugang zuletzt aktiv war – so fallen ungenutzte Zugänge auf.

## [2.1.0] - 2026-10-26

### Hinzugefügt
- Neue Seite „Benutzer“: Administratoren und API-Zugänge lassen sich in der Oberfläche anlegen, bearbeiten und löschen; der Schlüssel eines API-Zugangs wird beim Anlegen erzeugt, einmal zum Kopieren angezeigt und lässt sich jederzeit neu erzeugen.

### Geändert
- Die Oberfläche hat das neue Design des StAN-Managers: Reiter mit Unterpunkten, Menü-Knopf am Handy, Formulare in Abschnitten, einheitliche Knöpfe und Tabellen, die am Handy als Karten erscheinen; Löschen fragt vorher nach.

## [2.0.0] - 2026-10-22

### Sicherheit
- Die technische Grundlage des Bots wurde auf aktuelle, weiterhin gepflegte Versionen gebracht; damit sind zahlreiche bekannte Sicherheitslücken geschlossen.

## [1.2.0] - 2026-10-19

### Hinzugefügt
- Beim Planen einer Nachricht an eine Unterhaltung lässt sich die Unterhaltung über die Namen der Teilnehmer suchen und auswählen; die Nummer der Unterhaltung kann weiterhin von Hand eingetragen werden und bleibt auch bei archivierten Unterhaltungen erhalten.

### Geändert
- Die Oberfläche ist an das Layout des StAN-Managers angeglichen: größere Eingabefelder, bessere Darstellung auf dem Handy und breite Tabellen lassen sich seitlich scrollen.

## [1.1.1] - 2026-10-18

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
