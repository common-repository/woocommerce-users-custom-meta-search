<?php
/**
 * Plugin Name: WooCommerce - Custom Users Meta Extra Search
 * Plugin URI: http://www.remicorson.com/custom-users-meta-search/
 * Description: Add WooCommerce users meta fields to user search in the WordPress administration
 * Version: 1.0
 * Author: Remi Corson
 * Author URI: http://remicorson.com
 * Requires at least: 3.0
 * Tested up to: 3.6
 *
 * Text Domain: woo_user_search
 * Domain Path: languages
 *
 */
 
/*
|--------------------------------------------------------------------------
| CONSTANTS
|--------------------------------------------------------------------------
*/

if( !defined( 'CUMES_BASE_FILE' ) )			define( 'CUMES_BASE_FILE', __FILE__ );
if( !defined( 'CUMES_BASE_DIR' ) ) 			define( 'CUMES_BASE_DIR', dirname( CUMES_BASE_FILE ) );
if( !defined( 'CUMES_PLUGIN_URL' ) ) 		define( 'CUMES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
if( !defined( 'CUMES_PLUGIN_VERSION' ) ) 	define( 'CUMES_PLUGIN_VERSION', '1.0' );

/*
|--------------------------------------------------------------------------
| INTERNATIONALIZATION
|--------------------------------------------------------------------------
*/

function woo_user_search_textdomain() {
	load_plugin_textdomain( 'woo_user_search', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/*
|--------------------------------------------------------------------------
| APPLY ACTIONS & FILTERS IS WOOCOMMERCE IS ACTIVE
|--------------------------------------------------------------------------
*/

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
	/*
	|--------------------------------------------------------------------------
	| ACTIONS
	|--------------------------------------------------------------------------
	*/
	
	add_action( 'init', 'woo_user_search_textdomain' );
	if( is_admin() ) {
		add_action('pre_user_query', 'woo_user_extra_search');
	}
	
	/*
	|--------------------------------------------------------------------------
	| FILTERS
	|--------------------------------------------------------------------------
	*/
	if( is_admin() ) {
		add_filter( 'woocommerce_general_settings', 'woo_add_user_fields_settings' );
	}


} // endif WooCommerce active


/* ----------------------------------------
* Make WooCommerce users custom meta fields available in users search
----------------------------------------- */
	
function woo_add_user_fields_settings( $settings ) {

	$updated_settings = array();
	
	foreach ( $settings as $section ) {
	
		// at the bottom of the General Options section
		if ( isset( $section['id'] ) && 'general_options' == $section['id'] &&
		isset( $section['type'] ) && 'sectionend' == $section['type'] ) {
			
			$updated_settings[] = array(
			'name'     => __( 'User fields search', 'woo_user_search' ),
			'desc_tip' => __( 'Choose user fields to include wether or not in the main WordPress users search.', 'woo_user_search' ),
			'id'       => 'woocommerce_user_search_fields',
			'type'     => 'multiselect',
			'options'  => array(
								'billing_first_name'  => __('Billing Firstname', 'woo_user_search'),
								'billing_last_name'   => __('Billing Lastname', 'woo_user_search'),
								'billing_company'     => __('Billing Company', 'woo_user_search'),
								'billing_address_1'   => __('Billing Address 1', 'woo_user_search'),
								'billing_address_2'   => __('Billing Address 2', 'woo_user_search'),
								'billing_city'        => __('Billing City', 'woo_user_search'),
								'billing_postcode'    => __('Billing Postcode', 'woo_user_search'),
								'billing_state'       => __('Billing State', 'woo_user_search'),
								'billing_country'     => __('Billing Country', 'woo_user_search'),
								'billing_phone'       => __('Billing Phone', 'woo_user_search'),
								'billing_email'       => __('Billing Email', 'woo_user_search'),
								'shipping_first_name' => __('Shipping Firstname', 'woo_user_search'),
								'shipping_last_name'  => __('Shipping Lastname', 'woo_user_search'),
								'shipping_company'    => __('Shipping Company', 'woo_user_search'),
								'shipping_address_1'  => __('Shipping Address 1', 'woo_user_search'),
								'shipping_address_2'  => __('Shipping Address 2', 'woo_user_search'),
								'shipping_city'       => __('Shipping City', 'woo_user_search'),
								'shipping_postcode'   => __('Shipping Postcode', 'woo_user_search'),
								'shipping_state'      => __('Shipping State', 'woo_user_search'),
								'shipping_country'    => __('Shipping Country', 'woo_user_search')
							),
			'css'   => 'min-width:350px;',
			'class' => 'chosen_select'
			);
		}
		$updated_settings[] = $section;
	}
	
	return $updated_settings;
}


// the actual improvement of the query
function woo_user_extra_search( $wp_user_query ) {

	global $woocommerce, $woocommerce_settings, $wpdb;
	
    if( false === strpos( $wp_user_query->query_where, '@' ) && !empty( $_GET["s"] ) ) {

        $fields_id = array();

		// Get the WooCommerce custom users meta fields
		
		// Uncomment for manual use and test purpose only 
		//$fields_meta = 'billing_first_name,billing_last_name,billing_company,billing_email,shipping_first_name,shipping_last_name,shipping_company';
		//$field_key = array_map('trim', explode(",",$fields_meta));
		
		// Get fields
		$field_key = get_option( 'woocommerce_user_search_fields' );

		$add = "";

		if( !empty( $field_key ) ) {
			$add = " OR meta_key='" . implode("' OR meta_key='", $field_key ) . "'";
		}

        $usermeta_affected_ids = $wpdb->get_results("SELECT DISTINCT user_id FROM " . $wpdb->prefix . "usermeta WHERE (meta_key='first_name' OR meta_key='last_name'" . $add . ") AND meta_value LIKE '%" . mysql_real_escape_string( $_GET["s"] ) . "%'");
       
        foreach( $usermeta_affected_ids as $maf ) {
            array_push( $fields_id, $maf->user_id );
        }

        $users_affected_ids = $wpdb->get_results("SELECT DISTINCT ID FROM " . $wpdb->prefix . "users WHERE user_nicename LIKE '%" . mysql_real_escape_string( $_GET["s"] ) . "%' OR user_email LIKE '%" . mysql_real_escape_string( $_GET["s"] ) . "%'" );


        foreach( $users_affected_ids as $maf ) {
            if( !in_array( $maf->ID, $fields_id ) ) {
                array_push( $fields_id, $maf->ID );
            }
        }

        $id_string = implode( ",", $fields_id );

        $wp_user_query->query_where = str_replace( "user_nicename LIKE '%" . mysql_real_escape_string( $_GET["s"] ) . "%'", "ID IN(" . $id_string . ")", $wp_user_query->query_where);
    }
    return $wp_user_query;
   
}

