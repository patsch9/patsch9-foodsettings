# Versionen und Releases

Für neue Releases gilt der Git-Tag als autoritative Release-Version. Der Quellstand auf `main` muss deshalb vor einem Release nicht mehr manuell auf die neue Versionsnummer umgeschrieben werden.

## Schema

Erlaubt sind ausschließlich:

- Stable/Hauptrelease: `YYYY.MM.N`, z. B. `2026.11.1`
- Alpha: `YYYY.MM.N-alphaN`, z. B. `2026.11.2-alpha1`
- Beta: `YYYY.MM.N-betaN`, z. B. `2026.11.2-beta1`

Dabei gilt:

- `MM` ist immer zweistellig (`01` bis `12`).
- `N` beginnt bei `1`.
- Alpha-/Beta-Zähler beginnen ebenfalls bei `1`.
- Neue Tags haben kein führendes `v`.
- `rc`, `-alpha.1`, `-beta.1` und andere Suffixe sind nicht zulässig.

Historische Tags und Releases bleiben unverändert.

## Automatische Versionsübernahme

Der Release-Workflow validiert zuerst den Tag und stellt weiterhin sicher, dass der Tag-Commit in `main` enthalten ist und zu einem gemergten Pull Request nach `main` gehört.

Für den eigentlichen Release-Build wird anschließend eine temporäre Arbeitskopie verwendet. Dort setzt `tools/apply_release_version.py` die Tag-Version automatisch in:

1. `Version` im Plugin-Header.
2. `P9PS_VERSION`.
3. `Stable tag` in `readme.txt`.
4. den Dateinamen des Release-ZIPs und seiner SHA-256-Datei.
5. Titel und Versionsnummer des GitHub Releases.

Auch bei Alpha- und Beta-Releases entspricht `Stable tag` innerhalb des GitHub-Prerelease-Pakets der Tag-Version. Dadurch bleibt das Paket für den strikten WordPress Plugin Check intern konsistent. Da Foodsettings nicht nach WordPress.org veröffentlicht wird, hat dieser Wert keinen Einfluss auf einen WordPress.org-Stable-Kanal.

Die Änderung findet nur im flüchtigen GitHub-Runner statt; der Release-Workflow schreibt keine Versionsänderung zurück nach `main`.

## Prüfungen

Normale Pull Requests behalten die vollständigen PHP-, Paket-, JavaScript-, WordPress-/WooCommerce-, Security- und Plugin-Check-Prüfungen.

Beim Tag-Lauf wird die bereits geprüfte Release-Quelle nicht vollständig erneut getestet. Stattdessen wird:

1. das Tag-Schema validiert,
2. Main-/PR-Provenance geprüft,
3. die Tag-Version in die temporäre Release-Arbeitskopie injiziert,
4. die resultierenden Versionsmetadaten validiert,
5. das installierbare Paket gebaut,
6. eine SHA-256-Prüfsumme erzeugt und
7. genau dieses Artefakt als GitHub Release veröffentlicht.

Die Release-Regressionstests decken Stable-, Alpha- und Beta-Tags sowie abgelehnte Legacy-/Fehlformate ab.

## Changelog

Changelog-Inhalte bleiben redaktionelle Projektdokumentation. Sie sind im normalen Entwicklungsstand weiterhin auf Konsistenz geprüft, werden beim Tag-Build aber nicht als Quelle der Release-Version verwendet. Die veröffentlichte Versionsnummer stammt ausschließlich aus dem Release-Tag.

## Veröffentlichungsziel

Foodsettings wird aktuell ausschließlich über GitHub veröffentlicht. Es gibt keinen WordPress.org-/SVN-Deploy für dieses Plugin. Alpha- und Beta-Releases bleiben daher ebenfalls reine GitHub-Prereleases.

Der Paketordner heißt weiterhin immer `patsch9-productsettings`, damit WordPress eine vorhandene Installation als dasselbe Plugin erkennt.

## Sicherheit

- Release-Tags müssen auf einem Commit in `main` liegen.
- Der Tag-Commit muss zu einem gemergten Pull Request nach `main` gehören.
- Das Release-Schema wird strikt validiert.
- Die Versionsänderung erfolgt nur im temporären Release-Workspace.
- Das veröffentlichte ZIP wird mit SHA-256 abgesichert.
- Actions bleiben auf Commit-SHAs gepinnt.
