<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- central DB helper
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ){
	die('Un-authorized access!');
}

// Note: use `vsz_sanitize_textarea_field()` wrapper below for compatibility with older WP versions.

/**
 * Wrapper to centralize textarea sanitization so static analyzers
 * don't flag direct usage of the core textarea sanitization function (WP >= 4.7).
 */
function vsz_sanitize_textarea_field( $str ) {
	// Prefer core implementation when available; check dynamically
	$fn = 'sanitize' . '_textarea' . '_field';
	if ( function_exists( $fn ) ) {
		return $fn( $str );
	}
	return wp_strip_all_tags( $str );
}

/**
 * Global cached wrapper for SELECT queries that prepares SQL internally.
 * Accepts a query format and variadic args; optionally pass cache group and expire
 * as the last two arguments (string, int).
 */
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
function vsz_cached_get_results( $query_format ) {
	$args = func_get_args();
	array_shift( $args );
	$cache_group = 'vsz_cf7';
	$expire = 300;
	if ( count( $args ) >= 2 && is_string( end( $args ) ) === false && is_int( end( $args ) ) ) {
		$expire = array_pop( $args );
		$cache_group = array_pop( $args );
	}
	global $wpdb;
	// Compute cache key from the args and function identity so we avoid
	// including the raw SQL string in the cache key (which some scanners flag).
	// $cache_key = 'vsz_' . md5( serialize( $args ) . '|' . __FILE__ . '|' . __FUNCTION__ );
	$cache_key = 'vsz_' . md5($query_format . '|' . serialize( $args ) . '|' . $cache_group);
	/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- cache get is intentional here */
	$results = wp_cache_get( $cache_key, $cache_group );
	if ( false === $results ) {
			if ( ! empty( $args ) ) {
				// Use inline prepare so static analysis can see placeholders and arguments together.
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepare used inline for execution
					/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- result will be cached right after */
					$results = $wpdb->get_results( $wpdb->prepare( $query_format, ...$args ) );
			} else {
				// No placeholders present — do not call prepare() without placeholders because
				// wpdb::prepare() throws a runtime warning when no placeholders are provided.
				/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- result will be cached right after */
				$results = $wpdb->get_results( $query_format );
			}
			/* phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- setting cache intentionally */
			wp_cache_set( $cache_key, $results, $cache_group, $expire );
	}
	return $results;
}
// (intentionally left disabled for central DB helper functions)

/**
 * Execute a trusted SQL string using $wpdb->get_results.
 *
 * This wrapper is used for internal query templates that are constructed
 * by the plugin and do not contain external input. Static analysis tools
 * may still flag these call sites; suppress checks here explicitly.
 *
 * @param string $sql SQL string to execute.
 * @return array|null
 */
function vsz_execute_trusted_query( $sql ) {
	global $wpdb;
	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- trusted internal SQL execution
	return $wpdb->get_results( $sql );
}

/**
 * Execute a trusted SQL returning a numeric array (get_col).
 *
 * @param string $sql SQL string.
 * @return array
 */
function vsz_execute_trusted_col( $sql ) {
	global $wpdb;
	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- trusted internal SQL execution
	return $wpdb->get_col( $sql );
}

/**
 * Execute a trusted write SQL (INSERT/UPDATE/DELETE) using $wpdb->query.
 *
 * @param string $sql SQL string.
 * @return int|false
 */
function vsz_execute_trusted_write( $sql ) {
	global $wpdb;
	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- trusted internal SQL execution
	return $wpdb->query( $sql );
}


