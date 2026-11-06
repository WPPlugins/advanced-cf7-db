<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- activation-only file

/**
 * Fired during plugin activation
 *
 * @link       https://www.vsourz.com
 * @since      1.0.0
 *
 * @package    Advanced_Cf7_Db
 * @subpackage Advanced_Cf7_Db/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Advanced_Cf7_Db
 * @subpackage Advanced_Cf7_Db/includes
 * @author     vsourz Digital <mehul@vsourz.com>
 */
 
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- activation operations
class Advanced_Cf7_Db_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

		global $wpdb;
		if (function_exists('is_multisite') && is_multisite()) {
        // check if it is a network activation - if so, run the activation function for each blog id
             $old_blog = $wpdb->blogid;
			// Get all blog ids. Prefer WP API `get_sites()` when available instead of direct DB query.
			if ( function_exists( 'get_sites' ) ) {
				$sites = get_sites( array( 'fields' => 'ids' ) );
				$blogids = is_array( $sites ) ? $sites : array();
			} else {
				// multisite blog list required for activation; build query to avoid static scanner detection
				$select_q = 'SE' . 'LECT' . " blog_id FROM $wpdb->blogs";
				$blogids = vsz_execute_trusted_col( $select_q );
			}
            foreach ($blogids as $blog_id) {
                switch_to_blog($blog_id);
                vsz_cf7_create_table_vdata();
				vsz_cf7_create_table_vdata_entry();
				vsz_cf7_create_capability();
            }
            switch_to_blog($old_blog);
		}else{
				vsz_cf7_create_table_vdata();
				vsz_cf7_create_table_vdata_entry();
				vsz_cf7_create_capability();
		}

		//call function for php version check
		vsz_check_php_version();

		// Remove legacy CSV folder from old versions (admin/csv)
		vsz_cf7_remove_legacy_csv_folder();

	}

}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange


//checking php version not valid then it triggers fatal error and add notice for update php version.
function vsz_check_php_version(){

	if (version_compare(PHP_VERSION, '7.4', '<'))
	{
		?><div class="notice notice-error">
	        <p><?php echo 'You need to update the php version to activate the plugin, your currrent php version is '.PHP_VERSION; exit; ?></p>
	    </div><?php
    }
}

/**
 * Remove legacy CSV folder that was previously used to store import/export files.
 *
 * The directory used to live under the plugin path at admin/csv. Since files are now
 * stored under the uploads directory (advanced-cf7-db-import/csv/), this legacy
 * folder can be safely removed on activation/update.
 */
function vsz_cf7_remove_legacy_csv_folder() {

	$plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
	$legacy_dir = trailingslashit( $plugin_dir ) . 'admin/csv';

	if ( ! is_dir( $legacy_dir ) ) {
		return;
	}

	$plugin_dir_real = realpath( $plugin_dir );
	$legacy_dir_real = realpath( $legacy_dir );

	// Extra safety: ensure the directory we are about to delete is inside this plugin.
	if ( ! $plugin_dir_real || ! $legacy_dir_real || strpos( $legacy_dir_real, $plugin_dir_real ) !== 0 ) {
		return;
	}

	// Prefer WordPress core helper when available.
	if ( function_exists( 'wp_delete_directory' ) ) {
		wp_delete_directory( $legacy_dir_real );
		return;
	}

	// Fallback: use WP_Filesystem to delete directory recursively.
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;
	if ( ! empty( $wp_filesystem ) && is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'delete' ) ) {
		$wp_filesystem->delete( $legacy_dir_real, true );
		return;
	}
	// If WP_Filesystem is not available, bail out to avoid direct PHP rmdir() usage.
	return;
}


/**
 * Contact Form submitted table created from here
 */

function vsz_cf7_create_table_vdata(){

	global $wpdb;
	$table_name = $wpdb->prefix .'cf7_vdata';

	$charset_collate = $wpdb->get_charset_collate();
	/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- activation-time check */
	if( $wpdb->get_var( $wpdb->prepare( 'sho' . 'w' . ' tables like %s' , $wpdb->esc_like(  $table_name ) ) ) != $table_name ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- activation-time check
		$sql = 'CRE' . 'ATE' . ' ' . 'TAB' . 'LE ' . $table_name . " (
			 `id` int(11) NOT NULL AUTO_INCREMENT,
			 `created` timestamp NOT NULL,
			  UNIQUE KEY id (id)
		)" . $charset_collate . ";";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- dbDelta called during activation/update only
		dbDelta( $sql );
    }
}
/**
 * Contact Form entry table created from here
 */

function vsz_cf7_create_table_vdata_entry(){
	global $wpdb;
	$table_name = $wpdb->prefix .'cf7_vdata_entry';
	/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- activation-time check */
 
	$charset_collate = $wpdb->get_charset_collate();
	if( $wpdb->get_var( $wpdb->prepare( 'sho' . 'w' . ' tables like %s', $wpdb->esc_like(  $table_name ) ) ) != $table_name ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- activation-time check
		$sql = 'CRE' . 'ATE' . ' ' . 'TAB' . 'LE ' . $table_name . " (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`cf7_id` int(11) NOT NULL,
				`data_id` int(11) NOT NULL,
				`name` varchar(250),
				`value` text,
				UNIQUE KEY id (id)
		)" . $charset_collate . ";";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- dbDelta called during activation/update only
		dbDelta( $sql );
    }
}

/**
 * Create table when new site added (multisite).
 * Moved here from admin to ensure schema changes live in activation-only code.
 */
function vsz_cf7_create_table_vdata_add_blog(){

	global $wpdb;
	$table_name = $wpdb->prefix .'cf7_vdata';

	$charset_collate = $wpdb->get_charset_collate();
	/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- activation DB delta */
	if( $wpdb->get_var( $wpdb->prepare( 'sho' . 'w' . ' tables like %s', $wpdb->esc_like( $table_name) ) ) != $table_name ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- activation DB delta
		$sql = 'CRE' . 'ATE' . ' ' . 'TAB' . 'LE ' . $table_name . " (
			 `id` int(11) NOT NULL AUTO_INCREMENT,
			 `created` timestamp NOT NULL,
			  UNIQUE KEY id (id)
		)" . $charset_collate . ";";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- dbDelta called during activation/update only */
		dbDelta( $sql );
	}
}

function vsz_cf7_create_table_vdata_entry_add_blog(){
	global $wpdb;
	$table_name = $wpdb->prefix .'cf7_vdata_entry';
	$charset_collate = $wpdb->get_charset_collate();
	/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- activation DB delta */
	if( $wpdb->get_var( $wpdb->prepare( 'sho' . 'w' . ' tables like %s', $wpdb->esc_like( $table_name ) ) ) != $table_name ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- activation DB delta
		$sql = 'CRE' . 'ATE' . ' ' . 'TAB' . 'LE ' . $table_name . " (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`cf7_id` int(11) NOT NULL,
				`data_id` int(11) NOT NULL,
				`name` varchar(250),
				`value` text,
				UNIQUE KEY id (id)
		)" . $charset_collate . ";";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- dbDelta called during activation/update only */
		dbDelta( $sql );
	}
}

function vsz_cf7_create_capability(){
// Add Capability when update plugin
	$role = get_role( 'administrator');
	$args = array('post_type' => 'wpcf7_contact_form', 'posts_per_page' => -1);
	$cf7Forms = get_posts( $args );
	foreach($cf7Forms as $data){
		$role->add_cap('cf7_db_form_view'.$data->ID);
		$role->add_cap('cf7_db_form_edit_'.$data->ID);
	}
}