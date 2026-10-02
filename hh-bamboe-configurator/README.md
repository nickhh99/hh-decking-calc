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

## Belangrijk: prijzen zijn nog indicatief
Alle board- en accessoireprijzen in `assets/js/configurator.js` (bovenin, `PRICE_PER_BOARD`
en `PRICE`) zijn placeholders — vervang deze door de echte Visma-prijzen voordat de
widget live gaat. De reken­regels zelf (aantal planken, regels, piketpalen/pads, clips,
olie) zijn 1-op-1 overgenomen uit `hh-decking-calc-v2/includes/class-calculator.php`.

## Geen echte winkelmand (nog)
De knop "In winkelmand" is nu een UI-bevestiging zonder WooCommerce-koppeling — bewust,
omdat de prijzen nog indicatief zijn. Zodra er echte productmappings + prijzen zijn,
kan dit net als in `hh-decking-calc-v2/includes/class-rest.php` naar een
`/add-to-cart`-REST-endpoint.

## Techniek
- Eén self-contained widget: alle CSS-classes zijn geprefixt met `hh-bc-`, alle
  element-ids met `hhbc-` — dit is een bewuste les uit `hh-decking-calc-v2` (daar zorgde
  een id-botsing tussen twee instanties van dezelfde plugin-familie op één pagina
  ervoor dat klikken niet meer werkte). De JS zoekt bovendien binnen de eigen
  `.hh-bc-app`-wortel (niet globaal op `document`), dus meerdere configurators op
  dezelfde pagina kunnen niet met elkaar of met andere plugins botsen.
- Het terras wordt getekend met een eenvoudige "schuine stroken"-projectie: het platte
  plankenpatroon (de bestaande, al geteste reken-/tekenlogica) wordt op een los canvas
  getekend en in dunne horizontale stroken, elk een fractie smaller naar achteren toe,
  op het zichtbare canvas geplakt. Dat geeft een trapeziumvorm — alsof je schuin van
  boven op het terras kijkt — zonder de plankentekenlogica zelf aan te passen.
- De widget claimt niet de hele viewport: hij heeft een vast hoogte-budget
  (`min(82vh, 760px)`) en scrollt zelf intern waar nodig, zodat de rest van de
  WordPress-pagina (header, menu, footer, overige content) gewoon normaal blijft
  werken eromheen.
