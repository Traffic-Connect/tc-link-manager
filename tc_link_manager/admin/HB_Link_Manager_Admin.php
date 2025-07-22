<?php

namespace admin;

use HB_Link_Manager_Helpers;

class HB_Link_Manager_Admin {

	public string $prefix;
	public array $config;

	public function __construct() {
		$this->prefix = 'hb_link_manager_';
		$this->config = require plugin_dir_path( TC_MU_LINK_MANAGER_FILE_PATH ) . 'config.php';
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_styles' ] );
		add_action( 'admin_init', [ $this, 'plugin_settings' ] );
	}

	/**
	 * Создаем пункт меню
	 * @return void
	 */
	public function menu() {
		add_options_page( 'TC Link Manager', 'TC Link Manager', 'manage_options', $this->prefix . 'common_page',
			[ $this, 'display' ]
		);
	}

	/**
	 * Подключение стилей и скриптов
	 * @return void
	 */
	public function register_styles() {
		wp_enqueue_style( $this->prefix . 'style', plugins_url( 'assets/css/style.css', dirname( __FILE__ ) ), [],
			filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'assets/css/style.css' ) );
	}

	function display() {

		$prli_links            = HB_Link_Manager_Helpers::get_links_prli_links();
		$links                 = $this->get_links();
		$hb_link_manager_links = get_option( 'hb_link_manager_links', [] );

		?>
        <div class="wrap <?= $this->prefix ?>container">
            <h2><?php echo get_admin_page_title() ?></h2>
            <h3>Текущие ссылки установленные в Pretty Links</h3>
            <table class="widefat striped prli_links">
                <thead>
                <tr>
                    <th>Link ID</th>
                    <th>Pretty Link</th>
                    <th>Url</th>
                    <th>Статус</th>
                    <th>Время проверки</th>
                </tr>
                </thead>
				<?php foreach ( $prli_links as $item ): ?>
                    <tr>
                        <td>
							<?= $item['id'] ?>
                        </td>
                        <td>
                            <a href="<?= home_url() ?>/<?= $item['slug'] ?>" target="_blank">
								<?= home_url() ?><strong>/<?= $item['slug'] ?></strong>
                            </a>
                        </td>
                        <td>
                            <a href="<?= $item['url'] ?>" target="_blank"><?= $item['url'] ?></a>
                        </td>
                        <td>
							<?php if ( ! empty( $hb_link_manager_links ) ): ?>
								<?php if ( $hb_link_manager_links[ $item['id'] ]['type'] == 'ok' ): ?>
                                    <span class="ok">
                                    <?= $hb_link_manager_links[ $item['id'] ]['msg'] ?>
                                </span>
								<?php else: ?>
                                    <span class="error">
                                    <?= $hb_link_manager_links[ $item['id'] ]['msg'] ?>
                                </span>
								<?php endif ?>
							<?php endif ?>
                        </td>
                        <td>
							<?php if ( ! empty( $hb_link_manager_links ) ): ?>
								<?= wp_date( "d.m.Y / H:i:s", $hb_link_manager_links[ $item['id'] ]['time'] ) ?>
							<?php endif ?>
                        </td>
                    </tr>
				<?php endforeach ?>
            </table>

            <h3>Внешние ссылки на сайте</h3>
            <table class="widefat striped links">
                <thead>
                <tr>
                    <th>Page</th>
                    <th>Url</th>
                    <th>Details</th>
                </tr>
                </thead>
				<?php foreach ( $links as $item ): ?>
                    <tr>
                        <td>
							<?php if ( ! empty( $item['post_id'] ) ): ?>
                                <a href="<?= get_permalink( $item['post_id'] ) ?>" target="_blank">
									<?= get_permalink( $item['post_id'] ) ?>
                                </a>
							<?php elseif ( $item['type'] == 'options' ): ?>
                                -
							<?php endif ?>
                        </td>
                        <td>
                            <a href="<?= $item['url'] ?>" target="_blank"><?= $item['url'] ?></a>
                        </td>
                        <td>
							<?php var_dump( $item['comment'] ) ?>
                        </td>
                    </tr>
				<?php endforeach ?>
            </table>

            <form action="options.php" method="POST">
				<?php
					settings_fields( $this->prefix . 'common_group' );
					do_settings_sections( $this->prefix . 'common_page' );
				?>

				<?php if( $this->config['telegram']['token'] !== '' && $this->config['telegram']['chat_id_broken_links'] !== '' && $this->config['telegram']['chat_id_new_links'] !== '' ): ?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">Telegram token:</th>
								<td><code><?php echo $this->config['telegram']['token']; ?></code></td>
							</tr>
							<tr>
								<th scope="row">TG Chat ID (broken links):</th>
								<td><code><?php echo $this->config['telegram']['chat_id_broken_links']; ?></code></td>
							</tr>
							<tr>
								<th scope="row">TG Chat ID (new links):</th>
								<td><code><?php echo $this->config['telegram']['chat_id_new_links']; ?></code></td>
							</tr>
						</tbody>
					</table>
				<?php else: ?>
					<p style="color: red; font-weight: bold">Ключи для Telegram не установлены!</p>
				<?php endif; ?>
				<?php submit_button();?>
            </form>
        </div>
		<?php
	}

	/**
	 * Регистрируем опции, секции и поля для секции
	 * @return void
	 */
	public function plugin_settings() {
		register_setting( $this->prefix . 'common_group', $this->prefix . 'settings', 'sanitize_callback' );
		add_settings_section( $this->prefix . 'settings_id', 'Основные настройки', '', $this->prefix . 'common_page', [
			'section_class' => $this->prefix . 'settings_id',
		] );

		add_settings_field( $this->prefix . 'link_rewrite', 'Замена ссылок',
			[ $this, 'build_field' ], $this->prefix . 'common_page', $this->prefix . 'settings_id',
			[
				'type'         => 'textarea',
				'option_array' => $this->prefix . 'settings',
				'label_for'    => 'link_rewrite',
				'option'       => 'link_rewrite',
				'rows'         => 3,
				'comment'      => 'Формат: Link ID;Url;Time1;Time2;Weekdays. Пример: 1;https://google.com;07:00;12:00;1,3,5',
			]
		);
	}

	/**
	 * Build Field Function
	 *
	 * @param $args
	 *
	 * @return void
	 */
	public function build_field( $args ) {
		$option_id   = $args['option'];
		$option_name = $args['option_array'] . "[" . $args['option'] . "]";
		$option      = get_option( $args['option_array'] );
		$value       = isset( $option[ $args['option'] ] ) ? esc_attr( $option[ $args['option'] ] ) : null;
		if ( empty( $value ) && ! empty( $args['default'] ) ) {
			$value = $args['default'];
		}
		?>

		<?php if ( $args['type'] == 'checkbox' ): ?>
            <input id="<?= $option_id ?>" type="checkbox" name="<?= $option_name ?>"
                   value="1" <?php checked( 1, $value ) ?> />
		<?php endif ?>

		<?php if ( $args['type'] == 'textarea' ): ?>
            <textarea id="<?= $option_id ?>" name="<?= $option_name ?>"
                      rows="<?= $args['rows'] ?>"><?= $value ?></textarea>
		<?php endif ?>

		<?php if ( $args['type'] == 'input' ): ?>
            <input id="<?= $option_id ?>" type="text" name="<?= $option_name ?>" value="<?= $value ?>"/>
		<?php endif ?>

		<?php if ( $args['type'] == 'select' ): ?>
            <select name="<?= $option_name ?>" id="<?= $option_id ?>">
				<?php foreach ( $args['choices'] as $key => $item ): ?>
					<?php if ( $key == $value ): ?>
                        <option value="<?= $key ?>" selected><?= $item ?></option>
					<?php else: ?>
                        <option value="<?= $key ?>"><?= $item ?></option>
					<?php endif ?>
				<?php endforeach ?>
            </select>
		<?php endif ?>

		<?php if ( ! empty( $args['comment'] ) ): ?>
            <p class="description"><?= $args['comment'] ?></p>
		<?php endif ?>

		<?php
	}

	/**
	 * Очистка данных
	 *
	 * @param $options
	 *
	 * @return mixed
	 */
	public function sanitize_callback( $options ) {

		foreach ( $options as $name => & $val ) {
			if ( $name == 'input' ) {
				$val = strip_tags( $val );
			}

			if ( $name == 'checkbox' ) {
				$val = intval( $val );
			}
		}

		return $options;
	}

	/**
	 * Get Links
	 * @return array
	 */
	public function get_links() {
		global $wpdb;
		$ar      = array();
		$ar_uniq = array();

		// posts
		$res = $wpdb->get_results( "SELECT id,post_content,post_type FROM " . $wpdb->posts . " 
		WHERE post_status = 'publish' AND post_type != 'acf-field'", ARRAY_A );
		foreach ( $res as $row ) {
			$regexp = '#["\'](https?://.+)["\']#siU';
			preg_match_all( $regexp, $row['post_content'], $matches );

			if ( ! empty( $matches[1] ) ) {
				$pattern = "#^" . home_url() . "#";
				foreach ( $matches[1] as $url ) {
					if ( ! preg_match( $pattern, $url ) ) {
						if ( ! in_array( $url, $ar_uniq ) ) {
							$ar[]      = array(
								'post_id' => $row['id'],
								'type'    => "posts",
								'comment' => [
									'type'      => "posts",
									'post_type' => $row['post_type'],
								],
								'url'     => $url,
							);
							$ar_uniq[] = $url;
						}
					}
				}
			}
		}

		// postmeta
		$res = $wpdb->get_results( "SELECT post_id,meta_key,meta_value FROM " . $wpdb->postmeta . " WHERE 
		meta_value LIKE 'http%'", ARRAY_A );
		foreach ( $res as $row ) {
			$regexp = '#(https?://.+)#';
			preg_match( $regexp, $row['meta_value'], $matches );

			if ( ! empty( $matches[0] ) ) {
				$pattern = "#^" . home_url() . "#";
				if ( ! preg_match( $pattern, $matches[0] ) ) {
					if ( ! in_array( $matches[0], $ar_uniq ) ) {
						$ar[]      = array(
							'post_id' => $row['post_id'],
							'type'    => "postmeta",
							'comment' => [
								'type'     => "postmeta",
								'meta_key' => $row['meta_key'],
							],
							'url'     => $matches[0],
						);
						$ar_uniq[] = $matches[0];
					}
				}
			}
		}

		// options
		$res = $wpdb->get_results( "SELECT option_name,option_value FROM " . $wpdb->options . " WHERE 
		option_name LIKE 'options_%' AND option_value LIKE 'http%'", ARRAY_A );
		foreach ( $res as $row ) {
			$regexp = '#(https?://.+)#';
			preg_match( $regexp, $row['option_value'], $matches );

			if ( ! empty( $matches[0] ) ) {
				$pattern = "#^" . home_url() . "#";
				if ( ! preg_match( $pattern, $matches[0] ) ) {
					if ( ! in_array( $matches[0], $ar_uniq ) ) {
						$ar[]      = array(
							'type'    => "options",
							'comment' => [
								'type'        => "options",
								'option_name' => $row['option_name'],
							],
							'url'     => $matches[0],
						);
						$ar_uniq[] = $matches[0];
					}
				}
			}
		}

		$this->update_option_all_links( $ar );

		return $ar;
	}

	/**
	 * Обновляем опцию со всеми ссылками
	 *
	 * @param $ar
	 *
	 * @return void
	 */
	public function update_option_all_links( $ar ) {
		$links      = array();
		$prli_links = HB_Link_Manager_Helpers::get_links_prli_links();
		foreach ( $prli_links as $item ) {
			$links[] = $item['url'];
		}
		foreach ( $ar as $item ) {
			$links[] = $item['url'];
		}

		$links = array_unique( $links );
		update_option( 'hb_link_manager_all_links', $links );
	}
}
