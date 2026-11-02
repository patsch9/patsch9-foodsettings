# Änderungen

## 2026.11.1

- Release-Ablauf an Accounting Bridge und Rental Engine angeglichen: `vYYYY.M.N`, Vorabversionen, Metadatenprüfung, installierbares ZIP und SHA-256-Prüfsumme. Veröffentlichung ausschließlich auf GitHub.
- Dasselbe geprüfte Paket wird nach PHP-Matrix, Domänentests, echten WordPress-/WooCommerce-Integrationstests und strengem WordPress Plugin Check veröffentlicht.
- Actions an vollständige Commit-SHAs gebunden; Leserechte für Prüfungen und Schreibrechte nur im Veröffentlichungsjob. Keine gespeicherten Checkout-Zugangsdaten.
- Paketbau mit Laufzeit-Allowlist, festem Ordnernamen, deterministischen Dateien und Ablehnung von Symlinks, versteckten Dateien und unerwarteten Dateitypen.
- Shortcode schützt auch passwortgeschützte Produkte vor unberechtigter Ausgabe.
- Leere Natriumwerte aus Open Food Facts werden nicht zu null Gramm Salz umgerechnet.
- Integrationstests erfordern eine ausdrückliche Testumgebungsvariable. Update URI verhindert eine Verwechslung mit fremden WordPress.org-Plugins.

## 0.1.0

- Erste testbare Implementierung mit Lebensmittelfeldern, BLS 4.0, OFF-Import, Rezeptberechnung, Nutri-Score und Prüffreigabe.
