<?php

defined( 'ABSPATH' ) || exit;

/**
 * Admin interface.
 */
class Sarrloop_AI_Admin {

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public static function init() {

		add_action(
			'admin_menu',
			array( __CLASS__, 'menu' )
		);

		add_action(
			'admin_enqueue_scripts',
			array( __CLASS__, 'assets' )
		);

		add_action(
            'wp_ajax_sarrloop_generate_now',
            array( __CLASS__, 'generate_now' )
        );

        add_action(
            'wp_ajax_sarrloop_generate_featured_image',
            array( __CLASS__, 'generate_featured_image' )
        );

        add_action(
            'wp_ajax_sarrloop_clear_logs',
            array( __CLASS__, 'clear_logs' )
        );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public static function menu() {

		add_menu_page(
			__( 'Sarrloop AI Content', 'sarrloop-ai-content-publisher' ),
			__( 'Sarrloop AI', 'sarrloop-ai-content-publisher' ),
			'manage_options',
			'sarrloop-ai-content-publisher',
			array( __CLASS__, 'page' ),
			SARRLOOP_AI_URL . 'assets/sarrloop.png',
			58
		);
	}

	/**
	 * Load assets.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public static function assets( $hook ) {

		if ( 'toplevel_page_sarrloop-ai-content-publisher' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'sarrloop-ai-admin',
			SARRLOOP_AI_URL . 'admin/css/admin.css',
			array(),
			SARRLOOP_AI_VERSION
		);

		wp_enqueue_script(
			'sarrloop-ai-admin',
			SARRLOOP_AI_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			SARRLOOP_AI_VERSION,
			true
		);

		wp_localize_script(
			'sarrloop-ai-admin',
			'SarrloopAI',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'sarrloop_ai_admin' ),
				'confirmClearLogs'   => __( 'Are you sure you want to clear the logs?', 'sarrloop-ai-content-publisher' ),
				'generating'         => __( 'Generating...', 'sarrloop-ai-content-publisher' ),
				'generatingNotice'   => __( 'Generating article... Please wait.', 'sarrloop-ai-content-publisher' ),
				'generateBtn'        => __( 'Generate Article Now', 'sarrloop-ai-content-publisher' ),
				'successTitle'       => __( 'Success!', 'sarrloop-ai-content-publisher' ),
				'editArticle'        => __( 'Edit Article', 'sarrloop-ai-content-publisher' ),
				'generatingImage'    => __( 'Generating featured image...', 'sarrloop-ai-content-publisher' ),
				'generationFailed'   => __( 'Generation failed:', 'sarrloop-ai-content-publisher' ),
				'imageSuccess'       => __( 'Featured image added successfully.', 'sarrloop-ai-content-publisher' ),
				'imageFailed'        => __( 'Featured image generation failed.', 'sarrloop-ai-content-publisher' ),
				'unableToClearLogs'  => __( 'Unable to clear logs.', 'sarrloop-ai-content-publisher' ),
				'timeoutError'       => __( 'The request took too long. The article may already have been created. Please check Posts before trying again.', 'sarrloop-ai-content-publisher' ),
				'serverError'        => __( 'WordPress returned a server error (HTTP 500). Please check your error log.', 'sarrloop-ai-content-publisher' ),
				'permissionError'    => __( 'Permission or security check failed (HTTP 403).', 'sarrloop-ai-content-publisher' ),
				'notFoundError'      => __( 'The AJAX endpoint was not found (HTTP 404).', 'sarrloop-ai-content-publisher' ),
				'communicationError' => __( 'Unable to communicate with the server.', 'sarrloop-ai-content-publisher' ),
				'topicPlaceholder'   => __( 'e.g. Website performance optimization', 'sarrloop-ai-content-publisher' ),
				'btnTextPlaceholder' => __( 'Button text', 'sarrloop-ai-content-publisher' ),
				'categoryPlaceholder'=> __( 'Optional category', 'sarrloop-ai-content-publisher' ),
				'schedulePlaceholder'=> __( 'Schedule name', 'sarrloop-ai-content-publisher' ),
				'activeLabel'        => __( 'Active', 'sarrloop-ai-content-publisher' ),
				'removeLabel'        => __( 'Remove', 'sarrloop-ai-content-publisher' ),
			)
		);
	}

	/**
	 * Admin page.
	 *
	 * @return void
	 */
	public static function page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Sarrloop_AI_Settings::get();

		?>
		<div class="wrap sarrloop-ai-wrap">

			<?php settings_errors( 'sarrloop_ai_settings_group' ); ?>

			<div class="sarrloop-ai-header">
				<div>
					<h1><?php esc_html_e( 'Sarrloop AI Content Publisher', 'sarrloop-ai-content-publisher' ); ?></h1>
					<p>
						<?php
						esc_html_e(
							'Automatically generate SEO-friendly WordPress content using OpenAI or Gemini.',
							'sarrloop-ai-content-publisher'
						);
						?>
					</p>
				</div>

				<button type="button"
					class="button button-primary sarrloop-generate-now">
					<?php esc_html_e( 'Generate Article Now', 'sarrloop-ai-content-publisher' ); ?>
				</button>
			</div>

			<div id="sarrloop-generate-result"></div>

			<form method="post"
				action="options.php"
				id="sarrloop-ai-settings-form">

				<?php
				settings_fields( 'sarrloop_ai_settings_group' );
				?>

				<div class="sarrloop-ai-grid">

					<?php self::render_provider_section( $settings ); ?>

					<?php self::render_content_section( $settings ); ?>

					<?php self::render_topics_section( $settings ); ?>

					<?php self::render_links_section( $settings ); ?>

					<?php self::render_schedules_section( $settings ); ?>

					<?php self::render_image_section( $settings ); ?>

				</div>

				<?php submit_button( __( 'Save Settings', 'sarrloop-ai-content-publisher' ) ); ?>

			</form>

			<?php self::render_external_cron_section(); ?>

			<?php self::render_logs(); ?>

		</div>
		<?php
	}