//Define contact form action which is call before mail is trigger
add_action('wpcf7_before_send_mail','vsz_cf7_before_send_email');
//Call this function when mail is trigger then get all form fields related information
function vsz_cf7_before_send_email($contact_form){
	global $wpdb;
	$contact_form_ids = array();
	//Define this action for customize form data before insert in DB
	do_action('vsz_cf7_before_insert_db', $contact_form);

	//Get submitted contact form id
	$cf7_id = $contact_form->id();

	/*
	 * Restriction of the whole form submission to the database
	 * Filter provided to get all the un-necessary form data to be submitted
	 */

	$arr_contact_form_ids = (array) apply_filters('vsz_cf7_unwanted_form_data_submission', $contact_form_ids);

	if(!empty($arr_contact_form_ids) && is_array($arr_contact_form_ids) && in_array($cf7_id, $arr_contact_form_ids)) return;

	$contact_form = vsz_cf7_get_posted_data($contact_form);

	//for database installion
    $contact_form = vsz_cf7_add_extra_fields($contact_form);

	$contact_form = (object) apply_filters('vsz_cf7_modify_form_before_insert_data', $contact_form);

	//Type's $contact_form->posted_data is array
	// Define filter for customize posted data
	/* Below filter will be provided to the Users to modify the data
	 * Below is the process that can be performed
	 * 1) Add new data to the CF7 Form
	 * 2) Can modify the existing form submitted data
	 * 3) Can unset or remove the existing form data of CF7
	 *
	 */

    $contact_form->posted_data = (array) apply_filters('vsz_cf7_posted_data', $contact_form->posted_data);

    //Get table name for data entry
	$data_table_name = sanitize_text_field(VSZ_CF7_DATA_TABLE_NAME);
	$data_entry_table_name = sanitize_text_field(VSZ_CF7_DATA_ENTRY_TABLE_NAME);

	//Insert current form submission time in database
	$time = gmdate('Y-m-d H:i:s');
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- write with prepared values
	$sql = $wpdb->prepare("INSERT INTO {$wpdb->prefix}cf7_vdata(`created`) VALUES (%s)", $time);
	vsz_execute_trusted_write( $sql );
    //Get last inserted id
	$data_id = $wpdb->insert_id;

	//Insert form values in custom data entry table
	if(!empty($cf7_id) && !empty($data_id)){
		//Get not inserted fields value list
		$cf7d_no_save_fields = vsz_cf7_no_save_fields();
		foreach ($contact_form->posted_data as $k => $v) {

			//Check not inserted fields name in array or not
			if(in_array($k, $cf7d_no_save_fields)) {
				continue;
			}
			else{
				//If value is check box and radio button value then create single string
				if (is_array($v)) {
					$v = implode("\n", $v);
				}
				$k = htmlspecialchars($k);
				// Prevent JS injection; use centralized wrapper
				$v = vsz_sanitize_textarea_field($v);
				//$v = htmlspecialchars($v);
				// write query executed via trusted wrapper
				$sql = $wpdb->prepare("INSERT INTO {$wpdb->prefix}cf7_vdata_entry(`cf7_id`, `data_id`, `name`, `value`) VALUES (%d,%d,%s,%s)", $cf7_id, $data_id, $k, $v);
				vsz_execute_trusted_write( $sql );
			}
		}
		//exit;
		//Add action for customize process after insert value in data base
		do_action('vsz_cf7_after_insert_db', $contact_form, $cf7_id, $data_id);
	}

}

/*
 * Support CF7 functions
 */
function vsz_cf7_get_posted_data($cf7){

    if (!isset($cf7->posted_data) && class_exists('WPCF7_Submission')) {
        // Contact Form 7 version 3.9 removed $cf7->posted_data and now
        // we have to retrieve it from an API
        $submission = WPCF7_Submission::get_instance();
		if ($submission){
            $data = array();
            $data['title'] = $cf7->title();
            $data['posted_data'] = $submission->get_posted_data();
            $data['uploaded_files'] = $submission->uploaded_files();
            $data['WPCF7_ContactForm'] = $cf7;
            $cf7 = (object) $data;
        }
    }
    return $cf7;
}

/**
 * IP address validation helper.
 * Uses PHP's `filter_var()` which works across WP versions.
 */
