<?php
/**
 * Plugin Name:       HH Bamboe Terras Configurator
 * Description:       Losse marketing-widget voor de winter bamboe-actie: compacte terrasconfigurator
 *                     (rechthoekig terras) met richtprijs, incl. doorverwijzing naar offerte-op-maat
 *                     voor afwijkende vormen. Plaats via shortcode [hh_bamboe_configurator].
 * Version:           1.0.0
 * Author:            Jij
 * Text Domain:       hh-bamboe-configurator
 * Requires at least: 6.0
 * Requires PHP:      8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// BELANGRIJK: hoog dit nummer op bij ELKE wijziging aan configurator.js/configurator.css.
// WordPress gebruikt dit als cache-busting query-param (?ver=...) op de asset-URL's.
define( 'HH_BC_VERSION', '1.0.0' );
define( 'HH_BC_PATH', plugin_dir_path( __FILE__ ) );
define( 'HH_BC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Assets & fonts enqueuen — alleen op pagina's die de shortcode daadwerkelijk gebruiken,
 * zodat deze widget niet overal op de site wordt geladen.
 */
function hh_bc_enqueue_assets() {
	if ( ! is_singular() || ! has_shortcode( get_post()?->post_content ?? '', 'hh_bamboe_configurator' ) ) {
		return;
	}

	wp_enqueue_style(
		'hh-bc-google-fonts',
		'https://fonts.googleapis.com/css2?family=Amiko:wght@400;600;700&family=Lato:wght@400;700&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'hh-bc-configurator',
		HH_BC_URL . 'assets/css/configurator.css',
		array(),
		HH_BC_VERSION
	);

	wp_enqueue_script(
		'hh-bc-configurator',
		HH_BC_URL . 'assets/js/configurator.js',
		array(),
		HH_BC_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'hh_bc_enqueue_assets' );

/**
 * Shortcode: [hh_bamboe_configurator]
 *
 * Attributen (optioneel):
 *   contact_url  — waar "mail ons je schets + maten" naartoe linkt (default: /contact)
 *   promo_code   — kortingscode die in de chip + prijsregel wordt getoond (default: BAMBOE15)
 *   promo_pct    — kortingspercentage dat bij die code hoort (default: 15)
 */
function hh_bc_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'contact_url' => '/contact',
			'promo_code'  => 'BAMBOE15',
			'promo_pct'   => '15',
		),
		$atts,
		'hh_bamboe_configurator'
	);

	$contact_url = esc_url( $atts['contact_url'] );
	$promo_code  = esc_html( strtoupper( $atts['promo_code'] ) );
	$promo_pct   = esc_html( $atts['promo_pct'] );

	ob_start();
	?>
	<div class="hh-bc-app"
		data-hhbc-promo-code="<?php echo esc_attr( $promo_code ); ?>"
		data-hhbc-promo-pct="<?php echo esc_attr( $promo_pct ); ?>">

		<header class="hh-bc-topbar">
			<h1>Stel je bamboe terras samen</h1>
			<div class="hh-bc-promo-chip">Winteractie: <b><?php echo $promo_code; ?></b> = <?php echo $promo_pct; ?>% korting</div>
		</header>

		<div class="hh-bc-shape-note" id="hhbc-shapeNote">
			<p>Voor een <strong>rechthoekig</strong> terras. Schuine kant, hoek eruit of obstakel? <a href="<?php echo $contact_url; ?>" id="hhbc-shapeNoteLink">Mail ons je schets + maten voor een offerte op maat →</a></p>
			<button type="button" class="hh-bc-shape-note-close" id="hhbc-shapeNoteClose" aria-label="Notitie sluiten">✕</button>
		</div>

		<div class="hh-bc-layout">
			<section class="hh-bc-preview-pane">
				<div class="hh-bc-presets" id="hhbc-presetRow"></div>
				<div class="hh-bc-preview-wrap"><canvas id="hhbc-stage"></canvas></div>
				<div class="hh-bc-dims">
					<div class="hh-bc-dim-field">
						<label for="hhbc-inLengte">Lengte</label>
						<div class="hh-bc-stepper">
							<button type="button" id="hhbc-lenMinus" aria-label="Lengte verminderen">−</button>
							<input type="number" id="hhbc-inLengte" value="5.0" step="0.1" min="1" max="20" inputmode="decimal">
							<span class="hh-bc-unit">m</span>
							<button type="button" id="hhbc-lenPlus" aria-label="Lengte vermeerderen">+</button>
						</div>
					</div>
					<div class="hh-bc-dim-field">
						<label for="hhbc-inBreedte">Breedte</label>
						<div class="hh-bc-stepper">
							<button type="button" id="hhbc-breMinus" aria-label="Breedte verminderen">−</button>
							<input type="number" id="hhbc-inBreedte" value="3.0" step="0.1" min="1" max="20" inputmode="decimal">
							<span class="hh-bc-unit">m</span>
							<button type="button" id="hhbc-brePlus" aria-label="Breedte vermeerderen">+</button>
						</div>
					</div>
				</div>
			</section>

			<section class="hh-bc-options-pane">
				<div class="hh-bc-opt-scroll">

					<div class="hh-bc-opt-pair">
						<div class="hh-bc-opt-group">
							<label class="hh-bc-group-label">Legpatroon</label>
							<div class="hh-bc-opt-row hh-bc-cols-2" id="hhbc-patternRow">
								<label class="hh-bc-opt"><input type="radio" name="hhbc-pattern" value="recht" checked>
									<div class="hh-bc-opt-title">Recht</div></label>
								<label class="hh-bc-opt"><input type="radio" name="hhbc-pattern" value="visgraat">
									<div class="hh-bc-opt-title">Visgraat</div></label>
							</div>
						</div>
						<div class="hh-bc-opt-group">
							<label class="hh-bc-group-label">Leggen in de</label>
							<div class="hh-bc-opt-row hh-bc-cols-2" id="hhbc-richtingRow">
								<label class="hh-bc-opt"><input type="radio" name="hhbc-richting" value="lengte" checked>
									<div class="hh-bc-opt-title">Lengte</div></label>
								<label class="hh-bc-opt"><input type="radio" name="hhbc-richting" value="breedte">
									<div class="hh-bc-opt-title">Breedte</div></label>
							</div>
						</div>
					</div>

					<div class="hh-bc-opt-group">
						<label class="hh-bc-group-label">Plankmaat</label>
						<div class="hh-bc-opt-row hh-bc-cols-3" id="hhbc-maatRow">
							<label class="hh-bc-opt" data-hhbc-mm="100"><input type="radio" name="hhbc-maat" value="100">
								<div class="hh-bc-opt-title">100 mm</div>
								<div class="hh-bc-lock-note">Niet bij visgraat</div></label>
							<label class="hh-bc-opt" data-hhbc-mm="140"><input type="radio" name="hhbc-maat" value="140" checked>
								<div class="hh-bc-opt-title">140 mm</div>
								<div class="hh-bc-lock-note">Niet bij visgraat</div></label>
							<label class="hh-bc-opt" data-hhbc-mm="200"><input type="radio" name="hhbc-maat" value="200">
								<div class="hh-bc-opt-title">200 mm</div>
								<div class="hh-bc-lock-note">Niet bij visgraat</div></label>
						</div>
					</div>

					<div class="hh-bc-opt-pair">
						<div class="hh-bc-opt-group">
							<label class="hh-bc-group-label">Kleur</label>
							<div class="hh-bc-opt-row hh-bc-cols-2" id="hhbc-colorRow">
								<label class="hh-bc-opt hh-bc-color-opt"><input type="radio" name="hhbc-color" value="espresso" checked>
									<canvas data-hhbc-swatch="espresso"></canvas><span class="hh-bc-opt-title">Espresso</span></label>
								<label class="hh-bc-opt hh-bc-color-opt"><input type="radio" name="hhbc-color" value="ebony">
									<canvas data-hhbc-swatch="ebony"></canvas><span class="hh-bc-opt-title">Ebony</span></label>
							</div>
						</div>
						<div class="hh-bc-opt-group">
							<label class="hh-bc-group-label">Onderconstructie</label>
							<div class="hh-bc-opt-row hh-bc-cols-2" id="hhbc-polesRow">
								<label class="hh-bc-opt"><input type="radio" name="hhbc-poles" value="none" checked>
									<div class="hh-bc-opt-title">Balkon / Beton</div></label>
								<label class="hh-bc-opt"><input type="radio" name="hhbc-poles" value="with">
									<div class="hh-bc-opt-title">In de tuin</div></label>
							</div>
						</div>
					</div>

					<div class="hh-bc-opt-group" id="hhbc-poleSizeWrapper" hidden>
						<label class="hh-bc-group-label">Maat piketpaal</label>
						<div class="hh-bc-opt-row hh-bc-cols-2" id="hhbc-poleSizeRow">
							<label class="hh-bc-opt"><input type="radio" name="hhbc-poleSize" value="40x40" checked>
								<div class="hh-bc-opt-title">40×40 mm</div></label>
							<label class="hh-bc-opt"><input type="radio" name="hhbc-poleSize" value="50x50">
								<div class="hh-bc-opt-title">50×50 mm</div></label>
						</div>
					</div>

					<details class="hh-bc-price-details">
						<summary>Prijsopbouw &amp; kortingscode</summary>
						<div class="hh-bc-price-details-body">
							<div class="hh-bc-summary-row"><span>Oppervlakte</span><strong class="hh-bc-num" id="hhbc-odSurface">–</strong></div>
							<div class="hh-bc-summary-row"><span>Legpatroon</span><strong id="hhbc-odPattern">–</strong></div>
							<div class="hh-bc-summary-row"><span>Plankmaat / kleur</span><strong id="hhbc-odMaat">–</strong></div>
							<hr class="hh-bc-summary-divider">
							<div class="hh-bc-summary-row"><span>Vlonderplanken (<span id="hhbc-liBoardsQty">0</span>×)</span><strong class="hh-bc-num" id="hhbc-liBoards">€ 0</strong></div>
							<div class="hh-bc-summary-row"><span>Regels onderconstructie (<span id="hhbc-liRegelsQty">0</span>×)</span><strong class="hh-bc-num" id="hhbc-liRegels">€ 0</strong></div>
							<div class="hh-bc-summary-row" id="hhbc-liPalenRow"><span id="hhbc-liPalenLabel">Piketpalen</span><strong class="hh-bc-num" id="hhbc-liPalen">€ 0</strong></div>
							<div class="hh-bc-summary-row" id="hhbc-liBoutenRow" hidden><span>Slotbouten</span><strong class="hh-bc-num" id="hhbc-liBouten">€ 0</strong></div>
							<div class="hh-bc-summary-row"><span>Clips</span><strong class="hh-bc-num" id="hhbc-liClips">€ 0</strong></div>
							<div class="hh-bc-summary-row"><span>Onderhoudsolie</span><strong class="hh-bc-num" id="hhbc-liOlie">€ 0</strong></div>
							<div class="hh-bc-summary-row" id="hhbc-srDiscountRow" hidden><span>Korting (<span id="hhbc-srDiscountPct">0</span>%)</span><strong class="hh-bc-num" id="hhbc-srDiscount">− € 0</strong></div>

							<div class="hh-bc-discount-row">
								<input type="text" id="hhbc-inDiscount" placeholder="Kortingscode" maxlength="20">
								<button type="button" class="hh-bc-btn-secondary" id="hhbc-applyDiscountBtn">Toepassen</button>
							</div>
							<div class="hh-bc-discount-msg" id="hhbc-discountMsg"></div>
							<p class="hh-bc-price-note">Richtprijs incl. vlonderplanken, regels, bevestiging, clips en onderhoudsolie — o.b.v. dezelfde rekenregels als de volledige rekentool. Prijzen per stuk zijn nog indicatief. Verzending niet inbegrepen. Geldt voor een rechthoekig terras — bij een afwijkende vorm <a href="<?php echo $contact_url; ?>">mail ons je schets + maten →</a></p>
						</div>
					</details>

				</div>

				<div class="hh-bc-pricebar">
					<div class="hh-bc-pricebar-info">
						<span class="hh-bc-p-label">Richtprijs, alles inbegrepen</span>
						<span class="hh-bc-p-value hh-bc-num" id="hhbc-prTotal">€ 0</span>
						<span class="hh-bc-p-m2">≈ <strong class="hh-bc-num" id="hhbc-prPerM2">€ 0</strong> per m²</span>
					</div>
					<button type="button" class="hh-bc-btn-cart" id="hhbc-cartBtn">In winkelmand</button>
				</div>
			</section>
		</div>

	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'hh_bamboe_configurator', 'hh_bc_shortcode' );
