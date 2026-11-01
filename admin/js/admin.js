jQuery(document).ready(function ($) {

	'use strict';

	const i18n = window.SarrloopAI || {};

	/*
	 * Add repeater row.
	 */
	$(document).on('click', '.sarrloop-add-row', function (e) {

		e.preventDefault();

		const button = $(this);
		const type = button.data('type');
		const repeater = $('.sarrloop-repeater[data-repeater="' + type + '"]');

		// Hide no-links note if present
		$('.sarrloop-no-links-note').remove();

		const index = repeater.find('.sarrloop-repeater-row').length;

		let html = '';

		if (type === 'topics') {

			html = `
				<div class="sarrloop-repeater-row">

					<input
						type="text"
						name="sarrloop_ai_settings[topics][${index}][name]"
						value=""
						placeholder="${escapeHtml(i18n.topicPlaceholder || 'e.g. Website performance optimization')}"
					/>

					<label>
						<input
							type="checkbox"
							name="sarrloop_ai_settings[topics][${index}][active]"
							value="1"
							checked
						/>
						${escapeHtml(i18n.activeLabel || 'Active')}
					</label>

					<button type="button" class="button sarrloop-remove-row">
						${escapeHtml(i18n.removeLabel || 'Remove')}
					</button>

				</div>
			`;
		}

		if (type === 'links') {

			html = `
				<div class="sarrloop-repeater-row sarrloop-link-row">

					<input
						type="text"
						name="sarrloop_ai_settings[links][${index}][text]"
						value=""
						placeholder="${escapeHtml(i18n.btnTextPlaceholder || 'Button text')}"
					/>

					<input
						type="url"
						name="sarrloop_ai_settings[links][${index}][url]"
						value=""
						placeholder="https://example.com"
					/>

					<input
						type="text"
						name="sarrloop_ai_settings[links][${index}][category]"
						value=""
						placeholder="${escapeHtml(i18n.categoryPlaceholder || 'Optional category')}"
					/>

					<label>
						<input
							type="checkbox"
							name="sarrloop_ai_settings[links][${index}][active]"
							value="1"
							checked
						/>
						${escapeHtml(i18n.activeLabel || 'Active')}
					</label>

					<button type="button" class="button sarrloop-remove-row">
						${escapeHtml(i18n.removeLabel || 'Remove')}
					</button>

				</div>
			`;
		}

		if (type === 'schedules') {

			html = `
				<div class="sarrloop-repeater-row">

					<input
						type="text"
						name="sarrloop_ai_settings[schedules][${index}][name]"
						value=""
						placeholder="${escapeHtml(i18n.schedulePlaceholder || 'Schedule name')}"
					/>

					<input
						type="time"
						name="sarrloop_ai_settings[schedules][${index}][time]"
						value="10:00"
					/>

					<label>
						<input
							type="checkbox"
							name="sarrloop_ai_settings[schedules][${index}][active]"
							value="1"
							checked
						/>
						${escapeHtml(i18n.activeLabel || 'Active')}
					</label>

					<button type="button" class="button sarrloop-remove-row">
						${escapeHtml(i18n.removeLabel || 'Remove')}
					</button>

				</div>
			`;
		}

		repeater.append(html);
	});


	/*
	 * Remove repeater row.
	 */
	$(document).on('click', '.sarrloop-remove-row', function (e) {

		e.preventDefault();

		const row = $(this).closest('.sarrloop-repeater-row');

		if (row.siblings('.sarrloop-repeater-row').length > 0) {
			row.remove();
		} else {
			row.find('input[type="text"], input[type="url"]').val('');
		}
	});


	/*
	 * Generate article.
	 */
	$(document).on('click', '.sarrloop-generate-now', function (e) {

		e.preventDefault();

		const button = $(this);
		const result = $('#sarrloop-generate-result');

		button.prop('disabled', true);
		button.text(i18n.generating || 'Generating...');

		result
			.removeClass('success error')
			.html(
				'<div class="notice notice-info">' +
				'<p><strong>' + escapeHtml(i18n.generating || 'Generating...') + '</strong> ' +
				escapeHtml(i18n.generatingNotice || 'Please wait. AI image generation may take some time.') +
				'</p></div>'
			);

		$.ajax({

			url: i18n.ajaxUrl,

			type: 'POST',

			dataType: 'json',

			timeout: 300000,

			data: {
				action: 'sarrloop_generate_now',
				nonce: i18n.nonce
			},

			success: function (response) {

				if (response && response.success) {

					let html =
						'<div class="notice notice-success">' +
						'<p><strong>' + escapeHtml(i18n.successTitle || 'Success!') + '</strong> ' +
						escapeHtml(
							response.data.message ||
							'Article created successfully.'
						);

					if (response.data.edit_url) {

						html +=
							' <a href="' +
							escapeHtml(response.data.edit_url) +
							'" target="_blank">' + escapeHtml(i18n.editArticle || 'Edit Article') + '</a>';
					}

					html += '</p>';

					/*
					* Generate featured image separately.
					*/
					if (
						response.data.generate_image &&
						response.data.post_id
					) {

						html +=
							'<p id="sarrloop-image-status">' +
							'<span class="spinner is-active" style="float:none;"></span> ' +
							escapeHtml(i18n.generatingImage || 'Generating featured image...') +
							'</p>';
					}

					html += '</div>';

					result.html(html);

					/*
					* Start image generation separately.
					*/
					if (
						response.data.generate_image &&
						response.data.post_id
					) {

						generateFeaturedImage(
							response.data.post_id
						);
					}

				} else {

					let message = i18n.generationFailed || 'The article could not be generated.';

					if (
						response &&
						response.data &&
						response.data.message
					) {
						message = response.data.message;
					}

					result.html(
						'<div class="notice notice-error">' +
						'<p><strong>' + escapeHtml(i18n.generationFailed || 'Generation failed:') + '</strong> ' +
						escapeHtml(message) +
						'</p></div>'
					);
				}
			},

			error: function (xhr, status, error) {

				let message = '';

				/*
				 * Timeout.
				 */
				if (status === 'timeout') {

					message = i18n.timeoutError ||
						'The request took too long. The article may already have been created. Please check Posts before trying again.';

				} else {

					/*
					 * Try to extract WordPress JSON error.
					 */
					try {

						const response = JSON.parse(xhr.responseText);

						if (
							response &&
							response.data &&
							response.data.message
						) {
							message = response.data.message;
						}

					} catch (e) {
						/* Not JSON */
					}

					/*
					 * If WordPress/PHP returned HTML, show a useful message.
					 */
					if (!message && xhr.responseText) {

						if (xhr.status === 500) {

							message = i18n.serverError ||
								'WordPress returned a server error (HTTP 500). Please check your error log.';

						} else if (xhr.status === 403) {

							message = i18n.permissionError ||
								'Permission or security check failed (HTTP 403).';

						} else if (xhr.status === 404) {

							message = i18n.notFoundError ||
								'The AJAX endpoint was not found (HTTP 404).';

						} else {

							message =
								'Server returned HTTP ' +
								xhr.status +
								'.';
						}
					}

					if (!message) {
						message = i18n.communicationError ||
							'Unable to communicate with the server.';
					}
				}

				result.html(
					'<div class="notice notice-error">' +
					'<p><strong>Error:</strong> ' +
					escapeHtml(message) +
					'</p></div>'
				);
			},

			complete: function () {

				button.prop('disabled', false);

				button.text(i18n.generateBtn || 'Generate Article Now');
			}

		});

	});


	/*
	 * Clear logs.
	 */
	$(document).on('click', '.sarrloop-clear-logs', function (e) {

		e.preventDefault();

		if (!window.confirm(i18n.confirmClearLogs || 'Are you sure you want to clear the logs?')) {
			return;
		}

		const button = $(this);

		button.prop('disabled', true);

		$.ajax({

			url: i18n.ajaxUrl,

			type: 'POST',

			dataType: 'json',

			data: {
				action: 'sarrloop_clear_logs',
				nonce: i18n.nonce
			},

			success: function (response) {

				if (response.success) {
					window.location.reload();
				} else {

					alert(
						response.data && response.data.message
							? response.data.message
							: (i18n.unableToClearLogs || 'Unable to clear logs.')
					);

					button.prop('disabled', false);
				}
			},

			error: function () {

				alert(i18n.unableToClearLogs || 'Unable to clear logs.');

				button.prop('disabled', false);
			}
		});
	});


	/*
	 * HTML escaping.
	 */
	function escapeHtml(text) {

		return $('<div>')
			.text(text || '')
			.html();
	}

	/*
	 * Generate featured image separately.
	 */
	function generateFeaturedImage(postId) {

		$.ajax({

			url: i18n.ajaxUrl,

			type: 'POST',

			dataType: 'json',

			timeout: 300000,

			data: {
				action: 'sarrloop_generate_featured_image',
				nonce: i18n.nonce,
				post_id: postId
			},

			success: function (response) {

				const status = $('#sarrloop-image-status');

				if (response && response.success) {

					status.html(
						'✓ ' +
						escapeHtml(
							response.data.message ||
							(i18n.imageSuccess || 'Featured image added successfully.')
						)
					);

				} else {

					const message =
						response &&
						response.data &&
						response.data.message
							? response.data.message
							: (i18n.imageFailed || 'Featured image generation failed.');

					status.html(
						'⚠ ' +
						escapeHtml(message)
					);
				}
			},

			error: function (xhr, status) {

				const statusElement = $('#sarrloop-image-status');

				if (status === 'timeout') {

					statusElement.html(
						'⚠ ' + escapeHtml(i18n.timeoutError || 'Image generation is taking too long. Check the post later.')
					);

				} else {

					statusElement.html(
						'⚠ ' + escapeHtml(i18n.imageFailed || 'Featured image generation failed.')
					);
				}
			}
		});
	}

});