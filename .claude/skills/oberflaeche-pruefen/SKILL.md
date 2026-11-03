---
name: oberflaeche-pruefen
description: Prüft nach jeder Änderung an Templates, Styles, Navigation oder Formularen des HermineBots alle Seiten in allen Bildschirmbreiten gegen das einheitliche Erscheinungsbild des StAN-Managers (CI) und misst, ob etwas aus dem Fenster läuft, umbricht oder zerbricht. Unbedingt verwenden, bevor eine Oberflächen-Änderung im Bot als fertig gemeldet oder zum Commit vorgeschlagen wird, und wenn der Nutzer nach Layout, Handy, Mobile, CI, Einheitlichkeit, Farben, Navigation oder „sieht anders aus als im StAN-Manager“ fragt, auch ohne das Wort „Skill“.
---

# Oberfläche prüfen (HermineBot)

Der Bot sieht aus wie der StAN-Manager und nutzt dessen CSS und Skripte (`public/assets/stan/`). Deshalb gelten dieselben Regeln und dieselben Messungen. Maßstab ist der Abschnitt „Erscheinungsbild (CI)“ in `../StAN-Manager/docs/bausteine/OBERFLAECHE.md`; gemessen wird mit dessen `layout-check.js`, nicht mit einer Kopie. Das Ziel: Fehler findet das Werkzeug, nicht der Nutzer.

## Vor dem Bauen

1. Abschnitt „Erscheinungsbild (CI)“ in `../StAN-Manager/docs/bausteine/OBERFLAECHE.md` lesen.
2. Zu jedem neuen Baustein das Gegenstück suchen: im StAN-Manager eine vergleichbare Seite (Template unter `../StAN-Manager/templates/`) und die Klasse in `public/assets/stan/stan.css` (`grep -o '\.name[^{]*' public/assets/stan/stan.css`). Vorhandene Klasse nutzen, nichts Ähnliches in `main.css` nachbauen. Beispiel aus einem Fehler: eigene `.event-actions` statt `.icon-actions` – am Handy zerbrachen die Knöpfe Buchstabe für Buchstabe.
3. `main.css` nur für das, was es allein im Bot gibt.

## Ablauf

1. **Messen mit einem Befehl:** `.claude/skills/oberflaeche-pruefen/scripts/pruefen.sh <leerer-ordner-im-scratchpad>`. Er läuft `tests/Templates`, rendert alle Seiten als Admin, misst in allen Breiten des StAN-Prüfskripts bis 360 px und tippt Menü und Tooltips durch. Rückgabewert 0 heißt: nichts gefunden. Seiten, die Hermine erreichen oder senden, sperrt `render.php`.
2. **Jede Zeile ohne 200 klären.** Die Login-Seite hat wie im StAN-Manager ein eigenes Layout (mittig, ohne Kopf) und läuft nicht durch die Messung; nach Änderungen daran von Hand ansehen: `php8.5 .claude/skills/oberflaeche-pruefen/scripts/render.php <ordner> gast /`.
3. **Ansehen:** Mindestens eine breite und eine Handy-Ansicht (390 und 360 px) jeder geänderten Seite als Screenshot. Server: `cd <ordner> && python3 -I -m http.server 8781 --bind 127.0.0.1`. Messen findet Überläufe, aber nicht, was unruhig wirkt.
4. **Einheitlichkeit:** Die geänderte Seite neben ihr Gegenstück im StAN-Manager legen und die CI-Liste Punkt für Punkt durchgehen (Rahmen, Knöpfe, Abzeichen, Farben, Worte, Datum mit Uhrzeit nicht umbrechen, `|no_break`). Bei größeren Änderungen zusätzlich den Agenten `ci-review`.
5. **Bedienen:** Was sich nur beim Benutzen zeigt, wirklich benutzen (Menü am Handy, Rückfragen, Formular mit Fehler). Nie „Jetzt senden“, „Event-Ausführer“ oder Unterhaltungen laden: Das erreicht Hermine.
6. **Aufräumen:** Browser schließen, Server über die PID aus `ss -ltnp` beenden, Screenshots löschen, `git status` prüfen.

## Bericht

Nenne Vorbild, Zahl der Seiten und Breiten und was nur gerendert und was bedient wurde. „Keine Abweichung“ heißt nur: Das Skript fand nichts. Niemals „überall“, wenn es Stichproben waren.

## Neue Regel

Findet der Nutzer einen Fehler selbst, kommt die Regel als Prüfung in den StAN-Manager (`layout-check.js` oder dessen `PageStructureTest`) und, wenn sie sich am Quelltext prüfen lässt, auch in `tests/Templates/PageStructureTest.php` hier. So gilt sie für beide Projekte.

## Grenzen

Lokal, ohne Hermine, ohne `daemon:tick`. Braucht den StAN-Manager daneben (`../StAN-Manager`, sonst `STAN_MANAGER_DIR`) und Node mit Playwright wie dort.
