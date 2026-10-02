<?php
namespace HH\BambooConfigurator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product/variatie-ID's voor de bamboe terrasconfigurator.
 *
 * LET OP: hier staan alleen ID's, GEEN prijzen — prijzen worden bij elke paginaweergave
 * live uit WooCommerce gelezen (zie get_price() in hh-bamboe-configurator.php), zodat een
 * prijswijziging in de shop automatisch doorwerkt zonder dat hier iets aangepast hoeft te worden.
 *
 * Bron: dezelfde productkoppelingen als hh-decking-calc-v2/includes/config.php
 * (CONFIG['mappings'] en CONFIG['accessories']) — hier gedupliceerd zodat deze losse
 * marketing-widget geen harde runtime-afhankelijkheid heeft van die andere plugin.
 * Wijzig je daar een ID (nieuw product, vervangen variatie), werk 'm dan ook hier bij.
 */
const PRODUCT_IDS = [

	// Vlonderplanken (recht) — per plankmaat + kleur. Let op: ebony bestaat in de
	// catalogus alleen bij 140mm; bij 100/200mm is alleen espresso leverbaar
	// (zie syncColorAvailability() in configurator.js, die dit ook afdwingt in de UI).
	'planks' => [
		100 => [ 'espresso' => 51985 ],
		140 => [ 'espresso' => 33442, 'ebony' => 33446 ],
		200 => [ 'espresso' => 48135 ],
	],

	// Visgraat — vaste plankmaat 140x700mm, beide kleuren leverbaar.
	'visgraat' => [ 'espresso' => 40920, 'ebony' => 40926 ],

	// Regel onderconstructie: voor bamboe altijd Bangkirai 40x60, 3900mm
	// (ongeacht Balkon/Beton of In de tuin — zie calc_regels() SCENARIO A/D in
	// hh-decking-calc-v2/includes/class-calculator.php).
	'regel' => [ 'product' => 47705, 'variation' => 47706 ],

	// Piketpalen (In de tuin), 100cm variant.
	'piketpaal' => [
		'40x40' => [ 'product' => 13159, 'variation' => 13160 ],
		'50x50' => [ 'product' => 39142, 'variation' => 39143 ],
	],

	// Granulaatpads (Balkon/Beton).
	'granulaatpad' => 2591,

	// Slotbouten (alleen bij In de tuin): 100mm bij 40x40 paal, 110mm bij 50x50 paal.
	'slotbouten' => [
		'40x40' => [ 'product' => 32650, 'variation' => 32654 ],
		'50x50' => [ 'product' => 32650, 'variation' => 49076 ],
	],

	// Clips — tussenclips (doos à 100), start-/eindclips (doos à 25, alleen bij recht patroon).
	'tussenclips' => 36446,
	'startclips'  => 36445,

	// Onderhoudsolie — 0,75L en 2,5L.
	'olie' => [ 'small' => 49359, 'large' => 49365 ],
];
