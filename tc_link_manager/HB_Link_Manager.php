<?php

class HB_Link_Manager {

	public array $option;

	public function __construct() {
		$this->option = get_option( 'hb_link_manager_settings', [] );
		add_action( 'wp', [ $this, 'cron_activation' ] );
		add_action( 'hb_link_manager_cron_hook', [ $this, 'cron_job' ] );
		add_filter( 'prli_target_url', [ $this, 'links_rewrite' ] );
		add_action( 'save_post', [ $this, 'check_links_save_post' ], 10, 2 );
		add_action( 'acf/options_page/save', [ $this, 'check_links_save_post' ], 10, 2 );
	}

	/**
	 * Замена ссылок
	 *
	 * @param $arr
	 *
	 * @return mixed
	 */
	public function links_rewrite( $arr ) {
		if ( empty( $this->option['link_rewrite'] ) ) {
			return $arr;
		}

		$link_rewrite_array_str = explode( PHP_EOL, $this->option['link_rewrite'] );
		foreach ( $link_rewrite_array_str as $str ) {
			$str                = trim( $str );
			$link_rewrite_array = explode( ';', $str );
			if ( ! empty( $link_rewrite_array[0] ) && intval( $link_rewrite_array[0] ) == $arr['link_id'] ) {
				// Если указан день недели
				if ( ! empty( $link_rewrite_array[4] ) ) {
					$weekday_array = explode( ',', $link_rewrite_array[4] );
					$weekday_array = array_map( 'trim', $weekday_array );
					$cur_weekday   = wp_date( 'N' );
					if ( ! in_array( $cur_weekday, $weekday_array ) ) {
						continue;
					}
				}
				// Если указано время начала и конца
				if ( ! empty( $link_rewrite_array[2] ) && ! empty( $link_rewrite_array[3] ) ) {
					$cur_time = strtotime( wp_date( 'Y-m-d H:i' ) );
					$time_1   = strtotime( wp_date( 'Y-m-d ' . $link_rewrite_array[2] ) );
					$time_2   = strtotime( wp_date( 'Y-m-d ' . $link_rewrite_array[3] ) );
					if ( $cur_time >= $time_1 && $cur_time <= $time_2 ) {
						$arr['url'] = $link_rewrite_array[1];
					}
				}
			}
		}

		return $arr;
	}

	/**
	 * Проверка ссылок и уведомление в случае ошибки
	 * @return void
	 */
	public function check_links() {
		$hb_link_manager_links = get_option( 'hb_link_manager_links', [] );
		$pretty_links          = HB_Link_Manager_Helpers::get_links_prli_links();
		foreach ( $pretty_links as $item ) {
			$r = wp_remote_head( $item['url'] );
			if ( is_wp_error( $r ) ) {
				$hb_link_manager_links[ $item['id'] ] = $this->build_option_links( $item['id'], 'error',
					$r->get_error_message() );
				$this->notification( $r->get_error_message(), $item['url'] );
			} else {
				if ( ! empty( $r['response']['code'] ) ) {
					$msg                    = 'Response code: ' . $r['response']['code'];
					$true_code_response_arr = explode( ',', $this->option['true_code_response'] );
					$true_code_response_arr = array_map( 'trim', $true_code_response_arr );
					if ( in_array( $r['response']['code'], $true_code_response_arr ) ) {
						$hb_link_manager_links[ $item['id'] ] = $this->build_option_links( $item['id'], 'ok',
							$r['response']['code'] );
					} else {
						$hb_link_manager_links[ $item['id'] ] = $this->build_option_links( $item['id'], 'error', $msg );
						$this->notification( 'Response code: ' . $r['response']['code'], $item['url'] );
					}
				} else {
					$msg                                  = "Response code not defined";
					$hb_link_manager_links[ $item['id'] ] = $this->build_option_links( $item['id'], 'error', $msg );
					$this->notification( $msg, $item['url'] );
				}
			}
		}

		if ( ! empty( $hb_link_manager_links ) ) {
			update_option( 'hb_link_manager_links', $hb_link_manager_links );
		}
	}

	/**
	 * Активация крона
	 */
	public function cron_activation() {
		if ( ! wp_next_scheduled( 'hb_link_manager_cron_hook' ) ) {
			wp_schedule_event( time(), 'daily', 'hb_link_manager_cron_hook' );
		}
	}

	/**
	 * Добавляем функцию крона
	 */
	public function cron_job() {
		$this->check_links();
	}

