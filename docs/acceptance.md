# Abnahme an IP-Symcon 9

Vorbereitung: Bibliothek installieren, Instanz *Doorstation Access* anlegen, Lesevariable (`doorstation/rfid/read`), Statusvariable und Türöffner-Skript wählen, Archivierung der Lesevariable einschalten. Kachel auf einem Tablet und das alte WebFront auf dem Laptop öffnen.

- [ ] 1. Unbekannte Karte vorhalten → rot „unbekannt“, Tür bleibt zu, Meldung „Unbekannter Schlüssel …“ im Log.
- [ ] 2. In der Kachel „+“, Namen eingeben, „Anlegen“ → Wochenplan unter der Instanz; nächstes Vorhalten grün, Türöffner-Skript läuft (`KEY_NAME` im Skript prüfen).
- [ ] 3. Wochenplan einschränken (z. B. jetzt „Gesperrt“) → beim Vorhalten rot „außerhalb Wochenplan“.
- [ ] 4. „Schlüssel verwalten“: deaktivieren → rot „inaktiv“; löschen → wieder „unbekannt“.
- [ ] 5. Wochenplan in der Konsole umbenennen → Name sofort in Kachel und HTML-Box.
- [ ] 6. Archivierung ausschalten → Hinweis auf der Konfigurationsseite und in der Kachel, Zutritt funktioniert weiter.
- [ ] 7. Home Key (iPhone, Watch) und AirTag zusätzlich zur Karte.
- [ ] 8. Kleine Kachel (nur letzter Eintrag) und große Kachel, helles und dunkles Design, Symbole sichtbar.
- [ ] 9. Wochenplan-Aktionen erscheinen als „Zugang“ (grün) und „Gesperrt“ (rot) – prüft `IPS_SetEventScheduleAction` unter Symcon 9.
- [ ] 10. ⏱ in der Kachel öffnet den Wochenplan (`openObject`), Bearbeiten dort möglich.
- [ ] 11. Statusvariable auf `degraded` → Punkt gelb, HTML-Box „Leser: degraded“.
