# Versionen und Releases

Das Plugin verwendet dasselbe Schema wie Accounting Bridge und Rental Engine:

- Plugin-Version: `YYYY.M.N`, z. B. `2026.11.1`. Monat ohne führende Null; fortlaufende Nummer innerhalb des Monats.
- Git-Tag: `v2026.11.1`.
- Vorabversion: `2026.11.2-beta.1` und Tag `v2026.11.2-beta.1`. Erlaubt sind `alpha`, `beta` und `rc`, jeweils mit positiver Nummer nach einem Punkt.
- Bei stabilen Versionen müssen Plugin-Header, `P9PS_VERSION`, `Stable tag` und beide Änderungsprotokolle übereinstimmen. Bei Vorabversionen bleibt `Stable tag` auf dem letzten stabilen Stand.

`0.1.0` bleibt als historische erste Implementierung dokumentiert. Der erste Release im gemeinsamen Schema wird als `2026.11.1` vorbereitet. Diese Änderung erstellt selbst weder Tag noch Release.

## Ablauf

1. Version im Plugin-Header, in `P9PS_VERSION` und in `readme.txt` aktualisieren; Eintrag in `CHANGELOG.md` und im Readme-Changelog ergänzen. README und Validierungsstand aktuell halten.
2. `python3 tools/release_metadata.py` und die relevanten Tests ausführen, Pull Request nach `main` öffnen und alle Checks bestehen lassen.
3. Pull Request zusammenführen und einen passenden `v`-Tag am geprüften Commit in `main` erstellen. Tags außerhalb der Historie von `main` werden abgelehnt. Vollständiger Checkout prüft dies auch im privaten Repository ohne nachträglichen authentifizierten Fetch.
4. Der Release-Workflow prüft den Tag und ruft denselben Qualitätsworkflow erneut für den konkreten Release-Commit auf. Dies ist eine bewusste Absicherung des tatsächlich veröffentlichten Standes, unabhängig von früheren PR-Prüfungen.
5. Erst nach erfolgreicher PHP-Matrix, Paketprüfung, WordPress-/WooCommerce-Integration und strengem Plugin Check erhält der Veröffentlichungsjob Schreibrechte. Er lädt das unveränderte geprüfte Artefakt, prüft die SHA-256-Summe und erstellt den GitHub-Release.

Vorabversionen erhalten das GitHub-Kennzeichen „Pre-release“ und ersetzen nicht den neuesten stabilen Release. Bestehende Releases werden nicht überschrieben; für Korrekturen eine neue Version verwenden. Bei Infrastrukturfehlern den fehlgeschlagenen Lauf erneut starten.

## Paket

`python3 tools/build_zip.py` erzeugt ZIP und Prüfsumme unter `dist/`. Der Ordner im ZIP heißt immer `patsch9-productsettings`, damit WordPress bestehende Installationen erkennt. Enthalten sind ausschließlich Plugin-Datei, `readme.txt`, GPL-Lizenz, Laufzeit-Code, CSS/JavaScript und der lokale BLS-Datenauszug. Tests, Workflows, Werkzeuge, Git-Daten, lokale Konfiguration und Entwicklungsdokumentation werden nicht ausgeliefert. Dateizeiten und Dateirechte im ZIP sind fest definiert; gleiche Quellen erzeugen mit derselben Python-/zlib-Version dieselben Paketbytes.

**Veröffentlichungsziel ist ausschließlich GitHub.** Es sind keine WordPress.org-/SVN-Schritte, SVN-Zugangsdaten oder externen Update-Downloads eingerichtet. `Update URI` verhindert, dass WordPress.org versehentlich ein fremdes Plugin desselben Slugs als Update anbietet; die Angabe implementiert keinen Updater.
