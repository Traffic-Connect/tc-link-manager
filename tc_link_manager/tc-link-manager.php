<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HB_Link_Manager' ) ) :

	require __DIR__ . "/HB_Link_Manager.php";
	require __DIR__ . "/HB_Link_Manager_Helpers.php";
	require __DIR__ . "/admin/HB_Link_Manager_Admin.php";

	add_action('init', function () {
		$user = get_current_user_id();
		if ( ! is_super_admin( $user ) ) {
			return;
		}
		if ( is_admin() ) {
			new admin\HB_Link_Manager_Admin();
		}
	});

	new HB_Link_Manager();
endif;
