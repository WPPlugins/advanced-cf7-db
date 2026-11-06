<?php

/**
 * @link              https://wordpress.org/plugins/advanced-cf7-db/
 * @since             1.1.0
 * @package           Advanced_CF7_DB
 *
 * @wordpress-plugin
 * Plugin Name:       Vsourz Form Entries for Contact Form 7
 * Plugin URI:        https://wordpress.org/plugins/advanced-cf7-db/
 * Description:       Save Contact Form 7 submissions to the database and view, search, export, import, and manage entries.
 * Version:           2.1.6
 * Author:            Vsourz Digital
 * Author URI:        https://www.vsourz.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       advanced-cf7-db
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 5.6
 * Requires Plugins:  contact-form-7
 */
 
 // Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Allow build process to disable bundling/loading the Freemius SDK (for WP.org ZIPs).
if ( ! defined( 'ADCFDB_INCLUDE_FREEMIUS_SDK' ) ) {
	define( 'ADCFDB_INCLUDE_FREEMIUS_SDK', true );
}

if ( ! function_exists( 'adcfdb_fs' ) ) {
    // Create a helper function for easy SDK access.
    function adcfdb_fs() {
        global $adcfdb_fs;

        if ( ! isset( $adcfdb_fs ) ) {
			// Include Freemius SDK only when enabled and when it's not already loaded
			// by another plugin to reduce the risk of class/function collisions.
			if ( ADCFDB_INCLUDE_FREEMIUS_SDK && ! function_exists( 'fs_dynamic_init' ) ) {
				require_once dirname(__FILE__) . '/vendor/freemius/start.php';
			}

            $adcfdb_fs = fs_dynamic_init( array(
                'id'                  => '13605',
                'slug'                => 'advanced-cf7-db',
                'type'                => 'plugin',
                'public_key'          => 'pk_1ebb43745daf94d915c50746114ea',
                'is_premium'          => false,
                'has_addons'          => false,
                'has_paid_plans'      => false,
                'menu'                => array(
                    'slug'           => 'contact-form-listing',
                    'account'        => false,
                    'support'        => false,
                ),
            ) );
        }

        return $adcfdb_fs;
    }

	// Init Freemius only when the SDK is bundled and the init function exists.
	if ( ADCFDB_INCLUDE_FREEMIUS_SDK && function_exists( 'fs_dynamic_init' ) ) {
		adcfdb_fs();
		// Signal that SDK was initiated.
		do_action( 'adcfdb_fs_loaded' );
	}
}

function adcfdb_fs_custom_connect_message_on_update(
	$message,
	$user_first_name,
	$product_title,
	$user_login,
	$site_link,
	$freemius_link
) {
	/* translators: 1: User first name (displayed bold). 2: Site URL. 3: Freemius/account link. */
	// translators: %1$s = user first name, %2$s = site URL, %3$s = freemius link.
	return sprintf(
		// translators: %1$s: user first name; %2$s: site URL; %3$s: Freemius/account link.
		__( 'Hey %1$s, <br/> In order to enjoy all our features and functionality, <b>Vsourz Form Entries for Contact Form 7</b> needs to connect your user, %1$s at %2$s to %3$s', 'advanced-cf7-db' ),
		'<b>'.$user_first_name.'</b>',
		$site_link,
		$freemius_link
	);
}
// Register Freemius filters only when Freemius is available.
if ( function_exists( 'adcfdb_fs' ) && adcfdb_fs() ) {
	adcfdb_fs()->add_filter( 'connect_message_on_update', 'adcfdb_fs_custom_connect_message_on_update', 10, 6 );
}

function adcfdb_fs_custom_icon() {
    return dirname( __FILE__ ) . '/admin/images/icon-128x128.jpg';
}
if ( function_exists( 'adcfdb_fs' ) && adcfdb_fs() ) {
	adcfdb_fs()->add_filter( 'plugin_icon' , 'adcfdb_fs_custom_icon' );
}

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
global $vsz_cf7db_current_version;
$vsz_cf7db_current_version = '2.1.6';
/**
 * Defining all the table names and setting their prefix here
 */
global $wpdb;

//added in  1.8.4
if(!defined('VSZ_CF7_TEXT_DOMAIN')){
	define('VSZ_CF7_TEXT_DOMAIN','advanced-cf7-db');
}

