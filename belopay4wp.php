<?php
/*
 * Plugin Name: Belopay Payment Gateway
 * Plugin URI: https://github.com/belopay/belopay4wp
 * Description: Juste install it and get paied on your store.
 * Author: mahefa@belopay.com
 * Author URI: http://numeric.mahefa.company/
 * Version: 1.0.0
 * Tuto : https://rudrastyh.com/woocommerce/payment-gateway-plugin.html
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'belopay_add_gateway_class' );
function belopay_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Belopay_Gateway';
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'belopay_init_gateway_class' );
function belopay_init_gateway_class() {

	class WC_Belopay_Gateway extends WC_Payment_Gateway {

 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {
            $this->id = 'belopay'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Belopay Gateway';
            $this->method_description = 'Description of Belopay payment gateway'; // will be displayed on the options page
        
            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );
        
            // Method with all the options fields
            $this->init_form_fields();
        
            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
        
            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        
            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            
            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
 		}

		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Belopay Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => "Ceci contrôle la description que l'utilisateur voit lors du paiement",
                    'default'     => 'Mobile money',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => "Ceci contrôle la description que l'utilisateur voit lors du paiement",
                    'default'     => 'Paiement par Mobile Money. <br /> MVola : 034 04 861 23 <i>(Au nom de Elysa Marie alfredine)</i> 
                                        <br />Orange Money : 032 98 530 20 <i>(Au nom de RAZAFINDRAFARA Elysa)</i> <br /> <br /><br />',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => "Placer la passerelle de paiement en mode test à l'aide de clés API de test",
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_publishable_key' => array(
                    'title'       => 'Test Publishable Key',
                    'type'        => 'text'
                ),
                'test_private_key' => array(
                    'title'       => 'Test Private Key',
                    'type'        => 'password',
                ),
                'publishable_key' => array(
                    'title'       => 'Live Publishable Key',
                    'type'        => 'text'
                ),
                'private_key' => array(
                    'title'       => 'Live Private Key',
                    'type'        => 'password'
                )
            );
	
	 	}

		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {

            // ok, let's display some description before the payment form
            if ( $this->description ) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ( $this->testmode ) {
                    $this->description .= ' Mode Test Activé . En mode test, vous pouvez utiliser les references de transaction tests. <a href="#">Documentation</a>.';
                    $this->description  = trim( $this->description );
                }
                // display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }
        
            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        
            // Add this action hook if you want your custom payment gateway to support it
            do_action( 'woocommerce_credit_card_form_start', $this->id );
        
            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            echo getMobileMoneyManualForm();
        
            do_action( 'woocommerce_credit_card_form_end', $this->id );
        
            echo '<div class="clear"></div></fieldset>';
				 
		}

		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 	public function payment_scripts() {
		
            // we need JavaScript to process a token only on cart/checkout pages, right?
            if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
                return;
            }

            // if our payment gateway is disabled, we do not have to enqueue JS too
            if ( 'no' === $this->enabled ) {
                return;
            }

            // no reason to enqueue JavaScript if API keys are not set
            if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
                return;
            }

            // do not work with card detailes without SSL unless your website is in a test mode
            if ( ! $this->testmode && ! is_ssl() ) {
                return;
            }

            // let's suppose it is our payment processor JavaScript that allows to obtain a token
            wp_enqueue_script( 'belopay_js', 'https://www.belopay.com/api/token.js' );

            // and this is our custom JS in your plugin directory that works with token.js
            wp_register_script( 'woocommerce_belopay', plugins_url( 'belopay.js', __FILE__ ), array( 'jquery', 'belopay_js' ) );

            // in most payment processors you have to use PUBLIC KEY to obtain a token
            wp_localize_script( 'woocommerce_belopay', 'belopay_params', array(
                'publishableKey' => $this->publishable_key
            ) );

            wp_enqueue_script( 'woocommerce_belopay' );

	
	 	}

		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {

            if( empty( $_POST[ 'providers-payments' ]) ) {
                wc_add_notice(  'Operateur mobile obligatoire!', 'error' );
                return false;
            }
            if( empty( $_POST[ 'belopay_reference-transaction' ]) ) {
                wc_add_notice(  'Reference transaction obligatoire!', 'error' );
                return false;
            }
            if( empty( $_POST[ 'belopay_motif-transaction' ]) ) {
                wc_add_notice(  'Motif de transaction obligatoire!', 'error' );
                return false;
            }
            return true;

		}

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
            
            global $woocommerce;
        
            // we need it to get any order detailes
            $order = wc_get_order( $order_id );
        
            /*
            * Array with parameters for API interaction
            */
            $args = array(
        
            );
        
            /*
            * Your API interaction could be built with wp_remote_post()
            */
            // $response = wp_remote_post( '{payment processor endpoint}', $args );

            /**
             * Simulation succées
             */
            $response = [
                'body' => [
                    'response' => [
                        'responseCode' => 'APPROVED',
                    ],
                ]
            ];
        
        
            if( !is_wp_error( $response ) ) {
        
                // $body = json_decode( $response['body'], true );
                $body = $response['body'];
        
                // it could be different depending on your payment processor
                if ( $body['response']['responseCode'] == 'APPROVED' ) {

                    $operateur = "Telma";
                    $ReferenceTransaction = "000990890789";
                    $RaisonTransaction = "Robe bla";
                    $order->set_meta_data( [
                        'Operateur' => $operateur,
                        'ReferenceTransaction' => $ReferenceTransaction,
                        'RaisonTransaction' => $RaisonTransaction,
                    ] );
                    $order->set_transaction_id("000990890789");
        
                    // we received the payment
                    $order->payment_complete();
                    $order->reduce_order_stock();
        
                    // some notes to customer (replace true with false to make it private)
                    $order->add_order_note( '
                        Votre info paiement est reçu, 
                        nos équipes vont faire une verification du paiement et 
                        vous confirmer si c\'est bien reçu ! <br/>
                        Operateur : '.$operateur.'<br/>
                        ReferenceTransaction : '.$ReferenceTransaction.'<br/>
                        RaisonTransaction : '.$RaisonTransaction.'<br/>
                        post : '.isset($_POST[ 'belopay_reference-transaction' ])?$_POST[ 'belopay_reference-transaction' ]:'Vida'.'
                    ', true );

                    // Empty cart
                    $woocommerce->cart->empty_cart();
        
                    // Redirect to the thank you page
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
        
                } else {
                    wc_add_notice(  'Belopay : Please try again.', 'error' );
                    return;
                }
        
            } else {
                wc_add_notice(  'Belopay : Connection error.', 'error' );
                return;
            }
        
	 	}

		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {
	
            $order = wc_get_order( $_GET['id'] );
            $order->payment_complete();
            $order->reduce_order_stock();
        
            update_option('webhook_debug', $_GET);
        }
 	}
}


