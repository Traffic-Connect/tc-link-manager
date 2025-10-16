<?php

class HB_Link_Manager {

	public array $option;
	public array $config;
	private string $team_name;
	public array $config_teams;

	public function __construct() {
		$this->option = get_option( 'hb_link_manager_settings', [] );
		$this->config = require plugin_dir_path( __FILE__ ) . 'config.php';
		$this->config_teams = require plugin_dir_path( __FILE__ ) . 'config-teams.php';

		wp_clear_scheduled_hook( 'hb_link_manager_cron_hook' );
		add_action( 'wp', [ $this, 'cron_activation' ] );
		add_action( 'tc_link_manager_cron_hook', [ $this, 'cron_job' ] );
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
		$this->team_name       = $this->get_team_name();

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		$plugin_data     = get_plugin_data( TC_MU_LINK_MANAGER_ENTRY_FILE_PATH );
		$current_version = $plugin_data['Version'];

		if ( get_option( 'tc_link_manager_version' ) === false ) {
			$this->option['true_code_response'] = '200,301,302,303,304,305,306,307,308';
			update_option( 'hb_link_manager_settings', $this->option );
		}

		foreach ( $pretty_links as $item ) {
			$r = wp_remote_head( $item['url'] );

			if ( is_wp_error( $r ) ) {
				$hb_link_manager_links[ $item['id'] ] = $this->build_option_links( $item['id'], 'error',
					$r->get_error_message() );
				$this->notification( $r->get_error_message(), $item['url'], false, "broken" );
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
						// Временно отправляем уведомление только для 404 кодов
						if ( $r['response']['code'] == '404' ) {
							$this->notification( 'Response code: ' . $r['response']['code'], $item['url'], false,
								"broken" );
						}
					}
				} else {
					$msg                                  = "Response code not defined";
					$hb_link_manager_links[ $item['id'] ] = $this->build_option_links( $item['id'], 'error', $msg );
					$this->notification( $msg, $item['url'], false, "broken" );
				}
			}
		}

		if ( ! empty( $hb_link_manager_links ) ) {
			update_option( 'hb_link_manager_links', $hb_link_manager_links );
		}

		update_option( 'tc_link_manager_version', $current_version );
	}

	/**
	 * Активация крона
	 */
	public function cron_activation() {
		if ( ! wp_next_scheduled( 'tc_link_manager_cron_hook' ) ) {
			wp_schedule_event( time(), 'weekly', 'tc_link_manager_cron_hook' );
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
		$this->team_name            = $this->get_team_name();

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
						$this->notification( "New link:", $link, true, "new" );
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
	public function notification( $event, $link, $user, $link_type ) {
		$message_add = null;
		$tg_token    = $this->config['telegram']['token'];
		$chat_id     = $this->config['telegram']['chat_id_general'];
		$thread_id   = $link_type == "new" ? $this->config['telegram']['thread_id_new_links'] : $this->config['telegram']['thread_id_broken_links'];

		if ( $user ) {
			$current_user = wp_get_current_user();
			$user_info    = isset( $_COOKIE["sso_email"] ) ? $_COOKIE["sso_email"] : $current_user->user_login;
			$ip           = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
			$message_add  = $user_info . "(" . $ip . ")";
		}

		$this->log( $event . " " . $link . "|" . $message_add );

		if ( $tg_token !== '' && $chat_id !== '' ) {
			require __DIR__ . "/vendor/autoload.php";
			$bot = new \TelegramBot\Api\BotApi( $tg_token );

			$site_info = home_url();
			$add_info  = $this->getServerIP() . ( $this->team_name ? " (Team " . $this->team_name . ")" : null );

			$message = $site_info . "\n" . $add_info . "\n" . $event . "\n" . $link;
			if ( $user ) {
				$message = $site_info . "\n" . $add_info . "\n" . $event . " " . $link . "\n" . $message_add;
			}

			$bot->sendMessage( $chat_id, $message, null, false, null, null, false, $thread_id, null, null );

			if( $this->team_name && $this->team_name !== '' ){
				$team_chat_id = isset( $this->config_teams[$this->team_name]['general'] ) ? $this->config_teams[$this->team_name]['general'] : false;
				$team_thread_id = $link_type == "new" ? $this->config_teams[$this->team_name]['thread_id_new_links'] : $this->config_teams[$this->team_name]['thread_id_broken_links'];
				
				if( $team_chat_id ){
					$bot->sendMessage( $team_chat_id, $message, null, false, null, null, false, $team_thread_id, null, null );
				}
			}
		}
	}

	/**
	 * Получает название команды из API менеджера
	 *
	 * @return string|null
	 */
	private function get_team_name() {
		$manager_token = $this->config['api']['manager_token'] ?? '';

		if ( empty( $manager_token ) ) {
			return '';
		}

		// Извлекаем домен из home_url()
		$domain = parse_url( home_url(), PHP_URL_HOST );

		if ( empty( $domain ) ) {
			return '';
		}

		$api_url     = 'https://manager.tcnct.com/api/site/team';
		$request_url = $api_url . '?domain=' . urlencode( $domain ) . '&token=' . urlencode( $manager_token );

		$response = wp_remote_get( $request_url, [
			'timeout' => 15,
			'headers' => [
				'User-Agent' => 'TC-Link-Manager/1.0'
			]
		] );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return '';
		}

		return $data['team'] ?? '';
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

	//Get Server IP
	private function getServerIP() {
		if ( ! empty( $_SERVER['SERVER_ADDR'] ) ) {
			return $_SERVER['SERVER_ADDR'];
		}

		return gethostbyname( $_SERVER['SERVER_NAME'] );
	}
}
