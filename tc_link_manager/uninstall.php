<?php

/**
 * Выполняем действия при удалении плагина
 **/

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

delete_option( 'hb_link_manager_settings' );
delete_option( 'hb_link_manager_links' );
delete_option( 'hb_link_manager_all_links' );
