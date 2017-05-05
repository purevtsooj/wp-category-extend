<?php
/*
Plugin Name: Wordpress categories from an external API
Plugin URI: #
Description: External Categories
Author: Purevtsooj Davaatseren
Version: 1.0
Author URI: #
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Define plugins paths
 */
define( 'WCE_PLUGIN_DIR', trailingslashit(dirname(__FILE__)) );
define( 'WCE_PLUGIN_URL', trailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );


/**
 * Main WP_Category_Extend Class.
 *
 * @class WP_Category_Extend
 * @version	1.0
 */
class WP_Category_Extend{


	/**
	 * @access public
	 */
	function __construct(){
		// Admin init function
		add_action('admin_init', array($this, 'admin_init'));

		// Admin Enqueue Scripts
		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'), 999);
		
		// Ajax request: Update category
		add_action('wp_ajax_wce_update_category', array($this, 'wce_update_category_hook'));
        add_action('wp_ajax_nopriv_wce_update_category', array($this, 'wce_update_category_hook'));

        // Cron Job: schedules and background function
        add_action('cron_update_category', array($this, 'update_category'));
        add_filter('cron_schedules', array($this, 'wce_cron_schedules'));

        // Check cron schedules
        register_activation_hook( __FILE__, array($this, 'register_activation_hook') );
	}


	/**
	 * @param array $schedules
	 */
	public function wce_cron_schedules( $schedules ) {
		$intervals['wce_schedule'] = array( 
										// 'interval' => 60*30,
										'interval' => 60,
										'display' => esc_html__('WCE Schedule', 'wce')
									);
		$schedules = array_merge( $intervals, $schedules);
		return $schedules;
	}


	public function register_activation_hook(){
		if( !(wp_next_scheduled('cron_update_category')) && !wp_installing() ){
			wp_schedule_event(time(), 'wce_schedule', 'cron_update_category');
		}
	}


	public function admin_enqueue_scripts(){
		$screen = get_current_screen();
		wp_enqueue_style('wp-categories-extend-style', WCE_PLUGIN_URL . 'css/category.css' );

		if( isset($screen->id) && $screen->id=='options-general' ){
			$nonce = wp_create_nonce('wce_action_nonce');

			wp_enqueue_style('wce-options-general', WCE_PLUGIN_URL . 'css/options-general.css' );
			wp_enqueue_script('wce-options-general', WCE_PLUGIN_URL . 'js/options-general.js', array('jquery'), false, true );
			wp_add_inline_script('wce-options-general', sprintf('var wce_options = { nonce:"%s" };', $nonce) );
		}
	}



	public function admin_init(){
        add_settings_field(
        	'wce_update_category',
        	'',
        	array($this, 'render_field_html'),
        	'general'
    	);
	}


	public function render_field_html() {
        printf('<div class="wce-update-wrap">
	        		<hr>
	        		<br>
	        		<a href="javascript:;" class="button button-primary button-large wce-update-category">
	        			<span class="btn-text">%s</span>
	        			<span class="btn-loader"><img src="%simages/loader.svg"></span>
	    			</a>
	    			<br>
	        		<pre>Hello</pre>
	        		<br>
	    			<hr>
    			</div>', esc_html__('Update categories now', 'wce'), WCE_PLUGIN_URL);
    }


    public function wce_update_category_hook(){

    	$nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    	if( !is_user_logged_in() || !wp_verify_nonce( $nonce, 'wce_action_nonce' ) ){
    		exit;
    	}

 		$update_category = $this->update_category();

 		if( $update_category ){

	 		echo json_encode( array(
 								'result' => 'success',
 								'message' => esc_html__('Categories updated.', 'wce')
							) );
 		}
 		else{
 			echo json_encode( array(
 								'result' => 'failed',
 								'message' => esc_html__("Couldn't connect to server.", "wce")
							) );
 		}

 		exit;
    }

    public function update_category(){

    	try{
    		// $url = "http://localhost:3000/categories";
    		$url = "https://api.myjson.com/bins/youfx";
	    	$remote_data = wp_remote_get( $url );
	        $categories_data = array_key_exists('body', $remote_data) ? $remote_data['body'] : '';
	 		$categories = (array)json_decode($categories_data, true);

	 		if( !empty($categories) ){

	 			if( !function_exists('wp_insert_category') ){
 					require_once(ABSPATH . 'wp-config.php'); 
				   	require_once(ABSPATH . 'wp-includes/wp-db.php'); 
				   	require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
	 			}

	 			$all_categories = get_categories(array('hide_empty'=>0));
	 			foreach ($all_categories as $cat) {
	 				wp_delete_category($cat->term_id);
	 			}

	 			$parent_categories = array();
	 			$child_categories = array();
		 		foreach ($categories as $cat) {
		 			if( $cat['parent_id']==null ){
		 				$new_cat = array(
							'cat_name' => $cat['name']
						);

						$cat_id = wp_insert_category( $new_cat );
						$parent_categories['id_'.$cat['id']] = abs($cat_id);
		 			}
		 			else{
		 				$child_categories[] = $cat;
		 			}
		 		}

		 		foreach ($child_categories as $cat) {
		 			$new_cat = array(
						'cat_name' => $cat['name'],
						'category_parent' => $parent_categories['id_'.$cat['parent_id']]
					);
					wp_insert_category( $new_cat );
		 		}

		 		// rescheduling event
		 		wp_clear_scheduled_hook('cron_update_category');
				wp_schedule_event(time(), 'wce_schedule', 'cron_update_category');

				return true;

	 		}
    	}
    	catch(Exception $e){
    		error_log( $e->getMessage() );
    	}

    	return false;
    }
	
}

new WP_Category_Extend();

