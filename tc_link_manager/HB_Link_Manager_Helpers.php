<?php

class HB_Link_Manager_Helpers {

	/**
	 * Получаем список ссылок из Pretty Links
	 */
	public static function get_links_prli_links() {
		global $wpdb;

		return $wpdb->get_results( "SELECT id,url,slug FROM " . $wpdb->prefix . "prli_links 
                   WHERE link_status = 'enabled'", ARRAY_A );
	}

	/**
	 * Функция для отладки (для авторизованных)
	 */
	public static function dd( $var, $stop = false ) {
		if ( is_user_logged_in() ) {
			self::d( $var, $stop );
		}
	}

	/**
	 * Функция для отладки
	 */
	public static function d( $var, $stop = false ) {
		echo "<pre>";
		var_dump( $var );
		echo "</pre>";
		if ( $stop ) {
			die();
		}
	}
}
