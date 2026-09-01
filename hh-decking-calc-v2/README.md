# HH Decking Calculator V2 (Test)

TEST-versie van de decking calculator. Dit is een **volledig losse WordPress-plugin**,
onafhankelijk van `hh-decking-calc/` (v1) — beide kunnen tegelijk actief zijn zonder
conflicten (eigen constantes, namespace, REST-namespace, shortcode en enqueue-handles).

Gebruik deze v2 om nieuwe producten/rekenregels te testen (bijv. de nieuwe bamboe
vlonderplank-maten) voordat ze naar de live plugin (v1) overgezet worden.

## Installatie
1. Plaats de map `hh-decking-calc-v2/` in `wp-content/plugins/`.
2. Activeer via **Plugins** in WP Admin (naast de bestaande "HH Decking Calculator").
3. Maak een (test)pagina en voeg de shortcode toe: `[hh_decking_calculator_v2]`.

## Wat is anders dan v1?
- Plugin-slug/bestand: `hh-decking-calc-v2.php`.
- Constantes: `HH_DC2_VERSION`, `HH_DC2_PATH`, `HH_DC2_URL`.
- PHP-namespace: `HH\DeckingCalcV2`.
- Shortcode: `[hh_decking_calculator_v2]`.
- REST-namespace: `/wp-json/hh-decking-v2/v1/...`.
- JS-localize object: `HHDC2` (i.p.v. `HHDC`).
- **Bamboe vlonderplanken**: er kan nu een **breedte/maat** gekozen worden (100 / 140 / 200mm),
  elk met de juiste planklengte. Voorheen was er geen manier om dit te selecteren en werd
  altijd hetzelfde (eerste) product gepakt, met een verkeerde planklengte voor het 200mm-product.
  Zie `includes/config.php` (mappings `hhline_bamboe_plank_*`) en `find_mapping()` in
  `includes/class-calculator.php`.

## Configuratie
- Open `includes/config.php` en vul je **product/variatie IDs** in bij `CONFIG['mappings']`.
- Pas `waste_percent` aan indien nodig.
- Accessoires-regels zijn mock; later vervangen door Excel-naar-PHP.

## Endpoints
- `POST /wp-json/hh-decking-v2/v1/calc` (nonce vereist: `X-WP-Nonce`)
- `POST /wp-json/hh-decking-v2/v1/add-to-cart` (stub, voegt toe aan mand als WC beschikbaar is)

## Security
- REST endpoints vereisen `wp_rest` nonce (voor front-end al meegegeven).
- Input wordt gesanitized en gevalideerd.

## Testen (PHPUnit)
1. Vereist PHP 8.1+ en PHPUnit.
2. Run: `phpunit --bootstrap vendor/autoload.php tests/test-calculator.php` (of direct zonder bootstrap als je global phpunit gebruikt).
3. Test is minimaal en controleert de mock-output.

## Roadmap
- Excel-formules 1-op-1 vertalen naar `Calculator` (pure functions).
- Lengte-strategie helper (dichtstbijzijnde/vast set).
- LinesBuilder centraliseren.
- Order meta uitbreiden.
- Fallbacks voor ontbrekende mappings/voorraad.
- Bij goedkeuring: wijzigingen overzetten naar v1 (`hh-decking-calc/`).
