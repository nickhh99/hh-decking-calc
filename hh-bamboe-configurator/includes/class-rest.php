<?php
namespace HH\BambooConfigurator;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add-to-cart endpoint voor de bamboe configurator. Dezelfde "alles of niets"-aanpak als
 * hh-decking-calc-v2/includes/class-rest.php: de client stuurt de al client-side berekende
 * regels (product/variatie-ID + aantal) op, de server valideert voorraad/koopbaarheid en
 * voegt pas toe als ALLE regels lukken — anders wordt alles weer teruggedraaid en krijgt
 * de klant te zien welk onderdeel niet beschikbaar is, i.p.v. een half gevulde mand.
 */
class REST {

	public static function register_routes(): void {
		register_rest_route(
			'hh-bamboe-configurator/v1',
			'/add-to-cart',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'add_to_cart' ),
				'permission_callback' => array( __CLASS__, 'permissions' ),
			)
		);
	}

	public static function permissions(): bool {
		return (bool) wp_verify_nonce( $_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest' );
	}

	private static function init_woocommerce_context(): bool {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		if ( is_null( WC()->session ) ) {
			$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
			WC()->session   = new $session_class();
			WC()->session->init();
		}

		if ( is_null( WC()->customer ) ) {
			WC()->customer = new \WC_Customer( get_current_user_id(), true );
		}

		if ( is_null( WC()->cart ) ) {
			WC()->cart = new \WC_Cart();
		}

		return true;
	}

	public static function add_to_cart( WP_REST_Request $request ): WP_REST_Response {
		if ( ! self::init_woocommerce_context() ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'WooCommerce initialisatie mislukt.' ), 500 );
		}

		$data  = json_decode( $request->get_body(), true );
		$lines = is_array( $data['lines'] ?? null ) ? $data['lines'] : array();

		if ( empty( $lines ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Geen regels ontvangen.' ), 400 );
		}

		// Validatie: alleen product/variatie-ID's accepteren die ook echt in onze eigen
		// PRODUCT_IDS-config voorkomen — voorkomt dat willekeurige ID's vanaf de client
		// (gemanipuleerd request) aan de mand toegevoegd kunnen worden.
		$allowed_ids = self::allowed_product_ids();

		$added_keys   = array();
		$failed_items = array();

		foreach ( $lines as $line ) {
			$product_id   = (int) ( $line['product_id'] ?? 0 );
			$variation_id = (int) ( $line['variation_id'] ?? 0 );
			$qty          = max( 1, (int) ( $line['qty'] ?? 0 ) );

			if ( ! in_array( $product_id, $allowed_ids, true ) ) {
				$failed_items[] = "Onbekend product (ID: $product_id)";
				continue;
			}

			$product_obj = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );

			if ( ! $product_obj ) {
				$failed_items[] = "Onbekend product (ID: $product_id)";
				continue;
			}

			if ( ! $product_obj->is_purchasable() || ( $product_obj->managing_stock() && ! $product_obj->is_in_stock() ) ) {
				$failed_items[] = $product_obj->get_name();
				continue;
			}

			try {
				$cart_key = WC()->cart->add_to_cart( $product_id, $qty, $variation_id );
				if ( $cart_key ) {
					$added_keys[] = $cart_key;
				} else {
					$failed_items[] = $product_obj->get_name();
				}
			} catch ( \Exception $e ) {
				$failed_items[] = $product_obj->get_name();
				error_log( 'HH Bamboe Configurator add-to-cart error: ' . $e->getMessage() );
			}
		}

		if ( ! empty( $failed_items ) ) {
			foreach ( $added_keys as $key ) {
				WC()->cart->remove_cart_item( $key );
			}
			return new WP_REST_Response(
				array(
					'success'      => false,
					'out_of_stock' => true,
					'items'        => array_unique( $failed_items ),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'cart_url' => wc_get_cart_url(),
			),
			200
		);
	}

	/**
	 * Platte lijst van alle product-ID's (geen variatie-ID's) die ergens in PRODUCT_IDS
	 * voorkomen — de whitelist voor add_to_cart().
	 */
	private static function allowed_product_ids(): array {
		$ids  = PRODUCT_IDS;
		$flat = array();

		foreach ( $ids['planks'] as $colors ) {
			foreach ( $colors as $product_id ) {
				$flat[] = (int) $product_id;
			}
		}
		foreach ( $ids['visgraat'] as $product_id ) {
			$flat[] = (int) $product_id;
		}
		$flat[] = (int) $ids['regel']['product'];
		foreach ( $ids['piketpaal'] as $map ) {
			$flat[] = (int) $map['product'];
		}
		$flat[] = (int) $ids['granulaatpad'];
		foreach ( $ids['slotbouten'] as $map ) {
			$flat[] = (int) $map['product'];
		}
		$flat[] = (int) $ids['tussenclips'];
		$flat[] = (int) $ids['startclips'];
		$flat[] = (int) $ids['olie']['small'];
		$flat[] = (int) $ids['olie']['large'];

		return array_unique( $flat );
	}
}
