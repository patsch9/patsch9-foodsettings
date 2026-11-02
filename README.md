# P9 Product Settings – Lebensmittel

Eigenständiges WooCommerce-Plugin für Lebensmittelangaben, öffentliche Nährwertdaten und eigene Rezepturen. **Version 2026.11.1 ist für den ersten GitHub-Release vorbereitet.** WooCommerce wird benötigt, Germanized/Germanized Pro nicht. Es wird kein Code aus Germanized Pro übernommen und keine Lizenzprüfung umgangen.

## Funktionen

- Produktdaten-Reiter **Lebensmittel (P9)** im klassischen Produkteditor.
- Zutatenliste mit sicherer HTML-Hervorhebung, Übersicht der 14 Allergenklassen, getrennte Spurenhinweise.
- Nährwerttabelle je 100 g / 100 ml, optional zusätzlich je Portion, mit kJ und kcal sowie Fett, gesättigten Fettsäuren, Kohlenhydraten, Zucker, Eiweiß, Salz und optional Ballaststoffen.
- Bezeichnung des Lebensmittels, Nettofüllmenge, Abtropfgewicht, Lebensmittelunternehmer/Anschrift, Herkunft, Lagerung, Zubereitung, Haltbarkeitshinweis und Alkoholgehalt.
- **7.140 Lebensmittel des BLS 4.0 lokal enthalten**, ohne API-Schlüssel, Registrierung oder Laufzeitkosten. Suche nach deutschen Begriffen oder BLS-Code.
- **Open Food Facts API v3.6**: bewusster Barcodeabruf, Vorschau und Übernahme. Unterstützt aktuelles `nutrition.aggregated_set` sowie ältere `nutriments`-Antworten. Keine Hintergrundsynchronisation.
- Rezepteditor mit editierbaren Zutaten-Snapshots und Mengen in Gramm. Berechnung anhand des tatsächlichen fertigen, essbaren Gesamtgewichts. Zutatenvorschlag in absteigender Einsatzmenge.
- Nutri-Score nach dem aktualisierten Algorithmus 2023: allgemeine Lebensmittel, Käse, rotes Fleisch, Fette/Öle/Nüsse/Samen, Getränke sowie reines Wasser. Fehlende Ballaststoffwerte, Prozentanteile oder Süßstoffangaben verhindern eine vermeintlich sichere Einstufung.
- Versionsbezogene Prüffreigabe: Änderungen entziehen die bestehende Freigabe; ungeprüfte Angaben erscheinen nicht im Shop. Unverändertes Speichern erhält eine Freigabe.
- Ausgabe im Produkt-Tab, alternativ Shortcode `[p9ps_food]` / `[p9ps_food id="123"]` für eigene Vorlagen und Shortcode-Blöcke.
- Privater Stand der freigegebenen Deklaration in Bestellpositionen für klassischen und Block-Checkout; **keine privaten Rezeptmengen** im Bestell-Snapshot. HPOS-Unterstützung.

## Installation