	/**
	 * Provider settings.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_provider_section( $settings ) {

		?>
		<div class="sarrloop-card">

			<h2><?php esc_html_e( 'AI Provider', 'sarrloop-ai-content-publisher' ); ?></h2>

			<table class="form-table">

				<tr>
					<th><?php esc_html_e( 'Provider', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<select name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[provider]">
							<option value="openai" <?php selected( $settings['provider'], 'openai' ); ?>>
								OpenAI
							</option>

							<option value="gemini" <?php selected( $settings['provider'], 'gemini' ); ?>>
								Google Gemini
							</option>
						</select>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'OpenAI API Key', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<input
							type="password"
							class="regular-text"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[openai_api_key]"
							value="<?php echo esc_attr( $settings['openai_api_key'] ); ?>"
							autocomplete="off"
						/>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'OpenAI Text Model', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<input
							type="text"
							class="regular-text"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[openai_text_model]"
							value="<?php echo esc_attr( $settings['openai_text_model'] ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'e.g. gpt-4o-mini, gpt-4o, o1-mini, o3-mini. Reasoning models and standard models are fully supported.', 'sarrloop-ai-content-publisher' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'OpenAI Image Model', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<input
							type="text"
							class="regular-text"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[openai_image_model]"
							value="<?php echo esc_attr( $settings['openai_image_model'] ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'e.g. gpt-image-1, dall-e-3. GPT image models and DALL-E models are automatically formatted.', 'sarrloop-ai-content-publisher' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Gemini API Key', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<input
							type="password"
							class="regular-text"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[gemini_api_key]"
							value="<?php echo esc_attr( $settings['gemini_api_key'] ); ?>"
							autocomplete="off"
						/>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Gemini Text Model', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<input
							type="text"
							class="regular-text"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[gemini_text_model]"
							value="<?php echo esc_attr( $settings['gemini_text_model'] ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'e.g. gemini-1.5-flash, gemini-1.5-pro, gemini-2.0-flash.', 'sarrloop-ai-content-publisher' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Gemini Image Model', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<input
							type="text"
							class="regular-text"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[gemini_image_model]"
							value="<?php echo esc_attr( $settings['gemini_image_model'] ); ?>"
						/>
						<p class="description">
							<?php esc_html_e( 'e.g. imagen-3.0-generate-002. Supports both Imagen and multimodal Gemini image models.', 'sarrloop-ai-content-publisher' ); ?>
						</p>
					</td>
				</tr>

			</table>

		</div>
		<?php
	}

	/**
	 * Content section.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_content_section( $settings ) {

		$users = get_users(
			array(
				'capability' => 'edit_posts',
			)
		);

		$categories = get_categories(
			array(
				'hide_empty' => false,
			)
		);

		?>
		<div class="sarrloop-card">

			<h2><?php esc_html_e( 'Content Settings', 'sarrloop-ai-content-publisher' ); ?></h2>

			<table class="form-table">

				<tr>
					<th><?php esc_html_e( 'Website Focus / Niche', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<input
							type="text"
							class="regular-text"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[site_niche]"
							value="<?php echo esc_attr( $settings['site_niche'] ?? '' ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. Real Estate, Health, WordPress, etc.', 'sarrloop-ai-content-publisher' ); ?>"
						/>

						<p class="description">
							<?php esc_html_e( 'Optional. Guides AI content and topic generation to fit your specific website.', 'sarrloop-ai-content-publisher' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Article Length', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<input
							type="number"
							min="500"
							max="5000"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[article_length]"
							value="<?php echo esc_attr( $settings['article_length'] ); ?>"
						/>

						<p class="description">
							<?php esc_html_e( '500 - 5000 words.', 'sarrloop-ai-content-publisher' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Language', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<input
							type="text"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[language]"
							value="<?php echo esc_attr( $settings['language'] ); ?>"
						/>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Post Status', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<select name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[post_status]">
							<option value="draft" <?php selected( $settings['post_status'], 'draft' ); ?>>
								<?php esc_html_e( 'Draft', 'sarrloop-ai-content-publisher' ); ?>
							</option>

							<option value="publish" <?php selected( $settings['post_status'], 'publish' ); ?>>
								<?php esc_html_e( 'Publish', 'sarrloop-ai-content-publisher' ); ?>
							</option>

							<option value="pending" <?php selected( $settings['post_status'], 'pending' ); ?>>
								<?php esc_html_e( 'Pending Review', 'sarrloop-ai-content-publisher' ); ?>
							</option>
						</select>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Author', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<select name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[author_id]">

							<option value="0">
								<?php esc_html_e( 'Current user', 'sarrloop-ai-content-publisher' ); ?>
							</option>

							<?php foreach ( $users as $user ) : ?>

								<option
									value="<?php echo esc_attr( $user->ID ); ?>"
									<?php selected( $settings['author_id'], $user->ID ); ?>
								>
									<?php echo esc_html( $user->display_name ); ?>
								</option>

							<?php endforeach; ?>

						</select>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Category', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<select name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[category_id]">

							<option value="0">
								<?php esc_html_e( 'Default category', 'sarrloop-ai-content-publisher' ); ?>
							</option>

							<?php foreach ( $categories as $category ) : ?>

								<option
									value="<?php echo esc_attr( $category->term_id ); ?>"
									<?php selected( $settings['category_id'], $category->term_id ); ?>
								>
									<?php echo esc_html( $category->name ); ?>
								</option>

							<?php endforeach; ?>

						</select>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Features', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>

						<label>
							<input
								type="checkbox"
								name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[generate_seo]"
								value="1"
								<?php checked( $settings['generate_seo'], 1 ); ?>
							/>
							<?php esc_html_e( 'Generate SEO metadata', 'sarrloop-ai-content-publisher' ); ?>
						</label>

						<br>

						<label>
							<input
								type="checkbox"
								name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[generate_image]"
								value="1"
								<?php checked( $settings['generate_image'], 1 ); ?>
							/>
							<?php esc_html_e( 'Generate featured image', 'sarrloop-ai-content-publisher' ); ?>
						</label>

						<br>

						<label>
							<input
								type="checkbox"
								name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[add_internal_links]"
								value="1"
								<?php checked( $settings['add_internal_links'], 1 ); ?>
							/>
							<?php esc_html_e( 'Add internal links', 'sarrloop-ai-content-publisher' ); ?>
						</label>

						<br>

						<label>
							<input
								type="checkbox"
								name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[add_promo_link]"
								value="1"
								<?php checked( $settings['add_promo_link'], 1 ); ?>
							/>
							<?php esc_html_e( 'Add promotional CTA', 'sarrloop-ai-content-publisher' ); ?>
						</label>

					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Maximum Internal Links', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<input
							type="number"
							min="0"
							max="10"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[max_internal_links]"
							value="<?php echo esc_attr( $settings['max_internal_links'] ); ?>"
						/>
					</td>
				</tr>

			</table>

		</div>
		<?php
	}

	/**
	 * Topics.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_topics_section( $settings ) {

		?>
		<div class="sarrloop-card">

			<h2><?php esc_html_e( 'Topics', 'sarrloop-ai-content-publisher' ); ?></h2>

			<table class="form-table">

				<tr>
					<th><?php esc_html_e( 'Topic Mode', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>

						<select name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[topic_mode]">

							<option
								value="my_topics"
								<?php selected( $settings['topic_mode'], 'my_topics' ); ?>
							>
								<?php esc_html_e( 'My Topics', 'sarrloop-ai-content-publisher' ); ?>
							</option>

							<option
								value="ai_topics"
								<?php selected( $settings['topic_mode'], 'ai_topics' ); ?>
							>
								<?php esc_html_e( 'AI Generated Topics', 'sarrloop-ai-content-publisher' ); ?>
							</option>

							<option
								value="mixed"
								<?php selected( $settings['topic_mode'], 'mixed' ); ?>
							>
								<?php esc_html_e( 'My Topics + AI Generated', 'sarrloop-ai-content-publisher' ); ?>
							</option>

						</select>

					</td>
				</tr>

			</table>

			<div class="sarrloop-repeater" data-repeater="topics">

				<?php
				$topics = ! empty( $settings['topics'] )
					? $settings['topics']
					: array(
						array(
							'name'   => '',
							'active' => 1,
						),
					);

				foreach ( $topics as $index => $topic ) :
					?>

					<div class="sarrloop-repeater-row">

						<input
							type="text"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[topics][<?php echo esc_attr( $index ); ?>][name]"
							value="<?php echo esc_attr( $topic['name'] ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. Website performance optimization', 'sarrloop-ai-content-publisher' ); ?>"
						/>

						<label>
							<input
								type="checkbox"
								name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[topics][<?php echo esc_attr( $index ); ?>][active]"
								value="1"
								<?php checked( ! empty( $topic['active'] ) ); ?>
							/>
							<?php esc_html_e( 'Active', 'sarrloop-ai-content-publisher' ); ?>
						</label>

						<button type="button" class="button sarrloop-remove-row">
							<?php esc_html_e( 'Remove', 'sarrloop-ai-content-publisher' ); ?>
						</button>

					</div>

				<?php endforeach; ?>

			</div>

			<button
				type="button"
				class="button sarrloop-add-row"
				data-type="topics"
			>
				<?php esc_html_e( '+ Add Topic', 'sarrloop-ai-content-publisher' ); ?>
			</button>

		</div>
		<?php
	}

	/**
	 * Links.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_links_section( $settings ) {

		?>
		<div class="sarrloop-card">

			<h2><?php esc_html_e( 'Promotional Links', 'sarrloop-ai-content-publisher' ); ?></h2>

			<p class="description">
				<?php esc_html_e( 'Optional: Add your own promotional or affiliate links to recommend at the end of generated articles.', 'sarrloop-ai-content-publisher' ); ?>
			</p>

			<table class="form-table" style="margin-bottom:15px;">
				<tr>
					<th><?php esc_html_e( 'CTA Heading', 'sarrloop-ai-content-publisher' ); ?></th>
					<td>
						<input
							type="text"
							class="regular-text"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[promo_heading]"
							value="<?php echo esc_attr( $settings['promo_heading'] ?? __( 'Recommended Resource', 'sarrloop-ai-content-publisher' ) ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. Recommended Resource', 'sarrloop-ai-content-publisher' ); ?>"
						/>
					</td>
				</tr>
			</table>

			<div class="sarrloop-repeater" data-repeater="links">

				<?php
				$links = ! empty( $settings['links'] )
					? $settings['links']
					: array();

				if ( empty( $links ) ) :
					?>
					<p class="description sarrloop-no-links-note">
						<?php esc_html_e( 'No promotional links added yet. Click "+ Add Link" below to add your custom links.', 'sarrloop-ai-content-publisher' ); ?>
					</p>
				<?php
				else :
					foreach ( $links as $index => $link ) :
						?>

						<div class="sarrloop-repeater-row sarrloop-link-row">

							<input
								type="text"
								name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[links][<?php echo esc_attr( $index ); ?>][text]"
								value="<?php echo esc_attr( $link['text'] ); ?>"
								placeholder="<?php esc_attr_e( 'Button text', 'sarrloop-ai-content-publisher' ); ?>"
							/>

							<input
								type="url"
								name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[links][<?php echo esc_attr( $index ); ?>][url]"
								value="<?php echo esc_attr( $link['url'] ); ?>"
								placeholder="https://example.com"
							/>

							<input
								type="text"
								name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[links][<?php echo esc_attr( $index ); ?>][category]"
								value="<?php echo esc_attr( $link['category'] ); ?>"
								placeholder="<?php esc_attr_e( 'Optional category', 'sarrloop-ai-content-publisher' ); ?>"
							/>

							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[links][<?php echo esc_attr( $index ); ?>][active]"
									value="1"
									<?php checked( ! empty( $link['active'] ) ); ?>
								/>
								<?php esc_html_e( 'Active', 'sarrloop-ai-content-publisher' ); ?>
							</label>

							<button type="button" class="button sarrloop-remove-row">
								<?php esc_html_e( 'Remove', 'sarrloop-ai-content-publisher' ); ?>
							</button>

						</div>

					<?php endforeach; endif; ?>

			</div>

			<button
				type="button"
				class="button sarrloop-add-row"
				data-type="links"
			>
				<?php esc_html_e( '+ Add Link', 'sarrloop-ai-content-publisher' ); ?>
			</button>

		</div>
		<?php
	}

	/**
	 * Schedules.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_schedules_section( $settings ) {

		?>
		<div class="sarrloop-card">

			<h2><?php esc_html_e( 'Publishing Schedule', 'sarrloop-ai-content-publisher' ); ?></h2>

			<p class="description">
				<?php esc_html_e( 'Add multiple publishing times. Each schedule can be paused independently.', 'sarrloop-ai-content-publisher' ); ?>
			</p>

			<div class="sarrloop-repeater" data-repeater="schedules">

				<?php
				$schedules = ! empty( $settings['schedules'] )
					? $settings['schedules']
					: array(
						array(
							'name'   => 'Morning',
							'time'   => '10:00',
							'active' => 1,
						),
					);

				foreach ( $schedules as $index => $schedule ) :
					?>

					<div class="sarrloop-repeater-row">

						<input
							type="text"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[schedules][<?php echo esc_attr( $index ); ?>][name]"
							value="<?php echo esc_attr( $schedule['name'] ); ?>"
							placeholder="<?php esc_attr_e( 'Schedule name', 'sarrloop-ai-content-publisher' ); ?>"
						/>

						<input
							type="time"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[schedules][<?php echo esc_attr( $index ); ?>][time]"
							value="<?php echo esc_attr( $schedule['time'] ); ?>"
						/>

						<label>
							<input
								type="checkbox"
								name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[schedules][<?php echo esc_attr( $index ); ?>][active]"
								value="1"
								<?php checked( ! empty( $schedule['active'] ) ); ?>
							/>
							<?php esc_html_e( 'Active', 'sarrloop-ai-content-publisher' ); ?>
						</label>

						<button type="button" class="button sarrloop-remove-row">
							<?php esc_html_e( 'Remove', 'sarrloop-ai-content-publisher' ); ?>
						</button>

					</div>

				<?php endforeach; ?>

			</div>

			<button
				type="button"
				class="button sarrloop-add-row"
				data-type="schedules"
			>
				<?php esc_html_e( '+ Add Schedule', 'sarrloop-ai-content-publisher' ); ?>
			</button>

			<p class="description">
				<?php esc_html_e( 'WordPress timezone:', 'sarrloop-ai-content-publisher' ); ?>
				<strong><?php echo esc_html( wp_timezone_string() ); ?></strong>
			</p>

		</div>
		<?php
	}

	/**
	 * Image settings.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function render_image_section( $settings ) {

		?>
		<div class="sarrloop-card">

			<h2><?php esc_html_e( 'Featured Image', 'sarrloop-ai-content-publisher' ); ?></h2>

			<table class="form-table">

				<tr>
					<th><?php esc_html_e( 'Aspect Ratio', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>

						<select name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[image_aspect_ratio]">

							<option value="16:9" <?php selected( $settings['image_aspect_ratio'], '16:9' ); ?>>
								<?php esc_html_e( '16:9 Landscape', 'sarrloop-ai-content-publisher' ); ?>
							</option>

							<option value="1:1" <?php selected( $settings['image_aspect_ratio'], '1:1' ); ?>>
								<?php esc_html_e( '1:1 Square', 'sarrloop-ai-content-publisher' ); ?>
							</option>

							<option value="4:3" <?php selected( $settings['image_aspect_ratio'], '4:3' ); ?>>
								<?php esc_html_e( '4:3', 'sarrloop-ai-content-publisher' ); ?>
							</option>

							<option value="3:2" <?php selected( $settings['image_aspect_ratio'], '3:2' ); ?>>
								<?php esc_html_e( '3:2', 'sarrloop-ai-content-publisher' ); ?>
							</option>

						</select>

					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Image Style', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>
						<input
							type="text"
							class="regular-text"
							name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[image_style]"
							value="<?php echo esc_attr( $settings['image_style'] ); ?>"
							placeholder="<?php esc_attr_e( 'Professional modern editorial', 'sarrloop-ai-content-publisher' ); ?>"
						/>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Image Text', 'sarrloop-ai-content-publisher' ); ?></th>

					<td>

						<select name="<?php echo esc_attr( Sarrloop_AI_Settings::OPTION ); ?>[image_text]">

							<option value="no" <?php selected( $settings['image_text'], 'no' ); ?>>
								<?php esc_html_e( 'No text', 'sarrloop-ai-content-publisher' ); ?>
							</option>

							<option value="yes" <?php selected( $settings['image_text'], 'yes' ); ?>>
								<?php esc_html_e( 'Allow minimal text', 'sarrloop-ai-content-publisher' ); ?>
							</option>

						</select>

					</td>
				</tr>

			</table>

		</div>
		<?php
	}

	/**
	 * External cron settings/status.
	 *
	 * @return void
	 */
	private static function render_external_cron_section() {
		$cron_url = Sarrloop_AI_Cron::get_cron_url();
		$status   = Sarrloop_AI_Cron::get_status();
		$queue    = get_option( Sarrloop_AI_Cron::IMAGE_QUEUE_OPTION, array() );

		?>
		<div class="sarrloop-card">
			<h2><?php esc_html_e( 'Automated Publishing & Cron', 'sarrloop-ai-content-publisher' ); ?></h2>
			<p>
				<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span>
				<strong><?php esc_html_e( 'WordPress background cron is active and scheduled every 5 minutes.', 'sarrloop-ai-content-publisher' ); ?></strong>
			</p>
			<p class="description">
				<?php esc_html_e( 'Optional External Cron: If your web host disables WP-Cron or you prefer a system crontab/web cron, you can call this secure REST URL directly:', 'sarrloop-ai-content-publisher' ); ?>
			</p>
			<p><input type="text" class="large-text code" readonly value="<?php echo esc_attr( $cron_url ); ?>" onclick="this.select();"></p>
			<p class="description"><?php esc_html_e( 'The URL contains a private authentication key. Do not share it publicly.', 'sarrloop-ai-content-publisher' ); ?></p>
			<table class="widefat striped" style="margin-top:15px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Schedule', 'sarrloop-ai-content-publisher' ); ?></th>
						<th><?php esc_html_e( 'Time', 'sarrloop-ai-content-publisher' ); ?></th>
						<th><?php esc_html_e( 'Active', 'sarrloop-ai-content-publisher' ); ?></th>
						<th><?php esc_html_e( 'Last Run', 'sarrloop-ai-content-publisher' ); ?></th>
						<th><?php esc_html_e( 'Next Run', 'sarrloop-ai-content-publisher' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $status ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No schedules configured.', 'sarrloop-ai-content-publisher' ); ?></td></tr>
				<?php else : foreach ( $status as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item['name'] ); ?></td>
						<td><?php echo esc_html( $item['time'] ); ?></td>
						<td><?php echo ! empty( $item['active'] ) ? esc_html__( 'Yes', 'sarrloop-ai-content-publisher' ) : esc_html__( 'No', 'sarrloop-ai-content-publisher' ); ?></td>
						<td><?php echo ! empty( $item['last_run'] ) ? esc_html( $item['last_run'] ) : '—'; ?></td>
						<td><?php echo ! empty( $item['next_run'] ) ? esc_html( $item['next_run'] ) : '—'; ?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
			<p><strong><?php esc_html_e( 'Pending featured images:', 'sarrloop-ai-content-publisher' ); ?></strong> <?php echo esc_html( is_array( $queue ) ? count( $queue ) : 0 ); ?></p>
		</div>
		<?php
	}

	/**
	 * Logs.
	 *
	 * @return void
	 */
	private static function render_logs() {

		$logs = Sarrloop_AI_Logger::get();

		?>

		<div class="sarrloop-card sarrloop-logs">

			<div class="sarrloop-log-header">

				<h2><?php esc_html_e( 'Activity Logs', 'sarrloop-ai-content-publisher' ); ?></h2>

				<button
					type="button"
					class="button sarrloop-clear-logs"
				>
					<?php esc_html_e( 'Clear Logs', 'sarrloop-ai-content-publisher' ); ?>
				</button>

			</div>

			<?php if ( empty( $logs ) ) : ?>

				<p><?php esc_html_e( 'No logs yet.', 'sarrloop-ai-content-publisher' ); ?></p>

			<?php else : ?>

				<table class="widefat striped">

					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'sarrloop-ai-content-publisher' ); ?></th>
							<th><?php esc_html_e( 'Type', 'sarrloop-ai-content-publisher' ); ?></th>
							<th><?php esc_html_e( 'Message', 'sarrloop-ai-content-publisher' ); ?></th>
						</tr>
					</thead>

					<tbody>

						<?php foreach ( array_reverse( $logs ) as $log ) : ?>

							<tr>

								<td>
									<?php echo esc_html( $log['time'] ); ?>
								</td>

								<td>
									<span class="sarrloop-log-type sarrloop-log-<?php echo esc_attr( $log['type'] ); ?>">
										<?php echo esc_html( ucfirst( $log['type'] ) ); ?>
									</span>
								</td>

								<td>
									<?php echo esc_html( $log['message'] ); ?>
								</td>

							</tr>

						<?php endforeach; ?>

					</tbody>

				</table>

			<?php endif; ?>

		</div>

		<?php
	}

