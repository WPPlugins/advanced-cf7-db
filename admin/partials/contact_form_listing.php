<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
	die('Un-authorized access!');
}


/**
 * Detect plugin. For use in Admin area only.
 */
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

//Check contact form class exist or not
if(!is_plugin_active('contact-form-7/wp-contact-form-7.php')){
	?><div class="notice error is-dismissible">
		<p><?php esc_html_e('Please activate Contact Form plugin first.','advanced-cf7-db'); ?></p>
	</div><?php
}
else if(defined('WPCF7_VERSION') && WPCF7_VERSION < '4.6'){
	?><div class="notice error is-dismissible">
		<p><?php esc_html_e('Please update latest version for Contact Form plugin first.','advanced-cf7-db'); ?></p>
	</div><?php
}
else{

	//enqueue date time picker CSS in file
	wp_enqueue_style('vsz-cf7-datetimepicker-css');

	wp_enqueue_style('vsz-cf7-font-awesome-css');

	//enqueue Sortable JS in file
	wp_enqueue_script('jquery-ui-sortable');

	wp_enqueue_script('vsz-cf7-db-admin-js');
	wp_enqueue_script('vsz-cf7-datetimepicker-js');

	//Get all existing contact form list
	$form_list = vsz_cf7_get_the_form_list();
	$url = '';
	$fid = '';
	
	$nonce = wp_create_nonce('vsz-cf7-action-nonce');

	if(!wp_verify_nonce( $nonce, 'vsz-cf7-action-nonce')){
		echo esc_html('You have no permission to access this page');
		return;
	}
	
	//Get selected form Id value
	if(isset($_GET['cf7_id']) && !empty($_GET['cf7_id'])){
		$edit = false;
		$entry_actions = array();
		$fid = intval(sanitize_text_field(wp_unslash($_GET['cf7_id'])));
		if (!vsz_cf7_check_capability('cf7_db_form_view'.$fid) && !vsz_cf7_check_capability('cf7_db_form_edit_'.$fid)){
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			wp_die( __('You do not have sufficient permissions to access this page.', 'advanced-cf7-db') );
		}
		if(vsz_cf7_check_capability('cf7_db_form_edit_'.$fid)){
			$edit = true;
			$entry_actions = array(
				'delete' => 'Delete'
			);
		}

		$lang = isset($_GET['lang']) ? sanitize_text_field( wp_unslash( $_GET['lang'] ) ) : '';
		$translated_id = apply_filters('wpml_object_id', $fid, 'wpcf7_contact_form', false, $lang);
		$menu_url = menu_page_url('contact-form-listing',false);
		if ($translated_id != $fid) {
			$fid = '';
			$url = $menu_url;
		} else {
			$fid = $translated_id;
			$url = $menu_url . '&cf7_id=' . $fid;
		}
	}

	//Get search related value
	$search =  '';
	if(isset($_POST['search_cf7_value']) && !empty($_POST['search_cf7_value'])){
		$search = addslashes(addslashes(htmlspecialchars(sanitize_text_field(wp_unslash($_POST['search_cf7_value'])))));
	}

	//Get all form names which entry store in DB
	global $wpdb;
	//Get table name for data entry
	$data_entry_table_name = sanitize_text_field(VSZ_CF7_DATA_ENTRY_TABLE_NAME);

	$data = vsz_cached_get_results("SELECT `cf7_id` FROM {$wpdb->prefix}cf7_vdata_entry GROUP BY `cf7_id`", 'vsz_cf7', 300 );
	$arr_form_id = array();
	if(!empty($data)){
		foreach($data as $row){
			$arr_form_id[] = (int)$row->cf7_id;
		}
	}

	?><div class="wrap">
		<h2><?php
			esc_html_e('View Form Information','advanced-cf7-db');
		?></h2>
		<div class="notice notice-info inline">
			<p><strong><?php esc_html_e('Note:','advanced-cf7-db'); ?></strong> <?php esc_html_e('The frontend entry display shortcode [acf7db] has been removed from version 2.1.6. It is now available in the User Access Manager add-on.','advanced-cf7-db'); ?></p>
		</div>
	</div>
	<div class="wrap select-specific">
		<table class="form-table inner-row">
			<tr class="form-field form-required select-form">
				<th><?php esc_html_e('Select Form name','advanced-cf7-db');  ?></th>
				<td>
					<form name="cf7_name" id="cf7_name" action="<?php menu_page_url('contact-form-listing');?>" method="">
						<select name="cf7_id" id="cf7_id" onchange="submit_cf7()">
							<option value=""><?php esc_html_e('Select Form name','advanced-cf7-db');  ?></option><?php
							//Display all existing form list here
							$exist_entry_flag = false;
							if(!empty($form_list)){

								foreach($form_list as $objForm){
									if(!empty($arr_form_id) && in_array($objForm->id(),$arr_form_id)){
										$exist_entry_flag = true;
										if (vsz_cf7_check_capability('cf7_db_form_view'.$objForm->id()) || vsz_cf7_check_capability('cf7_db_form_edit_'.$objForm->id()) ){
											if(!empty($fid) && $fid === $objForm->id())
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
												print '<option value="'.$objForm->id().'" selected>'.esc_html($objForm->title()).'</option>';
											else
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
												print '<option value="'.$objForm->id().'" >'.esc_html($objForm->title()).'</option>';
										}
									}//Close if
								}//close for each
							}//close if
						?></select>
					</form>
				</td>
			</tr>
		</table>
	</div><?php

	//Get form Id related fields information
	$fields = vsz_cf7_get_db_fields($fid);

	//Check contact form id set or not
	if (!empty($fid) && empty($fields)){
		?>
		<div class="wrap our-class">
			<form class="vsz-cf7-listing row" action="<?php print esc_url($url);?>" method="post" id="cf7d-admin-action-frm" >
				<input type="hidden" name="_wpnonce" value="<?php echo esc_html($nonce); ?>">
				<?php
				//Display setting screen button
				do_action('vsz_cf7_display_settings_btn', $fid);
				?>
			</form>
		</div>
		<?php
		//Define setting and Edit popup call back function
		do_action('vsz_cf7_after_admin_form',$fid);
	}else if (!empty($fid) && !empty($fields)){

		// Make sure that $fid will contain integer value only.
		$fid = (int) $fid;

		//Add filter for ordering in entry
		$cf7d_entry_order_by = (string) apply_filters('vsz_cf7_entry_order_by', '`data_id` DESC');
		$cf7d_entry_order_by = sanitize_sql_orderby($cf7d_entry_order_by);

		$start_date = '';
		$end_date = '';
		$search_date_query = '';

		//Get post per page value from general setting screen
		$show_record = '';
		$show_record = get_option('vsz_cf7_settings_show_record_' . $fid, array());
		if(empty($show_record)){
			$show_record = 10;
		}
		$posts_per_page = $show_record;
		//Add post per page filter here , Any user call this filter and customize post per page count
		$items_per_page = (int)apply_filters('vsz_cf7_entry_per_page', (!empty($posts_per_page) ? $posts_per_page : 10));
		//Get current page information from  query
		$page = isset($_POST['cpage']) && !empty($_POST['cpage']) ? abs((int)sanitize_text_field(wp_unslash($_POST['cpage']))) : 1;
		//Setup offset related value here
		$offset = (int)( $page * $items_per_page ) - $items_per_page;
		//Customize parameter wise listing screen query

		//Check start and end date is valid or not
		if(isset($_POST['start_date']) && isset($_POST['end_date']) && !empty($_POST['start_date']) && !empty($_POST['end_date'])){
			$s_date = date_create_from_format("d/m/Y",sanitize_text_field(wp_unslash($_POST['start_date'])));
			$e_date = date_create_from_format("d/m/Y",sanitize_text_field(wp_unslash($_POST['end_date'])));
		}
		else{
			$s_date = false;
			$e_date = false;
		}

		//Check search field value empty or not
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if(isset($_POST['search_cf7_value']) && !empty($_POST['search_cf7_value']) && isset($_POST['start_date']) && isset($_POST['end_date']) && empty($_POST['start_date']) && empty($_POST['end_date'])){

			$query = $this->cached_get_results("SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = %d AND data_id IN( SELECT * FROM ( SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = %d AND `value` LIKE %s GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} LIMIT %d,%d ) temp_table) ORDER BY {$cf7d_entry_order_by}", $fid, $fid, '%' . $wpdb->esc_like($search) . '%', $offset, $items_per_page, 'vsz_cf7', 300 );

			$arr_total = $this->cached_get_results("SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = %d AND `value` LIKE %s GROUP BY `data_id`", $fid, '%' . $wpdb->esc_like($search) . '%', 'vsz_cf7', 300 );
		}
		//Check search field value empty and date filter active or not
		else if(isset($_POST['search_cf7_value']) && empty($_POST['search_cf7_value']) && isset($_POST['start_date']) && isset($_POST['end_date']) && !empty($_POST['start_date']) && !empty($_POST['end_date']) && $s_date !== false && $e_date !== false){

			//Get start date information
			$start_date =  date_format($s_date,"Y-m-d");

			//Get end date information
			$end_date =  date_format($e_date,"Y-m-d");
			$query_end_date = $end_date." 23:59:59";

			$query = $this->cached_get_results("SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = %d AND data_id IN( SELECT * FROM ( SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = %d AND `name` = 'submit_time' AND value between %s and %s GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} LIMIT %d,%d ) temp_table) ORDER BY {$cf7d_entry_order_by}", $fid, $fid, $start_date, $query_end_date,  $offset, $items_per_page, 'vsz_cf7', 300 );

			//Get total entries information
			$arr_total = $this->cached_get_results("SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = %d AND data_id IN( SELECT * FROM ( SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = %d AND `name` = 'submit_time' AND value between %s and %s GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} ) temp_table) GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by}", $fid, $fid, $start_date, $query_end_date, 'vsz_cf7', 300 );
		}
		//Check search field value not empty and date filter active or not
		else if(isset($_POST['search_cf7_value']) && !empty($_POST['search_cf7_value']) && isset($_POST['start_date']) && isset($_POST['end_date']) && !empty($_POST['start_date']) && !empty($_POST['end_date']) && $s_date !== false && $e_date !== false){


			//Get start date information
			$start_date =  date_format($s_date,"Y-m-d");

			//Get end date information
			$end_date =  date_format($e_date,"Y-m-d").' 23:59:59';

			//Get date filter related entries information
			$date_query = $this->cached_get_results("SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = %d AND `name` = 'submit_time' AND value between %s and %s GROUP BY `data_id` ORDER BY `data_id` DESC", $fid, $start_date, $end_date, 'vsz_cf7', 300 );

			$rs_date = $date_query;
			//Get all entries and setup a string
			$data_ids = '';
			if(!empty($rs_date)){
				foreach($rs_date as $objdata_id){
					$data_ids .= $objdata_id->data_id .',';
				}
				$data_ids = rtrim($data_ids,',');
			}

			//get all entrise information
			$query = $this->cached_get_results("SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = %d AND data_id IN( SELECT * FROM ( SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = %d AND `value` LIKE %s AND FIND_IN_SET(data_id, %s) GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} LIMIT %d,%d ) temp_table) ORDER BY {$cf7d_entry_order_by}", $fid, $fid, '%' . $wpdb->esc_like($search) . '%', $data_ids, $offset, $items_per_page, 'vsz_cf7', 300 );
			//Get total entries information
			$arr_total = $this->cached_get_results("SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = %d AND data_id IN( SELECT * FROM ( SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = %d AND `value` LIKE %s AND FIND_IN_SET(data_id, %s) GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} ) temp_table) GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by}", $fid, $fid, '%' . $wpdb->esc_like($search) . '%', $data_ids, 'vsz_cf7', 300 );

		}
		//Call when any filter not active on Listing screen
		else{
			if(isset($_GET["orderby"]) && isset($_GET["order"]) && !empty($_GET["orderby"]) && !empty($_GET["order"]) && (strtolower(sanitize_text_field(wp_unslash($_GET["order"]))) == 'asc' || strtolower(sanitize_text_field(wp_unslash($_GET["order"]))) == 'desc')){
				
				$order = esc_sql(sanitize_text_field(wp_unslash($_GET['order'])));
				$orderby = esc_sql(sanitize_text_field(wp_unslash($_GET['orderby'])));

				$qry = $this->cached_get_results("SELECT `data_id` FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = %d AND `name` = %s AND data_id IN( SELECT * FROM ( SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = %d GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} LIMIT %d,%d ) temp_table) ORDER BY `value` {$order}, {$cf7d_entry_order_by}", $fid, $orderby, $fid, $offset, $items_per_page, 'vsz_cf7', 300 );
				$idVals =  $qry;

				$id_val = array();
				if(!empty($idVals)){
					foreach($idVals as $o_id){
						$id_val[] = $o_id->data_id;
					}
				}

				$id_val_str = "";
				if(!empty($id_val)){
					$id_val_str = implode(',',$id_val);
				}

				$query = $this->cached_get_results("SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = %d AND data_id IN( SELECT * FROM ( SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE 1 = 1 AND `cf7_id` = %d GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} LIMIT %d,%d ) temp_table) ORDER BY FIELD(`data_id`, %s )", $fid, $fid,  $offset, $items_per_page, $id_val_str, 'vsz_cf7', 300 );

			}
			else{
				$query = $this->cached_get_results("SELECT * FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = %d AND data_id IN (SELECT * FROM (SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = %d GROUP BY `data_id` ORDER BY {$cf7d_entry_order_by} LIMIT %d,%d) temp_table) ORDER BY {$cf7d_entry_order_by}", $fid, $fid, $offset, $items_per_page, 'vsz_cf7', 300 );
			}

			//Get total entries information
			if(!empty($search)){
				$arr_total = $this->cached_get_results("SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = %d AND `value` LIKE %s GROUP BY `data_id`", $fid, '%' . $wpdb->esc_like($search) . '%', 'vsz_cf7', 300 );
			}else{
				$arr_total = $this->cached_get_results("SELECT data_id FROM {$wpdb->prefix}cf7_vdata_entry WHERE `cf7_id` = %d GROUP BY `data_id`", $fid, 'vsz_cf7', 300 );
			}
		}
		// phpcs:enable
		
	
		//Execute query here
		$data = $query;

		//Get entry wise all fields information
		$data_sorted = vsz_cf7_sortdata($data);

		//get total count
		$total = count($arr_total);

		//Define bulk action array

		//Add filter for customize bulk action values
		$entry_actions = (array) apply_filters('vsz_cf7_entry_actions', $entry_actions);

		//define nonce for get entry details
		$getDatanonce = wp_create_nonce( 'vsz-cf7-get-entry-nonce-'.$fid );

		//Form listing design structure start here
		?><div class="wrap our-class">
			<form class="vsz-cf7-listing row" action="<?php print esc_url($url);?>" method="post" id="cf7d-admin-action-frm" >
				<input type="hidden" name="page" value="contact-form-listing">
				<input type="hidden" name="fid" value="<?php echo esc_html($fid); ?>">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_html(wp_create_nonce('vsz-cf7-action-nonce')); ?>"><?php
					//Display setting screen button
					do_action('vsz_cf7_display_settings_btn', $fid);
				?><div class="span12">
					<div class="date-filter from-to" style="display:block;">
						<div class="from-to-date-search">
					<input type="text" name="start_date" id="start_date" placeholder="From" value="<?php print isset($_POST['start_date']) ? esc_attr(sanitize_text_field(wp_unslash($_POST['start_date']))) : '';?>" class="input-cf-date">
					<input type="text" name="end_date" id="end_date" placeholder="To" value="<?php print isset($_POST['end_date']) ? esc_attr(sanitize_text_field(wp_unslash($_POST['end_date']))) : '';?>" class="input-cf-date" >
							<input type="button" name="search_date" id="search_date" value="<?php esc_html_e('Search By Date','advanced-cf7-db');  ?>" title="<?php esc_html_e('Search By Date','advanced-cf7-db');  ?>" class="button action" >
						</div>
						<div class="type-something"><?php
							//Display Search section here
							do_action('vsz_cf7_after_datesection_btn', $fid);
						?></div>
						<div class="reset-class"><a href="<?php print esc_url($url);?>" title="<?php esc_html_e('Reset All','advanced-cf7-db');  ?>" class="button"><?php esc_html_e('Reset All','advanced-cf7-db'); ?></a></div>
					</div>
					<div class="clear"></div>
				</div>
				<div class="span12 bulk-actions">
					<div class="tablenav top">
						<div class="actions bulkactions">
							<label for="bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e('Select bulk action','advanced-cf7-db');  ?></label>
							<select name="action" id="bulk-action-selector-top">
								<option value="-1"><?php esc_html_e('Bulk Actions','advanced-cf7-db'); ?></option><?php
								//Get all bulk action values
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo vsz_cf7_arr_to_option($entry_actions);
							?></select>
							<input id="doaction" name="btn_apply" class="button action" value="<?php esc_html_e('Apply','advanced-cf7-db'); ?>" title="<?php esc_html_e('Apply','advanced-cf7-db'); ?>" type="submit" />
							
							<?php
							//Display Export button option values
							do_action('vsz_cf7_after_bulkaction_btn', $fid);
							?><div class="tablenav-pages">
								<span class="displaying-num"><?php echo (($total == 1) ?
								'1 ' . esc_html('item') :
								$total . ' ' . esc_html('items')) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?></span>
								<span class="pagination-links"><?php
									//Setup pagination structure
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									print ( paginate_links(array(
										'base' => add_query_arg('cpage', '%#%'),
										'format' => '',
										'prev_text' => __('&laquo;', 'advanced-cf7-db'),
										'next_text' => __('&raquo;', 'advanced-cf7-db'),
										'total' => ceil($total / $items_per_page),
										'current' => $page,
									)));


								?></span>
							</div>
						</div>
						<br class="clear">
					</div>
				</div>
				<div class="span12 table-structure">
					<div class="table-inner-structure">
						<table class="wp-list-table widefat fixed striped posts cf7d-admin-table">
							<thead>
								<tr><?php
									echo '<td id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1" /></td>';
									//Display Edit headion field
									if($edit == true){
										do_action('vsz_cf7_admin_after_heading_field');
									}
									//Define table header section here
									foreach ($fields as $k => $v){
										// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										echo '<th class="manage-column" data-key="'.esc_html($v).'">'.vsz_cf7_admin_get_field_name($v).'</th>';
									}
								?></tr>
							</thead>
							<tbody><?php
								//Add character count functionalirty here
								$display_character = (int) apply_filters('vsz_display_character_count',30);
								$arr_field_type_info = vsz_field_type_info($fid);

								//Get all fields related information
								if(!empty($data_sorted)){
									foreach ($data_sorted as $k => $v) {
										$k = (int)$k;
										echo '<tr>';
										echo '<th class="check-column" scope="row"><input id="cb-select-'.esc_html($k).'" type="checkbox" title="Check" name="del_id[]" value="'.esc_html($k).'" /></th>';
										$row_id = $k;
										//Display edit entry icon
										if($edit == true){
											do_action('vsz_cf7_admin_after_body_field', $fid, $row_id,$getDatanonce);
										}
										foreach ($fields as $k2 => $v2) {
											//Get fields related values
											$_value = ((isset($v[$k2])) ? $v[$k2] : '&nbsp;');
											/* Date format as per the wordpress setting 2026-09-08*/
											if($k2 === 'submit_time' && $_value !== '&nbsp;'){
												$submit_datetime = date_create_from_format('Y-m-d H:i:s', $_value);
												if($submit_datetime instanceof DateTimeInterface){
													$_value = date_format($submit_datetime, get_option('date_format') . ' ' . get_option('time_format'));
												}
											}
											$_value1 = filter_var($_value, FILTER_SANITIZE_URL);

											//Check value is URL or not
											if (!filter_var($_value1, FILTER_VALIDATE_URL) === false) {
												$_value = esc_url($_value);
												//If value is url then setup anchor tag with value
												if(!empty($arr_field_type_info) && array_key_exists($k2,$arr_field_type_info) && $arr_field_type_info[$k2] == 'file'){
													//Add download attributes in tag if field type is attachement
													?><td data-head="<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped echo vsz_cf7_admin_get_field_name($v2); ?>">
														<a href="<?php echo esc_url($_value); ?>" target="_blank" title="<?php echo esc_url($_value); ?>" download ><?php echo esc_html(basename($_value)); ?>
														</a>
													</td><?php
												}
												else{
													?><td data-head="<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped echo vsz_cf7_admin_get_field_name($v2); ?>">
														<a href="<?php echo esc_url($_value); ?>" target="_blank" title="<?php echo esc_url($_value); ?>" ><?php echo esc_html(basename($_value)); ?>
														</a>
													</td><?php
												}
											}
											else{
												$_value = esc_html(html_entity_decode($_value));
												//var_dump(($_value)); var_dump(strlen($_value)); exit;
												if(strlen($_value) > $display_character){
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
													echo '<td data-head="'.vsz_cf7_admin_get_field_name($v2).'">'.esc_html(substr($_value, 0, $display_character)).'...</td>';
												}else{
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
													echo '<td data-head="'.vsz_cf7_admin_get_field_name($v2).'">'.esc_html($_value).'</td>';
												}
											}
										}//Close foreach
										echo '</tr>';
									}//Close foreach
								}
								else{
									?><tr><?php
										$span = count($fields) + 2;
										?><td colspan="<?php echo esc_html($span); ?>">
											<?php esc_html_e('No records found.','advanced-cf7-db');  ?>
										</td><?php
									?></tr><?php
								}
							?></tbody>
							<tfoot>
								<tr><?php
									//Setup header section in table footer area
									echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-2" /></td>';
									if($edit == true){
										do_action('vsz_cf7_admin_after_heading_field');
									}
									foreach ($fields as $k => $v){
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										echo '<th class="manage-column" data-key="'.esc_html($v).'">'.vsz_cf7_admin_get_field_name($v).'</th>';
									}
								?></tr>
							</tfoot>
						</table>
					</div>
				</div>

				<input type="hidden" name="cpage" value="<?php echo intval($page);?>" id="cpage">
				<input type="hidden" name="totalPage" value="<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  print ceil($total / $items_per_page);?>" id="totalPage">
				<?php $list_nonce = wp_create_nonce( 'vsz-cf7-form-list-nonce' ); ?>
				<input type="hidden" name="vsz_cf7_form_list_nonce"  value="<?php echo esc_attr($list_nonce); ?>" />
				
			</form>

			


			<script>
				//Setup pagination related functionality when click on page link then form submitted
				jQuery(".pagination-links a").on('click',function(){
					var final_id;
					var url = jQuery(this).attr('href');
					var id_check = /[?&]cpage=([^&]+)/i;
					var match = id_check.exec(url);
					if(match != null){
						final_id = parseInt(match[1]);
					}
					if(final_id != ''){
						jQuery(this).attr("href","javascript:void(0)");
						jQuery('#cpage').val(final_id);
						document.getElementById('cf7d-admin-action-frm').submit();
					}
				});

				//Add custom class in body tag when click on Setting button
				jQuery('#cf7d_setting_form').click(function(){
					jQuery('body').addClass('our-body-class');
				});
				//Updating record
				jQuery(document).on('click','#update_cf7_value',function(){
					var filterdata = jQuery('.vsz-cf7-listing').html();
					jQuery('.cf7d-modal-form').append('<div style="display:none">'+filterdata+'</div>');

				});

			</script>
		</div><?php
		//Define setting and Edit popup call back function
		do_action('vsz_cf7_after_admin_form',$fid);
	}//Close if for check form id empty or not
	else if(!$exist_entry_flag){

		//added in 1.8.4
		?><div class="popup-note">
			<span><?php
				esc_html_e('Currently not submission any form data.','advanced-cf7-db');
			?></span>
		</div><?php

	}
}