1. Das installierbare ZIP unter [GitHub-Releases](https://github.com/patsch9/patsch9-productsettings/releases) herunterladen, sobald ein Release erstellt wurde, z. B. `patsch9-productsettings-2026.11.1.zip`. Vorab steht dasselbe Paket als `release-assets` im jeweiligen GitHub-Actions-Lauf bereit. Die zugehörige `.zip.sha256` erlaubt die Integritätsprüfung mit `sha256sum -c DATEI.zip.sha256`. Alternativ lokal `python3 tools/build_zip.py` ausführen.
2. In WordPress unter **Plugins → Neues Plugin → Plugin hochladen** installieren und aktivieren. Den Ordnernamen `patsch9-productsettings` beibehalten. Ein ungeändert heruntergeladenes GitHub-Quellarchiv hat einen anderen Ordnernamen und ist nicht das empfohlene Updatepaket.
3. Produkt im **klassischen WooCommerce-Produkteditor** öffnen, ggf. zuerst als Entwurf speichern.
4. Produktdaten → Lebensmittel (P9) → Lebensmittel aktivieren und Angaben hinterlegen.
5. Endgültige Angaben kontrollieren, Prüffreigabe anklicken und speichern. Die Freigabe ist eine interne Arbeitsfunktion; sie ersetzt keine fachliche oder rechtliche Prüfung.
6. Ausgabe im verwendeten Theme überprüfen. Bei Block-Vorlagen ohne den klassischen Produkt-Tab den Shortcode-Block ergänzen.

Voraussetzungen: WordPress ab 6.7, PHP ab 8.1, WooCommerce ab 9.0. Getestete konkrete Umgebung und Grenzen stehen in [VALIDATION.md](VALIDATION.md). Der neue React-Produkteditor wird in dieser Version nicht unterstützt.

## Handelsprodukt importieren

Unter **WooCommerce → Lebensmitteldaten** Open Food Facts aktivieren. Im Produkt einen Barcode eingeben und abrufen. Vorschau mit Packung und Herstellerangaben abgleichen; erst danach **Als Produkt übernehmen** wählen. Das ersetzt die Nährwertfelder, Zutatenliste, Spuren und Allergenübersicht. Leere Quellwerte bleiben leer. Eine Kategorie- oder Prozentannahme vom vorherigen Produkt wird zurückgesetzt.

Community-Daten können falsch, unvollständig oder veraltet sein. Fehlende Angaben und Angaben unter einer Nachweisgrenze werden nicht zu null umgedeutet. Hersteller und Produktvariante sowie die Bezugsgröße kontrollieren. Ein fremder Nutri-Score wird nicht ungeprüft angezeigt.

## Eigene Rezeptur

1. Jede Zutat über BLS, Barcode oder manuell anlegen. Den passenden Zustand auswählen, etwa **roh** für rohe Einsatzmengen. Daten bleiben als Snapshot im Rezept erhalten; Änderungen der externen Datenquelle verändern keine freigegebene Deklaration.
2. Essbare Einsatzmengen in **Gramm** angeben. Für eine Quelle je 100 ml ist zur Rezeptübernahme eine gemessene Dichte in g/ml erforderlich. Es wird keine Dichte von Wasser unterstellt.
3. Fertiges Produkt nach Zubereitung/Abkühlen wiegen und dieses Gewicht als Fertiggewicht eintragen. Rezeptberechnungen werden je 100 g deklariert.
4. Berechnen. Pro Nährstoff gilt: `Summe(Zutatenmenge / 100 × Zutatenwert) / Fertiggewicht × 100`. Der Server rechnet beim Speichern erneut; Browserwerte sind nicht maßgeblich.
5. Zutatenvorschlag bewusst übernehmen und redaktionell prüfen: zusammengesetzte Zutaten, konkrete Getreide-/Nussarten, Allergenhervorhebung, QUID, Zusatzstoffe und Ausnahmen bei Wasserverlust. Der Vorschlag erzeugt keine vollständige rechtliche Zutatenkennzeichnung.
6. Prüffreigabe nach Abgleich der fertigen Angaben setzen und speichern.

Die einfache Massenbilanz eignet sich vor allem für Rezepturen mit Wasseraufnahme/-verlust. Verworfenes Kochwasser, auslaufendes/abgeschöpftes Fett, Gärung, Abseihen oder andere Nährstoffverluste erfordern fachliche Korrekturen bzw. passend bestimmte Endproduktwerte. Es werden keine Retentionsfaktoren simuliert. Fehlt ein Wert in einer Zutat, ist dieser Nährwert für das gesamte Rezept unbekannt. Keine Erfindung von Energie aus unvollständigen Makronährstoffen.

## Nutri-Score

Der interne Rechner setzt die **aktualisierten Regeln 2023** um, anhand des offiziellen Rechners vom 04.04.2024 und der FAQ vom 17.03.2025. Sonderregeln: Proteinobergrenze für rotes Fleisch; Proteinberücksichtigung für Käse; Energie aus gesättigten Fettsäuren und Fettsäure-/Gesamtfett-Verhältnis bei Fetten; eigene Schwellen und vier zusätzliche Negativpunkte für nicht nutritive Süßstoffe bei Getränken. Nur reines Wasser erhält bei Getränken A.

Die Produktkategorie und der anrechenbare Obst-/Gemüse-/Hülsenfruchtanteil müssen fachlich bestimmt werden. Nüsse oder beliebige Öle zählen nicht pauschal zu diesem Anteil. Trocknung, Konzentrate und die spezielle Fettkategorie können gesonderte Regeln erfordern. Dieser Anteil wird deshalb nicht aus Namen oder Zutaten-Keywords geraten. Die Berechnung verwendet Nährwerte in der Genauigkeit der ausgegebenen Deklaration und den Anteil auf eine Dezimalstelle gerundet. Alkohol über 1,2 % vol schaltet die Berechnung aus; weitere ausgeschlossene Gruppen sind bewusst als **nicht geeignet** zu kennzeichnen.

Für die Shopanzeige werden die passenden **offiziellen Grafiken und Nutzungsrechte** benötigt. Unter WooCommerce → Lebensmitteldaten Nutzungsrechte bestätigen und die offiziellen A–E-Grafiken aus der eigenen Registrierung auswählen. Zusätzlich muss am Produkt die Anzeige gewählt und diese Produktversion freigegeben sein. Ohne diese Voraussetzungen bleibt das Ergebnis intern. Es werden weder nachgebaute Logos noch eine vermeintliche automatische Registrierung geliefert.

## Grenzen des Umfangs

- **Kein vollständiger Germanized-Pro-Klon**: keine Pfandabrechnung, Grundpreisberechnung, altersabhängigen Verkaufssperren, Rechtsdokumente oder automatische Auszeichnung sämtlicher lebensmittelrechtlicher Sonderfälle.
- Grundpreise lassen sich z. B. weiterhin mit Germanized Free pflegen. Die Lebensmittelfelder dieses Plugins sind davon unabhängig.
- **Variable Produkte:** gemeinsame Angaben nur nach ausdrücklicher Bestätigung identischer Daten **einschließlich Füllmengen** aller Varianten. Abweichende Varianten in dieser Version als separate einfache Produkte anlegen. Keine automatische Vererbung auf abweichende Varianten.
- Keine Migration oder Synchronisation proprietärer Germanized-Pro-Metadaten. Bei gleichzeitiger Nutzung doppelten Inhalt im Theme selbst vermeiden.
- Keine automatische Aussage „vegan“, „glutenfrei“, „Bio“, gesundheitsbezogene Werbung oder Analyse von Kreuzkontamination. BLS-Nährwerte enthalten keine belastbare Allergenliste.
- Keine Chargen-, Rückruf-, Etiketten-/PDF- oder konkrete MHD-Verwaltung, keine Mengenprozente/QUID-Automatik, kein Export in allgemeine Ernährungsdatenbanken.
- Das Plugin speichert Daten, übernimmt aber keine Garantie für vollständige Lebensmittelkennzeichnung. Ob eine Nährwertdeklaration im Einzelfall erforderlich ist oder eine Ausnahme gilt, wird nicht entschieden. Eine Zutaten-/Allergenanzeige ohne Tabelle ist möglich, wenn sämtliche Nährwertfelder leer bleiben.

## Daten, Lizenzen und Datenschutz

### Bundeslebensmittelschlüssel

**Max Rubner-Institut (2025): Bundeslebensmittelschlüssel (BLS), Version 4.0 – Deutsche Nährstoffdatenbank. Karlsruhe.** DOI: [10.25826/Data20251217-134202-0](https://doi.org/10.25826/Data20251217-134202-0). [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). Offizielle Quelle: [blsdb.de](https://blsdb.de/).

Mitgeliefert wird ein bearbeiteter Auszug: deutsche Lebensmittelbezeichnungen, Codes und neun Nährwerte; keine sonstigen 129 Nährstoffe, Einzelreferenzen oder Allergendaten. Numerische Werte unverändert; `-`, `<LOD` und `<LOQ` sind `null`. Details und SHA-256 der Originaldatei stehen in `data/bls/manifest.json`. Die Datenlizenz ist unabhängig von der GPL-Lizenz des Plugin-Codes. Die öffentlich ausgegebene Quellenangabe darf bei Verwendung der Daten nicht entfernt werden.

Im Originalauszug haben 42 Datensätze einen höheren gesättigten Fettwert als Gesamtfett, teils aufgrund sehr kleiner Werte/Rundungen. Diese Widersprüche werden angezeigt und vor Speicherung abgewiesen. Das Plugin erfindet keine Korrekturwerte; bei betroffenen Zutaten andere belastbare Angaben verwenden.

Der Auszug ist reproduzierbar: Original-Workbook über die BLS-Downloadseite beziehen, dann `python tools/build_bls.py /pfad/BLS_4_0_Daten_2025_DE.xlsx`. `openpyxl` ist nur für diesen Maintainer-Schritt erforderlich. Es werden keine Daten ohne Aktion des Shopbetreibers nachgeladen.

### Open Food Facts

[API-Dokumentation](https://openfoodfacts.github.io/documentation/docs/Product-Opener/api/), [Nutzungsbedingungen](https://world.openfoodfacts.org/terms-of-use), [ODbL 1.0](https://opendatacommons.org/licenses/odbl/1-0/). Bei Nutzung sind Namensnennung und ggf. Share-Alike-Pflichten für Datenbanken zu beachten, insbesondere bei Weitergabe/Veröffentlichung einer abgeleiteten Datenbank. Die Plugin-Code-Lizenz ändert daran nichts. Keine Produktbilder werden importiert.

Nur explizite Barcodeabfragen nach Aktivierung: Übertragung von Barcode, Server-IP und identifizierender Plugin-Kennung an `world.openfoodfacts.org`. Keine Kunden-, Bestell- oder Rezeptdaten. Kein API-Key. Abruf mit festem HTTPS-Host, ohne Redirects, begrenzter Antwortgröße und Timeout; mindestens fünf Sekunden Abstand pro WordPress-Installation, 24 Stunden Cache und Pause bei 429/5xx. Bei gemeinsam genutzter Server-IP können mehrere Installationen zusammen die OFF-Grenzen überschreiten. Keine Namenssuche/Autocomplete über OFF.

### Speicherorte

Produktmeta `_p9ps_food` enthält die private Rezeptur und die Deklaration. Öffentlich wird ausschließlich die freigegebene Deklaration ohne Einsatzmengen ausgegeben. `_p9ps_food_snapshot` archiviert diesen Stand in Bestellpositionen, ebenfalls ohne private Rezeptmengen. Admin-/Importzugriff erfordert Produktschreibrechte und Nonce. Einstellungen erfordern `manage_woocommerce`.

Deaktivierung/Entfernung löscht Produkt- und Bestelldaten nicht automatisch. Das schützt bestehende Deklarationen und Bestellhistorie; Bereinigung vor einer endgültigen Entfernung bei Bedarf gezielt durchführen.

## Entwicklung

```sh
php tests/run.php
find . -name '*.php' -not -path './dist/*' -print0 | xargs -0 -n1 php -l
node --check assets/admin.js
python tools/build_zip.py
python3 tests/test-release.py
python3 tests/test-plugin-check.py
```

Integration ausschließlich in einer **separaten Wegwerf-Testinstallation** mit WooCommerce und WP-CLI: `P9PS_RUN_INTEGRATION_TESTS=1 wp eval-file tests/integration.php`. Dieser Test legt Produkte und einen Testbenutzer an und verändert die P9-Einstellungen. Nicht im Live-Shop ausführen.

Der Workflow `Plugin checks` prüft PHP 8.1/8.3/8.4, Domänen-/Referenz-/Datentests, JavaScript, Release-Metadaten und Paketgrenzen. Anschließend wird das erzeugte ZIP in einer frischen WordPress-/WooCommerce-Installation mit echter Datenbank installiert. Die Integrations- und Sicherheitsregressionen sowie der strenge WordPress Plugin Check laufen auf diesem Paket. Technische Fehler, Warnungen und unvollständige Checker-Ausgabe blockieren die Freigabe. Drei reine Verzeichnisrichtlinien werden wegen der GitHub-Verteilung und der deutschen Readme gezielt ausgenommen; Details und verbleibende Grenzen stehen in [SECURITY.md](SECURITY.md) und [VALIDATION.md](VALIDATION.md). Die festgeschriebenen Testabhängigkeiten werden zusätzlich mit `npm audit --audit-level=high` geprüft.

Release-Tags verwenden wie die anderen P9-Plugins `vYYYY.M.N`, optional `-alpha.N`, `-beta.N` oder `-rc.N`. Sie müssen exakt zur Plugin-Version passen und auf einen Commit in `main` zeigen. Der Release-Workflow verwendet dieselben Checks und veröffentlicht danach das bereits geprüfte ZIP samt SHA-256-Prüfsumme auf GitHub. Es gibt keinen WordPress.org-/SVN-Deploy und keinen automatischen Updater; GitHub-Updates werden per ZIP installiert. Ablauf: [VERSIONING.md](VERSIONING.md). Sicherheitskonzept und Meldungen: [SECURITY.md](SECURITY.md).

Referenzen: [Germanized-Funktionsbeschreibung](https://vendidero.de/doc/woocommerce-germanized/lebensmittel-auszeichnen), [offizieller Nutri-Score-Rechner und Nutzungsbedingungen](https://www.santepubliquefrance.fr/en/nutrition-and-physical-activity/nutri-score), [LMIV, VO (EU) 1169/2011](https://eur-lex.europa.eu/eli/reg/2011/1169/oj).

## Lizenz

Plugin-Code: GPL-2.0-or-later, siehe [LICENSE](LICENSE). BLS-Daten: CC BY 4.0. Importierte OFF-Daten: ODbL/Dateninhaltslizenz laut Quelle. Nutri-Score-Marken und offizielle Grafiken verbleiben bei den jeweiligen Rechteinhabern.

