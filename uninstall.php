<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * Remove plugin settings and options.
 */
delete_option( 'sarrloop_ai_settings' );
delete_option( 'sarrloop_ai_logs' );
delete_option( 'sarrloop_ai_external_cron_key' );
delete_option( 'sarrloop_ai_schedule_last_runs' );
delete_option( 'sarrloop_ai_image_queue' );
delete_option( 'sarrloop_ai_last_cron_test' );

/*
 * Remove transients.
 */
delete_transient( 'sarrloop_ai_generation_lock' );

/*
 * Clear scheduled cron jobs.
 */
wp_clear_scheduled_hook( 'sarrloop_ai_cron_worker' );
wp_clear_scheduled_hook( 'sarrloop_ai_cron_runner' );
wp_clear_scheduled_hook( 'sarrloop_ai_scheduled_generation' );
wp_clear_scheduled_hook( 'sarrloop_ai_generate_post_image' );

/*
 * Remove plugin-generated post metadata.
 * Generated posts and uploaded media are intentionally preserved.
 */
$sarrloop_ai_meta_keys = array(
	'_sarrloop_ai_meta_description',
	'_sarrloop_ai_focus_keyword',
	'_sarrloop_ai_topic',
	'_sarrloop_ai_generated',
);

foreach ( $sarrloop_ai_meta_keys as $sarrloop_ai_meta_key ) {
	delete_metadata( 'post', 0, $sarrloop_ai_meta_key, '', true );
}