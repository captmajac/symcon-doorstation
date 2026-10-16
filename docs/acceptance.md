# Abnahme an IP-Symcon 9

Stand 2026-10-16: erledigte Punkte abgehakt; 15 und 16 kamen mit der letzten Version hinzu.

Vorbereitung: Bibliothek installieren, Instanz *Doorstation Access* anlegen, RFID-Schlüssel-Variable (`doorstation/rfid/read`), RFID-Leser-Statusvariable und (optional) das bei Zugang auszuführende Skript wählen. Kachel auf einem Tablet und das alte WebFront auf dem Laptop öffnen.

- [ ] 1. Unbekannte Karte vorhalten → ID mit roter Zeit (Tooltip „unbekannt“), Tür bleibt zu, Meldung „Unbekannter Schlüssel …“ im Log.
- [ ] 2. In der Kachel „+“, Namen eingeben, „Anlegen“ → Wochenplan unter der Instanz (eine Gruppe je Wochentag); nächstes Vorhalten mit grüner Zeit, Skript läuft (`KEY_NAME` im Skript prüfen).
- [ ] 3. Wochenplan einschränken (z. B. jetzt „Gesperrt“) → beim Vorhalten rot „außerhalb Wochenplan“.
- [ ] 4. Zahnrad („Schlüssel verwalten“): deaktivieren → rot „inaktiv“; löschen → wieder „unbekannt“.
- [ ] 5. Wochenplan in der Konsole umbenennen → Name sofort in Kachel und HTML-Box.
- [x] 6. Dieselbe Karte dreimal hintereinander → drei Einträge in Kachel und HTML-Box (auch wenn die Lesevariable nur die ID enthält).
- [ ] 7. Home Key (iPhone, Watch) und AirTag zusätzlich zur Karte. *(Karte und iPhone-Home-Key geprüft; AirTag und Watch offen)*
- [x] 8. Kleine Kachel (nur letzter Eintrag) und große Kachel, helles und dunkles Design, Symbole sichtbar.
- [x] 9. Wochenplan-Aktionen erscheinen als „Zugang“ (grün) und „Gesperrt“ (rot) – prüft `IPS_SetEventScheduleAction` unter Symcon 9.
- [x] 10. ⏱ in der Kachel öffnet den Wochenplan (`openObject`), Bearbeiten dort möglich. *(9 und 10 in der Kachel-Visualisierung geprüft)*
- [x] 11. Statusvariable auf `degraded` → Punkt gelb, Variable „Leser“ unter der Instanz „Gestört“ (gelb); HTML-Box ohne Leserstatus.
- [x] 12. Ohne Skript: Instanz aktiv, bekannte Karte grün, Meldung „Zutritt für …, kein Skript für den Zugang gewählt“ im Log.
- [x] 13. Altes WebFront: Reihenfolge „Zugriffe“, Wochenpläne, „Leser“; Name links, Zeit rechts grün/rot; Wochenpläne aus der Kategorie „Schlüssel“ (ältere Version) wieder direkt unter der Instanz, Kategorie gelöscht.
- [x] 14. „Einträge in der Zugriffsliste“ auf 3 und auf 10 → Kachel und HTML-Box zeigen so viele Einträge; HTML-Box zeigt „Heute 19:22“ wie die Kachel, nach Mitternacht das Datum.
- [ ] 15. Variable „Leser“ zeigt Offline/Gestört/Online farbig über die Darstellung „Aufzählung“; das alte Profil `DSA.ReaderStatus` ist gelöscht; im alten WebFront Symbol „Network“ bei „Leser“ und „Door“ bei „Zugriffe“ (eigene Symbole bleiben).
- [x] 16. Kachel übernimmt Text- und Akzentfarbe des Symcon-Themes (Rahmen, Fokus, Häkchen); Grün/Rot für Zeit und Punkte bleiben.
