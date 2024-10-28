<?php
/*
Plugin Name: BCS BatchLine Book Importer
Description: Import books from BatchLine's web exporter
Version: 1.6.12
Author: BCS Studio
Author URI: https://bcs-studio.com
*/

define('MY_PLUGIN_PATH', plugin_dir_path(__FILE__)); 
require_once MY_PLUGIN_PATH . 'inc/bcs-license.php';
require_once MY_PLUGIN_PATH . 'inc/bcs-import.php';
require_once MY_PLUGIN_PATH . 'inc/bcs-options.php';
require_once MY_PLUGIN_PATH . 'inc/bcs-woo-functions.php';
require_once MY_PLUGIN_PATH . 'inc/bcs-book-preview.php';


// add_filter( 'generate_rewrite_rules', function ( $wp_rewrite ){
//     $wp_rewrite->rules = array_merge(
//         ['bcsimportdebug/?$' => 'index.php?custom=1'],
//         $wp_rewrite->rules
//     );
// } );
// add_filter( 'query_vars', function( $query_vars ){
//     $query_vars[] = 'custom';
//     return $query_vars;
// } );
// add_action( 'template_redirect', function(){
//     $custom = intval( get_query_var( 'custom' ) );
//     if ( $custom ) {
//         include plugin_dir_path( __FILE__ ) . 'templates/custom.php';
//         die;
//     }
// } );

add_action('init', 'action_init_redirect');

function action_init_redirect() {
	add_rewrite_rule('debug/?', 'index.php?bcs_import=debug', 'top');
}
add_filter('query_vars', 'filter_query_vars');

function filter_query_vars($query_vars) {
	$query_vars[] = 'bcs_import';
	return $query_vars;
}

add_action('parse_request', 'action_parse_request');

function action_parse_request(&$wp) {
	if (array_key_exists('bcs_import', $wp->query_vars)) {
		//get_header();
		$bcs_book_import_log = get_option('bcs_book_import_blocks_log');
		//print_r($bcs_book_import_log);
		//var_dump(implode(",", $bcs_book_import_log));
		foreach($bcs_book_import_log as $bcs_book_import_log_entry){
			echo '<p>';
			echo implode(",", $bcs_book_import_log_entry);
			echo '</p>';
		}
		//echo "I will replace it by my plug_php file";
		//get_sidebar(); 
		//get_footer(); 
		exit;
	}
} 