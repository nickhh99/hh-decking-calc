<?php
namespace HH\DeckingCalc;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class REST {

    public static function register_routes(): void {
        register_rest_route(
            'hh-decking/v1',
            '/calc',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'calc' ),
                'permission_callback' => array( __CLASS__, 'permissions' ),
            )
        );

        register_rest_route(
            'hh-decking/v1',
            '/add-to-cart',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'add_to_cart' ),
                'permission_callback' => array( __CLASS__, 'permissions' ),
            )
        );

        register_rest_route(
            'hh-decking/v1',
            '/variations-map',
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'variations_map' ),
                'permission_callback' => static function () {
                    return is_user_logged_in();
                },
                'args'                => array(
                    'product_id' => array( 'required' => true, 'type' => 'integer' ),
                ),
            )
        );
    }

    public static function permissions(): bool {
        return (bool) wp_verify_nonce( $_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest' );
    }

    /**
     * Helper om WooCommerce context te forceren in REST API.
     */
    private static function init_woocommerce_context() {
        if ( ! function_exists( 'WC' ) ) return false;

        if ( is_null( WC()->session ) ) {
            $session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
            WC()->session = new $session_class();
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

    public static function calc( WP_REST_Request $request ): WP_REST_Response {
        $raw = $request->get_body();
        $input = json_decode( $raw, true ) ?? [];

        if ( empty( $input ) || empty( $input['type'] ) ) {
            return new WP_REST_Response(
                array( 'success' => false, 'message' => __( 'Geen geldige invoer ontvangen.', 'hh-decking-calc' ) ),
                400
            );
        }

        $result = Calculator::calculate( $input );

        if ( isset( $result['error'] ) ) {
            return new WP_REST_Response(
                array( 'success' => false, 'message' => $result['error'] ),
                400
            );
        }

        return new WP_REST_Response(
            array( 'success' => true, 'data' => $result ),
            200
        );
    }

    /**
     * /add-to-cart → voegt producten toe met ALLES OF NIETS voorraad-check.
     */
    public static function add_to_cart( \WP_REST_Request $request ): \WP_REST_Response {
        if ( ! self::init_woocommerce_context() ) {
            return new \WP_REST_Response([ 'success' => false, 'message' => 'WooCommerce initialisatie mislukt.' ], 500);
        }

        $data = json_decode( $request->get_body(), true );
        $lines = $data['lines'] ?? [];
        $added_keys = [];
        $failed_items = [];

        foreach ( $lines as $line ) {
            $product_id   = (int) ( $line['product_id'] ?? 0 );
            $variation_id = (int) ( $line['variation_id'] ?? 0 );
            $qty          = max( 1, (int) ( $line['qty'] ?? 0 ) );
            $meta         = is_array( $line['meta'] ?? null ) ? array_map( 'sanitize_text_field', $line['meta'] ) : [];

            // Haal product object op
            $product_obj = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);
            
            if ( ! $product_obj ) {
                $failed_items[] = "Onbekend product (ID: $product_id)";
                continue;
            }

            // Voorraad controleren
            if ( ! $product_obj->is_purchasable() || ( $product_obj->managing_stock() && ! $product_obj->is_in_stock() ) ) {
                $failed_items[] = $product_obj->get_name();
                continue;
            }

            // Toevoegen aan mand met error handling
            try {
                $cart_key = WC()->cart->add_to_cart( $product_id, $qty, $variation_id, [], $meta );
                if ( $cart_key ) {
                    $added_keys[] = $cart_key;
                } else {
                    $failed_items[] = $product_obj->get_name();
                }
            } catch ( \Exception $e ) {
                $failed_items[] = $product_obj->get_name();
                error_log('HHDC Add to cart error: ' . $e->getMessage());
            }
        }

        // ALLES OF NIETS: Bij falen, mand legen en foutlijst sturen
        if ( ! empty( $failed_items ) ) {
            foreach ( $added_keys as $key ) {
                WC()->cart->remove_cart_item( $key );
            }
            return new \WP_REST_Response([
                'success'      => false,
                'out_of_stock' => true,
                'items'        => array_unique($failed_items)
            ], 200);
        }

        return new \WP_REST_Response([ 
            'success'  => true, 
            'cart_url' => wc_get_cart_url() 
        ], 200);
    }

    public static function variations_map( WP_REST_Request $request ): WP_REST_Response {
        $product_id = (int) $request->get_param( 'product_id' );
        if ( $product_id <= 0 ) {
            return new WP_REST_Response( array( 'error' => 'product_id ontbreekt' ), 400 );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            return new WP_REST_Response( array( 'error' => 'Geen variabel product of niet gevonden' ), 400 );
        }

        $data = array(
            'product_id' => $product_id,
            'attrs'      => $product->get_variation_attributes(),
            'variations' => array(),
        );

        foreach ( $product->get_children() as $vid ) {
            $var = wc_get_product( $vid );
            if ( ! $var ) continue;
            $data['variations'][] = array(
                'variation_id' => $vid,
                'attributes'   => $var->get_attributes(),
                'sku'          => $var->get_sku(),
                'price'        => $var->get_price(),
                'stock'        => $var->get_stock_quantity(),
            );
        }

        return new WP_REST_Response( $data, 200 );
    }
}