	/**
	 * Формирование опции с информацией о ссылке
	 *
	 * @param $key
	 * @param $type
	 * @param $msg
	 *
	 * @return array
	 */
	private function build_option_links( $key, $type, $msg ) {
		return [
			'id'   => $key,
			'type' => $type,
			'msg'  => $msg,
			'time' => time(),
		];
	}

	/**
	 * Проверяем ссылки при сохранении постов, Pretty Links и страниц настроек
	 *
	 * @param $post_id
	 * @param $post
	 */
	public function check_links_save_post( $post_id, $post ) {
		$links                      = array();
		$GLOBALS['acf_check_links'] = array();

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Page and Post
		if ( ! empty( $post->post_type ) && ( $post->post_type == 'page' || $post->post_type == 'post' ) ) {
			// Post content
			if ( ! empty( $post->post_content ) ) {
				$regexp = '#["\'](https?://.+)["\']#siU';
				preg_match_all( $regexp, $post->post_content, $matches );
				if ( ! empty( $matches[1] ) ) {
					$pattern = "#^" . home_url() . "#";
					foreach ( $matches[1] as $url ) {
						if ( ! preg_match( $pattern, $url ) ) {
							$links[] = $url;
						}
					}
				}
			}
			// Acf
			if ( ! empty( $_POST['acf'] ) && is_array( $_POST['acf'] ) ) {
				$this->acf_check( $_POST['acf'] );
			}
		}

		// Pretty Links
		if ( ! empty( $_POST['post_type'] ) && $_POST['post_type'] == 'pretty-link' && ! empty( $_POST['prli_url'] ) ) {
			$links[] = $_POST['prli_url'];
		}

		// Option Page
		if ( isset( $_POST['_acf_post_id'] ) && $_POST['_acf_post_id'] == 'options'
		     && isset( $_POST['acf'] ) && is_array( $_POST['acf'] ) ) {
			$this->acf_check( $_POST['acf'] );
		}

		$links_all = array_merge( $links, $GLOBALS['acf_check_links'] );
		$links_all = array_unique( $links_all );

		if ( $links_all ) {
			$hb_link_manager_all_links = get_option( 'hb_link_manager_all_links', [] );
			if ( $hb_link_manager_all_links ) {
				$hb_link_manager_all_links_new = $hb_link_manager_all_links;
				foreach ( $links_all as $link ) {
					if ( ! in_array( $link, $hb_link_manager_all_links ) ) {
						$this->notification( "New link:", $link, true );
						$hb_link_manager_all_links_new[] = $link;
					}
				}

				if ( $hb_link_manager_all_links_new != $hb_link_manager_all_links ) {
					update_option( 'hb_link_manager_all_links', $hb_link_manager_all_links_new );
				}
			}
		}
	}

	/**
	 * Обход массива acf и получение внешних ссылок
	 */
	private function acf_check( $acf ) {
		foreach ( $acf as $item ) {
			if ( is_array( $item ) ) {
				$this->acf_check( $item );
			} else {
				$regexp = '#(https?://.+)#';
				preg_match( $regexp, $item, $matches );
				if ( ! empty( $matches[0] ) ) {
					$pattern = "#^" . home_url() . "#";
					if ( ! preg_match( $pattern, $matches[0] ) ) {
						$GLOBALS['acf_check_links'][] = $matches[0];
					}
				}
			}
		}
	}

	/**
	 * Отправка уведомления
	 * @throws \TelegramBot\Api\Exception
	 * @throws \TelegramBot\Api\InvalidArgumentException
	 * @throws Exception
	 */
	public function notification( $event, $link, $user = false ) {
		$message_add = null;
		$tg_token = '7553983536:AAFvENvWpU0lajPTzzO0Hl8r3dXIFC1zApM';
		$chat_id = '-4902876387';

		if ( $user ) {
			$current_user = wp_get_current_user();
			$ip           = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
			$message_add  = $current_user->user_login . "(" . $ip . ")";
		}

		$this->log( $event . " " . $link . "|" . $message_add );
		require __DIR__ . "/vendor/autoload.php";
		$bot       = new \TelegramBot\Api\BotApi( $tg_token );
		$message   = home_url() . "\n" . $event . "\n" . $link;
		if ( $user ) {
			$message = home_url() . "\n" . $event . " " . $link . "\n" . $message_add;
		}

		$bot->sendMessage( $chat_id, $message );
	}

	/**
	 * Log
	 *
	 * @param $event
	 *
	 * @return void
	 */
	public function log( $event ) {
		$str    = date( "Y-m-d H:i:s" ) . "|" . $event;
		$handle = fopen( WP_CONTENT_DIR . "/hb_link_manager.log", "a" );
		fwrite( $handle, $str . "\n" );
		fclose( $handle );
	}
}
