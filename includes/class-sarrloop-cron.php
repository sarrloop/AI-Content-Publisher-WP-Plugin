<?php
/**
 * Sarrloop AI External Cron Manager.
 *
 * WordPress Cron is NOT used for scheduled article generation.
 * An external scheduler should call the REST endpoint every 5 minutes.
 *
 * @package Sarrloop_AI_Content
 */

defined( 'ABSPATH' ) || exit;

class Sarrloop_AI_Cron {

	const LOCK_KEY          = 'sarrloop_ai_generation_lock';
	const LAST_RUN_OPTION   = 'sarrloop_ai_schedule_last_runs';
	const IMAGE_QUEUE_OPTION = 'sarrloop_ai_image_queue';
	const CRON_KEY_OPTION   = 'sarrloop_ai_external_cron_key';
	const REST_NAMESPACE    = 'sarrloop-ai/v1';
	const REST_ROUTE        = '/cron';
	const CRON_HOOK         = 'sarrloop_ai_cron_worker';
	const CRON_RECURRENCE   = 'sarrloop_ai_five_minutes';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_worker' ) );
		add_action( 'sarrloop_ai_test_cron', array( __CLASS__, 'test_cron' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_RECURRENCE, self::CRON_HOOK );
		}
	}

	public static function add_cron_interval( $schedules ) {
		$schedules[ self::CRON_RECURRENCE ] = array(
			'interval' => 300,
			'display'  => esc_html__( 'Every 5 Minutes', 'sarrloop-ai-content-publisher' ),
		);
		return $schedules;
	}

	public static function cron_worker() {
		self::process();
	}

	public static function activate() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );

		if ( ! get_option( self::CRON_KEY_OPTION ) ) {
			update_option( self::CRON_KEY_OPTION, wp_generate_password( 48, false, false ), false );
		}

		if ( ! get_option( self::LAST_RUN_OPTION ) ) {
			update_option( self::LAST_RUN_OPTION, array(), false );
		}

		if ( ! get_option( self::IMAGE_QUEUE_OPTION ) ) {
			update_option( self::IMAGE_QUEUE_OPTION, array(), false );
		}

		self::clear_old_wp_cron_events();
		delete_transient( self::LOCK_KEY );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_RECURRENCE, self::CRON_HOOK );
		}

		Sarrloop_AI_Logger::add( 'Plugin activated. Background cron and external REST endpoint ready.', 'success' );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		self::clear_old_wp_cron_events();
		delete_transient( self::LOCK_KEY );
		Sarrloop_AI_Logger::add( 'Sarrloop AI scheduled workers stopped.', 'info' );
	}

	private static function clear_old_wp_cron_events() {
		foreach ( array( 'sarrloop_ai_scheduled_generation', 'sarrloop_ai_generate_post_image' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	public static function get_cron_key() {
		$key = get_option( self::CRON_KEY_OPTION, '' );
		if ( ! $key ) {
			$key = wp_generate_password( 48, false, false );
			update_option( self::CRON_KEY_OPTION, $key, false );
		}
		return $key;
	}

	public static function get_cron_url() {
		return add_query_arg(
			'sarrloop_cron_key',
			rawurlencode( self::get_cron_key() ),
			rest_url( self::REST_NAMESPACE . self::REST_ROUTE )
		);
	}

	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'rest_cron' ),
					'permission_callback' => array( __CLASS__, 'rest_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'rest_cron' ),
					'permission_callback' => array( __CLASS__, 'rest_permission' ),
				),
			)
		);
	}

	public static function rest_permission( WP_REST_Request $request ) {
		$secret = self::get_cron_key();
		$provided = $request->get_param( 'sarrloop_cron_key' );

		if ( $provided && hash_equals( $secret, (string) $provided ) ) {
			return true;
		}

		$authorization = '';
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$authorization = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		} elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$authorization = sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		if ( 0 === strpos( $authorization, 'Bearer ' ) ) {
			$provided = trim( substr( $authorization, 7 ) );
			if ( $provided && hash_equals( $secret, $provided ) ) {
				return true;
			}
		}

		return new WP_Error(
			'sarrloop_cron_unauthorized',
			__( 'Invalid Sarrloop AI cron key.', 'sarrloop-ai-content-publisher' ),
			array( 'status' => 401 )
		);
	}

	public static function rest_cron( WP_REST_Request $request ) {
		$started = microtime( true );

		ignore_user_abort( true );

		$result = self::process();
		$result['execution_time'] = round( microtime( true ) - $started, 2 );

		return new WP_REST_Response( $result, 200 );
	}

	public static function process() {
		if ( get_transient( self::LOCK_KEY ) ) {
			return array(
				'success' => true,
				'status'  => 'locked',
				'message' => 'Another Sarrloop AI job is already running.',
			);
		}

		$queue = get_option( self::IMAGE_QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) ) {
			$queue = array();
		}

		/* Finish one pending image before starting a new article. */
		if ( ! empty( $queue ) ) {
			$post_id = absint( reset( $queue ) );
			unset( $queue[ array_key_first( $queue ) ] );
			update_option( self::IMAGE_QUEUE_OPTION, array_values( $queue ), false );

			set_transient( self::LOCK_KEY, time(), 15 * MINUTE_IN_SECONDS );
			try {
				$result = self::generate_post_image( $post_id );
			} catch ( Throwable $e ) {
				$result = new WP_Error( 'image_exception', $e->getMessage() );
			} finally {
				delete_transient( self::LOCK_KEY );
			}

			if ( is_wp_error( $result ) ) {
				/* Put it back so a later external cron call can retry. */
				$retry_queue = get_option( self::IMAGE_QUEUE_OPTION, array() );
				if ( ! is_array( $retry_queue ) ) {
					$retry_queue = array();
				}
				$retry_queue[] = $post_id;
				update_option( self::IMAGE_QUEUE_OPTION, array_values( array_unique( $retry_queue ) ), false );
				return array(
					'success' => false,
					'status'  => 'image_failed',
					'post_id' => $post_id,
					'message' => $result->get_error_message(),
				);
			}

			return array(
				'success' => true,
				'status'  => 'image_completed',
				'post_id' => $post_id,
				'image_id' => absint( $result ),
				'message' => 'Featured image generated successfully.',
			);
		}

		$settings = Sarrloop_AI_Settings::get();
		$schedules = ! empty( $settings['schedules'] ) ? $settings['schedules'] : array();
		$due = array();

		foreach ( $schedules as $index => $schedule ) {
			if ( ! empty( $schedule['active'] ) && ! empty( $schedule['time'] ) && self::is_schedule_due( $index, $schedule ) ) {
				$due[] = array( 'index' => absint( $index ), 'schedule' => $schedule );
			}
		}

		if ( empty( $due ) ) {
			return array(
				'success' => true,
				'status'  => 'nothing_due',
				'message' => 'No schedule is due.',
			);
		}

		$item = $due[0];
		set_transient( self::LOCK_KEY, time(), 15 * MINUTE_IN_SECONDS );

		try {
			$result = self::run_schedule( $item['index'], $item['schedule'] );
		} catch ( Throwable $e ) {
			$result = array(
				'success' => false,
				'status'  => 'error',
				'message' => $e->getMessage(),
			);
			Sarrloop_AI_Logger::add( 'External cron fatal error: ' . $e->getMessage(), 'error' );
		} finally {
			delete_transient( self::LOCK_KEY );
		}

		return $result;
	}

	private static function is_schedule_due( $index, $schedule ) {
		$time = sanitize_text_field( $schedule['time'] );
		if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			return false;
		}

		$now = new DateTime( 'now', wp_timezone() );
		list( $hour, $minute ) = array_map( 'intval', explode( ':', $time ) );
		$current_minutes = ( (int) $now->format( 'H' ) * 60 ) + (int) $now->format( 'i' );
		$scheduled_minutes = ( $hour * 60 ) + $minute;

		if ( $current_minutes < $scheduled_minutes ) {
			return false;
		}

		$last_runs = get_option( self::LAST_RUN_OPTION, array() );
		$last_run = is_array( $last_runs ) && isset( $last_runs[ $index ] ) ? $last_runs[ $index ] : '';

		return ! $last_run || 0 !== strpos( $last_run, $now->format( 'Y-m-d' ) );
	}

	private static function run_schedule( $schedule_index, $schedule ) {
		$name = ! empty( $schedule['name'] ) ? sanitize_text_field( $schedule['name'] ) : 'Schedule #' . $schedule_index;

		Sarrloop_AI_Logger::add( 'Starting external scheduled article generation: ' . $name, 'info' );

		$article = Sarrloop_AI_Content::generate();
		if ( is_wp_error( $article ) ) {
			Sarrloop_AI_Logger::add( 'Scheduled article generation failed: ' . $article->get_error_message(), 'error' );
			return array( 'success' => false, 'status' => 'generation_failed', 'message' => $article->get_error_message() );
		}

		/*
		 * Publish the article first. Featured-image generation is performed
		 * in the SAME cron request so the article is complete after one run.
		 *
		 * If image generation fails, the post is still published and the post
		 * ID is queued for retry on the next cron request.
		 */
		$settings = Sarrloop_AI_Settings::get();
		$generate_image = ! empty( $settings['generate_image'] );

		$post_id = Sarrloop_AI_Publisher::publish( $article, false, false );
		if ( is_wp_error( $post_id ) ) {
			Sarrloop_AI_Logger::add( 'Scheduled publishing failed: ' . $post_id->get_error_message(), 'error' );
			return array( 'success' => false, 'status' => 'publishing_failed', 'message' => $post_id->get_error_message() );
		}

		$post_id = absint( $post_id );
		$image_id = 0;
		$image_queued = false;
		$image_error = '';

		if ( $generate_image ) {
			Sarrloop_AI_Logger::add(
				sprintf( 'Generating featured image immediately for scheduled post #%d.', $post_id ),
				'info'
			);

			try {
				$image_result = self::generate_post_image( $post_id );
			} catch ( Throwable $e ) {
				$image_result = new WP_Error( 'image_exception', $e->getMessage() );
			}

			if ( is_wp_error( $image_result ) ) {
				$image_error = $image_result->get_error_message();

				/* Queue only failed images for the next cron run. */
				$queue = get_option( self::IMAGE_QUEUE_OPTION, array() );
				if ( ! is_array( $queue ) ) {
					$queue = array();
				}

				$queue[] = $post_id;
				update_option(
					self::IMAGE_QUEUE_OPTION,
					array_values( array_unique( array_map( 'absint', $queue ) ) ),
					false
				);
				$image_queued = true;

				Sarrloop_AI_Logger::add(
					sprintf(
						'Featured image failed for post #%d and was queued for retry: %s',
						$post_id,
						$image_error
					),
					'warning'
				);
			} else {
				$image_id = absint( $image_result );
			}
		}

		/* Mark the schedule as completed only after the article has been created. */
		self::mark_schedule_run( $schedule_index );

		Sarrloop_AI_Logger::add(
			sprintf(
			'External scheduled article completed. Post ID: %d%s.',
			$post_id,
			$image_id ? ' Featured image ID: ' . $image_id : ( $image_queued ? ' Featured image queued for retry.' : '' )
		),
		$image_queued ? 'warning' : 'success'
		);

		return array(
			'success'       => true,
			'status'        => $image_queued ? 'article_completed_image_queued' : 'article_completed',
			'schedule'      => $name,
			'post_id'       => $post_id,
			'image_id'      => $image_id,
			'image_queued'  => $image_queued,
			'image_error'   => $image_error,
			'message'       => $image_queued
				? 'Article generated, but featured image failed and was queued for retry.'
				: 'Article and featured image generated successfully.',
		);
	}

	private static function mark_schedule_run( $schedule_index ) {
		$last_runs = get_option( self::LAST_RUN_OPTION, array() );
		if ( ! is_array( $last_runs ) ) {
			$last_runs = array();
		}
		$last_runs[ $schedule_index ] = current_time( 'mysql' );
		update_option( self::LAST_RUN_OPTION, $last_runs, false );
	}

	public static function generate_post_image( $post_id ) {
		$post_id = absint( $post_id );
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found.' );
		}

		$topic = get_post_meta( $post_id, '_sarrloop_ai_topic', true );
		if ( ! $topic ) {
			$topic = $post->post_title;
		}

		Sarrloop_AI_Logger::add( 'Generating featured image for post #' . $post_id, 'info' );
		$image_id = Sarrloop_AI_Image::generate( $post->post_title, $topic );

		if ( is_wp_error( $image_id ) ) {
			Sarrloop_AI_Logger::add( 'Scheduled image generation failed for post #' . $post_id . ': ' . $image_id->get_error_message(), 'error' );
			return $image_id;
		}

		$image_id = absint( $image_id );
		if ( ! $image_id ) {
			return new WP_Error( 'invalid_image_id', 'Image generation returned an invalid attachment ID.' );
		}

		set_post_thumbnail( $post_id, $image_id );
		Sarrloop_AI_Logger::add( 'Featured image attached successfully to post #' . $post_id . '. Image ID: ' . $image_id, 'success' );
		return $image_id;
	}

	public static function get_status() {
		$settings = Sarrloop_AI_Settings::get();
		$schedules = ! empty( $settings['schedules'] ) ? $settings['schedules'] : array();
		$last_runs = get_option( self::LAST_RUN_OPTION, array() );
		$result = array();

		foreach ( $schedules as $index => $schedule ) {
			$name = ! empty( $schedule['name'] ) ? sanitize_text_field( $schedule['name'] ) : 'Schedule #' . $index;
			$time = ! empty( $schedule['time'] ) ? sanitize_text_field( $schedule['time'] ) : '';
			$result[] = array(
				'name'     => $name,
				'time'     => $time,
				'active'   => ! empty( $schedule['active'] ),
				'last_run' => is_array( $last_runs ) && isset( $last_runs[ $index ] ) ? $last_runs[ $index ] : false,
				'next_run' => self::get_next_run( $index, $schedule ),
			);
		}
		return $result;
	}

	private static function get_next_run( $index, $schedule ) {
		if ( empty( $schedule['active'] ) || empty( $schedule['time'] ) ) {
			return false;
		}
		try {
			$timezone = wp_timezone();
			$now = new DateTime( 'now', $timezone );
			$target = new DateTime( $now->format( 'Y-m-d' ) . ' ' . $schedule['time'] . ':00', $timezone );
			$last_runs = get_option( self::LAST_RUN_OPTION, array() );
			$last_run = is_array( $last_runs ) && isset( $last_runs[ $index ] ) ? $last_runs[ $index ] : '';
			if ( $last_run && 0 === strpos( $last_run, $now->format( 'Y-m-d' ) ) || $target->getTimestamp() <= $now->getTimestamp() ) {
				$target->modify( '+1 day' );
			}
			return $target->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			return false;
		}
	}

	public static function test_cron() {
		Sarrloop_AI_Logger::add( 'SUCCESS: Sarrloop AI external cron test executed.', 'success' );
		update_option( 'sarrloop_ai_last_cron_test', current_time( 'mysql' ), false );
	}
}
