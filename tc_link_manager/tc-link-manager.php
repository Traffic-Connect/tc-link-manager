<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TC_MU_LINK_MANAGER_FILE_PATH', __FILE__ );

if ( ! class_exists( 'HB_Link_Manager' ) ) :

	require __DIR__ . "/HB_Link_Manager.php";
	require __DIR__ . "/HB_Link_Manager_Helpers.php";
	require __DIR__ . "/admin/HB_Link_Manager_Admin.php";

	add_action('init', 'tc_link_manager_plugin_init');
	function tc_link_manager_plugin_init(){
		$user = get_current_user_id();
		if ( ! is_super_admin( $user ) ) {
			return;
		}
		if ( is_admin() ) {
			new admin\HB_Link_Manager_Admin();
		}

		if ( ! function_exists('get_plugin_data') ) {
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }

		$plugin_data = get_plugin_data(TC_MU_LINK_MANAGER_ENTRY_FILE_PATH);
		$current_version = $plugin_data['Version'];
		$hb_link_manager_settings = get_option( 'hb_link_manager_settings', [] );

		if ( get_option('tc_link_manager_version') === false ) {
			$hb_link_manager_settings['true_code_response'] = '200,301,302,303,304,305,306,307,308';
			update_option('hb_link_manager_settings', $hb_link_manager_settings);
		}

		update_option('tc_link_manager_version', $current_version);
	}

	new HB_Link_Manager();
endif;
