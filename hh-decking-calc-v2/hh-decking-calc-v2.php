<?php
/**
 * Plugin Name:       HH Decking Calculator V2 (Test)
 * Description:       TEST-versie (v2) van de stappenplan calculator, onafhankelijk van de live plugin. Bevat de nieuwe bamboe vlonderplank-maten.
 * Version:           0.3.0
 * Author:            Jij
 * Text Domain:       hh-decking-calc-v2
 * Requires at least: 6.0
 * Requires PHP:      8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// BELANGRIJK: hoog dit nummer op bij ELKE wijziging aan wizard.js/wizard.css.
// WordPress gebruikt dit als cache-busting query-param (?ver=...) op de asset-URL's.
// Blijft dit nummer gelijk, dan kan de browser (of een cache-/optimalisatieplugin)
// gewoon het oude bestand blijven serveren, ook al staat het nieuwe al op de server.
define( 'HH_DC2_VERSION', '0.3.0' );
define( 'HH_DC2_PATH', plugin_dir_path( __FILE__ ) );
define( 'HH_DC2_URL', plugin_dir_url( __FILE__ ) );

require_once HH_DC2_PATH . 'includes/config.php';
require_once HH_DC2_PATH . 'includes/class-calculator.php';
require_once HH_DC2_PATH . 'includes/class-rest.php';

use HH\DeckingCalcV2\REST;

/**
 * Assets & Fonts enqueuen.
 */
function hh_dc2_enqueue_assets() {
	if ( ! is_singular() ) {
		return;
	}

	wp_enqueue_style(
		'hh-dc2-google-fonts',
		'https://fonts.googleapis.com/css2?family=Amiko:wght@400;600;700&family=Lato:wght@400;700&display=swap',
		array(),
		null
	);

	wp_register_style(
		'hh-dc2-wizard',
		HH_DC2_URL . 'assets/css/wizard.css',
		array(),
		HH_DC2_VERSION
	);

	wp_register_script(
		'hh-dc2-wizard',
		HH_DC2_URL . 'assets/js/wizard.js',
		array( 'wp-i18n' ),
		HH_DC2_VERSION,
		true
	);

	wp_localize_script(
		'hh-dc2-wizard',
		'HHDC2',
		array(
			'rest'   => array(
				'base' => esc_url_raw( get_rest_url( null, 'hh-decking-v2/v1' ) ),
			),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
			'i18n'   => array(
				'calcError' => __( 'Er ging iets mis bij berekenen.', 'hh-decking-calc-v2' ),
				'cartError' => __( 'Toevoegen aan winkelmand mislukt.', 'hh-decking-calc-v2' ),
				'fillReq'   => __( 'Vul alle verplichte velden in om verder te gaan.', 'hh-decking-calc-v2' ),
			),
			'config' => array(
				'wastePercent' => \HH\DeckingCalcV2\CONFIG['defaults']['waste_percent'],
				'mappings'     => \HH\DeckingCalcV2\CONFIG['mappings'],
			),
		)
	);

	wp_enqueue_style( 'hh-dc2-wizard' );
	wp_enqueue_script( 'hh-dc2-wizard' );
}
add_action( 'wp_enqueue_scripts', 'hh_dc2_enqueue_assets' );

/**
 * Shortcode: [hh_decking_calculator_v2]
 */
