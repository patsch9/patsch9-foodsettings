# Änderungen

## 2026.11.1

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
