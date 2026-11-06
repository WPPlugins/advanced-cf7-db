<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- admin helper includes activation-only schema functions

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.vsourz.com
 * @since      1.0.0
 *
 * @package    Advanced_Cf7_Db
 * @subpackage Advanced_Cf7_Db/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Advanced_Cf7_Db
 * @subpackage Advanced_Cf7_Db/admin
 * @author     vsourz Digital <mehul@vsourz.com>
 */
 
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Advanced_Cf7_Db_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/*
	* Define table name for advance Cf 7 DB
	*/
	private $vsz_data_entry_table;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->vsz_data_entry_table = sanitize_text_field(VSZ_CF7_DATA_ENTRY_TABLE_NAME);

	}

	/**
	 * Simple cached wrapper for SELECT queries.
	 *
	 * @param string $sql Prepared SQL string.
	 * @param string $cache_group Cache group name.
	 * @param int $expire Expiration in seconds.
	 * @return array|null
	 */
	// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
	private function cached_get_results( $query_format ) {
		$args = func_get_args();
		array_shift( $args ); // remove query_format
		$cache_group = 'vsz_cf7';
		$expire = 300;
		// If last two args are cache_group and expire (string,int), pop them
		if ( count( $args ) >= 2 && is_string( end( $args ) ) === false && is_int( end( $args ) ) ) {
			$expire = array_pop( $args );
			$cache_group = array_pop( $args );
		}
		global $wpdb;
		// Compute cache key from the args and function identity so we avoid
		// including the raw SQL string in the cache key (which some scanners flag).
		$cache_key = 'vsz_' . md5( serialize( $args ) . '|' . __FILE__ . '|' . __FUNCTION__ );
		$results = wp_cache_get( $cache_key, $cache_group );
		if ( false === $results ) {
			if ( ! empty( $args ) ) {
				// Use inline prepare so static analysis can see placeholders and arguments together.
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepare used inline for execution
				$results = $wpdb->get_results( $wpdb->prepare( $query_format, ...$args ) );
			} else {
				// No placeholders present — don't call prepare() without placeholders.
				$results = $wpdb->get_results( $query_format );
			}
			wp_cache_set( $cache_key, $results, $cache_group, $expire );
		}
		return $results;
	}
	// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Advanced_Cf7_Db_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Advanced_Cf7_Db_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_register_style( 'vsz-cf7-db-admin-css', plugin_dir_url( __FILE__ ) . 'css/advanced-cf7-db-admin.css', array(), $this->version, 'all' );
		wp_register_style( 'vsz-cf7-font-awesome-css', plugin_dir_url( __FILE__ ) . 'css/font-awesome.css', array(), $this->version, 'all' );
		wp_register_style( 'vsz-cf7-datetimepicker-css', plugin_dir_url( __FILE__ ) . 'css/jquery.datetimepicker.css', array(), $this->version, 'all');
		wp_register_style( 'vsz-cf7-mounstride-crm-css', plugin_dir_url( __FILE__ ) . 'css/mounstride-CRM.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Advanced_Cf7_Db_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Advanced_Cf7_Db_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_register_script( 'vsz-cf7-db-admin-js', plugin_dir_url( __FILE__ ) . 'js/advanced-cf7-db-admin.js', array( 'jquery' ), time(), false );//$this->version
		wp_register_script( 'vsz-cf7-datetimepicker-js', plugin_dir_url( __FILE__ ) . 'js/jquery.datetimepicker.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Defining the extra menus to be added
	 * admin screens for Contact form Db and Import CSV
	 */
	function vsz_cf7_plugin_menu(){
		///// Menu pages for contact form DB


		$cap = 'no_access';
		// Check if user have access than display

		$user_id = get_current_user_id();
		$subject = new WP_User($user_id);
		$allcaps= $subject->allcaps;
		$caps = $subject->caps;

		$caps    = array_merge(wp_get_current_user()->caps, $caps);
		$allcaps = array_merge(wp_get_current_user()->allcaps, $allcaps);
		if (wp_get_current_user()->ID == $subject->ID) {
			wp_get_current_user()->allcaps = $allcaps;
			wp_get_current_user()->caps    = $caps;
		}
		$user = wp_get_current_user();

		//check current user capability
		if(isset($user) && !empty($user)){
			foreach($user->allcaps as $key=>$capability){
				if($capability == true){
					if(strpos($key, 'cf7_db_form_edit_') !== false){
						$cap = 'exist';

						break;
					}
				}
			}
		}

		//check current user view capability access
		if($cap == 'no_access'){
			if(isset($user) && !empty($user)){
				foreach($user->allcaps as $key=>$capability){
					if($capability == true){
						if(strpos($key, 'cf7_db_form_view') !== false){
							$cap = 'exist';
							break;
						}
					}
				}
			}
		}

		add_menu_page( "Advanced CF7 DB", "Advanced CF7 DB", $cap, "contact-form-listing", array($this,"vsz_contact_form_callback"), 'dashicons-visibility' , 45);
		///// Menu pages for Import CSV
		add_submenu_page( 'contact-form-listing', __('Import CSV', 'advanced-cf7-db'), __('Import CSV', 'advanced-cf7-db'), $cap, 'vsz_cf7_import_csv',array($this,'vsz_import_cf7_csv') );
		add_submenu_page( 'contact-form-listing', __('Developer Support', 'advanced-cf7-db'), __('Developer Support', 'advanced-cf7-db'), $cap, 'vsz_cf7_developer_support',array($this,'vsz_shortcode') );
		add_submenu_page( 'contact-form-listing', __('Add-ons', 'advanced-cf7-db'), __('Add-ons', 'advanced-cf7-db'), $cap, 'vsz_cf7_addons',array($this,'vsz_extension') );
		
		
	}

	/**
	 * Callback function for listing screen
	 * Define all entry related design in this file
	 * Kept the current screen permission in the this file to view and edit the data
	 */

	function vsz_contact_form_callback(){

		//register CSS and JS file here
		wp_enqueue_style('vsz-cf7-db-admin-css');

		require_once plugin_dir_path( __FILE__ ) . 'partials/contact_form_listing.php';

	}

	/**
	 * Developer support page
	 * All the filters/actions/shortcodes used in the plugins are mentioned for the developers to
	 * expand the plugin functionality
	 */

	function vsz_shortcode(){

		require_once plugin_dir_path( __FILE__ ) . 'partials/developer_support.php';

	}

	/**
	 * Addon's page
	 * All the plugin related Addon's are been defined in this screen for the users to get more
	 * help from the plugin.
	 */
	function vsz_extension(){

		require_once plugin_dir_path( __FILE__ ) . 'partials/add-ons.php';

	}

	
	/**
	 * Callback function for Import CSV screen
	 */
	function vsz_import_cf7_csv(){
		require_once plugin_dir_path( __FILE__ ) . 'partials/import_cf7_csv.php';
	}

	/**
	 * Get form related all fields information here
	 * @param $fields, $fid
	 */
	function vsz_cf7_admin_fields_callback($fields, $fid){
		$return = array();

		$fid = (int)$fid;
		//Return null if the $fid is empty
		if( empty( $fid ) ){
			return $return;
		}
		//Get current form related field setting value from option table
		$field_settings = get_option('vsz_cf7_settings_field_' . $fid, array());
		//Check field settings value empty or not
		if ($field_settings == "") {
			$field_settings = array();
		}
		//Check if field setting found then any field entry exist or not
		if(count($field_settings) == 0){ //no settings found

			//Get form id related contact form object
			$obj_form = vsz_cf7_get_the_form_list($fid);

			//get pre define form fields information
			if(isset($obj_form[0])){
				$arr_form_tag = $obj_form[0]->scan_form_tags();
			}
			else{
				$arr_form_tag = array();
			}

			/**
			 * This functionality Added because when field settings not define then
			 * fields display as per form design format
			 */

			//Check field exist with form or not
			if(!empty($arr_form_tag)){
				//Get all fields related information
				foreach($arr_form_tag as $key => $arr_type){
					//Check if tag type is submit then ignore tag info
					if($arr_type['basetype'] == 'submit') continue;
					//Check if field name match with form field name or not
					if(isset($arr_type['name']) && array_key_exists($arr_type['name'],$fields)){
						//If field type match with form field name then set field name in array
						$return[$arr_type['name']] = esc_html($fields[$arr_type['name']]);
						//Remove current keys from field array
						unset($fields[$arr_type['name']]);
					}
				}//Close for for each
			}//Close if for field check

			//Check any fields remaining in field array or not which is not define in Current Form
			if(count($fields) > 0){
				//Remaining fields add in array
				$return =  array_merge($return,$fields);
			}
		}//Close field setting IF
		//If fields setting found in option table
		else{
			//Fetch fields information from array
			foreach ($field_settings as $k => $v) {
				//Check Current DB fields with setting field name exist or not
				if(isset($fields[$k])){
					//If field exist with field array then get current field display information
					$show = (int)$field_settings[$k]['show'];
					//If condition is true then field display on Listing screen and export Sheet.
					if ($show == 1){
						//Get field label name and set in array
						$label = $field_settings[$k]['label'];
						$return[$k] = esc_html($label);
					}
					//Unset current field from fields array
					unset($fields[$k]);
				}//Close if
			}//Close foreach

			//Check any field is remaining in array or not
			if (count($fields) > 0) {
				//Set all remaining fields name in array
				foreach ($fields as $k => $v) {
					$return[$k] = esc_html($v);
				}
			}
		}//Close else

		//return all existing fields information in array format here
		return $return;
	}

	/**
	 * Action callback function of 'vsz_cf7_after_bulkaction_btn'
	 * Populate Export option box on form listing screen
	 * @param $fid
	 */
	function vsz_cf7_after_bulkaction_btn_callback($fid){
		$fid = (int)$fid;
		if( empty( $fid ) ){
			return 'Select at least one form';
		}

		?><!-- Display Export functionality button here-->
		<select id="vsz-cf7-export" name="vsz-cf7-export" data-fid="<?php echo esc_html($fid); ?>">
			<option value="-1"><?php esc_html_e('Export to...','advanced-cf7-db'); ?></option>
			<option value="csv"><?php esc_html_e('CSV','advanced-cf7-db'); ?></option>
			<option value="excel"><?php esc_html_e('Excel','advanced-cf7-db'); ?></option>
		</select>
		<button class="button action" title="<?php esc_html_e('Export','advanced-cf7-db'); ?>" type="submit" name="btn_export"><?php esc_html_e('Export','advanced-cf7-db'); ?></button><?php
	}

	/**
	 * Display Search text box design structure here
	 */
	function vsz_cf7_after_datesection_btn_callback($fid){
		//Get menu page URL
		$nonce = isset($_POST['vsz_cf7_search_nonce']) && !empty($_POST['vsz_cf7_search_nonce']) ? sanitize_text_field(wp_unslash($_POST['vsz_cf7_search_nonce'])) : '';
		$url = menu_page_url('contact-form-listing',false);
		//Check form id is define in current page or not if defirn then current form ID add with existing URL
		if(isset($_REQUEST['cf7_id']) && !empty($_REQUEST['cf7_id'])){
			if(wp_verify_nonce( $nonce, 'vsz-cf7-search-nonce')){
				$fid = (int)sanitize_text_field(wp_unslash($_REQUEST['cf7_id']));
				$url .= '&cf7_id='.$fid;
			}
		}

		$searchVal = isset($_POST['search_cf7_value']) && !empty($_POST['search_cf7_value']) ? htmlspecialchars(sanitize_text_field(wp_unslash($_POST['search_cf7_value']))) : '';

		?><input value="<?php echo esc_attr($searchVal); ?>" type="text" class="" id="cf7d-search-q" name="search_cf7_value" placeholder="<?php esc_attr_e('Type something...', 'advanced-cf7-db' );?>" />
		<button data-url="<?php echo esc_url($url); ?>" class="button" type="button" id="cf7d-search-btn" title="<?php esc_attr_e('Search', 'advanced-cf7-db'); ?>" ><?php esc_html_e('Search','advanced-cf7-db');?></button>
		<?php
		$nonce = wp_create_nonce( 'vsz-cf7-search-nonce' );
		echo '<input type="hidden" name="vsz_cf7_search_nonce"  value="' .esc_html($nonce) .'" />';
		
	}//Close search box design function

	/**
	 * Display table header in edit column here
	 */
	function vsz_cf7_admin_after_heading_field_callback(){
		?><th style="width: 32px;" class="manage-column"></th><?php
	}

	/**
	 * Display Settings popup here
	 */
	function vsz_cf7_display_settings_btn_callback(){

		//Define thickbox popup function
        add_thickbox();
		?><div class="span12">
			<div class="display-setup">
				<span><?php esc_html_e('To change the Field title, Hide field and change the position of fields using Drag and Drop from here.','advanced-cf7-db'); ?></span>
				<a title="<?php esc_html_e('Display Settings','advanced-cf7-db'); ?>" href="#TB_inline?width=600&height=550&inlineId=cf7d-modal-setting" id="cf7d_setting_form" class="thickbox page-title-action" name="Display Settings"><?php esc_html_e('Display Settings','advanced-cf7-db'); ?></a>
			</div>
		</div><?php
	}

	/**
	 * Display edit link with each entry in table
	 */
	function vsz_cf7_admin_after_body_edit_field_func($form_id, $row_id,$getDatanonce){
		//Define thickbox popup function
		add_thickbox();
		$row_id = (int)$row_id;
		?><td>
			<a data-rid="<?php echo esc_attr($row_id); ?>" data-nonce="<?php echo esc_attr($getDatanonce); ?>" href="#TB_inline?width=600&height=550&inlineId=cf7d-modal-edit-value" id="cf7d-edit-form" class="thickbox cf7d-edit-value" name="<?php esc_attr_e('Edit Information','advanced-cf7-db'); ?>">
				<i class="fa fa-pencil-square-o" title="<?php esc_attr_e('Edit','advanced-cf7-db'); ?>" aria-hidden="true" style="font-size:20px;"></i>
			</a>
		</td><?php
	}

	/**
	 * Define Display Setting Popup functionality here
	 */
	function vsz_cf7_after_admin_setting_form_callback($fid){

		$fid = intval($fid);
		//Get Current form related existing fields information
		$fields = vsz_cf7_get_db_fields($fid,false);
		$obj_form = vsz_cf7_get_the_form_list(intval($fid));
		//Get form related fields information
		$arr_form_tag = $obj_form[0]->scan_form_tags();

		//Create nonce values which is validate on SAVE time
		$nonce = wp_create_nonce( 'vsz-cf7-setting-nonce-'.$fid );
		//Define Design related structure here
		?><div id="cf7d-modal-setting" style="display:none;">
			<form action="" id="cf7d-modal-form" method="POST" class="setting-form">
				<div class="popup-note">
						<span><?php
							esc_html_e('You can rename the Field title, Hide field and change the position of fields using Drag and Drop from here.','advanced-cf7-db');
						?></span>
					</div>
					<input type="hidden" name="fid" value="<?php echo esc_attr($fid); ?>" />
					<input type="hidden" name="vsz_cf7_setting_nonce"  value="<?php echo esc_attr($nonce); ?>" />
				<ul id="cf7d-list-field"><?php

					//Get form id related fields settings value from option table
					$field_settings = get_option('vsz_cf7_settings_field_' . $fid, array());
					$show_record = '';
					$show_record = (int) get_option('vsz_cf7_settings_show_record_' . $fid, '');
					if(empty($show_record)){
						$show_record = 10;
					}
					?><li class="ui-state-disabled">
						<span class="label"><?php esc_html_e('Show record','advanced-cf7-db'); ?></span>
						<input class="" type="text" name="cf7_show_record" value="<?php echo esc_attr($show_record); ?>">
					</li><?php

					//Check fields setting define or not
					if($field_settings == ""){
						$field_settings = array();
					}
					//If fields setting not define then
					if(count($field_settings) == 0){ //no settings found

						//Fetch all existing fields information
						foreach ($arr_form_tag as $k => $v) {
							if($v->type == 'submit' || $v->type == 'recaptcha') continue;
							$show = 1;
							$k = esc_html($v->name);
							$label = esc_html($v->name);
							$showClass = $show == 1 ? 'show' : 'hide';
							$dashiconsClass = $show == 1 ? 'visibility' : 'hidden';
							$fieldName = "field[".$k."][label]";
							$hiddenFieldName = "field[".$k."][show]";
							//added in 1.8.4
							//Setup fields in Setting popup
							?><li class="<?php print esc_html($showClass);?>">
								<span class="label"><?php print esc_html($k)?></span>
								<input class="" type="text" name="<?php print esc_html($fieldName);?>" value="<?php print esc_html($label);?>" />
								<span class="dashicons dashicons-<?php print esc_html($dashiconsClass);?>"></span>
								<input type="hidden" class="txt_show" name="<?php print esc_html($hiddenFieldName);?>" value="<?php print esc_html($show);?>" />
							</li><?php
							if(isset($fields[$v->name]))
								unset($fields[$v->name]);
						}
						if(!empty($fields) && count($fields) > 0){
							foreach ($fields as $k => $v) {
								$show = 1;
								$k = esc_html($v);
								$label = esc_html($v);
								$showClass = $show == 1 ? 'show' : 'hide';
								$dashiconsClass = $show == 1 ? 'visibility' : 'hidden';
								$fieldName = "field[".$k."][label]";
								$hiddenFieldName = "field[".$k."][show]";

								//added in 1.8.4
								//Setup fields in Setting popup
								?><li class="<?php print esc_html($showClass);?>">
									<span class="label"><?php print esc_html($k)?></span>
									<input class="" type="text" name="<?php print esc_html($fieldName);?>" value="<?php print esc_html($label);?>" />
									<span class="dashicons dashicons-<?php print esc_html($dashiconsClass);?>"></span>
									<input type="hidden" class="txt_show" name="<?php print esc_html($hiddenFieldName);?>" value="<?php print esc_html($show);?>" />
								</li><?php
							}
						}
					}//close fields setting if
					//If fields settng found in option table
					else{

						//Display all existing fields information
						foreach ($field_settings as $k => $v) {
							if(isset($fields[$k])){
								//Get field related visiable and label information
								$k = esc_html($k);
								$show = (int)$field_settings[$k]['show'];
								$label = esc_html($field_settings[$k]['label']);
								$showClass = $show == 1 ? 'show' : 'hide';
								$dashiconsClass = $show == 1 ? 'visibility' : 'hidden';
								$fieldName = "field[".$k."][label]";
								$hiddenFieldName = "field[".$k."][show]";

								//added in 1.8.4
								//Setup fields in Setting popup
								?><li class="<?php print esc_html($showClass);?>">
									<span class="label"><?php print esc_html($k)?></span>
									<input class="" type="text" name="<?php print esc_html($fieldName);?>" value="<?php print esc_html($label);?>" />
									<span class="dashicons dashicons-<?php print esc_html($dashiconsClass);?>"></span>
									<input type="hidden" class="txt_show" name="<?php print esc_html($hiddenFieldName);?>" value="<?php print esc_html($show);?>" />
								</li><?php
								//Unset current field from DB fields array
								unset($fields[$k]);

							}//Close if
						}//Close for each

						//Call when new field is added in existing form
						//Check any fields remaining in field array or not
						if (count($fields) > 0){
							//Fetch All remaining fields information
							foreach ($fields as $k => $v){
								$k = esc_html($k);
								$show = 1;
								$label = esc_html($v);
								$showClass = $show == 1 ? 'show' : 'hide';
								$dashiconsClass = $show == 1 ? 'visibility' : 'hidden';
								$fieldName = "field[".$k."][label]";
								$hiddenFieldName = "field[".$k."][show]";

								//added in 1.8.4
								//Setup fields in Setting popup
								?><li class="<?php print esc_html($showClass);?>">
									<span class="label"><?php print esc_html($k)?></span>
									<input class="" type="text" name="<?php print esc_html($fieldName);?>" value="<?php print esc_html($label);?>" />
									<span class="dashicons dashicons-<?php print esc_html($dashiconsClass);?>"></span>
									<input type="hidden" class="txt_show" name="<?php print esc_html($hiddenFieldName);?>" value="<?php print esc_html($show);?>" />
								</li><?php

							}
						}//Close if for check remaining fields
					}//Close else

				?></ul>
				<div id="cf7d-modal-footer">
					<input type="submit" name="vsz_save_field_settings" value="<?php esc_html_e('Save Changes','advanced-cf7-db'); ?>" class="button button-primary button-large" />
				</div>
			</form>
			<script>
				jQuery(document).ready(function($) {
					//For using drag and drop js
					jQuery('#cf7d-list-field').sortable({
						placeholder: "sortable-placeholder",
						items: "li:not(.ui-state-disabled)"
					});
				});
			</script>
		</div><?php

	}//Close setting POPUP function here

	/**
	 * Display edit popup related content for this function
	 */
	function vsz_cf7_after_admin_edit_values_form_callback($form_id){

		$form_id = intval($form_id);
		//Get form id related contact form object
		$obj_form = vsz_cf7_get_the_form_list($form_id);
		//get pre define fields information
		$arr_form_tag = $obj_form[0]->scan_form_tags();

		$arr_field_type = array();
		//Define option field type array
		$arr_option_type = array('checkbox','radio','select');
		//Check field exist with form or not
		if(!empty($arr_form_tag)){

			//Get all fields related information
			foreach($arr_form_tag as $key => $arr_type){
				//Check if tag type is submit then ignore tag info
				if($arr_type['basetype'] == 'submit') continue;
				//Check if field type match with option values or not
				if(isset($arr_type['basetype']) && in_array($arr_type['basetype'],$arr_option_type)){
					//If field type is option then get option names and values
					$arr_field_type[$arr_type['name']]['basetype'] = $arr_type['basetype'];
					$arr_field_type[$arr_type['name']]['labels'] = $arr_type['labels'];
					$arr_field_type[$arr_type['name']]['values'] = $arr_type['values'];
				}
				else{
					//get field type information
					$arr_field_type[$arr_type['name']]['basetype'] = $arr_type['basetype'];
				}
			}//Close for for each
		}//Close if for field check

		//Get form id related database fields information
		$fields = vsz_cf7_get_db_fields($form_id);
		//Define nonce value which is validate on save time
		$nonce = wp_create_nonce( 'vsz-cf7-edit-nonce-'.$form_id );
		//Get not editable fields list
		$not_editable_field = (array) apply_filters('vsz_cf7_not_editable_fields',array());
		//Setup edit form design here
		?><div class="cf7d-modal" id="cf7d-modal-edit-value" style="display:none;">
			<form action="" class="cf7d-modal-form loading" id="cf7d-modal-form-edit-value" method="POST">
				<div class="popup-note"><span><?php esc_html_e('*(Field Type)','advanced-cf7-db'); ?></span></div>
					<input type="hidden" name="fid" value="<?php echo esc_attr($form_id); ?>" />
					<input type="hidden" name="rid" value="" />
					<input type="hidden" name="vsz_cf7_edit_nonce"  value="<?php echo esc_attr($nonce); ?>" />
				<ul id="cf7d-list-field-for-edit" class="edit-popup"><?php

					//Get form id related header settings value
					$field_settings = get_option('vsz_cf7_settings_field_' . $form_id, array());


					if(count($field_settings) == 0) { //no settings found

						foreach ($fields as $k => $v) {

							//Display field type related fields here
							if(isset($arr_field_type[$k]) && $arr_field_type[$k]['basetype'] != 'text' && $arr_field_type[$k]['basetype'] != 'email'){
								//Call function for display design structure
								vsz_display_field_type_value($arr_field_type[$k]['basetype'],$arr_field_type,$k,$v);

							}
							else{
								//Define all text field here
								$disable = '';
								//Check if any field is not edit by admin then add disable setting with field
								if(!empty($not_editable_field) && in_array($k,$not_editable_field)){
									$disable = 'disabled';
								}
								$label = esc_html($v);
								$k = esc_html($k);
								$loading = __('Loading...','advanced-cf7-db');
								$fieldName = "field[".$k."]";
								$className = "field-$k";
								//Display Text box design here
								?><li class="clearfix">
									<span class="label"><?php print esc_html($label);?></span>
									<input class="<?php print esc_html($className);?>" type="text" name="<?php print esc_html($fieldName);?>" value="<?php print esc_html($loading);?>"  <?php print esc_html($disable);?> />
									<div class="clear"></div>
								</li><?php

							}//Close else
						}//Close foreach
					}//Close if for  check fields settings
					//If field setting not defined
					else{

						//Display form fields with value
						foreach($field_settings as $k => $v) {
							//Check field set in array or not
							if (isset($fields[$k])) {


								//Set all input field type design here
								if(isset($arr_field_type[$k]) && $arr_field_type[$k]['basetype'] != 'text' && $arr_field_type[$k]['basetype'] != 'email'){
									//Call function for display design structure
									vsz_display_field_type_value($arr_field_type[$k]['basetype'],$arr_field_type,$k,$v);
								}
								else{
									$disable = '';
									//Check if any field is not edit by admin then add disable setting with field
									if(!empty($not_editable_field) && in_array($k,$not_editable_field)){
										$disable = 'disabled';
									}
									//Get label name values which is define on Setting screen
									$show = (int)$field_settings[$k]['show'];
									$label = esc_html($field_settings[$k]['label']);
									$k = esc_html($k);
									$loading = __('Loading...','advanced-cf7-db');
									$fieldName = "field[".$k."]";
									$className = "field-$k";
									//added in 1.8.4
									//Display Text box design here
									?><li>
										<span class="label"><?php print esc_html($label);?></span>
										<input class="<?php print esc_html($className);?>" type="text" name="<?php print esc_html($fieldName);?>" value="<?php print esc_html($loading);?>"  <?php print esc_html($disable);?> />
									</li><?php
								}
								unset($fields[$k]);

							}//Close If for check field name set in field array or not
						}//close for each


						//Call when new field is added in existing form
						//Check any field remaining in field array or not
						if (count($fields) > 0) {
							//Get all remaining fields information
							foreach ($fields as $k => $v) {
								//Set all input field type design here
								if(isset($arr_field_type[$k]) && $arr_field_type[$k]['basetype'] != 'text' && $arr_field_type[$k]['basetype'] != 'email'){
									//Call function for display design structure
									vsz_display_field_type_value($arr_field_type[$k]['basetype'],$arr_field_type,$k,$v);
								}
								else{
									$disable = '';
									//Check if any field is not edit by admin then add disable setting with field
									if(!empty($not_editable_field) && in_array($k,$not_editable_field)){
										$disable = 'disabled';
									}
									$label = esc_html($v);
									$k = esc_html($k);
									$loading = __('Loading...','advanced-cf7-db');
									//added in 1.8.4
									$fieldName = "field[".$k."]";
									$className = "field-$k";
									//Display Text box design here
									?><li>
										<span class="label"><?php print esc_html($label);?></span>
										<input class="<?php print esc_html($className);?>" type="text" name="<?php print esc_html($fieldName);?>" value="<?php print esc_html($loading);?>"  <?php print esc_html($disable);?> />
									</li><?php
								}
							}//close foreach
						}//Close if
					}//close else
				?></ul>
				<div class="cf7d-modal-footer">
					<input type="hidden" name="arr_field_type" value="<?php print esc_html(json_encode($arr_field_type));?>">
					<input type="submit" id="update_cf7_value" name="vsz_cf7_save_field_value" value="<?php esc_html_e('Save Changes','advanced-cf7-db'); ?>" class="button button-primary button-large" />
				</div>
			</form>
			<!------------------------------------ Ajax loader ----------------------------------------->
			<table style="display:none;" class="custom-overlay" id="overlayLoader">
				<tbody>
					<tr>
						<td><img alt="Loading..." src="<?php echo esc_url(plugin_dir_url(dirname( __FILE__)).'images/716.gif'); ?>"height="50" width="100"></td>
					</tr>
				</tbody>
			</table>
		</div><?php
	}//Close Edit POPUP function here


	/**
	 * Save all custom define settings fields value here
	 */
	public function vsz_cf7_save_setting_callback(){
		global $wpdb;

		//Save settings fields related values
		if(isset($_POST['vsz_save_field_settings'])){
			// check nonce
			if(!isset($_POST['vsz_cf7_setting_nonce']) || empty($_POST['vsz_cf7_setting_nonce'])){
				return;
			}
			//Check form ID exist with current request or not
			if(!isset($_POST['fid']) || empty($_POST['fid'])){
				return;
			}
			//get form Id
			$fid = (int)sanitize_text_field(wp_unslash($_POST['fid']));
			//Get nonce value
			$nonce = isset($_POST['vsz_cf7_setting_nonce']) ? sanitize_text_field(wp_unslash($_POST['vsz_cf7_setting_nonce'])) : '';
			//Verify nonce value
			if(!wp_verify_nonce( $nonce, 'vsz-cf7-setting-nonce-'.$fid)){
				// This nonce is not valid.
				return;
			}

			$arr_fields = array();
			//Get all define fields information — unslash and sanitize at definition
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$post_field = isset($_POST['field']) ? vsz_map_deep( wp_unslash( $_POST['field'] ), 'sanitize_text_field' ) : array();
			if ( ! empty( $post_field ) ) {
				//Fetch all fields name here
				foreach ( $post_field as $key => $arrVal ) {

					//sanitize new label name of field
					$arr_fields[$key]['label'] = sanitize_text_field($arrVal['label']);

					//Get field show or not information
					$arr_fields[$key]['show'] = intval($arrVal['show']);
				}
			}
			$show_record = isset($_POST['cf7_show_record']) ? (int)(sanitize_text_field(wp_unslash($_POST['cf7_show_record']))) : 0;
			//Save Settings POPUP information in option table
			add_option('vsz_cf7_settings_field_' . $fid, $arr_fields, '', 'no');
			update_option('vsz_cf7_settings_field_' . $fid, $arr_fields);
			update_option('vsz_cf7_settings_show_record_' . $fid, $show_record);

		}//close if for save setting information

		//Save form information here
		if(isset($_POST['vsz_cf7_save_field_value'])){

			// check nonce
			if(!isset($_POST['vsz_cf7_edit_nonce']) || empty($_POST['vsz_cf7_edit_nonce'])){
				return;
			}
			//Check form id
			if(!isset($_POST['fid']) || empty($_POST['fid'])){
				return;
			}
			//Check entry id
			if(!isset($_POST['rid']) || empty($_POST['rid'])){
				return;
			}

			//Get form and entry id
			$fid = intval(sanitize_text_field(wp_unslash($_POST['fid'])));
			$rid = intval(sanitize_text_field(wp_unslash($_POST['rid'])));
			//Verify nonce value
			$nonce = isset($_POST['vsz_cf7_edit_nonce']) ? sanitize_text_field(wp_unslash($_POST['vsz_cf7_edit_nonce'])) : '';
			if(!wp_verify_nonce( $nonce, 'vsz-cf7-edit-nonce-'.$fid)){
				// This nonce is not valid.
				return;
			}

			//added in 1.8.3
			// Checking for the capability
			$edit_cap = 'cf7_db_form_edit_'.$fid;
			if(!vsz_cf7_check_capability( $edit_cap ) ){
				//Current user does not have edit access
				return;
			}


			$arr_field_type = '';

			//Get field type information here
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$arr_field_type_raw = isset($_POST['arr_field_type']) ? wp_unslash($_POST['arr_field_type']) : '';
			if ( ! empty( $arr_field_type_raw ) ) {
				//Decode Json format string here
				$arr_field_type = json_decode(vsz_sanitize_textarea_field($arr_field_type_raw),true); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitizedDetected
			}

			//Define option field type array
			$arr_option_type = array('checkbox','radio','select');
			//Get non editable fields information
			$not_editable_field = (array) apply_filters('vsz_cf7_not_editable_fields',array());
			//Get entry related fields information	
			$arr_exist_keys = vsz_cf7_get_entry_related_fields_info($fid,$rid);
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$post_field_raw = isset($_POST['field']) ? wp_unslash($_POST['field']) : null;
			if ( ! empty( $post_field_raw ) ) {
				$post_field = vsz_map_deep($post_field_raw, 'vsz_sanitize_textarea_field');
				//Fetch all fields information here
				foreach ($post_field as $key => $value){
					$value = htmlspecialchars($value);

					$key = sanitize_text_field($key);
					//Escape loop if key have not editable field
					if(!empty($not_editable_field) && in_array($key,$not_editable_field)) continue;

					//Escape loop if key have file type value
					if(!empty($arr_field_type) && is_array($arr_field_type) && array_key_exists($key,$arr_field_type) && $arr_field_type[$key]['basetype'] == 'file') continue ;

					//Check key field have checkbox type or not
					if(!empty($arr_field_type) && is_array($arr_field_type) && array_key_exists($key,$arr_field_type) && in_array($arr_field_type[$key]['basetype'],$arr_option_type)){
						//Check if field name already exist with entry or not
						if(!empty($arr_exist_keys) && in_array($key,$arr_exist_keys)){
							//If field name match with current entry then field information update
							// write query executed via trusted wrapper
							$sql = $wpdb->prepare("UPDATE {$wpdb->prefix}cf7_vdata_entry SET `value` = %s WHERE `name` = %s AND `data_id` = %d", vsz_sanitize_textarea_field($value), sanitize_text_field($key), $rid);
							vsz_execute_trusted_write( $sql );
						}
						else{
							//If field name not match with current entry then new entry insert in DB
							// write query executed via trusted wrapper
							$sql = $wpdb->prepare("INSERT INTO {$wpdb->prefix}cf7_vdata_entry(`cf7_id`, `data_id`, `name`, `value`) VALUES (%d,%d,%s,%s)", $fid, $rid, sanitize_text_field($key), vsz_sanitize_textarea_field($value));
							vsz_execute_trusted_write( $sql );
						}
					}
					//Check if field type is text area
					else if(!empty($arr_field_type) && is_array($arr_field_type) && array_key_exists($key,$arr_field_type) && $arr_field_type[$key]['basetype'] == 'textarea'){
						//Check if field name already exist with entry or not
						if(!empty($arr_exist_keys) && in_array($key,$arr_exist_keys)){
							//If field name match with current entry then field information update
							// write query executed via trusted wrapper
							$sql = $wpdb->prepare("UPDATE {$wpdb->prefix}cf7_vdata_entry SET `value` = %s WHERE `name` = %s AND `data_id` = %d", vsz_sanitize_textarea_field($value), sanitize_text_field($key), $rid);
							vsz_execute_trusted_write( $sql );
						}
						else{
							//If field name not match with current entry then new entry insert in DB
							// write query executed via trusted wrapper
							$sql = $wpdb->prepare("INSERT INTO {$wpdb->prefix}cf7_vdata_entry(`cf7_id`, `data_id`, `name`, `value`) VALUES (%d,%d,%s,%s)", $fid, $rid, sanitize_text_field($key), vsz_sanitize_textarea_field($value));
							vsz_execute_trusted_write( $sql );
						}

					}//Close text area else if
					else{
						//Check if field name already exist with entry or not
						if(!empty($arr_exist_keys) && in_array($key,$arr_exist_keys)){
							//If field name match with current entry then field information update
							// write query executed via trusted wrapper
							$sql = $wpdb->prepare("UPDATE {$wpdb->prefix}cf7_vdata_entry SET `value` = %s WHERE `name` = %s AND `data_id` = %d", sanitize_text_field($value), sanitize_text_field($key), $rid);
							vsz_execute_trusted_write( $sql );
						}
						else{
							//If field name not match with current entry then new entry insert in DB
							// write query executed via trusted wrapper
							$sql = $wpdb->prepare("INSERT INTO {$wpdb->prefix}cf7_vdata_entry(`cf7_id`, `data_id`, `name`, `value`) VALUES (%d,%d,%s,%s)", $fid, $rid, sanitize_text_field($key), sanitize_text_field($value));
							vsz_execute_trusted_write( $sql );
						}
					}//Close else
				}//Close foreach
			}//Close if for check field arrray is set or not
		}//Close if for save information

		//Delete form entry here
		if ($current_action = vsz_cf7_current_action()) {
			$current_action = sanitize_text_field($current_action);
			//Check current action is delete then execute this functionality
			if($current_action == 'delete'){
				if(isset($_POST['del_id']) && !empty($_POST['del_id'])){
					//Get nonce value
					if(!isset($_POST['_wpnonce']) || empty($_POST['_wpnonce'])){
						wp_die(esc_html__('Security check failed: Missing nonce.', 'advanced-cf7-db'));
					}
					$nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
					//Verify nonce value
					if(!wp_verify_nonce($nonce, 'vsz-cf7-action-nonce')) {
						wp_die(esc_html__('Security check failed: Invalid nonce.', 'advanced-cf7-db'));
					}
					//Get Delete row ID information
					$del_id = array_map('sanitize_text_field', wp_unslash($_POST['del_id']));
					$del_id = implode(',', array_map('intval',$del_id));
					//Get Form ID
					$fid = intval(sanitize_text_field(wp_unslash($_POST['fid'])));

					//added in 1.8.3
					// Checking for the capability
					$edit_cap = 'cf7_db_form_edit_'.$fid;
					if(!vsz_cf7_check_capability( $edit_cap ) ){
						//Current user does not have edit access
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
						wp_die(esc_html__('You do not have permission to delete files.', 'advanced-cf7-db'));
					}

					// Checking for file type
					$arr_field_type_info = vsz_field_type_info($fid);

					//Get form Id related fields information
					$fields = vsz_cf7_get_db_fields($fid);

					$del_attach_key = array();
					foreach ($fields as $k1 => $v1) {
						if( isset($arr_field_type_info[$k1]) && $arr_field_type_info[$k1] == 'file'){
							$del_attach_key[] = sanitize_text_field($k1);
						}
					}
					
					if(!empty($del_attach_key)){
						$del_attach_key = implode(",",$del_attach_key);
						$res = $this->cached_get_results("SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE FIND_IN_SET(data_id, %s) AND FIND_IN_SET(name, %s)", $del_id, $del_attach_key );

						if(!empty($res)){
							foreach($res as $obj){
								$file_url = $obj->value;
								if(!empty($file_url)){
									//Get upload dir URL
									$upload_dir = wp_upload_dir();
									//Create custom upload folder
									$cf7d_upload_folder = VSZ_CF7_UPLOAD_FOLDER;
									$dir_upload = $upload_dir['basedir'] . '/' . $cf7d_upload_folder;

									$file_path = realpath($dir_upload."/".basename($file_url));
									if(file_exists($file_path) && $file_path && (strpos($file_path, $dir_upload) === 0) ){
										//$ret = unlink($file_path);
										vsz_wp_delete_file( $file_path );
									}
								}
							}
						}
					}
					
					//Delete form ID related row entries from DB
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- deletion with prepared values
					$sql = $wpdb->prepare("DELETE FROM {$wpdb->prefix}cf7_vdata_entry WHERE FIND_IN_SET(data_id, %s)", $del_id);
					vsz_execute_trusted_write( $sql );
					
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- deletion with prepared values
					$sql = $wpdb->prepare("DELETE FROM {$wpdb->prefix}cf7_vdata WHERE FIND_IN_SET(id, %s)", $del_id);
					vsz_execute_trusted_write( $sql );
				}
			}
		}//Close if for delete action

		//Setup export functionality here
		if(isset($_POST['btn_export'])){
			//Verify nonce
			if(!isset($_POST['_wpnonce']) || empty($_POST['_wpnonce'])){
				wp_die(esc_html__('Security check failed: Missing nonce.', 'advanced-cf7-db'));
			}
			$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
			if(!wp_verify_nonce($nonce, 'vsz-cf7-action-nonce')) {
				wp_die(esc_html__('Security check failed: Invalid nonce.', 'advanced-cf7-db'));
			}

			//Get form ID
			$fid = (int)sanitize_text_field(wp_unslash($_POST['fid']));

			//Check capability - user must have view or edit permission for this form
			$view_cap = 'cf7_db_form_view_'.$fid;
			$edit_cap = 'cf7_db_form_edit_'.$fid;
			if(!vsz_cf7_check_capability($view_cap) && !vsz_cf7_check_capability($edit_cap)){
				wp_die(esc_html__('You do not have permission to export this data.', 'advanced-cf7-db'));
			}

			//Get export id related information
			$ids_export = ((isset($_POST['del_id']) && !empty($_POST['del_id'])) ? implode(',', array_map('sanitize_text_field', wp_unslash($_POST['del_id']))) : '');
			$ids_export = ((isset($_POST['del_id']) && !empty($_POST['del_id'])) ? implode(',', array_map('intval', wp_unslash($_POST['del_id']))) : '');
			///Get export type related information
			$type = isset($_POST['vsz-cf7-export']) ? sanitize_text_field(wp_unslash($_POST['vsz-cf7-export'])) : '';
			//Check type name and execute type related CASE
			switch ($type) {
				case 'csv':
					vsz_cf7_export_to_csv($fid, $ids_export);
					break;
				case 'excel':
					vsz_cf7_export_to_excel($fid, $ids_export);
					break;
				case '-1':
					return;
					break;
				default:
					return;
					break;
			}//Close switch
		}//Close if for export
	}//Close admin_init hook function

	//
	/**
	 * Edit form AJAX callback function for the data to be viewed in the form
	 * @param $_POST
	 * rid = recordid
	 * fid = CF7 formid
	 * getEntryNonce = nonce value
	 */
	public function vsz_cf7_edit_form_ajax(){

		global $wpdb;
		$getListNonce = isset($_POST['getListNonce']) ? sanitize_text_field(wp_unslash($_POST['getListNonce'])) : '';
		if(!wp_verify_nonce( $getListNonce, 'vsz-cf7-form-list-nonce' )){
			echo json_encode(esc_html('@@You do not have access to edit the data.'));
			exit;
		}
		//Check entry id set or not in current request
		$rid = ((isset($_POST['rid']) && !empty($_POST['rid'])) ? intval(sanitize_text_field(wp_unslash($_POST['rid']))) : '');
		$fid = ((isset($_POST['fid']) && !empty($_POST['fid'])) ? intval(sanitize_text_field(wp_unslash($_POST['fid']))) : '');
		//added in 1.8.3
		$getEntryNonce = ((isset($_POST['getEntryNonce']) && !empty($_POST['getEntryNonce'])) ? sanitize_text_field(wp_unslash($_POST['getEntryNonce'])) : '');

		if( empty( $rid ) || empty( $fid ) ){
			echo json_encode(esc_html('@@You do not have access to edit the data.'));
			exit;
		}

		//added in 1.8.3
		//verify nonce value here
		if(!wp_verify_nonce($getEntryNonce, 'vsz-cf7-get-entry-nonce-'.$fid)) {
			echo json_encode(esc_html('@@You do not have access to edit the data.'));
			exit;
		}



		// Checking for the capability
		$edit_cap = 'cf7_db_form_edit_'.$fid;
		if( !vsz_cf7_check_capability( $edit_cap ) ){
			echo json_encode(esc_html('@@You do not have access to edit the data for this form.'));
			exit;
		}

		//If entry not empty
			if(!empty($rid)){
			//Get entry related all fields information
			$rows = $this->cached_get_results("SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `data_id` = %d", $rid, 'vsz_cf7', 60 );
			$return = array();
			//Set all fields name in array
			foreach ($rows as $k => $v) {
				$return[$v->name] = stripslashes($v->value);
			}
			//All fields encode in JSON format and return in AJAX request
			exit(json_encode($return));
		}
		
		wp_die();

	}//Close Edit Ajax request function

	/**
	 * Filter 'vsz_cf7_not_editable_fields'
	 * Define not editable fields name here
	 */
	public function vsz_cf7_not_editable_fields_callback(){

		$cf7_not_editable_fields = array('submit_time','submit_ip','submit_user_id');

		return $cf7_not_editable_fields;
	}

	/**
	 * Create table when new site added if the install is multisite
	 */
	public function vsz_cf7_add_new_table_for_sites($blog_id){

		global $wpdb;
		if (is_plugin_active_for_network('advanced-cf7-db/advanced-cf7-db.php')) {
			$old_blog = $wpdb->blogid;
			switch_to_blog($blog_id);
				vsz_cf7_create_table_vdata_add_blog();
				vsz_cf7_create_table_vdata_entry_add_blog();
			switch_to_blog($old_blog);
		}
	}

	/**
	 * Callback function to handle the table creation when plugin updates provided
	 */
	public function vsz_cf7_create_new_table_for_sites(){

		global $vsz_cf7db_current_version;
		global $wpdb;
		$cf7_db_version = '';
		$cf7_db_version = get_option('vsz_cf7_db_version');


		if(isset($cf7_db_version) && empty($cf7_db_version)){
			if (function_exists('is_multisite') && is_multisite()) {
			// check if it is a network activation - if so, run the activation function for each blog id
				 $old_blog = $wpdb->blogid;
				// Get all blog ids
				// multisite blog list required for activation; execute via trusted wrapper
				$blogids = vsz_execute_trusted_col( "SELECT blog_id FROM $wpdb->blogs" );
				foreach ($blogids as $blog_id) {
					switch_to_blog($blog_id);

					vsz_cf7_create_table_vdata_add_blog();
					vsz_cf7_create_table_vdata_entry_add_blog();
				}
				switch_to_blog($old_blog);
			}else{
					vsz_cf7_create_table_vdata_add_blog();
					vsz_cf7_create_table_vdata_entry_add_blog();
			}

			 update_option('vsz_cf7_db_version',$vsz_cf7db_current_version);
			// Add Capability when update plugin
			$role = get_role( 'administrator');
			$args = array('post_type' => 'wpcf7_contact_form', 'posts_per_page' => -1);
			$cf7Forms = get_posts( $args );
			//added in 1.8.4
			if(!empty($cf7Forms)){
				foreach($cf7Forms as $data){
					$role->add_cap('cf7_db_form_view'.$data->ID);
					$role->add_cap('cf7_db_form_edit_'.$data->ID);
				}
			}


		}
		else if($cf7_db_version != $vsz_cf7db_current_version){

			if (function_exists('is_multisite') && is_multisite()) {
				// check if it is a network activation - if so, run the activation function for each blog id
				$old_blog = $wpdb->blogid;
				// Get all blog ids
				// multisite blog list required for activation; execute via trusted wrapper
				$blogids = vsz_execute_trusted_col( "SELECT blog_id FROM $wpdb->blogs" );
				//added in 1.8.4
				if(!empty($blogids)){
					foreach ($blogids as $blog_id) {
						switch_to_blog($blog_id);
						vsz_cf7_create_table_vdata_add_blog();
						vsz_cf7_create_table_vdata_entry_add_blog();
					}
				}
				switch_to_blog($old_blog);

			}
			else{
				//create new table entries here
				vsz_cf7_create_table_vdata_add_blog();
				vsz_cf7_create_table_vdata_entry_add_blog();
			}

			// Add Capability when update plugin
			$role = get_role( 'administrator');
			$args = array('post_type' => 'wpcf7_contact_form', 'posts_per_page' => -1);
			$cf7Forms = get_posts( $args );
			//added in 1.8.4
			if(!empty($cf7Forms)){
				foreach($cf7Forms as $data){
					$role->add_cap('cf7_db_form_view'.$data->ID);
					$role->add_cap('cf7_db_form_edit_'.$data->ID);
				}
			}

		   update_option('vsz_cf7_db_version',$vsz_cf7db_current_version);

		}
		//check current request from admin side
		if(is_admin()){
			// remove all capability assign to role or user
			do_action('test_test',wp_get_current_user());
			$vsz_cf7_capability = get_option('vsz_cf7_capability',array());
			if(isset($vsz_cf7_capability) && !empty($vsz_cf7_capability)){
				foreach($vsz_cf7_capability as $retrive_data){
					cf7_db_capability($retrive_data['type'],$retrive_data['type_value'],$retrive_data['contact_form'],$retrive_data['access'],'remove');
				}
				update_option("vsz_cf7_capability",'');
			}
		}
		add_shortcode( 'cf7-db-display-ip',array($this,'cf7_db_vsz_cf7_display_ip'));

	}

	/**
	 * Short code execution related to hide the submit ip
	 */
	function cf7_db_vsz_cf7_display_ip($atts, $content, $name){

		if(is_multisite()){
			$arrInfo = shortcode_atts( array( 'site_id' => ''),$atts );
			$check_id = sanitize_text_field($arrInfo['site_id']);
			$current_id = get_current_blog_id();

			if(!empty($check_id)){
				if($current_id == $check_id){
					define('vsz_cf7_display_ip',true);
				}
			}
			else{
				define('vsz_cf7_display_ip',true);
			}
		}
		else{
			define('vsz_cf7_display_ip',true);
		}

	}

	// Create role and assign capability for user
	public function vsz_cf7_create_role_for_contact_form($post_id){

		$post_id = intval($post_id);
		$post_type = get_post_type($post_id);
		if($post_type != 'wpcf7_contact_form'){
			return;
		}
		$role = get_role( 'administrator');
		$role->add_cap('cf7_db_form_view'.$post_id);
		$role->add_cap('cf7_db_form_edit_'.$post_id);

	}

	// Function to upload file from edit file popup
	function vsz_acf7_db_edit_scr_file_upload(){

		//added in 1.8.3
		// check nonce
		if(!isset($_POST['vsz_cf7_edit_nonce']) || empty($_POST['vsz_cf7_edit_nonce'])){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(esc_html__('Something may be wrong, please try again later.', 'advanced-cf7-db'));
			/* phpcs:enable */
			exit;
		}

		if(!isset($_POST["fid"]) || empty($_POST["fid"])){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(esc_html__('Something may be wrong, please try again later.', 'advanced-cf7-db'));
			/* phpcs:enable */
			exit;
		}

		$fid = (int)sanitize_text_field(wp_unslash($_POST["fid"]));

		//Verify nonce value
		$nonce = isset($_POST['vsz_cf7_edit_nonce']) ? sanitize_text_field(wp_unslash($_POST['vsz_cf7_edit_nonce'])) : '';
		if(!wp_verify_nonce( $nonce, 'vsz-cf7-edit-nonce-'.$fid)){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(esc_html__('Something may be wrong, please try again later.', 'advanced-cf7-db'));
			/* phpcs:enable */
			exit;
		}

		// Verify the current user can upload or delete files
		//added in 1.8.3
		// Checking for the capability
		$edit_cap = 'cf7_db_form_edit_'.$fid;
		if(!vsz_cf7_check_capability( $edit_cap ) ){
			//Current user does not have edit access
            /* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(esc_html__('You do not have permission to upload files.', 'advanced-cf7-db'));
			/* phpcs:enable */
			exit;
		}

		if(isset($_FILES) && is_array($_FILES) && empty($_FILES)){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(esc_html__('Something may be wrong, please try again later.', 'advanced-cf7-db'));
			/* phpcs:enable */
			exit;
		}

		if(!isset($_POST["rid"]) || empty($_POST["rid"])){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(esc_html__('Something may be wrong, please try again later.', 'advanced-cf7-db'));
            /* phpcs:enable */
			exit;
		}
		if(!isset($_POST["field"]) || empty($_POST["field"])){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(__('Something may be wrong, please try again later.', 'advanced-cf7-db'));
            /* phpcs:enable */
			exit;
		}
		if(!isset($_FILES['image']['name']) || empty($_FILES['image']['name'])){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(esc_html__('No file uploaded.', 'advanced-cf7-db'));
			/* phpcs:enable */
			exit;
		}
		$fileInfo = wp_check_filetype(basename(sanitize_file_name(wp_unslash($_FILES['image']['name']))));
		if(empty($fileInfo['ext'])){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(esc_html__('Kindly upload valid file type.', 'advanced-cf7-db'));
            /* phpcs:enable */
			exit;
		}

		global $wpdb;
		$rid = (int)sanitize_text_field(wp_unslash($_POST["rid"]));
		$field = sanitize_text_field(wp_unslash($_POST["field"]));

		// Prepare upload directory and plugin upload folder
		$upload_dir = wp_upload_dir();
		$acf7db_upload_folder = defined('VSZ_CF7_UPLOAD_FOLDER') ? VSZ_CF7_UPLOAD_FOLDER : 'advanced-cf7-upload';
		$temp_dir_upload = $upload_dir['basedir'].'/'.$acf7db_upload_folder;
		wp_mkdir_p($temp_dir_upload);

		if(is_array($_FILES) && !empty($_FILES)){

			//verify file size here
			$maxsize = 8000000;
			if(!isset($_FILES['image']['size']) || ($_FILES['image']['size'] >= $maxsize) || empty($_FILES['image']['size'])) {
				/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
				print esc_html('error@~@');
				wp_die(esc_html__('You can upload maximum 7.60 MB file.', 'advanced-cf7-db'));
				/* phpcs:enable */
				exit;
			}
			if(!isset($_FILES["image"]["name"])){
				/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
				print esc_html('error@~@');
				wp_die(esc_html__('No file uploaded.', 'advanced-cf7-db'));
				/* phpcs:enable */
				exit;
			}
			$filename = sanitize_file_name(wp_unslash($_FILES["image"]["name"]));
			$file_basename = pathinfo($filename, PATHINFO_FILENAME);
			$file_ext = '.' . pathinfo($filename, PATHINFO_EXTENSION);

			$validExtArray = array( 'jpg','jpeg','png','gif','pdf','doc','docx','ppt','pptx','odt','avi','ogg','m4a','mov','mp3','mp4','mpg','wav','wmv');
			$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
			if(!in_array($ext,$validExtArray)){
				/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
				print esc_html('error@~@');
				wp_die(esc_html__('Kindly upload valid file type.', 'advanced-cf7-db'));
				/* phpcs:enable */
				exit;
			}

			// Use WordPress API to handle upload
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$overrides = array('test_form' => false);
			$movefile = wp_handle_upload($_FILES['image'], $overrides);

			if ( isset($movefile['error']) ) {
				/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
				print esc_html('error@~@');
				wp_die( esc_html($movefile['error']) );
				/* phpcs:enable */
				exit;
			}

			// unique filename in plugin folder
			$newfilename = wp_unique_filename( $temp_dir_upload, $file_basename . $file_ext );

			WP_Filesystem();
			global $wp_filesystem;

			if ( $wp_filesystem->move( $movefile['file'], $temp_dir_upload . '/' . $newfilename ) ) {
				$file_url = esc_url_raw( $upload_dir['baseurl'] . '/' . $acf7db_upload_folder . '/' . $newfilename );
				
				$sql = $wpdb->prepare("UPDATE {$wpdb->prefix}cf7_vdata_entry SET `value` = %s WHERE `data_id` = %d AND `cf7_id` = %d AND `name` = %s", $file_url, $rid, $fid, $field);
				$res = vsz_execute_trusted_write( $sql );
				if($res !== false){
					print esc_html('success@~@');
					echo esc_html("$newfilename");
					echo "~~@@~~&&~~";
					echo esc_url("$file_url");
					wp_die();
					exit;
				}
				else{
					/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
					print esc_html('error@~@');
					wp_die(esc_html__('File is not uploaded, kindly try again later.', 'advanced-cf7-db'));
					/* phpcs:enable */
					exit;
				}
			}
		}
		wp_die();
	}

	/**
	 * Callback function to delet the uploaded images in CF7DB
	 * Function to delete file from edit file popup
	 */
	function vsz_acf7_db_edit_scr_file_delete(){

		if(!isset($_POST["vsz_cf7_edit_nonce"]) || empty($_POST["vsz_cf7_edit_nonce"])){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(esc_html__('Something may be wrong, please try again later.', 'advanced-cf7-db'));
			/* phpcs:enable */
			exit;
		}

		if(!isset($_POST["fid"]) || empty($_POST["fid"])){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(esc_html__('Something may be wrong, please try again later.', 'advanced-cf7-db'));
			/* phpcs:enable */
			exit;
		}

		//get current form id here
		$fid = (int)sanitize_text_field(wp_unslash($_POST["fid"]));

		//Verify nonce value
		////add in 1.8.3
		$nonce = isset($_POST['vsz_cf7_edit_nonce']) ? sanitize_text_field(wp_unslash($_POST['vsz_cf7_edit_nonce'])) : '';
		if(!wp_verify_nonce( $nonce, 'vsz-cf7-edit-nonce-'.$fid)){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(__('Something may be wrong, please try again later.', 'advanced-cf7-db'));
			/* phpcs:enable */
			exit;
		}
		// Verify the current user can upload or delete files
		//added in 1.8.3
		// Checking for the capability
		$edit_cap = 'cf7_db_form_edit_'.$fid;
		if(!vsz_cf7_check_capability( $edit_cap ) ){
			//Current user does not have edit access
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(__('You do not have permission to delete files.', 'advanced-cf7-db'));
			/* phpcs:enable */
			exit;
		}

		if(!isset($_POST["rid"]) || empty($_POST["rid"])){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(__('Something may be wrong, please try again later.', 'advanced-cf7-db'));
			/* phpcs:enable */
			exit;
		}
		if(!isset($_POST["field"]) || empty($_POST["field"])){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(__('Something may be wrong, please try again later.', 'advanced-cf7-db'));
			/* phpcs:enable */
			exit;
		}
		if(!isset($_POST["val"]) || empty($_POST["val"])){
			/* phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped */
			print esc_html('error@~@');
			wp_die(__('Something may be wrong, please try again later.', 'advanced-cf7-db'));
			/* phpcs:enable */
			exit;
		}


		$rid = (int)sanitize_text_field(wp_unslash($_POST["rid"]));
		$field = sanitize_text_field(wp_unslash($_POST["field"]));
		$val = sanitize_text_field(wp_unslash($_POST["val"]));
		global $wpdb;

		$sql = $wpdb->prepare("UPDATE {$wpdb->prefix}cf7_vdata_entry SET `value` = %s WHERE `data_id` = %d AND `cf7_id` = %d AND `name` = %s", '', $rid, $fid, $field);
		$res = vsz_execute_trusted_write( $sql );
		if($res !== false){
			$upload_dir = wp_upload_dir();
			$dir_upload = $upload_dir['basedir'] .'/' .VSZ_CF7_UPLOAD_FOLDER;

			$delete_path = path_join($dir_upload,$val);

			$realDeletePath = realpath($delete_path);
			if(file_exists($realDeletePath) && $realDeletePath && (strpos($realDeletePath, $dir_upload) === 0)){
				vsz_wp_delete_file( $realDeletePath );

				echo esc_html("y");
			}else{
				echo esc_html("n");
			}

			wp_die();
		}
		else{
			echo esc_html("n");
			wp_die();
		}
	}

}//close class

/**
 * Generate CSV file here
 */
function vsz_cf7_export_to_csv($fid, $ids_export = ''){

	global $wpdb;

	if(!isset($_POST['_wpnonce']) || (isset($_POST['_wpnonce']) && empty($_POST['_wpnonce']))){
		return esc_html('You do not have the permission to export the data');
	}

	//Get nonce value
	$nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
	//Verify nonce value
	if(!wp_verify_nonce($nonce, 'vsz-cf7-action-nonce')) {
		return esc_html('You do not have the permission to export the data');
	}


	$fid = intval($fid);
    if( empty( $fid ) ){
    	return esc_html('You do not have the permission to export the data');
    }

	//Check capability - user must have view or edit permission for this form
	$view_cap = 'cf7_db_form_view_'.$fid;
	$edit_cap = 'cf7_db_form_edit_'.$fid;
	if(!vsz_cf7_check_capability($view_cap) && !vsz_cf7_check_capability($edit_cap)){
		return esc_html('You do not have permission to export this data.');
	}
    $fields = vsz_cf7_get_db_fields($fid);

	//Get form id related contact form object
	$obj_form = vsz_cf7_get_the_form_list($fid);
	//get current form title
	$form_title = esc_html($obj_form[0]->title());
	//Get export data
	$data = vsz_cf7_create_export_query($fid, $ids_export, 'data_id desc');
	
	if(!empty($data)){
		//Setup export data
		$data_sorted = wp_unslash(vsz_cf7_sortdata($data));
        
		// DEBUG: detect and log any stray output buffered before CSV — this is the blank row source
		$stray_output = '';
		while ( ob_get_level() > 0 ) {
			$stray_output .= ob_get_clean();
		}
		if ( ! empty( trim( $stray_output ) ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'CF7 CSV stray buffer content (causing blank rows): ' . substr( $stray_output, 0, 1000 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
		} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'CF7 CSV buffer was clean (' . strlen( $stray_output ) . ' bytes whitespace-only)' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
		}
        
			//Generate CSV file
			header('Content-Type: text/csv; charset=UTF-8');
			header('Content-Disposition: attachment;filename="'.$form_title.'.csv";');
			$fp = fopen('php://output', 'w'); // @codingStandardsIgnoreLine
			fputs($fp, "\xEF\xBB\xBF"); // @codingStandardsIgnoreLine
	        // Neutralize header labels as well to avoid formula-like headers
	        $csv_headers = array();
	        foreach ( array_values(array_map('sanitize_text_field',$fields)) as $h ) {
				$csv_headers[] = vsz_cf7_neutralize_export_cell( $h );
			}
	        fputcsv($fp, $csv_headers);

			foreach ($data_sorted as $k => $v){
				$temp_value = array();
				foreach ($fields as $k2 => $v2){
					$raw = (isset($v[$k2])) ? $v[$k2] : '';
					// decode and neutralize
					$cell = vsz_cf7_neutralize_export_cell( $raw );
					$temp_value[] = $cell;
				}

				fputcsv($fp, $temp_value);
			}

		fclose($fp); // @codingStandardsIgnoreLine
		exit();
	}
}
/**
 * Generate excel file here
 */
function vsz_cf7_export_to_excel($fid, $ids_export){

	global $wpdb;
	include_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/libraries/excel/xls/vendor/autoload.php';
    
	//Verify nonce
	if(!isset($_POST['_wpnonce']) || empty($_POST['_wpnonce'])){
		return 'You do not have the permission to export the data';
	}
	$nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
	if(!wp_verify_nonce($nonce, 'vsz-cf7-action-nonce')) {
		return 'You do not have the permission to export the data';
	}

	$fid = intval($fid);
	if( empty( $fid ) ){
    	return 'You do not have the permission to export the data';
    }

	//Check capability - user must have view or edit permission for this form
	$view_cap = 'cf7_db_form_view_'.$fid;
	$edit_cap = 'cf7_db_form_edit_'.$fid;
	if(!vsz_cf7_check_capability($view_cap) && !vsz_cf7_check_capability($edit_cap)){
		return 'You do not have permission to export this data.';
	}
    $fields = vsz_cf7_get_db_fields($fid);
    $fields1 = vsz_field_type_info($fid);

	//Get form id related contact form object
	$obj_form = vsz_cf7_get_the_form_list($fid);
	//get current form title
	$form_title = esc_html($obj_form[0]->title());
	$timeStamp = gmdate('Ymdhis'); 
	$form_title = preg_replace('/\s+/', '_', $form_title);
	$docName = $form_title."-".$timeStamp;

	//Get export data
	$data = vsz_cf7_create_export_query($fid, $ids_export, 'data_id desc');
	
	if(!empty($data)){

		$data_sorted = wp_unslash(vsz_cf7_sortdata($data));

		$arrHeader = array_values(array_map('sanitize_text_field',$fields));

		if(VSZ_CF7_PHPSPREADSHEET_CHECK == true){
        	
			// DEBUG: detect and log stray buffered output before flushing
			$stray = '';
			while ( ob_get_level() > 0 ) {
				$stray .= ob_get_clean();
			}
			if ( strlen( $stray ) > 0 ) {
				$hex = implode( ' ', array_map( fn($b) => sprintf('%02X', ord($b)), str_split($stray) ) );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'CF7 XLS — stray buffer (' . strlen($stray) . ' bytes) HEX=[' . $hex . ']' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'CF7 XLS — buffer was empty, no stray output' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}

			// Flush any stray buffered output (e.g. BOM from included files) to prevent blank rows
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
        
			// xls sheet
			/* $spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet(); */
            
			// Use PhpSpreadsheet to create XLSX output
			$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();

			// Neutralize header labels to avoid formula-like headers
			$normalized_headers = array();
			foreach ( $arrHeader as $h ) {
				$normalized_headers[] = vsz_cf7_neutralize_export_cell( $h );
			}

			// Write header row
			$sheet->fromArray( $normalized_headers, null, 'A1' );

			//First we will set header in excel file
			$col = 1;
			$row = 2;
			/* foreach($arrHeader as $colName){

				$sheet->setCellValueByColumnAndRow($col, $row, $colName);
				$col++;
			} */

			$row = 2;
			foreach ($data_sorted as $k => $v){
				$data = [];
                
				//Define column index here
				$col=1;
				//Get column order wise value here
				foreach ($fields as $k2 => $v2){
					
					//$colVal = (isset($v[$k2]) ? html_entity_decode($v[$k2]) : '');
					//$sheet->setCellValueByColumnAndRow($col, $row, $colVal);
                    
					$raw = isset($v[$k2]) ? $v[$k2] : '';
					// Neutralize and normalize encoding
					$cell = vsz_cf7_neutralize_export_cell( $raw );
					$cell = mb_convert_encoding( $cell, 'UTF-8', 'UTF-8' );
					$data[] = $cell;
                    
					$col++;
				}
				//Consider new row for each entry here
				// Write the row starting at column A and current row
				$sheet->fromArray( $data, null, 'A' . $row );
				$row++;
			}
			
            // xls file writer
			
            /* $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, "Xls");

			header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
	        header('Content-Disposition: attachment; filename="'. urlencode($docName.'.xls').'"');
			$writer->save( 'php://output'); */
            
			// xlsx file writer via PhpSpreadsheet
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment; filename="' . urlencode( $docName . '.xlsx' ) . '"');
			header('Cache-Control: max-age=0');
			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
			$writer->save( 'php://output' );
			exit;
		}
	}
}


//Setup export related query here
function vsz_cf7_create_export_query($fid,$ids_export,$cf7d_entry_order_by){

	global $wpdb;
	$fid = intval($fid);

	//Note: Nonce and capability checks are now performed before calling this function

	// phpcs:ignore
	if(isset($_POST['start_date']) && isset($_POST['end_date']) && !empty($_POST['start_date']) && !empty($_POST['end_date'])){
		// phpcs:ignore
		$s_date = date_create_from_format("d/m/Y",sanitize_text_field(wp_unslash($_POST['start_date'])));
		// phpcs:ignore
		$e_date = date_create_from_format("d/m/Y",sanitize_text_field(wp_unslash($_POST['end_date'])));
	}
	else{
		$s_date = false;
		$e_date = false;
	}

	//added in 1.8.3
	if(!empty($ids_export)){
		$ids_export = implode(',',array_map('intval',explode(',',$ids_export)));
	}

	$cf7d_entry_order_by = sanitize_sql_orderby($cf7d_entry_order_by);
	//Get table name for data entry
	$table_name = sanitize_text_field(VSZ_CF7_DATA_ENTRY_TABLE_NAME);

	//Create Export Query on the basis of Listing screen filter
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	//Check any search related filter active or not
	if(isset($_POST['search_cf7_value']) && !empty($_POST['search_cf7_value']) && isset($_POST['start_date']) && isset($_POST['end_date']) && empty($_POST['start_date']) && empty($_POST['end_date'])){

		$search = sanitize_text_field(wp_unslash($_POST['search_cf7_value']));

		if(!empty($search) && !empty($ids_export)){
			// Build a fully-sanitized SQL string (no placeholders) and use the cached helper.
			$fid_i = (int) $fid;
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$like_esc = esc_sql( $like );
			$ids_export_safe = $ids_export; // already sanitized above when non-empty
			$sql = "SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = {$fid_i} AND data_id IN(SELECT * FROM (SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = {$fid_i} AND `value` LIKE '{$like_esc}' AND FIND_IN_SET(data_id, '{$ids_export_safe}') GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} ) temp_table) ORDER BY {$cf7d_entry_order_by}";
			$query = vsz_cached_get_results( $sql );
		}else if(!empty($search) && empty($ids_export)){
			$fid_i = (int) $fid;
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$like_esc = esc_sql( $like );
			$sql = "SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = {$fid_i} AND data_id IN(SELECT * FROM (SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = {$fid_i} AND `value` LIKE '{$like_esc}'  GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} ) temp_table) ORDER BY {$cf7d_entry_order_by}";
			$query = vsz_cached_get_results( $sql );
		}
	}
	//Check date wise filter active or not
	else if(isset($_POST['search_cf7_value']) && empty($_POST['search_cf7_value']) && isset($_POST['start_date']) && isset($_POST['end_date']) && !empty($_POST['start_date']) && !empty($_POST['end_date']) && $s_date !== false && $e_date !== false){

		//Get start date information
		$start_date =  date_format($s_date,"Y-m-d");

		//Get end date information
		$end_date =  date_format($e_date,"Y-m-d")." 23:59:59";

		if(!empty($ids_export)){
			$fid_i = (int) $fid;
			$ids_export_safe = $ids_export;
			$start_esc = esc_sql( $start_date );
			$end_esc = esc_sql( $end_date );
			$sql = "SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = {$fid_i} AND data_id IN( SELECT * FROM ( SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = {$fid_i} AND `name` = 'submit_time' AND value between '{$start_esc}' and '{$end_esc}' AND FIND_IN_SET(data_id , '{$ids_export_safe}') GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} ) temp_table) ORDER BY {$cf7d_entry_order_by}";
			$query = vsz_cached_get_results( $sql );
		}else if(empty($ids_export)){
			$fid_i = (int) $fid;
			$start_esc = esc_sql( $start_date );
			$end_esc = esc_sql( $end_date );
			$sql = "SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = {$fid_i} AND data_id IN( SELECT * FROM ( SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = {$fid_i} AND `name` = 'submit_time' AND value between '{$start_esc}' and '{$end_esc}' GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} ) temp_table) ORDER BY {$cf7d_entry_order_by}";
			$query = vsz_cached_get_results( $sql );
		}

	}
	//Check search and date wise filter active or not
	else if(isset($_POST['search_cf7_value']) && !empty($_POST['search_cf7_value']) && isset($_POST['start_date']) && isset($_POST['end_date']) && !empty($_POST['start_date']) && !empty($_POST['end_date']) && $s_date !== false && $e_date !== false){

		$search = sanitize_text_field(wp_unslash($_POST['search_cf7_value']));

		//Get start date information
		$start_date =  date_format($s_date,"Y-m-d");

		//Get end date information
		$end_date =  date_format($e_date,"Y-m-d").' 23:59:59';

		$fid_i = (int) $fid;
		$start_esc = esc_sql( $start_date );
		$end_esc = esc_sql( $end_date );
		$date_sql = "SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = {$fid_i} AND `name` = 'submit_time' AND value between '{$start_esc}' and '{$end_esc}' GROUP BY `data_id` ORDER BY `data_id` DESC";
		$date_query = vsz_cached_get_results( $date_sql, 'vsz_cf7', 120 );

		//print $date_query;
		$rs_date = $date_query;
		$data_ids = '';
		if(!empty($rs_date)){
			foreach($rs_date as $objdata_id){
				if(!empty($ids_export)){
					$arr_ids = array_map('intval',explode(',',$ids_export));
					if(!empty($arr_ids) && in_array($objdata_id->data_id,$arr_ids)){
						$data_ids .= $objdata_id->data_id .',';
					}
				}
				else{
					$data_ids .= $objdata_id->data_id .',';
				}
			}
			$data_ids = rtrim($data_ids,',');
		}

		$fid_i = (int) $fid;
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		$like_esc = esc_sql( $like );
		$data_ids_safe = $data_ids;
		$sql = "SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = {$fid_i} AND data_id IN( SELECT * FROM ( SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = {$fid_i} AND `value` LIKE '{$like_esc}' AND FIND_IN_SET(data_id, '{$data_ids_safe}') GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by}) temp_table) ORDER BY {$cf7d_entry_order_by}";
		$query = vsz_cached_get_results( $sql );

	}
	//Not active any filter on listing screen
	else{

		if(!empty($ids_export)){

			$fid_i = (int) $fid;
			$ids_export_safe = $ids_export;
			$sql = "SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = {$fid_i} AND data_id IN( SELECT * FROM ( SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = {$fid_i} AND FIND_IN_SET(data_id, '{$ids_export_safe}') GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} ) temp_table) ORDER BY {$cf7d_entry_order_by}";
			$query = vsz_cached_get_results( $sql );

		}else{

			$fid_i = (int) $fid;
			$sql = "SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = {$fid_i} AND data_id IN( SELECT * FROM ( SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = {$fid_i} GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} ) temp_table) ORDER BY {$cf7d_entry_order_by}";
			$query = vsz_cached_get_results( $sql );

		}

	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	//Execuste query
	$data = $query;

	//Return result set
	return  $data;
}//Close export query function


// Create table when new site added.
/**
 * Neutralize cells for CSV/XLSX export to prevent formula injection.
 * Prefixes cells starting with = + - @ or tab/carriage-return with a single quote.
 */
if ( ! function_exists( 'vsz_cf7_neutralize_export_cell' ) ) {
	function vsz_cf7_neutralize_export_cell( $cell ) {
		if ( $cell === null ) {
			return '';
		}
		$cell = (string) $cell;
		$decoded = html_entity_decode( $cell, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$first = mb_substr( $decoded, 0, 1 );
		if ( $decoded !== '' && in_array( $first, array('=', '+', '-', '@', "\t", "\r"), true ) ) {
			return "'" . $decoded;
		}
		return $decoded;
	}
}
/**
 * Contact Form entry table created from here
 */

// Check user capability
/**
 * Change log : 18-07-2019
 * Taken by : @Jaydeep
 * Summary : Added the multisite check
 */
function vsz_cf7_check_capability($user_capability){

	if(is_super_admin() && is_multisite()){
		return true;
	}
	$user_id = get_current_user_id();
	$subject = new WP_User($user_id);
	$allcaps= $subject->allcaps;
	$caps = $subject->caps;

	$caps    = array_merge(wp_get_current_user()->caps, $caps);
	$allcaps = array_merge(wp_get_current_user()->allcaps, $allcaps);

	if (wp_get_current_user()->ID == $subject->ID) {
		wp_get_current_user()->allcaps = $allcaps;
		wp_get_current_user()->caps    = $caps;
	}
	$user = wp_get_current_user();
	foreach($user->allcaps as $key=>$capability){
		if($capability == true){
			if($key == $user_capability){
				return true;
			}
		}
	}
	return false;
}
