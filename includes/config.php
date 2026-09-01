<?php
namespace HH\DeckingCalc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optie 1 – alle koppelingen & regels in code.
 * LET OP:
 * - 'type' moet één van: bamboe | hout | composiet
 * - 'color' alleen invullen waar kleur een eigen basisproduct is (bamboe/composiet)
 * - Hout heeft geen kleur (weglaten).
 * - Variabele producten: vul 'length_variations' met lengte_in_mm => variation_id
 * - Simpele producten: alleen 'product' invullen.
 */
const CONFIG = [
	'defaults' => [
		// Standaard zaagverlies/waste in %
		'waste_percent' => 10,
	],

	'mappings' => [

		// =========================
		// HOUT (variabele producten)
		// =========================

		// Bangkirai 21x145 (voorbeeld-IDs uit je sheet — verifieer even in WP)
		'bangkirai_21x145' => [
			'type'      => 'hout',
			'subtype' => 'bangkirai',
			'label'     => 'Bangkirai 21x145',
			'width_mm'  => 145,
			'thick_mm'  => 21,          // HOOGTE bepaalt keuze tussen 21/25/27
			'product'   => 12892,       // hoofdproduct-id
			'length_variations' => [     // lengte in mm => variation_id
				// 2300 => 4576,
				2450 => 15819,
				3650 => 15818,
				3950 => 41814,
				// 4300 => 4094,
				4600 => 41816,
				4900 => 41817,
			],
		],

		'bangkirai_25x145' => [
			'type'      => 'hout',
			'subtype' => 'bangkirai',
			'label'     => 'Bangkirai 25x145',
			'width_mm'  => 145,
			'thick_mm'  => 25,
			'product'   => 12900,
			'length_variations' => [
				2450 => 41033,
				2750 => 41032,
				3350 => 41030,
				3650 => 41029,
				4300 => 23813,
				4600 => 23814,
				4900 => 23815, // checken of dit klopt t.o.v. 21x145; vul anders juiste var-ID in
			],
		],

		'bangkirai_27x190' => [
			'type'      => 'hout',
			'subtype' => 'bangkirai',
			'label'     => 'Bangkirai 27x190',
			'width_mm'  => 190,
			'thick_mm'  => 27,
			'product'   => 12905,
			'length_variations' => [
				2450 => 41820,
				2750 => 41038,
				3050 => 41037,
				3350 => 41036,
				3650 => 41035,
				// 3950 => 3816,
				// 4600 => 1555,
				4900 => 23825,
			],
		],

		'angelim_43x140' => [
			'type'      => 'hout',
			'subtype' => 'angelim',
			'label'     => 'Angelim Vermelho 43x140',
			'width_mm'  => 140,
			'thick_mm'  => 43,
			'product'   => 47093,
			'length_variations' => [
				2500 => 47103,
				3000 => 47104,
				4000 => 47105,
				4500 => 47106,
			],
		],

		'angelim_43x190' => [
			'type'      => 'hout',
			'subtype' => 'angelim',
			'label'     => 'Angelim Vermelho 43x190',
			'width_mm'  => 190,
			'thick_mm'  => 43,
			'product'   => 47109,
			'length_variations' => [
				2500 => 47110,
				3000 => 47111,
				4000 => 47112,
			],
		],

		'douglas_28x195' => [
			'type'      => 'hout',
			'subtype' => 'douglas',
			'label'     => 'Douglas 28x195',
			'width_mm'  => 195,
			'thick_mm'  => 28,
			'product'   => 12619,
			'length_variations' => [
				3000 => 12624,
				4000 => 12625,
				5000 => 12623,
			],
		],

		'douglas_24x138' => [
			'type'      => 'hout',
			'subtype' => 'douglas',
			'label'     => 'Douglas 24x138',
			'width_mm'  => 138,
			'thick_mm'  => 24,
			'product'   => 12741,
			'length_variations' => [
				3000 => 12786,
				4000 => 12787,
				5000 => 29905,
			],
		],


		// =============================
		// COMPOSIET (variabele producten)
		// Kleur = basisproduct (color), variatie = lengte
		// =============================

		'hhline_composiet_stone_grey_23x140' => [
			'type'      => 'composiet',
			'color'     => 'stone_grey',
			'label'     => 'HHLine Composiet Stone Grey 23x140',
			'width_mm'  => 140,
			'thick_mm'  => 23,
			'product'   => 36213,
			'length_variations' => [
				2900 => 36214,
				4000 => 36215,     // TODO: vul variation_id voor 4000mm in (indien aanwezig)
			],
		],

		'hhline_composiet_ipe_23x140' => [
			'type'      => 'composiet',
			'color'     => 'ipe',
			'label'     => 'HHLine Composiet Ipe 23x140',
			'width_mm'  => 140,
			'thick_mm'  => 23,
			'product'   => 36208,
			'length_variations' => [
				2900 => 36209,
				4000 => 36210,     // TODO
			],
		],

		'hhline_composiet_teak_23x140' => [
			'type'      => 'composiet',
			'color'     => 'teak',
			'label'     => 'HHLine Composiet Teak 23x140',
			'width_mm'  => 140,
			'thick_mm'  => 23,
			'product'   => 47793,
			'length_variations' => [
				2900 => 47794,
				4000 => 47795,     // TODO
			],
		],

		'hhline_composiet_ebony_23x140' => [
			'type'      => 'composiet',
			'color'     => 'ebony',
			'label'     => 'HHLine Composiet Ebony 23x140',
			'width_mm'  => 140,
			'thick_mm'  => 23,
			'product'   => 36202,
			'length_variations' => [
				2900 => 36203,
				4000 => 36204,     // TODO
			],
		],


		// =========================
		// HHLINE BAMBOE (simpel)
		// =========================

		// Vlonderplanken
		'hhline_bamboe_plank_espresso_18x140x1860' => [
			'type'      => 'bamboe',
			'subtype'   => 'plank',
			'color'     => 'espresso',
			'label'     => 'HHLine Bamboe Vlonderplank Espresso 18x140x1860',
			'width_mm'  => 140,
			'thick_mm'  => 18,
			'product'   => 33442,
		],
		'hhline_bamboe_plank_ebony_18x140x1860' => [
			'type'      => 'bamboe',
			'subtype'   => 'plank',
			'color'     => 'ebony',
			'label'     => 'HHLine Bamboe Vlonderplank Ebony 18x140x1860',
			'width_mm'  => 140,
			'thick_mm'  => 18,
			'product'   => 33446,
		],
		'hhline_bamboe_plank_espresso_18x200x2000' => [
			'type'      => 'bamboe',
			'subtype'   => 'plank',
			'color'     => 'espresso',
			'label'     => 'HHLine Bamboe Vlonderplank Espresso 18x200x2000',
			'width_mm'  => 200,
			'thick_mm'  => 18,
			'product'   => 48135,
		],

		// Vlondertegels (paneel-varianten)
		'hhline_bamboe_tegel_espresso_0_54m2' => [
			'type'      => 'bamboe',
			'subtype'   => 'tegel',
			'color'     => 'espresso',
			'label'     => 'HHLine Bamboe Vlondertegel Espresso 0,54 m²',
			'width_mm'  => 0,   // voor tegels rekenen we straks anders
			'thick_mm'  => 0,
			'product'   => 48126,
		],
		'hhline_bamboe_tegel_ebony_0_54m2' => [
			'type'      => 'bamboe',
			'subtype'   => 'tegel',
			'color'     => 'ebony',
			'label'     => 'HHLine Bamboe Vlondertegel Ebony 0,54 m²',
			'width_mm'  => 0,
			'thick_mm'  => 0,
			'product'   => 48131,
		],

		// Visgraat
		'hhline_bamboe_visgraat_espresso_18x140x700' => [
			'type'      => 'bamboe',
			'subtype'   => 'visgraat',
			'color'     => 'espresso',
			'label'     => 'HHLine Bamboe Visgraat Espresso 18x140x700',
			'width_mm'  => 140,
			'thick_mm'  => 18,
			'product'   => 40920,
		],
		'hhline_bamboe_visgraat_ebony_18x140x700' => [
			'type'      => 'bamboe',
			'subtype'   => 'visgraat',
			'color'     => 'ebony',
			'label'     => 'HHLine Bamboe Visgraat Ebony 18x140x700',
			'width_mm'  => 140,
			'thick_mm'  => 18,
			'product'   => 40926,
		],

		// =========================
		// REGELS (balkon)
		// =========================

		'regel_douglas_44x70' => [
			'type'      => 'hout',
			'subtype'   => 'regel_douglas',
			'label'     => 'Douglas Regel 44x70',
			'width_mm'  => 44,
			'thick_mm'  => 70,
			'product'   => 12635, // TODO: vul hoofdproduct-id in
			'length_variations' => [
				// TODO: vul lengte_in_mm => variation_id in op basis van de 4 variaties
				2500 => 41481,
				3000 => 12638,
				4000 => 12639,
				5000 => 41480,
			],
		],

		'regel_hhline_25x40' => [
			'type'      => 'composiet',
			'subtype'   => 'regel_hhline',
			'label'     => 'HHLine Regel 25x40',
			'width_mm'  => 40,
			'thick_mm'  => 25,
			'product'   => 36441,
			'product_length_mm' => 2200, // vaste lengte 2200mm (info voor jezelf / evt. latere logica)
		],
	],

	// Accessoires doen we later (op basis van Excel-regels)
	'accessories' => [

	// ===== Universeel (voor alle types, keuzeopties) =====
	'piketpalen' => [
        'label'       => 'Piketpalen',
        'product_ids' => [
            13159, // Angelim Vermelho 40x40
            39142, // Angelim Vermelho 50x50
        ],
        // NIEUW: Variatie ID's voor de 100cm palen
        'variation_ids' => [
            13159 => 13160, // Variatie voor 40x40 100cm
            39142 => 39143, // Variatie voor 50x50 100cm
        ],
        'applicable'  => ['hout', 'bamboe', 'composiet'],
        'rule'        => 'manual',
    ],

	'regels' => [
			'label'       => 'Regels',
			'product_ids' => [
				47705, // Bangkirai verlijmd 40x60mm
				49209, // Angelim Vermelho 44x70mm Geschaafd
			],
			// NIEUW: Koppel hier het juiste VARIATIE ID aan het product ID voor de lengte 3900mm.
			// Vul op de plek van de 0 het ID in van de variatie "3900mm" van dat product.
			'variation_ids' => [
				47705 => 47706, // TODO: Vul hier ID in van Bangkirai variatie 390cm
				49209 => 49212, // TODO: Vul hier ID in van Angelim variatie 390cm
			],
			'applicable'  => ['hout', 'bamboe', 'composiet'],
			'rule'        => 'manual',
		],

	// ===== Schroeven (automatisch toegevoegd) =====
	'hhline_rvs_hardhout_vlonderschroef' => [
		'label'      => 'HHLine RVS Hardhout Vlonderschroef',
		'product_id' => 2531, // hoofdproduct
		'variations' => [
			'5.5x40' => 2536, // bij 21x145
			'5.5x50' => 2535, // bij 25x145
			'5.5x60' => 2534, // bij 27x190
			'5.5x100' => 49143,
		],
		'applicable' => ['hout'],
		'rule'       => [
			'type' => 'auto',
			'per_m2' => 1, // of andere logica later in Excel
		],
	],

	// ===== Granulaat Pads =====
	'granulaatpads' => [
			'label'       => 'Granulaat pads',
			'product_id'  => 2591, // <--- VUL HIER HET PRODUCT ID IN
			'applicable'  => ['hout', 'bamboe', 'composiet'],
			'rule'        => 'auto', // Automatisch berekend bij balkon
		],

	// ===== Clips =====
	'startclips' => [
		'label'      => 'HHLine startclips',
		'product_id' => 36445,
		'applicable' => ['bamboe', 'composiet'],
		'rule'       => [
			'type'     => 'auto',
			'per_board' => 1, // bijvoorbeeld 1 startclip per rij
		],
	],

	'eindclips' => [
		'label'      => 'HHLine eindclips',
		'product_id' => 36445, // zelfde ID of andere? checken
		'applicable' => ['bamboe', 'composiet'],
		'rule'       => [
			'type'     => 'auto',
			'per_board' => 1,
		],
	],

	'tussenclips' => [
		'label'      => 'HHLine tussenclips',
		'product_id' => 36446,
		'applicable' => ['bamboe', 'composiet'],
		'rule'       => [
			'type'      => 'auto',
			'per_board' => 20, // bijv. 20 clips per plank
		],
	],

	'slotbouten' => [
		'label'      => 'HHLine Slotbouten',
		'product_id' => 32650, // Hoofdproduct ID
		'variations' => [
			'100' => 32654, // Variatie ID voor 100mm
			'110' => 49076, // Variatie ID voor 110mm
		],
		'applicable' => ['hout', 'bamboe', 'composiet'],
		'rule'       => 'auto',
	],
],

];