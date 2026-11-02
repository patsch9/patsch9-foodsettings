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

## Externe Daten

Open Food Facts ist standardmäßig aus. Bewusste Abrufe verwenden einen festen HTTPS-Host, die sichere WordPress-HTTP-API, keine Redirects, einen Timeout und eine Antwortgrößengrenze. Ein atomarer, installationsweiter Options-Lock begrenzt Abfragen; Ergebnisse werden zwischengespeichert und Serverfehler führen zu einer Pause. Fremdes HTML, fehlende Nährwerte und Datenquellen werden nicht automatisch als geprüft behandelt. BLS-Suche und Nährwertberechnung laufen lokal.

## Lieferkette und Qualitätskontrollen

Actions sind an vollständige Commit-SHAs gebunden. Prüfjobs erhalten nur Leserechte und keine persistierten Git-Zugangsdaten. Nur der nach allen Checks gestartete Release-Job erhält `contents: write`; er führt keinen ausgecheckten Plugin-Code aus. GitHub-Releases verwenden das zuvor installierte und geprüfte ZIP mit SHA-256-Summe. Paket-Allowlist und Symlink-Prüfungen verhindern versehentlich ausgelieferte lokale Dateien.

Automatisierte Tests decken Domänenlogik, Nutri-Score-Referenzfälle, Datenkonsistenz, Paketgrenzen und reale WordPress-/WooCommerce-Sicherheitsfälle ab. Ein strenger Plugin-Check-Ergebnisparser lehnt auch Warnungen, Checker-Abbrüche und unerkannte Ausgabe ab. Diese Prüfungen ersetzen kein unabhängiges Audit, keine Theme-Abnahme und keine Prüfung der fachlichen Lebensmittelangaben.

## Sicherheitslücke melden

Sensible Details über [GitHub „Report a vulnerability“](https://github.com/patsch9/patsch9-productsettings/security/advisories/new) melden, sofern private Meldungen für das Repository aktiviert sind. Andernfalls den Maintainer über die im GitHub-Profil angegebene Kontaktmöglichkeit ansprechen. Zugangsdaten, Kundendaten und vollständige private Rezepturen nicht in öffentlichen Issues veröffentlichen.