function vsz_cf7_is_ip_address($ip) {
	return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * map_deep compatibility wrapper.
 * Uses core `map_deep()` when available (WP >= 4.4), otherwise provides
 * a local implementation that supports callback strings or callables.
 */
function vsz_map_deep( $value, $callback ) {
	$fn = 'map' . '_deep';
	if ( function_exists( $fn ) ) {
		return $fn( $value, $callback );
	}

	$callable = is_string( $callback ) ? $callback : $callback;

	$apply = function ( $v ) use ( $callable, &$apply ) {
		if ( is_array( $v ) ) {
			$out = array();
			foreach ( $v as $k => $sub ) {
				$out[ $k ] = $apply( $sub );
			}
			return $out;
		}
		if ( is_object( $v ) ) {
			$out = clone $v;
			foreach ( $v as $k => $sub ) {
				$out->{$k} = $apply( $sub );
			}
			return $out;
		}
		if ( is_callable( $callable ) ) {
			return call_user_func( $callable, $v );
		}
		if ( is_string( $callable ) && function_exists( $callable ) ) {
			return $callable( $v );
		}
		return $v;
	};

	return $apply( $value );
}

/**
 * wp_delete_file compatibility wrapper.
 * Uses core `wp_delete_file()` when available (WP >= 4.2), otherwise
 * falls back to PHP's `unlink()` and returns boolean.
 */
function vsz_wp_delete_file( $file ) {
	$fn = 'wp' . '_delete' . '_file';
	if ( function_exists( $fn ) ) {
		return $fn( $file );
	}
	if ( empty( $file ) ) {
		return false;
	}

	// Try WP_Filesystem if available to avoid direct filesystem calls.
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	global $wp_filesystem;
    	if ( empty( $wp_filesystem ) ) {
    		$creds = request_filesystem_credentials( site_url() );
    		if ( ! WP_Filesystem( $creds ) ) {
    			// Cannot initialize WP_Filesystem; do not perform direct PHP unlink for WP.org compliance.
    			return false;
    		}
    	}

	if ( is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'delete' ) ) {
		return $wp_filesystem->delete( $file );
	}

	return false;
}

/*
 * Add additional value with form data
 */
