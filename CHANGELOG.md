# Änderungen

## Unveröffentlicht

- „Lebensmittel (P9)“ als WooCommerce-Produktoption in die Kopfzeile der Produktdaten verschoben. Der Lebensmitteldaten-Reiter ist nur sichtbar, wenn diese Option aktiviert ist; bestehende als Lebensmittel markierte Produkte werden beim ersten Speichern sauber in das neue Index-Metafeld übernommen.
- Pro Lebensmittel kann optional eine verpflichtende Checkout-Bestätigung für tatsächlich individuell angefertigte Waren aktiviert werden. Der Hinweis verweist auf § 312g Abs. 2 Nr. 1 BGB und macht im Editor ausdrücklich klar, dass der Lebensmittelstatus allein kein Widerrufsrecht ausschließt.
- Klassischer Checkout und Checkout-Block werden unterstützt. Im Checkout-Block wird die produktabhängige Bedingung über die WooCommerce Additional Checkout Fields API ab WooCommerce 9.9 ausgewertet; im klassischen Checkout erfolgt die Prüfung serverseitig anhand des Warenkorbs.
- Die erteilte Bestätigung wird mit Zeitpunkt, Wortlaut, Checkout-Typ und betroffenen Produkt-IDs an der Bestellung dokumentiert. Checkout-Rechtseinstellungen sind bewusst nicht Teil des Freigabe-Hashes der Lebensmitteldeklaration.
- Neue PHP- und DOM-Regressionstests sowie Release-Allowlist für Produktoption und Checkout-Modul ergänzt.

## 2026.11.1

- Optionales mehrzeiliges Feld „Zutatenhinweise“ für ergänzende Warntexte und Kakaoangaben: Klartext mit Zeilenumbrüchen unter der Zutatenliste, private Editor-Wiederherstellung und Bestell-Snapshots. Geänderte Hinweise entziehen die Prüffreigabe; leere neue Felder erhalten bestehende Freigabe-Hashes.
- Gemini-Review abgeglichen: Private Editor-Wiederherstellung nach Validierungsfehlern, Kategorieauswahl trotz Alkohol-Ausschluss erhalten, UTF-8-Zeichenlängen und bedingte Detailausgabe korrigiert.
- Widersprüchliche BLS-Zutaten zeigen die betroffenen Nährwerte und öffnen ihre editierbaren Felder; keine automatische Kappung von Quellenwerten.
- Logo-Auswahl auf echte Rasterbild-Anhänge begrenzt; zusätzliche Domänen-, Integrations- und JavaScript-DOM-Regressionen.
- Release-Ablauf an Accounting Bridge und Rental Engine angeglichen: `YYYY.MM.N`, optional `-alphaN` oder `-betaN`, Metadatenprüfung, installierbares ZIP und SHA-256-Prüfsumme. Veröffentlichung ausschließlich auf GitHub.
- Dasselbe geprüfte Paket wird nach PHP-Matrix, Domänentests, echten WordPress-/WooCommerce-Integrationstests und strengem WordPress Plugin Check veröffentlicht.
- Actions an vollständige Commit-SHAs gebunden; Leserechte für Prüfungen und Schreibrechte nur im Veröffentlichungsjob. Keine gespeicherten Checkout-Zugangsdaten.
- Paketbau mit Laufzeit-Allowlist, festem Ordnernamen, deterministischen Dateien und Ablehnung von Symlinks, versteckten Dateien und unerwarteten Dateitypen.
- Shortcode schützt auch passwortgeschützte Produkte vor unberechtigter Ausgabe.
- Leere Natriumwerte aus Open Food Facts werden nicht zu null Gramm Salz umgerechnet.
- Integrationstests erfordern eine ausdrückliche Testumgebungsvariable. Update URI verhindert eine Verwechslung mit fremden WordPress.org-Plugins.

## 0.1.0

- Erste testbare Implementierung mit Lebensmittelfeldern, BLS 4.0, OFF-Import, Rezeptberechnung, Nutri-Score und Prüffreigabe.
