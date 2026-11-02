# Validierung

## 2026.11.1 – Release- und Sicherheitsabgleich

Stand: 02.11.2026. Keine Installation oder Änderungen an einem produktiven Shop; kein Release-Tag erstellt.

- **Lokal, PHP 8.3.6:** 14.485 Domänen-/Datenprüfungen bestanden, einschließlich 20 offizieller Nutri-Score-Referenzfälle und aller 7.140 BLS-Einträge. Regression für leere OFF-Natriumwerte ergänzt: Salz bleibt unbekannt.
- **Release und Paket:** 12 Tests für Versionen, stabile und vorläufige Tags, Versionsabweichungen, fehlende Änderungsprotokolle, reproduzierbare ZIP-Dateien, Prüfsummen, fehlende Laufzeitdateien, Symlinks und versehentlich ausgelieferte Dateien. 15 Tests für den Plugin-Check-Ergebnisparser; er lehnt Abbrüche, Warnungen, nichtleere Befunde, ungültige und zusätzliche Ausgabe ab.
- **Installiertes ZIP, lokale Wegwerf-Installation:** WordPress 7.1.4, WooCommerce 11.2.1, PHP 8.3.6, SQLite Database Integration 3.1.2 und Plugin Check 2.1.0. 32 Integrationsprüfungen bestanden. Zusätzlich zum bisherigen Umfang: fehlender/ungültiger Speicher-Nonce, echter AJAX-Handler mit gültigem/ungültigem Nonce, fehlende Produkt-ID, allgemeine gegenüber objektspezifischer Bearbeitungsberechtigung, Schutz passwortgeschützter Produktangaben und Shortcodes.
- **Plugin Check:** Keine technischen Fehler oder Warnungen am installierten ZIP. Drei gezielte Verzeichnisrichtlinien-Ausnahmen: GitHub-`Update URI` und die beiden Sprachprüfungen der gewünschten deutschen Readme. Diese Ausnahmen sind keine Freigabe für WordPress.org; sie müssen vor einer dortigen Einreichung entfallen. JSON wird vor der Feldvalidierung bewusst unverändert gelesen; die beiden entsprechenden PHPCS-Ausnahmen sind direkt an den Eingaben begründet. Der direkte SQL-Zugriff dient dem atomaren OFF-Lock und verwendet vorbereitete Parameter.
- **Workflows:** Lokal mit Actionlint 1.7.12 und Zizmor 1.31.1 geprüft. Keine offenen Befunde; eine begründete Zizmor-Ausnahme behält die portable `./.github/workflows/...`-Syntax für denselben Commit bei. PHP-/JavaScript-Syntax und `git diff --check` bestanden.
- **Testwerkzeuge:** `@wordpress/env` auf 12.0.0 aktualisiert und vollständige Abhängigkeiten per Lockfile festgeschrieben. `npm audit` meldet zum Prüfdatum keine hohen/kritischen, aber sieben mittlere Meldungen einschließlich abgeleiteter Abhängigkeitspfade (Express/qs sowie glob/minimatch im Testwerkzeug). Sie sind nicht im Plugin-ZIP enthalten. Der verwendete Docker-Prüfpfad startet keinen Playground-Express-Server. Keine ungeprüften Major-Overrides; hohe/kritische Meldungen blockieren die CI. Mittlere Meldungen bleiben zur Beobachtung dokumentiert.
- **GitHub:** Die abschließenden Ergebnisse stehen im Workflow des aktuellen PR-Commits. Der Release-Workflow ruft dieselben Prüfungen für den konkreten Tag auf und veröffentlicht nur das zuvor installierte, geprüfte Artefakt.

Die weiterhin geltenden Grenzen stehen unten. Der aktuelle CI-Lauf ergänzt die lokale SQLite-Prüfung um die echte MariaDB-Umgebung von wp-env.

## Historische Validierung von 0.1.0

Stand: 20.10.2026. Erste testbare Version; keine Installation oder Änderungen an einem produktiven Shop.

## Durchgeführt

