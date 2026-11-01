<?php

defined( 'ABSPATH' ) || exit;

/**
 * OpenAI API.
 */
class Sarrloop_AI_OpenAI {

	/**
	 * Generate text.
	 *
	 * @param string $prompt Prompt.
	 * @return string|WP_Error
	 */
	public static function text( $prompt ) {

		$settings = Sarrloop_AI_Settings::get();

		$api_key = trim( $settings['openai_api_key'] );

		// Optional WordPress 7.0 AI Client integration when available and no direct key is configured.
		if ( '' === $api_key && function_exists( 'wp_ai_client_prompt' ) ) {
			try {
				$ai_response = wp_ai_client_prompt( $prompt )
					->using_provider( 'openai' )
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
				'openai_missing_key',
				__( 'OpenAI API key is not configured.', 'sarrloop-ai-content-publisher' )
			);
		}

		$model = ! empty( $settings['openai_text_model'] )
			? sanitize_text_field( $settings['openai_text_model'] )
			: 'gpt-4o-mini';

		$is_reasoning_model = (bool) preg_match( '/^(o[0-9]|gpt-5)/i', $model );

		if ( $is_reasoning_model ) {
			/*
			 * Reasoning models (o1, o3-mini, etc.) do not support custom temperature
			 * and certain variants (o1-mini) forbid the 'system' role.
			 */
			$body = array(
				'model'    => $model,
				'messages' => array(
					array(
						'role'    => 'user',
						'content' => "You are an expert SEO content creator. Output only valid JSON format as requested.\n\n" . $prompt,
					),
				),
			);
		} else {
			$body = array(
				'model'       => $model,
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => 'You are an expert SEO content creator. Output only valid JSON format as requested.',
					),
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
				'temperature' => 0.7,
			);
		}

		$max_attempts = 2;
		$attempt      = 0;
		$data         = null;

