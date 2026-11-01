<?php
/**
 * Plugin Name: Sarrloop AI Content Publisher
 * Description: Generate and publish AI-powered WordPress articles with OpenAI or Gemini, scheduled publishing, topics, promotional links and featured images.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Mohammad Naim Rahman Alvy
 * Author URI: https://sarrloop.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sarrloop-ai-content-publisher
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// Enforce minimum PHP version before loading the plugin.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					esc_html_e(
						'Sarrloop AI Content Publisher requires PHP 7.4 or newer.',
						'sarrloop-ai-content-publisher'
					);
					?>
				</p>
			</div>
			<?php
		}
	);
	return;
}

define( 'SARRLOOP_AI_VERSION', '1.0.0' );
define( 'SARRLOOP_AI_FILE', __FILE__ );
define( 'SARRLOOP_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'SARRLOOP_AI_URL', plugin_dir_url( __FILE__ ) );

/*
 * Load plugin classes.
 */
require_once SARRLOOP_AI_DIR . 'includes/class-sarrloop-logger.php';
require_once SARRLOOP_AI_DIR . 'includes/class-sarrloop-openai.php';
require_once SARRLOOP_AI_DIR . 'includes/class-sarrloop-gemini.php';
require_once SARRLOOP_AI_DIR . 'includes/class-sarrloop-content.php';
require_once SARRLOOP_AI_DIR . 'includes/class-sarrloop-image.php';
require_once SARRLOOP_AI_DIR . 'includes/class-sarrloop-publisher.php';
require_once SARRLOOP_AI_DIR . 'includes/class-sarrloop-cron.php';
require_once SARRLOOP_AI_DIR . 'includes/class-sarrloop-settings.php';
require_once SARRLOOP_AI_DIR . 'includes/class-sarrloop-admin.php';

/**
 * Main plugin class.
 */
final class Sarrloop_AI_Content_Publisher {

	/**
	 * Singleton instance.
	 *
	 * @var Sarrloop_AI_Content_Publisher|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Sarrloop_AI_Content_Publisher
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {

		add_action( 'plugins_loaded', array( $this, 'init' ) );

		register_activation_hook(
			SARRLOOP_AI_FILE,
			array( 'Sarrloop_AI_Cron', 'activate' )
		);

		register_deactivation_hook(
			SARRLOOP_AI_FILE,
			array( 'Sarrloop_AI_Cron', 'deactivate' )
		);
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init() {

		Sarrloop_AI_Settings::init();
		Sarrloop_AI_Admin::init();
		Sarrloop_AI_Cron::init();
	}
}

/**
 * Start plugin.
 */
Sarrloop_AI_Content_Publisher::instance();