- **PHP 8.3.6:** Syntaxprüfung sämtlicher PHP-Dateien und Ausführung von `php tests/run.php`: **14.484 Prüfungen bestanden**. Der Großteil prüft die Konsistenz aller 7.140 BLS-Datensätze und ihres Suchindexes.
- **Nutri-Score:** 20 numerische Referenzfälle aus den gespeicherten Ergebnissen des offiziellen Santé-publique-France-Workbooks vom 04.04.2024, verteilt auf alle fünf Algorithmusgruppen, stimmen in Punkten und Buchstaben überein. Zusätzlich Grenzwerttests für Energie, Zucker, gesättigte Fettsäuren und Salz sowie Sonderfälle für Käse, rotes Fleisch, Getränke mit Süßstoffen, Wasser und fehlende Eingaben.
- **Rezeptberechnung:** Massenbilanz mit Fertiggewicht, Konzentration durch Wasserverlust, unbekannte Werte, unmögliche Konzentrationen und ungültige Mengen. Fehlende Werte bleiben unbekannt.
- **BLS 4.0:** Originaldatei SHA-256 `524bbefe25b691f5cb3de7a9f3e27fa2967aebfeabf217d99414ba7806e78c60`; 7.140 Einträge, 127 unbekannte/Nachweisgrenzen-Werte im verwendeten Neun-Nährstoff-Auszug. 42 Teil-/Gesamtfett-Widersprüche im Original werden nicht automatisch korrigiert.
- **Separate echte Testinstallation:** WordPress **7.1.3**, WooCommerce **11.2.1**, PHP **8.3.6**, SQLite Database Integration **3.1.0**. `wp eval-file tests/integration.php`: **22 Prüfungen bestanden**. Getestet: Produkt-CRUD, Nonce/Berechtigung beim Speichern, echte WordPress-KSES-Bereinigung, Zutatenhervorhebung, Freigabe und Entzug, unverändertes Speichern, ungültige Eingaben, serverseitige Rezeptberechnung aus JSON-Formulardaten, geschützte Entwürfe, Shortcode sowie Bestell-Snapshots über Classic-/Store-API-Checkout-Hooks.
- **HTTP/AJAX in dieser Installation:** Login, Laden des echten Produkteditors und Ermittlung der Produkt-ID. BLS-Suche „Weizen Mehl“ liefert neun Treffer; Auswahl eines Treffers und anschließende Rezeptberechnung erfolgreich; ungültiger Nonce ergibt HTTP 403.
- **JavaScript:** Syntaxprüfung und acht DOM-Abläufe anhand des tatsächlich gerenderten Produkteditors: Suche, Vorschau, Zutat hinzufügen, JSON-Synchronisierung, Rezeptmodus, Schreibschutz berechneter Werte, bewusste Übernahme des Zutatenvorschlags, Freigabeentzug bei Änderung und Entfernen von Zutaten.
- **Open Food Facts:** Aktuelle v3.6-Staging-Antwort zu Barcode `3017620422003` abgerufen und erfolgreich normalisiert. Je 100 g: 2252 kJ, 539 kcal, 30,9 g Fett, 10,6 g gesättigte Fettsäuren, 57,5 g Kohlenhydrate, 56,3 g Zucker, 3,4 g Ballaststoffe, 6,3 g Protein, 0,107 g Salz. Keine Schreibzugriffe auf OFF. Zusätzlich synthetische Tests für v3.6-100-ml-Angaben, mg/g-Umrechnung, Kleiner-als-Werte, zubereitete statt verkaufte Daten und alte Sodium-/Salt-Felder.
- **OFF-HTTP-Grenze:** WordPress-HTTP-Testantwort ohne Produktionsnetzwerk. Identifizierender User-Agent, ausgeschaltete Redirects, 24-Stunden-Cache und Sperre der nächsten Netzwerkabfrage geprüft.
- **Paket:** Installierbares ZIP mit stabilem Plugin-Ordner. Enthält Code, Assets, BLS-Auszug, Lizenz und Anleitung; keine Testinstallation, Zugangsdaten oder privaten Rezepturen.

## Noch nicht abgedeckt

- Kein vollständiger Live-Kauf einschließlich Zahlungsdienstleister, Steuern, Versand, E-Mail und Rückerstattung. Checkout-Snapshots wurden über die realen WooCommerce-Hooks geprüft.
- Keine visuelle Browser-/Theme-Abnahme oder Tests auf Mobilgeräten; JavaScript-DOM-Tests prüfen Funktion, nicht Layout. Das verwendete Shop-Theme und etwaige andere Lebensmittel-Erweiterungen müssen in einer Staging-Kopie geprüft werden.
- Keine breite Matrix verschiedener WooCommerce-/WordPress-Versionen. MariaDB-Integration erfolgt ab 2026.11.1 in GitHub Actions; lokal wurde SQLite verwendet. Die CI-Matrix prüft die Domänenlogik zusätzlich mit PHP 8.1, 8.3 und 8.4; ihr Ergebnis ist im jeweiligen Workflow-Lauf sichtbar.
- Kein unabhängiges Sicherheits-Audit oder vollständiges WordPress.org-Plugin-Review. Die erstmalige Veröffentlichung im Plugin-Verzeichnis erfordert eine gesonderte Prüfung, einschließlich vollständiger Internationalisierung.
- Keine Validierung konkreter Lebensmittelrezepturen durch Labor, Lebensmittelüberwachung oder fachliche Kennzeichnungsprüfung. Datenqualität und fachliche Freigabe sind weiterhin erforderlich.