if(!defined('VSZ_ADVANCE_CF7_DB_VERSION')){
    define('VSZ_ADVANCE_CF7_DB_VERSION',$vsz_cf7db_current_version);
}

define('VSZ_CF7_DATA_TABLE_NAME',  $wpdb->prefix.'cf7_vdata');
define('VSZ_CF7_DATA_ENTRY_TABLE_NAME', $wpdb->prefix.'cf7_vdata_entry');

define('VSZ_CF7_UPLOAD_FOLDER','advanced-cf7-upload');
define('VSZ_CF7_URL_PATH',plugin_dir_url( __FILE__ ));


//excel sheet download using library for xls excel file format- defined contant
if( file_exists(dirname(__FILE__).'/includes/libraries/excel/xls/vendor/autoload.php') ){
	define('VSZ_CF7_PHPSPREADSHEET_CHECK', true );
}else{
	define('VSZ_CF7_PHPSPREADSHEET_CHECK', false );
}

//excel sheet download using library for xlsx excel file format- defined contant
if( file_exists(dirname(__FILE__).'/includes/libraries/excel/xlsx/PHP_XLSXWriter-master/xlsxwriter.class.php') ){
	define('VSZ_CF7_PHPXLSXWRITER_CHECK', true );
}else{
	define('VSZ_CF7_PHPXLSXWRITER_CHECK', false );
}

require_once(ABSPATH . 'wp-admin/includes/file.php');
/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-advanced-cf7-db-activator.php
 */
function vsz_cf7_activate_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-advanced-cf7-db-activator.php';
	Advanced_Cf7_Db_Activator::activate();
}



/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-advanced-cf7-db-deactivator.php
 */
function vsz_cf7_deactivate_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-advanced-cf7-db-deactivator.php';
	Advanced_Cf7_Db_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'vsz_cf7_activate_plugin' );
register_deactivation_hook( __FILE__, 'vsz_cf7_deactivate_plugin' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-advanced-cf7-db.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function vsz_cf7_run_plugin() {

	$plugin = new Advanced_Cf7_Db();
	$plugin->run();

}
vsz_cf7_run_plugin();

/**
 * Replace accented characters with non accented
 *
 * @param $str
 * @return mixed
 */

if(!function_exists("vsz_cf7_remove_accents")){
	function vsz_cf7_remove_accents($str) {
	  $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ', 'Ά', 'ά', 'Έ', 'έ', 'Ό', 'ό', 'Ώ', 'ώ', 'Ί', 'ί', 'ϊ', 'ΐ', 'Ύ', 'ύ', 'ϋ', 'ΰ', 'Ή', 'ή', '–');
	  $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o', 'Α', 'α', 'Ε', 'ε', 'Ο', 'ο', 'Ω', 'ω', 'Ι', 'ι', 'ι', 'ι', 'Υ', 'υ', 'υ', 'υ', 'Η', 'η', '-');
	  return str_replace($a, $b, $str);
	}
}

/**
 * Replace smart quotes and other special characters with appropriate characters
 *
 * @param $str
 * @return mixed
 */

if(!function_exists("vsz_cf7_remove_smart_quotes")){
	function vsz_cf7_remove_smart_quotes($content) {
		$content= str_replace(
		array("\xe2\x80\x98", "\xe2\x80\x99", "\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x93", "\xe2\x80\x94", "\xe2\x80\xa6"),
		array("'", "'", '"', '"', '-', '--', '...'), $content);

		$content= str_replace(
		array(chr(145), chr(146), chr(147), chr(148), chr(150), chr(151), chr(133)),
		array("'", "'", '"', '"', '-', '--', '...'), $content);

		return $content;
	}
}

/***
 * Check if the index file exists in our uploads ADCF7 DB or not
 * If dir exists, check for the index.html file
 * If not exists index.html then create it
 */
add_action('init', 'vsz_create_index_file');

function vsz_create_index_file()
{

	$upload_dir = wp_upload_dir();
	$acf7db_upload_folder = VSZ_CF7_UPLOAD_FOLDER;
	$acf7db_upload_dir = $upload_dir['basedir'].'/'.$acf7db_upload_folder;

	if ( file_exists( $acf7db_upload_dir ) ) {
		$index_file = $acf7db_upload_dir."/index.html";
		if ( !file_exists( realpath($index_file) ) ) {
			fopen($index_file, "w"); // @codingStandardsIgnoreLine
		}
	}

}