<?php
/**
 * WooCommerce Boleto Template.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

// Test if exist ref.
if ( isset( $_GET['ref'] ) ) {
    global $wpdb;

    // Sanitize the ref.
    $ref = sanitize_title( $_GET['ref'] );

    // Gets Order id.
    $order_id = woocommerce_get_order_id_by_order_key( $ref );

    if ( $order_id ) {
        // Gets the data saved from boleto.
        $order_data = get_post_meta( $order_id, 'wc_boleto_data', true );

        // Gets current bank.
        $settings = get_option( 'woocommerce_boleto_settings' );
        $bank = sanitize_text_field( $settings['bank'] );

        if ( $bank ) {

            // Sets the boleto details.
            $logo = sanitize_text_field( $settings['boleto_logo'] );
            $shop_name = get_bloginfo( 'name' );

            // Sets the boleto data.
            $data = array();
            foreach ( $order_data as $key => $value )
                $data[ $key ] = sanitize_text_field( $value );

            // Sets the settings data.
            foreach ( $settings as $key => $value )
                $data[ $key ] = sanitize_text_field( $value );

            // Client info.
            $data['demonstrativo1'] = sprintf( __( 'Payment for purchase in %s', 'wcboleto' ), $shop_name );
            $data['demonstrativo2'] = sprintf( __( 'Payment referred to the order #%s', 'wcboleto' ), $data['nosso_numero'] );
            $data['demonstrativo3'] = $shop_name . ' - ' . home_url();
            $data['instrucoes1']    = __( '- Mr. Cash, charge a fine of 2% after maturity', 'wcboleto' );
            $data['instrucoes2']    = __( '- Receive up to 10 days past due', 'wcboleto' );
            $data['instrucoes3']    = sprintf( __( '- For questions please contact us: %s', 'wcboleto' ), get_option( 'woocommerce_email_from_address' ) );
            $data['instrucoes4']    = '';

            // Shop data.
            $data['identificacao']  = $shop_name;

            $dadosboleto = apply_filters( 'wcboleto_data', $data );

            // Include bank templates.
            include WC_BOLETO_PATH . 'banks/' . $bank . '/functions.php';
            include WC_BOLETO_PATH . 'banks/' . $bank . '/layout.php';

            exit;
        }
    }
}

// If an error occurred is redirected to the homepage.
wp_redirect( home_url() );
exit;
