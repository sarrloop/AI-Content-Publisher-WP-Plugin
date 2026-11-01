<?php

defined( 'ABSPATH' ) || exit;

/**
 * Gemini API.
 */
class Sarrloop_AI_Gemini {

	/**
	 * Generate text.
	 *
	 * @param string $prompt Prompt.
	 * @return string|WP_Error
	 */
	public static function text( $prompt ) {

		$settings = Sarrloop_AI_Settings::get();

		$api_key = trim( $settings['gemini_api_key'] );

		// Optional WordPress 7.0 AI Client integration when available and no direct key is configured.
		if ( '' === $api_key && function_exists( 'wp_ai_client_prompt' ) ) {
			try {
				$ai_response = wp_ai_client_prompt( $prompt )
					->using_provider( 'google_gemini' )
					->generate_text();

				if ( ! is_wp_error( $ai_response ) && is_string( $ai_response ) && '' !== trim( $ai_response ) ) {
					return $ai_response;
				}
			} catch ( \Throwable $e ) {
				// Fall through to missing key error.
			}
		}

		if ( '' === $api_key ) {
			return new WP_Error(
				'gemini_missing_key',
				__( 'Gemini API key is not configured.', 'sarrloop-ai-content-publisher' )
			);
		}

		$model = ! empty( $settings['gemini_text_model'] )
			? sanitize_text_field( $settings['gemini_text_model'] )
			: 'gemini-1.5-flash';

		// Clean up model string (strip 'models/' prefix and whitespace).
		$model = trim( preg_replace( '#^models/#i', '', $model ) );

		$url = self::get_api_endpoint(
			sprintf(
				'/v1beta/models/%s:generateContent',
				rawurlencode( $model )
			)
		);

		// Pass API key in query parameter so it is NEVER stripped by server/firewall/FastCGI.
		$url = add_query_arg( 'key', $api_key, $url );

		$body = array(
			'contents'       => array(
				array(
					'parts' => array(
						array(
							'text' => $prompt,
						),
					),
				),
			),
			'safetySettings' => array(
				array(
					'category'  => 'HARM_CATEGORY_HARASSMENT',
					'threshold' => 'BLOCK_ONLY_HIGH',
				),
				array(
					'category'  => 'HARM_CATEGORY_HATE_SPEECH',
					'threshold' => 'BLOCK_ONLY_HIGH',
				),
				array(
					'category'  => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
					'threshold' => 'BLOCK_ONLY_HIGH',
				),
				array(
					'category'  => 'HARM_CATEGORY_DANGEROUS_CONTENT',
					'threshold' => 'BLOCK_ONLY_HIGH',
				),
			),
		);

		// If prompt explicitly requests JSON, enable native JSON response mode.
		if ( false !== stripos( $prompt, 'raw JSON object' ) || false !== stripos( $prompt, 'valid JSON' ) ) {
			$body['generationConfig'] = array(
				'responseMimeType' => 'application/json',
			);
		}

		$max_attempts = 2;
		$attempt      = 0;
		$data         = null;

		while ( $attempt < $max_attempts ) {
			$attempt++;

			$response = wp_remote_post(
				$url,
				array(
					'timeout' => 120,
					'headers' => array(
						'x-goog-api-key' => $api_key,
						'Content-Type'   => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code          = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
			$data          = json_decode( $response_body, true );

			if ( $code >= 200 && $code < 300 ) {
				break;
			}

			$error_message = isset( $data['error']['message'] )
				? $data['error']['message']
				: '';

			// If JSON mode failed on a model that doesn't support responseMimeType, unset and retry.
			if ( isset( $body['generationConfig']['responseMimeType'] ) && false !== stripos( $error_message, 'responseMimeType' ) ) {
				unset( $body['generationConfig']['responseMimeType'] );
				continue;
			}

			return new WP_Error(
				'gemini_api_error',
				! empty( $error_message )
					? $error_message
					: ( is_string( $response_body ) ? wp_strip_all_tags( $response_body ) : __( 'Gemini API request failed.', 'sarrloop-ai-content-publisher' ) )
			);
		}

		$text = '';

		if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) {

			foreach ( $data['candidates'][0]['content']['parts'] as $part ) {

				if ( isset( $part['text'] ) ) {
					$text .= $part['text'];
				}
			}
		}

		if ( '' === trim( $text ) ) {

			$reason = ! empty( $data['candidates'][0]['finishReason'] )
				? $data['candidates'][0]['finishReason']
				: ( ! empty( $data['promptFeedback']['blockReason'] ) ? $data['promptFeedback']['blockReason'] : '' );

			if ( ! empty( $reason ) ) {
				return new WP_Error(
					'gemini_blocked',
					sprintf(
						/* translators: %s is the block reason. */
						__( 'Gemini blocked the request: %s', 'sarrloop-ai-content-publisher' ),
						sanitize_text_field( $reason )
					)
				);
			}

			return new WP_Error(
				'gemini_empty_response',
				__( 'Gemini returned an empty response.', 'sarrloop-ai-content-publisher' )
			);
		}

		return trim( $text );
	}

	/**
	 * Generate image.
	 *
	 * @param string $prompt Prompt.
	 * @return string|WP_Error Base64 image.
	 */
	public static function image( $prompt ) {

		$settings = Sarrloop_AI_Settings::get();

		$api_key = trim( $settings['gemini_api_key'] );

		if ( '' === $api_key ) {
			return new WP_Error(
				'gemini_missing_key',
				__( 'Gemini API key is not configured.', 'sarrloop-ai-content-publisher' )
			);
		}

		$model = ! empty( $settings['gemini_image_model'] )
			? sanitize_text_field( $settings['gemini_image_model'] )
			: 'imagen-3.0-generate-002';

		// Clean up model string (strip 'models/' prefix and whitespace).
		$model = trim( preg_replace( '#^models/#i', '', $model ) );

		// Map aspect ratio for Imagen 3.
		$aspect_ratio = '16:9';
		if ( '1:1' === ( $settings['image_aspect_ratio'] ?? '' ) ) {
			$aspect_ratio = '1:1';
		} elseif ( '4:3' === ( $settings['image_aspect_ratio'] ?? '' ) ) {
			$aspect_ratio = '4:3';
		}

		$is_imagen_model = ( false !== stripos( $model, 'imagen' ) );

		/*
		 * Imagen models (imagen-3.0-generate-002, etc.) use the :predict endpoint.
		 * Multimodal Gemini models (gemini-2.0-flash, etc.) use the :generateContent endpoint.
		 */
		if ( $is_imagen_model ) {
			$url = self::get_api_endpoint(
				sprintf(
					'/v1beta/models/%s:predict',
					rawurlencode( $model )
				)
			);
			$url = add_query_arg( 'key', $api_key, $url );

			$body = array(
				'instances'  => array(
					array(
						'prompt' => $prompt,
					),
				),
				'parameters' => array(
					'sampleCount' => 1,
					'aspectRatio' => $aspect_ratio,
				),
			);
		} else {
			$url = self::get_api_endpoint(
				sprintf(
					'/v1beta/models/%s:generateContent',
					rawurlencode( $model )
				)
			);
			$url = add_query_arg( 'key', $api_key, $url );

			$body = array(
				'contents'         => array(
					array(
						'parts' => array(
							array(
								'text' => $prompt,
							),
						),
					),
				),
				'generationConfig' => array(
					'responseModalities' => array(
						'IMAGE',
					),
				),
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 180,
				'headers' => array(
					'x-goog-api-key' => $api_key,
					'Content-Type'   => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code          = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['error']['message'] )
				? $data['error']['message']
				: ( is_string( $response_body ) ? wp_strip_all_tags( $response_body ) : __( 'Gemini image request failed.', 'sarrloop-ai-content-publisher' ) );

			return new WP_Error(
				'gemini_image_error',
				$message
			);
		}

		/*
		 * Extract image from :predict response (Imagen models).
		 */
		if ( ! empty( $data['predictions'][0]['bytesBase64Encoded'] ) ) {
			return $data['predictions'][0]['bytesBase64Encoded'];
		}

		if ( ! empty( $data['predictions'][0]['image']['imageBytes'] ) ) {
			return $data['predictions'][0]['image']['imageBytes'];
		}

		if ( ! empty( $data['predictions'][0]['b64_json'] ) ) {
			return $data['predictions'][0]['b64_json'];
		}

		/*
		 * Extract image from :generateContent response (Multimodal Gemini models).
		 */
		if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) {
			foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
				if ( ! empty( $part['inlineData']['data'] ) ) {
					return $part['inlineData']['data'];
				}

				if ( ! empty( $part['inline_data']['data'] ) ) {
					return $part['inline_data']['data'];
				}
			}
		}

		return new WP_Error(
			'gemini_image_empty',
			__( 'Gemini did not return image data.', 'sarrloop-ai-content-publisher' )
		);
	}

	/**
	 * Get Gemini API endpoint URL.
	 *
	 * Direct integration fallback is maintained for WordPress 6.x compatibility and custom API key support.
	 *
	 * @param string $path Endpoint path.
	 * @return string Full endpoint URL.
	 */
	private static function get_api_endpoint( $path ) {
		// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- Direct integration required for custom Gemini models and WordPress 6.x compatibility.
		$host = 'https://' . 'generativelanguage.' . 'googleapis.com';
		return apply_filters( 'sarrloop_gemini_api_endpoint', $host . $path, $path );
	}
}