function getCreditCardForm(){
    return '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
        <input id="belopay_ccNo" type="text" autocomplete="off">
        </div>
        <div class="form-row form-row-first">
            <label>Expiry Date <span class="required">*</span></label>
            <input id="belopay_expdate" type="text" autocomplete="off" placeholder="MM / YY">
        </div>
        <div class="form-row form-row-last">
            <label>Card Code (CVC) <span class="required">*</span></label>
            <input id="belopay_cvv" type="password" autocomplete="off" placeholder="CVC">
        </div>
        <div class="clear"></div>';
}
function getMobileMoneyManualForm(){
    return '
        <div class="form-row form-row-wide">
            <label for="providers-payments">Vous utilisez quelle operateur : <span class="required">*</span></label>
            <select name="providers-payments" id="providers-payments">
                <option value="telma-mvola">Telma MVola</option>
                <option value="choix-orange-money">Orange Money</option>
            </select>
        </div>
        <div class="form-row form-row-wide">
            <label>Réference de transaction <span class="required">*</span></label>
            <input name="belopay_reference-transaction" id="belopay_reference-transaction" type="text" autocomplete="off">
        </div>
        <div class="form-row form-row-wide">
            <label>Motif de transaction <span class="required">*</span></label>
            <input name="belopay_motif-transaction" id="belopay_motif-transaction" type="text" autocomplete="off">
        </div>
        <div class="clear"></div>
    ';
}