	/**
     * Generate article manually.
     *
     * Image generation is intentionally handled separately
     * to prevent long-running AJAX requests.
     *
     * @return void
     */
    public static function generate_now() {

        check_ajax_referer(
            'sarrloop_ai_admin',
            'nonce'
        );

        if ( ! current_user_can( 'manage_options' ) ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Permission denied.',
                        'sarrloop-ai-content-publisher'
                    ),
                ),
                403
            );
        }

        $topic = isset( $_POST['topic'] )
            ? sanitize_text_field(
                wp_unslash( $_POST['topic'] )
            )
            : '';

        Sarrloop_AI_Logger::add(
            'Manual article generation started.',
            'info'
        );

        /*
        * Generate article.
        */
        $article = Sarrloop_AI_Content::generate( $topic );

        if ( is_wp_error( $article ) ) {

            Sarrloop_AI_Logger::add(
                'Article generation failed: ' .
                $article->get_error_message(),
                'error'
            );

            wp_send_json_error(
                array(
                    'message' => $article->get_error_message(),
                ),
                500
            );
        }

        /*
        * Create the WordPress post WITHOUT generating
        * the featured image.
        */
        $post_id = Sarrloop_AI_Publisher::publish(
            $article,
            true,
            false
        );

        if ( is_wp_error( $post_id ) ) {

            Sarrloop_AI_Logger::add(
                'Publishing failed: ' .
                $post_id->get_error_message(),
                'error'
            );

            wp_send_json_error(
                array(
                    'message' => $post_id->get_error_message(),
                ),
                500
            );
        }

        Sarrloop_AI_Logger::add(
            sprintf(
                'Article created successfully. Post ID: %d.',
                $post_id
            ),
            'success'
        );

        /*
        * Tell JavaScript whether an image is required.
        */
        $settings = Sarrloop_AI_Settings::get();

        wp_send_json_success(
            array(
                'message' => sprintf(
                    /* translators: %d is the post ID. */
                    __(
                        'Article created successfully. Post ID: %d',
                        'sarrloop-ai-content-publisher'
                    ),
                    $post_id
                ),

                'post_id' => $post_id,

                'edit_url' => get_edit_post_link(
                    $post_id,
                    'raw'
                ),

                'generate_image' => ! empty(
                    $settings['generate_image']
                ),
            )
        );
    }

    /**
     * Generate featured image for an existing post.
     *
     * @return void
     */
    public static function generate_featured_image() {

        check_ajax_referer(
            'sarrloop_ai_admin',
            'nonce'
        );

        if ( ! current_user_can( 'manage_options' ) ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Permission denied.',
                        'sarrloop-ai-content-publisher'
                    ),
                ),
                403
            );
        }

        $post_id = isset( $_POST['post_id'] )
            ? absint( $_POST['post_id'] )
            : 0;

        if ( ! $post_id ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Invalid post ID.',
                        'sarrloop-ai-content-publisher'
                    ),
                ),
                400
            );
        }

        $post = get_post( $post_id );

        if ( ! $post ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'Post not found.',
                        'sarrloop-ai-content-publisher'
                    ),
                ),
                404
            );
        }

        $topic = get_post_meta(
            $post_id,
            '_sarrloop_ai_topic',
            true
        );

        if ( ! $topic ) {
            $topic = $post->post_title;
        }

        Sarrloop_AI_Logger::add(
            sprintf(
                'Featured image generation started for post #%d.',
                $post_id
            ),
            'info'
        );

        $image_id = Sarrloop_AI_Image::generate(
            $post->post_title,
            $topic
        );

        if ( is_wp_error( $image_id ) ) {

            Sarrloop_AI_Logger::add(
                'Featured image failed for post #' .
                $post_id .
                ': ' .
                $image_id->get_error_message(),
                'error'
            );

            wp_send_json_error(
                array(
                    'message' => $image_id->get_error_message(),
                ),
                500
            );
        }

        if ( ! absint( $image_id ) ) {

            wp_send_json_error(
                array(
                    'message' => __(
                        'The image was generated but no attachment ID was returned.',
                        'sarrloop-ai-content-publisher'
                    ),
                ),
                500
            );
        }

        set_post_thumbnail(
            $post_id,
            absint( $image_id )
        );

        Sarrloop_AI_Logger::add(
            sprintf(
                'Featured image added successfully to post #%d.',
                $post_id
            ),
            'success'
        );

        wp_send_json_success(
            array(
                'message' => __(
                    'Featured image added successfully.',
                    'sarrloop-ai-content-publisher'
                ),
                'image_id' => absint( $image_id ),
            )
        );
    }

	/**
	 * Clear logs.
	 *
	 * @return void
	 */
	public static function clear_logs() {

		check_ajax_referer(
			'sarrloop_ai_admin',
			'nonce'
		);

		if ( ! current_user_can( 'manage_options' ) ) {

			wp_send_json_error(
				array(
					'message' => __( 'Permission denied.', 'sarrloop-ai-content-publisher' ),
				),
				403
			);
		}

		Sarrloop_AI_Logger::clear();

		wp_send_json_success(
			array(
				'message' => __( 'Logs cleared.', 'sarrloop-ai-content-publisher' ),
			)
		);
	}
}