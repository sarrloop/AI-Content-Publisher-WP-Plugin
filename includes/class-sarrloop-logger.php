<?php

defined( 'ABSPATH' ) || exit;

/**
 * Logger.
 */
class Sarrloop_AI_Logger {

	/**
	 * Log option name.
	 */
	const OPTION = 'sarrloop_ai_logs';

	/**
	 * Add log.
	 *
	 * @param string $message Message.
	 * @param string $type    Log type.
	 * @return void
	 */
	public static function add( $message, $type = 'info' ) {

		$logs = get_option( self::OPTION, array() );

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$logs[] = array(
			'time'    => current_time( 'mysql' ),
			'type'    => sanitize_key( $type ),
			'message' => sanitize_textarea_field( $message ),
		);

		/*
		 * Keep the last 100 records.
		 */
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}

		update_option( self::OPTION, $logs, false );
	}

	/**
	 * Get logs.
	 *
	 * @return array
	 */
	public static function get() {

		$logs = get_option( self::OPTION, array() );

		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Clear logs.
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}
}