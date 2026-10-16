# Abnahme an IP-Symcon 9

Vorbereitung: Bibliothek installieren, Instanz *Doorstation Access* anlegen, Lesevariable (`doorstation/rfid/read`), Statusvariable und (optional) Türöffner-Skript wählen. Kachel auf einem Tablet und das alte WebFront auf dem Laptop öffnen.

- [ ] 1. Unbekannte Karte vorhalten → rot „unbekannt“, Tür bleibt zu, Meldung „Unbekannter Schlüssel …“ im Log.
- [ ] 2. In der Kachel „+“, Namen eingeben, „Anlegen“ → Wochenplan unter der Instanz (eine Gruppe je Wochentag); nächstes Vorhalten grün, Türöffner-Skript läuft (`KEY_NAME` im Skript prüfen).
- [ ] 3. Wochenplan einschränken (z. B. jetzt „Gesperrt“) → beim Vorhalten rot „außerhalb Wochenplan“.
- [ ] 4. Zahnrad („Schlüssel verwalten“): deaktivieren → rot „inaktiv“; löschen → wieder „unbekannt“.
- [ ] 5. Wochenplan in der Konsole umbenennen → Name sofort in Kachel und HTML-Box.
- [ ] 6. Dieselbe Karte dreimal hintereinander → drei Einträge in Kachel und HTML-Box (auch wenn die Lesevariable nur die ID enthält).
- [ ] 7. Home Key (iPhone, Watch) und AirTag zusätzlich zur Karte.
- [ ] 8. Kleine Kachel (nur letzter Eintrag) und große Kachel, helles und dunkles Design, Symbole sichtbar.
- [ ] 9. Wochenplan-Aktionen erscheinen als „Zugang“ (grün) und „Gesperrt“ (rot) – prüft `IPS_SetEventScheduleAction` unter Symcon 9.
- [ ] 10. ⏱ in der Kachel öffnet den Wochenplan (`openObject`), Bearbeiten dort möglich.
- [ ] 11. Statusvariable auf `degraded` → Punkt gelb, Variable „Leser“ unter der Instanz „Gestört“ (gelb); HTML-Box ohne Leserstatus.
- [ ] 12. Ohne Türöffner-Skript: Instanz aktiv, bekannte Karte grün, Meldung „Zutritt für …, kein Türöffner-Skript gewählt“ im Log.
- [ ] 13. Altes WebFront: Reihenfolge „Zugriffe“, Wochenpläne, „Leser“; Zeilen grün/rot; Wochenpläne aus der Kategorie „Schlüssel“ (ältere Version) wieder direkt unter der Instanz, Kategorie gelöscht.
