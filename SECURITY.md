# Sicherheitskonzept

## Eingaben und Berechtigungen

- Produktdaten werden nur mit gültigem eigenen Nonce und objektspezifischem `edit_post` gespeichert. Import-/Berechnungs-AJAX erfordert zusätzlich `edit_products` und einen tatsächlichen Produktdatensatz.
- Einstellungen erfordern `manage_woocommerce` und einen eigenen Nonce. Ein Nonce ersetzt keine Berechtigungsprüfung.
- Freigabedaten aus dem Browser werden nicht übernommen. Der Server validiert Zahlen, Mengen, Einheiten, Kategorien und Nährwert-Konsistenz; Rezeptwerte werden beim Speichern neu berechnet.
- Zutatenmarkup ist auf wenige Hervorhebungen beschränkt. Sonstige Texte, Attribute und URLs werden beim Ausgeben im passenden Kontext escaped. Quellen-URLs entstehen nur aus bekannten Anbietern und validierten Codes.
- Rezept-/Quellen-JSON ist auf 300.000 Bytes, Tiefe 32 und eine begrenzte Zahl von Einträgen beschränkt.

## Veröffentlichung und private Daten

Eine SHA-256-Prüffreigabe bindet die angezeigten Daten an den geprüften Produktstand. Änderungen entziehen die Freigabe. Sie ist eine interne Integritätsprüfung, keine digitale Signatur und keine rechtliche Zertifizierung. Nutzer mit Produktbearbeitungsrechten dürfen weiterhin Rezepturen ändern und freigeben.

Öffentliche Ausgabe und Bestell-Snapshots enthalten keine privaten Rezeptmengen. Entwürfe und passwortgeschützte Produkte dürfen nicht über einen fremden Shortcode umgangen werden. Administrativer Vorschauzugriff unterliegt den WordPress-Berechtigungen.

## Private Editor-Wiederherstellung

Fehlerhafte Formulareingaben werden ausschließlich nach bestandenem Produkt-Nonce und objektspezifischer Autorisierung für höchstens eine Stunde separat zwischengespeichert. Benutzer- und Produkt-ID begrenzen den Zugriff; auch das Lesen und Verwerfen prüft erneut die Produktberechtigung. Verwerfen verwendet einen eigenen Nonce. Eine erfolgreiche Produktvalidierung entfernt die eigene Wiederherstellung.

Dieser Zwischenspeicher ist auf 300.000 JSON-Bytes sowie 100 Rezeptzeilen und 110 Quellen begrenzt. Er übernimmt ausschließlich bekannte Formularfelder, sichere Zutatenhervorhebung und kontrollierte Listenstrukturen, niemals Freigabe-Hashes oder Nonces. Ungültige numerische Eingaben bleiben bewusst als Text zur Korrektur erhalten; HTML-Attribute werden serverseitig escaped, JavaScript nutzt `.val()` und `.text()`. Wiederhergestellte Werte werden weder veröffentlicht noch für den Nutri-Score berechnet. Beim erneuten Speichern greift vollständig dieselbe Domänenvalidierung und Freigaberegel wie bei normalen Eingaben. JSON-Decodierungsfehler und übergroße Nutzlasten werden nicht als erfolgreiche Wiederherstellung ausgegeben.

## Externe Daten

Open Food Facts ist standardmäßig aus. Bewusste Abrufe verwenden einen festen HTTPS-Host, die sichere WordPress-HTTP-API, keine Redirects, einen Timeout und eine Antwortgrößengrenze. Ein atomarer, installationsweiter Options-Lock begrenzt Abfragen; Ergebnisse werden zwischengespeichert und Serverfehler führen zu einer Pause. Fremdes HTML, fehlende Nährwerte und Datenquellen werden nicht automatisch als geprüft behandelt. BLS-Suche und Nährwertberechnung laufen lokal.

## Lieferkette und Qualitätskontrollen

Actions sind an vollständige Commit-SHAs gebunden. Prüfjobs erhalten nur Leserechte und keine persistierten Git-Zugangsdaten. Nur der nach allen Checks gestartete Release-Job erhält `contents: write`; er führt keinen ausgecheckten Plugin-Code aus. GitHub-Releases verwenden das zuvor installierte und geprüfte ZIP mit SHA-256-Summe. Paket-Allowlist und Symlink-Prüfungen verhindern versehentlich ausgelieferte lokale Dateien.

Automatisierte Tests decken Domänenlogik, Nutri-Score-Referenzfälle, Datenkonsistenz, Paketgrenzen und reale WordPress-/WooCommerce-Sicherheitsfälle ab. Ein strenger Plugin-Check-Ergebnisparser lehnt technische Fehler, Warnungen, Checker-Abbrüche und unerkannte Ausgabe ab. Drei ausdrücklich benannte Verzeichnisrichtlinien werden für die reine GitHub-Verteilung ausgenommen: `plugin_updater_detected` wegen des schützenden `Update URI`-Headers sowie `readme_short_description_non_official_language` und `readme_description_non_official_language` wegen der gewünschten deutschen Dokumentation. Keine Sicherheitsprüfung ist ausgenommen. Vor einer WordPress.org-Einreichung müssen diese drei Ausnahmen entfallen. Die Testwerkzeuge sind in `package-lock.json` festgeschrieben und werden ohne Installationsskripte installiert.

Diese Prüfungen ersetzen kein unabhängiges Audit, keine Theme-Abnahme und keine Prüfung der fachlichen Lebensmittelangaben.

## Sicherheitslücke melden

Sensible Details über [GitHub „Report a vulnerability“](https://github.com/patsch9/patsch9-foodsettings/security/advisories/new) melden, sofern private Meldungen für das Repository aktiviert sind. Andernfalls den Maintainer über die im GitHub-Profil angegebene Kontaktmöglichkeit ansprechen. Zugangsdaten, Kundendaten und vollständige private Rezepturen nicht in öffentlichen Issues veröffentlichen.
