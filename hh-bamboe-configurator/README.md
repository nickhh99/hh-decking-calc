# HH Bamboe Terras Configurator

Losse marketing-widget voor de winter bamboe-actie (Google Ads-campagne). Compacte
configurator voor een **rechthoekig** terras — past zonder scrollen op desktop én
mobiel, met een schuine ("van boven schuin bekeken") weergave van het terras en een
richtprijs incl. alle accessoires, berekend met dezelfde rekenregels als de volledige
rekentool. Voor afwijkende vormen (schuine kant, hoek eruit, obstakel) verwijst de
widget actief door naar een offerte-op-maat via het contactformulier, in plaats van
te proberen onregelmatige vormen zelf te configureren.

## Installatie
1. Plaats de map `hh-bamboe-configurator/` in `wp-content/plugins/`.
2. Activeer via **Plugins** in WP Admin.
3. Maak een pagina (bv. de winteractie-landingspagina) en voeg de shortcode toe:
   `[hh_bamboe_configurator]`

## Shortcode-attributen (optioneel)
```
[hh_bamboe_configurator contact_url="/contact" promo_code="BAMBOE15" promo_pct="15"]
```
- `contact_url` — waar "mail ons je schets + maten" naartoe linkt (default `/contact`)
- `promo_code` — kortingscode die in de chip + prijsregel wordt getoond en gecontroleerd
- `promo_pct` — bijbehorend kortingspercentage

## Prijzen: live uit WooCommerce, nergens hardcoded
`includes/config.php` bevat alleen product-/variatie-ID's (dezelfde koppelingen als
`hh-decking-calc-v2/includes/config.php`). Bij elke paginaweergave haalt
`hh_bc_build_price_map()` in `hh-bamboe-configurator.php` de actuele prijs per ID live
op via `wc_get_product()->get_price()`, en geeft die als `window.HHBC.prices` mee aan de
JS (`wp_localize_script`). Wijzig je een prijs in de shop, dan klopt de configurator
vanzelf mee — er hoeft nergens in deze plugin iets aangepast te worden. Kan een prijs
niet gevonden worden (product verwijderd, WooCommerce uit), dan toont de widget een
duidelijke waarschuwing i.p.v. een misleidende "€ 0", en wordt "In winkelmand" uitgezet.

De reken­regels zelf (aantal planken, regels, piketpalen/pads, clips, olie) zijn 1-op-1
overgenomen uit `hh-decking-calc-v2/includes/class-calculator.php`. Let op: Ebony bestaat
in de catalogus alleen bij de 140mm vlonderplank — bij 100/200mm (recht patroon) is
alleen Espresso leverbaar; de UI dwingt dat ook af (zie `syncColorAvailability()` in
`assets/js/configurator.js`).

Wijzig je een product-ID in `hh-decking-calc-v2/includes/config.php` (nieuw product,
vervangen variatie), werk `hh-bamboe-configurator/includes/config.php` dan ook bij —
er is bewust geen harde runtime-afhankelijkheid tussen de twee plugins.

## Echte winkelmand
"In winkelmand" voegt nu echt alle regels toe aan de WooCommerce-mand: de vlonderplank/
visgraatplank, regels, piketpalen-of-granulaatpads (+ slotbouten bij piketpalen), clips
en olie — elk met het juiste product-/variatie-ID en de client-side berekende hoeveelheid.
`includes/class-rest.php` registreert `POST /wp-json/hh-bamboe-configurator/v1/add-to-cart`
(zelfde "alles of niets"-aanpak als `hh-decking-calc-v2/includes/class-rest.php`: lukt één
regel niet — geen voorraad, onbekend product — dan wordt de hele poging teruggedraaid en
krijgt de klant te zien wat er misging, i.p.v. een half gevulde mand). Ter beveiliging
accepteert de endpoint alleen product-ID's die ook echt in `PRODUCT_IDS` voorkomen, dus een
gemanipuleerd request kan geen willekeurig product toevoegen. Bij succes stuurt de widget
door naar de winkelmand-pagina.

## Techniek
- Eén self-contained widget: alle CSS-classes zijn geprefixt met `hh-bc-`, alle
  element-ids met `hhbc-` — dit is een bewuste les uit `hh-decking-calc-v2` (daar zorgde
  een id-botsing tussen twee instanties van dezelfde plugin-familie op één pagina
  ervoor dat klikken niet meer werkte). De JS zoekt bovendien binnen de eigen
  `.hh-bc-app`-wortel (niet globaal op `document`), dus meerdere configurators op
  dezelfde pagina kunnen niet met elkaar of met andere plugins botsen.
- Het terras wordt getekend met een eenvoudige "schuine stroken"-projectie: het platte
  plankenpatroon wordt op een los canvas getekend en in dunne horizontale stroken, elk
  een fractie smaller naar achteren toe, op het zichtbare canvas geplakt. Dat geeft een
  trapeziumvorm — alsof je schuin van boven op het terras kijkt.
- De planken zelf zijn **echte productfoto's**, niet getekend: `hh_bc_build_image_map()`
  haalt de WooCommerce featured image per product op (`get_the_post_thumbnail_url()`,
  dezelfde functie als `get_image_url()` in `hh-decking-calc-v2/includes/class-calculator.php`)
  en geeft die mee als `window.HHBC.images`. De JS crop't die foto "cover" per plankcel,
  spiegelt 'm om en om en voegt een lichte, pseudo-random helderheidsvariatie toe — zo
  oogt het tegelen van één foto niet als een zichtbaar herhaald kopie-plak-patroon. Heeft
  een product (nog) geen foto, of is die nog niet geladen? Dan valt die ene plank terug op
  de procedureel getekende placeholder — nooit een kapot plaatje.
- De widget claimt niet de hele viewport: hij heeft een vast hoogte-budget per onderdeel
  (preview `34dvh`, app-shell `min(82vh, 760px)`) en scrollt zelf intern waar nodig, zodat
  de rest van de WordPress-pagina (header, menu, footer, overige content) gewoon normaal
  blijft werken eromheen. Let op: dit moet in `dvh` (viewport-relatief), niet in `%` — een
  percentage-hoogte wordt namelijk berekend t.o.v. de output van de rest van de widget
  (topbar, vorm-notitie, prijsbalk), en krimpt dus juist wanneer er meer chrome verschijnt
  (bv. de prijs-waarschuwing) — precies wanneer de preview stabiel zou moeten blijven.