function hh_dc2_shortcode() {
	ob_start();
	?>
	<div class="hh-dc-wrapper" id="hh-dc2-wrapper">

		<div class="hh-dc-progress">
			<div class="hh-dc-progress-track"><div class="hh-dc-progress-fill" id="dc2-progress-fill"></div></div>
			<div class="hh-dc-steps-indicator">
				<span class="step-dot active">1. Materiaal</span>
				<span class="step-dot">2. Afmeting</span>
				<span class="step-dot">3. Opties</span>
				<span class="step-dot">4. Resultaat</span>
			</div>
		</div>

		<form id="dc2-form" class="hh-dc-form" novalidate>

			<div class="hh-dc-slide active" data-step="1">
				<div class="hh-dc-slide-header">
					<h3>Kies je materiaal</h3>
					<p>Selecteer de gewenste uitstraling voor je terras.</p>
				</div>

				<div class="hh-dc-cards-grid">

                    <label class="hh-dc-card-option">
						<input type="radio" name="type" value="hout" required>
						<div class="hh-dc-card-inner">
                            <div class="hh-dc-card-img" style="background-image: url('https://www.haarlemsehouthandel.nl/wp-content/uploads/2026/02/PHOTO-2026-01-28-15-02-01-3.jpg');"></div>
							<div class="hh-dc-card-content">
								<span class="hh-dc-card-title">Hout</span>
								<span class="hh-dc-card-desc">Natuurlijke uitstraling, robuust en warm.</span>
							</div>
						</div>
					</label>

                    <label class="hh-dc-card-option">
						<input type="radio" name="type" value="bamboe">
						<div class="hh-dc-card-inner">
                             <div class="hh-dc-card-img" style="background-image: url('https://www.haarlemsehouthandel.nl/wp-content/uploads/2026/02/PHOTO-2026-01-28-15-02-01-2.jpg');"></div>
							<div class="hh-dc-card-content">
								<span class="hh-dc-card-title">Bamboe</span>
								<span class="hh-dc-card-desc">Duurzaam, extreem hard en stabiel.</span>
							</div>
						</div>
					</label>

                    <label class="hh-dc-card-option">
						<input type="radio" name="type" value="composiet">
						<div class="hh-dc-card-inner">
                             <div class="hh-dc-card-img" style="background-image: url('https://www.haarlemsehouthandel.nl/wp-content/uploads/2026/02/PHOTO-2026-01-28-15-02-01.jpg');"></div>
							<div class="hh-dc-card-content">
								<span class="hh-dc-card-title">Composiet</span>
								<span class="hh-dc-card-desc">Onderhoudsarm, kleurvast en strak.</span>
							</div>
						</div>
					</label>

				</div>

                <div id="dc2-subtype-wrapper" style="display:none; margin-top:30px;">
                    <h4 style="text-align:center; margin-bottom:15px;">Kies de uitvoering</h4>
					<div id="dc2-subtype-container" class="hh-dc-cards-grid">
                        </div>
				</div>
			</div>

			<div class="hh-dc-slide" data-step="2">
				<div class="hh-dc-slide-header">
					<h3>Afmetingen</h3>
					<p>Wat is de grootte van het oppervlak?</p>
				</div>
				<div class="hh-dc-row-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
					<div class="hh-dc-field-group">
						<label for="dc2-length">Lengte (m)</label>
						<input type="number" step="0.01" min="0" id="dc2-length" name="length" placeholder="Bijv. 5.00" required>
					</div>
					<div class="hh-dc-field-group">
						<label for="dc2-width">Breedte (m)</label>
						<input type="number" step="0.01" min="0" id="dc2-width" name="width" placeholder="Bijv. 3.00" required>
					</div>
				</div>
			</div>

			<div class="hh-dc-slide" data-step="3">
				<div class="hh-dc-slide-header">
					<h3>Details & Afwerking</h3>
				</div>

				<div id="dc2-height-wrapper" style="display:none; margin-bottom:24px;">
					<label style="display:block; font-weight:600; margin-bottom:8px;">Dikte plank</label>
					<div id="dc2-height-container" class="hh-dc-cards-grid cols-2"></div>
				</div>

                <div id="dc2-color-wrapper" style="display:none; margin-bottom:24px;">
                    <label style="display:block; font-weight:600; margin-bottom:8px;">Kleur</label>
					<div id="dc2-color-container" class="hh-dc-cards-grid"></div>
				</div>

                <div id="dc2-poles-wrapper" style="margin-bottom:24px;">
					<label style="display:block; font-weight:600; margin-bottom:8px;">Onderconstructie</label>
					<div id="dc2-poles-container" class="hh-dc-cards-grid cols-2"></div>
                    </div>

                <div id="dc2-pole-size-wrapper" style="display:none;">
					<label style="display:block; font-weight:600; margin-bottom:8px;">Maat piketpaal</label>
					<div id="dc2-pole-size-container" class="hh-dc-cards-grid cols-2"></div>
				</div>
			</div>

			<div id="dc2-slide-error" class="hh-dc-slide-error" style="display:none;" role="alert"></div>

			<div class="hh-dc-nav-buttons">
				<button type="button" id="dc2-btn-prev" class="hh-dc-btn secondary" disabled>Terug</button>
				<button type="button" id="dc2-btn-next" class="hh-dc-btn primary">Volgende</button>
				<button type="submit" id="dc2-btn-calc" class="hh-dc-btn primary" style="display:none;">Bereken materiaal</button>
			</div>

		</form>

		<div id="dc2-result-container" class="hh-dc-result-container" style="display:none;">
			<div id="dc2-result" class="hh-dc-result"></div>

            <p class="hh-dc-contact-help">Kom je er niet uit? <a href="/contact">Neem contact op</a></p>

			<div class="hh-dc-actions-final">
				<button type="button" id="dc2-btn-restart" class="hh-dc-btn secondary">Opnieuw berekenen</button>
				<button type="button" id="dc2-add-to-cart" class="hh-dc-btn primary cart-btn" style="display:none;">In winkelmand plaatsen</button>
			</div>
		</div>

	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'hh_decking_calculator_v2', 'hh_dc2_shortcode' );

add_action(
	'rest_api_init',
	static function () {
		REST::register_routes();
	}
);