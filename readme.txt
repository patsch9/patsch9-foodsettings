=== Patsch9 Food Settings – Lebensmittel ===
Contributors: patsch9
Tags: woocommerce, food, nutrition, ingredients, allergens
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 2026.11.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lebensmittelangaben, lokale BLS-Nährwertsuche, Barcodeimport und Rezeptberechnung für WooCommerce.

== Description ==

Erste testbare Version. Zutaten, Allergene, Nährwertdeklaration und weitere Lebensmittelangaben im klassischen WooCommerce-Produkteditor pflegen. Germanized Pro wird nicht benötigt.

Das zusätzliche mehrzeilige Feld „Zutatenhinweise“ nimmt ergänzende Warntexte oder Kakaoangaben auf. Freigegebene Hinweise erscheinen unter der Zutatenliste; Zeilenumbrüche bleiben erhalten. Hinweise werden manuell gepflegt und mit der Deklaration in Bestellpositionen archiviert. Neue oder geänderte Hinweise erfordern eine erneute Freigabe. Ein leeres Feld verändert vorhandene Freigaben nicht.

7.140 BLS-4.0-Einträge (Max Rubner-Institut, CC BY 4.0) sind lokal enthalten. Open Food Facts kann für bewusste Barcodeabfragen aktiviert werden. Nährwerte eigener Rezepturen lassen sich mit Zutatenmengen und fertigem Produktgewicht berechnen. Fehlende Werte werden nicht als null angenommen.

Nutri-Score-Berechnung nach dem aktualisierten Algorithmus 2023. Für eine öffentliche Anzeige sind passende Nutzungsrechte und eigene offizielle Grafiken erforderlich. Importierte Angaben, Allergene und Rezeptvorschläge müssen vor Freigabe selbst überprüft werden.

Kein vollständiger Ersatz für sämtliche Germanized-Pro-Funktionen. Keine Pfandabrechnung, automatische rechtliche Prüfung oder Varianten mit abweichender Zusammensetzung. Umfang, Anleitung und Grenzen: README.md im Repository.

=== External services ===

Open Food Facts, nur nach Aktivierung und bewusstem Barcodeabruf: Barcode, Server-IP und Plugin-Kennung werden an https://world.openfoodfacts.org gesendet. Keine Kunden-, Bestell- oder Rezeptdaten. 24 Stunden Cache, keine Hintergrundsynchronisation oder Browseranfragen von Besuchern. Daten unter ODbL; Bedingungen: https://world.openfoodfacts.org/terms-of-use ; Datenschutz: https://world.openfoodfacts.org/privacy . Keine Bilder werden übernommen.

Die lokale BLS-Suche erzeugt keine externen Abfragen. Datenquelle: Max Rubner-Institut (2025): Bundeslebensmittelschlüssel (BLS), Version 4.0 – Deutsche Nährstoffdatenbank. Karlsruhe. DOI https://doi.org/10.25826/Data20251217-134202-0 ; Lizenz https://creativecommons.org/licenses/by/4.0/ . Auszug auf neun Nährwerte reduziert, fehlende Werte als null, numerische Werte unverändert.

Nutri-Score-Grafiken werden nicht von einem externen Dienst abgerufen. Offizielle Grafiken sind nach Klärung der Nutzungsrechte in der eigenen Mediathek zu hinterlegen. Informationen: https://www.santepubliquefrance.fr/en/nutrition-and-physical-activity/nutri-score .

== Installation ==

1. Installierbares ZIP mit dem Ordner patsch9-foodsettings hochladen und aktivieren.
2. WooCommerce aktivieren; klassischen Produkteditor verwenden.
3. Produktdaten > Lebensmittel (Patsch9) ausfüllen, Angaben prüfen, Prüffreigabe setzen und speichern.
4. Bei Bedarf WooCommerce > Lebensmitteldaten konfigurieren.

== Frequently Asked Questions ==

= Ist Germanized erforderlich? =

Nein. Grundpreise und andere Shop-Pflichten müssen unabhängig davon eingerichtet werden.

= Sind Zutaten und Allergene automatisch verlässlich? =

Nein. BLS-Daten enthalten keine belastbare Allergenliste, OFF ist eine Community-Datenbank. Zutaten und Allergene müssen fachlich geprüft und korrekt hervorgehoben werden.

= Funktioniert das mit variablen Produkten? =

Nur mit ausdrücklich bestätigten identischen Zutaten, Nährwerten und Füllmengen aller Varianten. Andernfalls getrennte einfache Produkte anlegen.

= Werden private Rezeptmengen öffentlich ausgegeben? =

Nein. Öffentlich erscheinen ausschließlich freigegebene Deklarationen und Quellenangaben. Administratoren und Benutzer mit Produktbearbeitungsrechten haben Zugriff auf die Rezeptur.

== Changelog ==

= 2026.11.1 =
* Eingaben nach Validierungsfehlern im privaten Editor-Zwischenspeicher wiederherstellbar; Shopangaben bleiben unverändert.
* Alkohol-Ausschluss ohne Verlust der Kategorieauswahl, UTF-8-Suche und Darstellung optionaler Angaben verbessert.
* Widersprüchliche BLS-Nährwerte sind leichter erkennbar und bleiben manuell korrigierbar.
* GitHub-Releases mit geprüften Installationspaketen, SHA-256-Prüfsummen und einheitlicher Versionierung.
* Verbindliche WordPress-/WooCommerce-, Sicherheits- und Paketprüfungen vor jeder Veröffentlichung.
* Passwortgeschützte Produktangaben geschützt; unbekannte Natriumwerte bleiben unbekannt.

= 0.1.0 =
* Erste Implementierung: Lebensmittelfelder, BLS-4.0-Datensatz, OFF-v3-Import, Rezepteditor, Nutri-Score-2023-Rechner und Prüffreigabe.