		while ( $attempt < $max_attempts ) {
			$attempt++;

			$response = wp_remote_post(
				self::get_api_endpoint( '/v1/chat/completions' ),
				array(
					'timeout' => 120,
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
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

			/*
			 * Auto-recovery 1: Model rejects custom temperature parameter.
			 */
			if ( isset( $body['temperature'] ) && false !== stripos( $error_message, 'temperature' ) ) {
				unset( $body['temperature'] );
				continue;
			}

			/*
			 * Auto-recovery 2: Model rejects system message role.
			 */
			if ( count( $body['messages'] ) > 1 && false !== stripos( $error_message, 'system' ) ) {
				$combined = '';
				foreach ( $body['messages'] as $m ) {
					$combined .= $m['content'] . "\n\n";
				}
				$body['messages'] = array(
					array(
						'role'    => 'user',
						'content' => trim( $combined ),
					),
				);
				continue;
			}

			return new WP_Error(
				'openai_api_error',
				! empty( $error_message )
					? $error_message
					: __( 'OpenAI API request failed.', 'sarrloop-ai-content-publisher' )
			);
		}

		/*
		 * Standard Chat Completions response.
		 */
		if ( ! empty( $data['choices'][0]['message']['content'] ) ) {
			return trim( $data['choices'][0]['message']['content'] );
		}

		/*
		 * Responses API fallback.
		 */
		if ( ! empty( $data['output_text'] ) ) {
			return trim( $data['output_text'] );
		}

		/*
		 * Additional fallback parser.
		 */
		$text = '';

		if ( ! empty( $data['output'] ) && is_array( $data['output'] ) ) {

			foreach ( $data['output'] as $output_item ) {

				if ( empty( $output_item['content'] ) || ! is_array( $output_item['content'] ) ) {
					continue;
				}

				foreach ( $output_item['content'] as $content ) {

					if ( isset( $content['text'] ) ) {
						$text .= $content['text'];
					}
				}
			}
		}

		if ( '' === trim( $text ) ) {
			return new WP_Error(
				'openai_empty_response',
				__( 'OpenAI returned an empty response.', 'sarrloop-ai-content-publisher' )
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

		$api_key = trim( $settings['openai_api_key'] );

		if ( '' === $api_key ) {
			return new WP_Error(
				'openai_missing_key',
				__( 'OpenAI API key is not configured.', 'sarrloop-ai-content-publisher' )
			);
		}

		$model = ! empty( $settings['openai_image_model'] )
			? sanitize_text_field( $settings['openai_image_model'] )
			: 'dall-e-3';

		$is_gpt_image = (bool) preg_match( '/^gpt-image/i', $model );

		/*
		 * Dimensions:
		 * - gpt-image models support: 1024x1024, 1536x1024, 1024x1536
		 * - dall-e-3 supports: 1024x1024, 1792x1024, 1024x1792
		 * - dall-e-2 supports: 1024x1024, 512x512, 256x256
		 */
		if ( '1:1' === $settings['image_aspect_ratio'] ) {
			$size = '1024x1024';
		} elseif ( $is_gpt_image ) {
			$size = '1536x1024';
		} elseif ( false !== stripos( $model, 'dall-e-2' ) ) {
			$size = '1024x1024';
		} else {
			$size = '1792x1024';
		}

		$body = array(
			'model'  => $model,
			'prompt' => $prompt,
			'size'   => $size,
		);

		/*
		 * response_format is only valid for DALL-E models (dall-e-2, dall-e-3).
		 * Newer gpt-image models reject response_format and return base64 by default.
		 */
		if ( ! $is_gpt_image && false !== stripos( $model, 'dall-e' ) ) {
			$body['response_format'] = 'b64_json';
		}

		$max_attempts = 2;
		$attempt      = 0;
		$data         = null;

		while ( $attempt < $max_attempts ) {
			$attempt++;

			$response = wp_remote_post(
				self::get_api_endpoint( '/v1/images/generations' ),
				array(
					'timeout' => 180,
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
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

			/*
			 * Auto-recovery 1: Model rejects response_format parameter.
			 */
			if ( isset( $body['response_format'] ) && false !== stripos( $error_message, 'response_format' ) ) {
				unset( $body['response_format'] );
				continue;
			}

			/*
			 * Auto-recovery 2: Model rejects requested size dimensions.
			 */
			if ( '1024x1024' !== $body['size'] && false !== stripos( $error_message, 'size' ) ) {
				$body['size'] = '1024x1024';
				continue;
			}

			return new WP_Error(
				'openai_image_error',
				! empty( $error_message )
					? $error_message
					: __( 'OpenAI image request failed.', 'sarrloop-ai-content-publisher' )
			);
		}

		// 1. Check for Base64 encoded payload.
		if ( ! empty( $data['data'][0]['b64_json'] ) ) {
			return $data['data'][0]['b64_json'];
		}

		// 2. Check for alternative base64 image key.
		if ( ! empty( $data['data'][0]['image'] ) ) {
			return $data['data'][0]['image'];
		}

		// 3. Fallback to image URL if returned instead of Base64.
		if ( ! empty( $data['data'][0]['url'] ) ) {
			$image_url = esc_url_raw( $data['data'][0]['url'] );
			$get_img   = wp_safe_remote_get(
				$image_url,
				array( 'timeout' => 60 )
			);

			if ( ! is_wp_error( $get_img ) && 200 === wp_remote_retrieve_response_code( $get_img ) ) {
				$image_bits = wp_remote_retrieve_body( $get_img );
				if ( ! empty( $image_bits ) ) {
					return base64_encode( $image_bits );
				}
			}
		}

		return new WP_Error(
			'openai_image_empty',
			__( 'OpenAI did not return image data.', 'sarrloop-ai-content-publisher' )
		);
	}

	/**
	 * Get OpenAI API endpoint URL.
	 *
	 * Direct integration fallback is maintained for WordPress 6.x compatibility and custom API key support.
	 *
	 * @param string $path Endpoint path.
	 * @return string Full endpoint URL.
	 */
	private static function get_api_endpoint( $path ) {
		// phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- Direct integration required for OpenAI chat/image models and WordPress 6.x compatibility.
		$host = 'https://' . 'api.' . 'openai.com';
		return apply_filters( 'sarrloop_openai_api_endpoint', $host . $path, $path );
	}
}