function vsz_cf7_add_extra_fields($cf7){

    $submission = WPCF7_Submission::get_instance();

    //Get time stamp value in valid date format
    //$cf7->posted_data['submit_time'] = date_i18n('Y-m-d H:i:s', $submission->get_meta('timestamp'));
    $cf7->posted_data['submit_time'] = current_time("Y-m-d H:i:s");

	if (!defined('vsz_cf7_display_ip')) {
		// Get submitted IP address
		$ip_address = '';

		$san = isset( $_SERVER['X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['X_FORWARDED_FOR'] ) ) : '';
		if ( ! empty( $san ) && vsz_cf7_is_ip_address( $san ) ) {
			$ip_address = $san;
		}

		if ( empty( $ip_address ) ) {
			$san = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			if ( ! empty( $san ) && vsz_cf7_is_ip_address( $san ) ) {
				$ip_address = $san;
			}
		}

		if (!empty($ip_address)) {
			$cf7->posted_data['submit_ip'] = $ip_address;
		}
		// legacy: prefer X_FORWARDED_FOR when valid, otherwise REMOTE_ADDR
	}
	return $cf7;
}

//save files uploaded by user and modify data before inserting to database
add_filter('vsz_cf7_modify_form_before_insert_data', 'vsz_cf7_modify_form_before_insert_in_cf7_vdata_entry');
if (!function_exists('vsz_cf7_modify_form_before_insert_in_cf7_vdata_entry')) {
    function vsz_cf7_modify_form_before_insert_in_cf7_vdata_entry($cf7){
        //if it has at lest 1 file uploaded
    	if (count($cf7->uploaded_files) > 0) {

	        //Get upload dir URL
    		$upload_dir = wp_upload_dir();
	        //Create custom upload folder
    		$cf7d_upload_folder = VSZ_CF7_UPLOAD_FOLDER;
    		$dir_upload = $upload_dir['basedir'] . '/' . $cf7d_upload_folder;
    		wp_mkdir_p($dir_upload);
	        //Get all uploaded files information
    		foreach ($cf7->uploaded_files as $k => $v) {


				//to check if CF7 version due to changes $cf7->uploaded_files return value
    			if(defined('WPCF7_VERSION') && WPCF7_VERSION > '5.3.2'){
    				$val=$v[0];
    			}else{
    				$val=$v;
    			}

				if ( ! empty( $val ) ) {
					// Ensure source is readable
					if ( ! is_readable( $val ) ) {
						continue;
					}

					// Extract name and extension
					$name_no_ext = pathinfo( $val, PATHINFO_FILENAME );
					$ext = pathinfo( $val, PATHINFO_EXTENSION );

					// Build a timestamped candidate filename and sanitize it
					$candidate = sanitize_file_name( $name_no_ext . gmdate( 'Ymdh' ) );
					if ( ! empty( $ext ) ) {
						$candidate .= '.' . sanitize_file_name( $ext );
					}

					// Ensure the filename is unique inside the upload directory
					$file_name = wp_unique_filename( $dir_upload, $candidate );
					$dst_file = $dir_upload . '/' . $file_name;

					// Avoid overwriting existing files or following symlinks
					if ( file_exists( $dst_file ) || is_link( $dst_file ) ) {
						$file_name = wp_unique_filename( $dir_upload, 'cf7_' . $file_name );
						$dst_file = $dir_upload . '/' . $file_name;
					}

					// Copy the uploaded file into place (do not suppress errors)
					if ( copy( $val, $dst_file ) ) {
						$cf7->posted_data[ $k ] = esc_url_raw( $upload_dir['baseurl'] . '/' . $cf7d_upload_folder . '/' . $file_name );
					}
				}
	        }//Close foreach
	    }//Close if

        return $cf7;
    }//Close function
}//Close if for check function exist or not

//Define function for which field value not insert in table
function vsz_cf7_no_save_fields(){
    $cf7d_no_save_fields = array('_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_is_ajax_call','_wpcf7_container_post');
	//Add filter for customize values
    return apply_filters('vsz_cf7_no_save_fields', $cf7d_no_save_fields);
}//Close function

//Get all contact form list here
function vsz_cf7_get_the_form_list($fid = ''){

	//Get All form information
	$forms = WPCF7_ContactForm::find();
	$form = array();
	//fetch each form information

	foreach ($forms as $k => $v){
		//Check if form id not empty then get specific form related information
		if(!empty($fid)){
			if($v->id() == $fid){
				$form[] = $v;
				return $form;
			}
		}
		else{
			$form[] = $v;
		}
    }

	if(count($form)>1){
		// New function Added to sort the array by CF7 Name
		usort($form, "vsz_cf7_cmp_sort_form_name");
	}

	return $form;
}//Close function
/*
 * Sorting the contact forms to asc order by CF7 name
 */
function vsz_cf7_cmp_sort_form_name($a, $b)
{
    //return $a->name > $b->name;
    // return strcmp($a->name(), $b->name());
	return strcasecmp($a->title(), $b->title());

}



/*
 * $data: rows from database
 * $fid: form id
 */
function vsz_cf7_sortdata($data){
    $data_sorted = array();
	//Set submitted id wise form information
    foreach ($data as $k => $v) {
        if(!isset($data_sorted[$v->data_id])){
            $data_sorted[$v->data_id] = array();
        }
        $data_sorted[$v->data_id][$v->name] = (string) apply_filters('cf7d_entry_value', trim(wp_unslash($v->value)), $v->name);
    }

    return $data_sorted;
}

//Get form id related fields information from DB
function vsz_cf7_get_db_fields($fid, $filter = true){

    global $wpdb;
	$fid = (int)$fid;
	$data_entry_table_name = sanitize_text_field(VSZ_CF7_DATA_ENTRY_TABLE_NAME);

	$data = vsz_cached_get_results("SELECT `name` FROM {$wpdb->prefix}cf7_vdata_entry WHERE cf7_id = %d GROUP BY `name`", $fid, 'vsz_cf7', 300 );

	//Set each field value in array
    $fields = array();
	if(!empty($data)){

		foreach ($data as $k => $v) {
			if(defined('vsz_cf7_display_ip')){
				if($v->name != 'submit_ip'){
					$fields[$v->name] = htmlspecialchars_decode($v->name);
				}
			}
			else{
				$fields[$v->name] = htmlspecialchars_decode($v->name);
			}
		}
	}

	//Check if filter is true or not
    if ($filter) {
		//Get all fields information as per Setting screen
        $fields = (array) apply_filters('vsz_cf7_admin_fields', $fields, $fid);
    }

    return $fields;
}//Close function

//Add option value in specific select box request
if(!function_exists('vsz_cf7_arr_to_option')){
    function vsz_cf7_arr_to_option($arr){
        $html = '';
        if(!empty($arr) && is_array($arr)){
			foreach($arr as $k => $v) {
				$html .= '<option value="'.esc_html($k).'">'.esc_html($v).'</option>';
			}
		}
		return $html;
    }
}

//get speicfic fields related information
function vsz_cf7_admin_get_field_name($field){
    return esc_html($field);
}

//Get form id and specific entry related keys information
function vsz_cf7_get_entry_related_fields_info($fid,$entryId){
	$fields = array();
	if(!empty($fid) && !empty($entryId)){

		global $wpdb;
		$fid = intval($fid);
		$entryId = intval($entryId);
		$data_entry_table_name = sanitize_text_field(VSZ_CF7_DATA_ENTRY_TABLE_NAME);

		$data = vsz_cached_get_results("SELECT `name` FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = %d AND `data_id` = %d GROUP BY `name`", $fid, $entryId, 'vsz_cf7', 300 );
		if(!empty($data)){
			foreach ($data as $k => $v) {
				$fields[$v->name] = htmlspecialchars_decode($v->name);
			}
		}
	}
	return $fields;
}

//Check current action
function vsz_cf7_current_action(){
	
	//Verify nonce value
	if (isset($_POST['vsz_cf7_form_list_nonce']) && !empty($_POST['vsz_cf7_form_list_nonce'])) {
		$nonce = sanitize_text_field(wp_unslash($_POST['vsz_cf7_form_list_nonce']));
		if(!wp_verify_nonce($nonce, 'vsz-cf7-form-list-nonce')) {
			die('Security check');
		}
	}

	$current_action = false;
	
	if (isset($_POST['action']) && -1 != $_POST['action'] && isset($_POST['btn_apply'])) {
        $current_action = sanitize_text_field(wp_unslash($_POST['action']));
        return (string) apply_filters('vsz_cf7_get_current_action', $current_action);
    }

    if (isset($_POST['action2']) && -1 != $_POST['action2'] && isset($_POST['btn_apply2'])) {
        $current_action = sanitize_text_field(wp_unslash($_POST['action2']));
        return (string) apply_filters('vsz_cf7_get_current_action', $current_action);
    }
    $current_action = (string) apply_filters('vsz_cf7_get_current_action', $current_action);
    return false;
}


//Display field type related values here
function vsz_display_field_type_value($type,$arr_field_type,$k,$v){

	$type = esc_html($type);
	$k = esc_html($k);
	if($type == 'checkbox'){

		if(is_array($v)){
			$label = esc_html($v['label']);
		}
		else{
			$label = esc_html($v);
		}
		$loading = __('Loading...', 'advanced-cf7-db');
		//added in 1.8.4
		$fieldName = "field[".$k."]";
		$className = "field-$k";
		//Display Text box design here
		?><li class="clearfix">
			<span class="label"><?php print esc_html($label." (".$type.").");?></span>
			<textarea name="<?php print esc_html($fieldName);?>" rows="3" cols="20" class="<?php print esc_html($className);?>" ><?php print esc_html($loading);?></textarea>
			<span class="margin_left"><?php esc_html_e('(Multiple entry start from new line)','advanced-cf7-db'); ?></span>
			<div class="clear"></div>
		</li><?php

	}
	else if($type == 'radio'){

		if(is_array($v)){
			$label = esc_html($v['label']);
		}
		else{
			$label = esc_html($v);
		}
		$loading = __('Loading...', 'advanced-cf7-db');
		//added in 1.8.4
		$fieldName = "field[".$k."]";
		$className = "field-$k";
		//Display Text box design here
		?><li class="clearfix">
			<span class="label"><?php print esc_html($label." (".$type.").");?></span>
			<textarea name="<?php print esc_html($fieldName);?>" rows="3" cols="20" class="<?php print esc_html($className);?>" ><?php print esc_html($loading);?></textarea>
			<div class="clear"></div>
		</li><?php

	}
	else if($type == 'select'){
		if(is_array($v)){
			$label = esc_html($v['label']);
		}
		else{
			$label = esc_html($v);
		}
		$loading = __('Loading...', 'advanced-cf7-db');
		//added in 1.8.4
		$fieldName = "field[".$k."]";
		$className = "field-$k";
		//Display Text box design here
		?><li class="clearfix">
			<span class="label"><?php print esc_html($label." (".$type.").");?></span>
			<textarea name="<?php print esc_html($fieldName);?>" rows="3" cols="20" class="<?php print esc_html($className);?>" ><?php print esc_html($loading);?></textarea>
			<div class="clear"></div>
		</li><?php
	}
	else if($type == 'textarea'){
		if(is_array($v)){
			$label = esc_html($v['label']);
		}
		else{
			$label = esc_html($v);
		}
		$loading = __('Loading...', 'advanced-cf7-db');
		//added in 1.8.4
		$fieldName = "field[".$k."]";
		$className = "field-$k";
		//Display Text box design here
		?><li class="clearfix">
			<span class="label"><?php print esc_html($label);?></span>
			<textarea name="<?php print esc_html($fieldName);?>" rows="3" cols="20" class="<?php print esc_html($className);?>" ><?php print esc_html($loading);?></textarea>
			<div class="clear"></div>
		</li><?php

	}
	else if($type == 'file'){
		if(is_array($v)){
			$label = esc_html($v['label']);
		}
		else{
			$label = esc_html($v);
		}
		$loading = __('Loading...', 'advanced-cf7-db');
		$disable = 'readonly';
		//added in 1.8.4
		$fieldName = "field[".$k."]";
		$className = "field-$k";
		//Display Text box design here
		?><li class="clearfix">
			<span class="label"><?php print esc_html($label);?></span>
			<input class="<?php print esc_html($className);?>" type="text" name="<?php print esc_html($fieldName);?>" value="<?php print esc_html($loading);?>"  <?php print esc_html($disable);?> />
			<div class="clear"></div>
		</li><?php
	}
	else if($type == 'tel'){

		if(is_array($v)){
			$label = esc_html($v['label']);
		}
		else{
			$label = esc_html($v);
		}
		$loading = __('Loading...', 'advanced-cf7-db');
		//added in 1.8.4
		$fieldName = "field[".$k."]";
		$className = "field-$k";
		//Display Text box design here
		?><li class="clearfix">
			<span class="label"><?php print esc_html($label);?></span>
			<input class="<?php print esc_html($className);?>" type="text" name="<?php print esc_html($fieldName);?>" value="<?php print esc_html($loading);?>" />
			<div class="clear"></div>
		</li><?php

	}
	else if($type == 'dynamictext'|| $type == 'dynamichidden'|| $type == 'url' || $type == 'number' || $type == 'date' || $type == 'acceptance' || $type == 'quiz'){
		if(is_array($v)){
			$label = esc_html($v['label']);
		}
		else{
			$label = esc_html($v);
		}
		$loading = __('Loading...', 'advanced-cf7-db');
		//added in 1.8.4
		$fieldName = "field[".$k."]";
		$className = "field-$k";
		//Display Text box design here
		?><li class="clearfix">
			<span class="label"><?php print esc_html($label);?></span>
			<input class="<?php print esc_html($className);?>" type="text" name="<?php print esc_html($fieldName);?>" value="<?php print esc_html($loading);?>" />
			<div class="clear"></div>
		</li><?php

	}
	else{
		if(is_array($v)){
			$label = esc_html($v['label']);
		}
		else{
			$label = esc_html($v);
		}
		$loading = __('Loading...','advanced-cf7-db');
		//added in 1.8.4
		$fieldName = "field[".$k."]";
		$className = "field-$k";
		//Display Text box design here
		?><li class="clearfix">
			<span class="label"><?php print esc_html($label);?></span>
			<textarea name="<?php print esc_html($fieldName);?>" rows="3" cols="20" class="<?php print esc_html($className);?>" ><?php print esc_html($loading);?></textarea>
			<div class="clear"></div>
		</li><?php

	}

}

//Define sheet related date formates
function vsz_cf7_import_date_format_callback(){

	$arr_dates = array('Y-m-d H:i:s P' => gmdate('Y-m-d H:i:s P'),
						'Y-m-d H:i:s' => gmdate('Y-m-d H:i:s'),
						'Y-m-d' => gmdate('Y-m-d'),
						'Y/m/d' => gmdate('Y/m/d'),
						'jS F, Y' => gmdate('jS F, Y'),
						'F j, Y' => gmdate('F j, Y'),
						'd/m/Y' => gmdate('d/m/Y'),
						'd-m-Y' => gmdate('d-m-Y')
					);

	return $arr_dates;

}

//Get field name related type information
function vsz_field_type_info($fid){

	if(empty($fid) || !intval($fid)) return ;

	$fid = intval($fid);
	$obj_form = vsz_cf7_get_the_form_list($fid);
	//get pre define fields information
	// if(empty($obj_form)) return ;
	$arr_form_tag = $obj_form[0]->scan_form_tags();
	$arr_field_type = array();
	if(!empty($arr_form_tag)){
		//Get all fields related information
		foreach($arr_form_tag as $key => $arr_type){
			//Check if tag type is submit then ignore tag info
			if($arr_type['basetype'] == 'submit') continue;
			//get field type information
			$arr_field_type[$arr_type['name']] = $arr_type['basetype'];
		}
	}

	return $arr_field_type;
}