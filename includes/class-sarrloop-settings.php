<?php

defined( 'ABSPATH' ) || exit;

/**
 * Settings manager.
 */
class Sarrloop_AI_Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'sarrloop_ai_settings';

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public static function init() {

		add_action(
			'admin_init',
			array( __CLASS__, 'register_settings' )
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {

		return array(

			'provider' => 'openai',

			'openai_api_key'     => '',
			'openai_text_model'  => 'gpt-4o-mini',
			'openai_image_model' => 'dall-e-3',

			'gemini_api_key'     => '',
			'gemini_text_model'  => 'gemini-1.5-flash',
			'gemini_image_model' => 'imagen-3.0-generate-002',

			'site_niche'    => '',
			'promo_heading' => 'Recommended Resource',

			'topic_mode' => 'my_topics',

			'topics' => array(
				array(
					'name'   => 'Website performance optimization',
					'active' => 1,
				),
				array(
					'name'   => 'SEO best practices for beginners',
					'active' => 1,
				),
				array(
					'name'   => 'Website security guide',
					'active' => 1,
				),
			),

			/*
			 * Default to empty links to strictly follow WordPress.org guidelines.
			 * Site owners can enter their own promotional or affiliate links.
			 */
			'links' => array(),

			'schedules' => array(
				array(
					'name'   => 'Morning',
					'time'   => '10:00',
					'active' => 1,
				),
				array(
					'name'   => 'Evening',
					'time'   => '18:00',
					'active' => 1,
				),
			),

			'article_length' => '1500',
			'language'       => 'English',
			'post_status'    => 'draft',
			'author_id'      => 0,
			'category_id'    => 0,

			'generate_seo'       => 1,
			'generate_image'     => 1,
			'add_internal_links' => 1,
			'add_promo_link'     => 0,

			'image_aspect_ratio' => '16:9',
			'image_size'         => '1K',
			'image_style'        => 'professional modern editorial',
			'image_text'         => 'no',

			'max_internal_links' => 3,
		);
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	public static function get() {

		$saved = get_option( self::OPTION, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args(
			$saved,
			self::defaults()
		);
	}

	/**
	 * Get single setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get_value( $key, $default = null ) {

		$settings = self::get();

		return isset( $settings[ $key ] )
			? $settings[ $key ]
			: $default;
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public static function register_settings() {

		register_setting(
			'sarrloop_ai_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {

		$defaults = self::defaults();

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$output = array();

		$output['provider'] = in_array(
			$input['provider'] ?? '',
			array( 'openai', 'gemini' ),
			true
		)
			? $input['provider']
			: 'openai';

		$output['openai_api_key'] = isset( $input['openai_api_key'] )
			? sanitize_text_field( $input['openai_api_key'] )
			: '';

		$output['openai_text_model'] = isset( $input['openai_text_model'] )
			? sanitize_text_field( $input['openai_text_model'] )
			: $defaults['openai_text_model'];

		$output['openai_image_model'] = isset( $input['openai_image_model'] )
			? sanitize_text_field( $input['openai_image_model'] )
			: $defaults['openai_image_model'];

		$output['gemini_api_key'] = isset( $input['gemini_api_key'] )
			? sanitize_text_field( $input['gemini_api_key'] )
			: '';

		$output['gemini_text_model'] = isset( $input['gemini_text_model'] )
			? sanitize_text_field( $input['gemini_text_model'] )
			: $defaults['gemini_text_model'];

		$output['gemini_image_model'] = isset( $input['gemini_image_model'] )
			? sanitize_text_field( $input['gemini_image_model'] )
			: $defaults['gemini_image_model'];

		$output['site_niche'] = isset( $input['site_niche'] )
			? sanitize_text_field( $input['site_niche'] )
			: '';

		$output['promo_heading'] = isset( $input['promo_heading'] )
			? sanitize_text_field( $input['promo_heading'] )
			: $defaults['promo_heading'];

		$output['topic_mode'] = in_array(
			$input['topic_mode'] ?? '',
			array( 'my_topics', 'ai_topics', 'mixed' ),
			true
		)
			? $input['topic_mode']
			: 'my_topics';

		/*
		 * Topics.
		 */
		$output['topics'] = array();

		if ( ! empty( $input['topics'] ) && is_array( $input['topics'] ) ) {

			foreach ( $input['topics'] as $topic ) {

				if ( ! is_array( $topic ) ) {
					continue;
				}

				$name = isset( $topic['name'] )
					? sanitize_text_field( $topic['name'] )
					: '';

				if ( '' === $name ) {
					continue;
				}

				$output['topics'][] = array(
					'name'   => $name,
					'active' => ! empty( $topic['active'] ) ? 1 : 0,
				);
			}
		}

		/*
		 * Links.
		 */
		$output['links'] = array();

		if ( ! empty( $input['links'] ) && is_array( $input['links'] ) ) {

			foreach ( $input['links'] as $link ) {

				if ( ! is_array( $link ) ) {
					continue;
				}

				$text = isset( $link['text'] )
					? sanitize_text_field( $link['text'] )
					: '';

				$url = isset( $link['url'] )
					? esc_url_raw( $link['url'] )
					: '';

				if ( '' === $text || '' === $url ) {
					continue;
				}

				$output['links'][] = array(
					'text'     => $text,
					'url'      => $url,
					'category' => isset( $link['category'] )
						? sanitize_text_field( $link['category'] )
						: '',
					'active' => ! empty( $link['active'] ) ? 1 : 0,
				);
			}
		}

		/*
		 * Schedules.
		 */
		$output['schedules'] = array();

		if ( ! empty( $input['schedules'] ) && is_array( $input['schedules'] ) ) {

			foreach ( $input['schedules'] as $schedule ) {

				if ( ! is_array( $schedule ) ) {
					continue;
				}

				$name = isset( $schedule['name'] )
					? sanitize_text_field( $schedule['name'] )
					: '';

				$time = isset( $schedule['time'] )
					? sanitize_text_field( $schedule['time'] )
					: '';

				if ( '' === $name || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
					continue;
				}

				$output['schedules'][] = array(
					'name'   => $name,
					'time'   => $time,
					'active' => ! empty( $schedule['active'] ) ? 1 : 0,
				);
			}
		}

		$output['article_length'] = isset( $input['article_length'] )
			? absint( $input['article_length'] )
			: 1500;

		$output['article_length'] = max(
			500,
			min( 5000, $output['article_length'] )
		);

		$output['language'] = isset( $input['language'] )
			? sanitize_text_field( $input['language'] )
			: 'English';

		$output['post_status'] = in_array(
			$input['post_status'] ?? '',
			array( 'draft', 'publish', 'pending' ),
			true
		)
			? $input['post_status']
			: 'draft';

		$output['author_id'] = isset( $input['author_id'] )
			? absint( $input['author_id'] )
			: 0;

		$output['category_id'] = isset( $input['category_id'] )
			? absint( $input['category_id'] )
			: 0;

		$output['generate_seo'] = ! empty( $input['generate_seo'] ) ? 1 : 0;
		$output['generate_image'] = ! empty( $input['generate_image'] ) ? 1 : 0;
		$output['add_internal_links'] = ! empty( $input['add_internal_links'] ) ? 1 : 0;
		$output['add_promo_link'] = ! empty( $input['add_promo_link'] ) ? 1 : 0;

		$output['image_aspect_ratio'] = in_array(
			$input['image_aspect_ratio'] ?? '',
			array( '1:1', '16:9', '4:3', '3:2' ),
			true
		)
			? $input['image_aspect_ratio']
			: '16:9';

		$output['image_size'] = in_array(
			$input['image_size'] ?? '',
			array( '512', '1K', '2K', '4K' ),
			true
		)
			? $input['image_size']
			: '1K';

		$output['image_style'] = isset( $input['image_style'] )
			? sanitize_text_field( $input['image_style'] )
			: 'professional';

		$output['image_text'] = in_array(
			$input['image_text'] ?? '',
			array( 'yes', 'no' ),
			true
		)
			? $input['image_text']
			: 'no';

		$output['max_internal_links'] = isset( $input['max_internal_links'] )
			? absint( $input['max_internal_links'] )
			: 3;

		$output['max_internal_links'] = max(
			0,
			min( 10, $output['max_internal_links'] )
		);

		return $output;
	}
}