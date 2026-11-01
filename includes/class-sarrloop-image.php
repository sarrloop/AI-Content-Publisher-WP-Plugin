<?php

defined( 'ABSPATH' ) || exit;

/**
 * Image generator and media uploader.
 */
class Sarrloop_AI_Image {

	/**
	 * Generate featured image.
	 *
	 * @param string $title Article title.
	 * @param string $topic Topic.
	 * @return int|WP_Error Attachment ID.
	 */
	public static function generate( $title, $topic ) {

		$settings = Sarrloop_AI_Settings::get();

		$style     = sanitize_text_field( $settings['image_style'] );
		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );
		$niche     = ! empty( $settings['site_niche'] )
			? $settings['site_niche']
			: ( $site_desc ? $site_desc : 'Editorial' );

		$text_instruction = 'Do not include any text, words, letters, logos, watermarks, or typography in the image.';

		if ( 'yes' === $settings['image_text'] ) {
			$text_instruction = 'Use minimal clean editorial typography only if appropriate.';
		}

		$prompt = '
Create a professional featured image for a blog article on "' . esc_html( $site_name ) . '".

Article title:
' . $title . '

Topic:
' . $topic . '

Niche / Focus:
' . $niche . '

Style:
' . $style . '

Requirements:
- High quality editorial appearance.
- Clean, engaging composition.
- Suitable for a WordPress blog featured image.
- No copyrighted characters.
- No brand logos.
- No misleading imagery.
- Landscape composition.
- Strong visual hierarchy.
- ' . $text_instruction;

		if ( 'gemini' === $settings['provider'] ) {
			$image = Sarrloop_AI_Gemini::image( $prompt );
		} else {
			$image = Sarrloop_AI_OpenAI::image( $prompt );
		}

		if ( is_wp_error( $image ) ) {
			return $image;
		}

		return self::save_to_media_library(
			$image,
			$title
		);
	}

	/**
	 * Save Base64 image to Media Library.
	 *
	 * @param string $base64 Image data.
	 * @param string $title Image title.
	 * @return int|WP_Error
	 */
	private static function save_to_media_library( $base64, $title ) {

		if ( empty( $base64 ) ) {
			return new WP_Error(
				'empty_image',
				__( 'No image data was returned.', 'sarrloop-ai-content-publisher' )
			);
		}

		/*
		 * Remove potential data URI prefix.
		 */
		if ( false !== strpos( $base64, ',' ) ) {
			$parts = explode( ',', $base64, 2 );

			if ( 2 === count( $parts ) ) {
				$base64 = $parts[1];
			}
		}

		$image_data = base64_decode( $base64 );

		if ( false === $image_data ) {
			return new WP_Error(
				'invalid_image',
				__( 'Unable to decode generated image.', 'sarrloop-ai-content-publisher' )
			);
		}

		$filename = sanitize_title( $title );

		if ( '' === $filename ) {
			$filename = 'ai-featured-image';
		}

		$filename .= '-' . time() . '.png';

		$upload = wp_upload_bits(
			$filename,
			null,
			$image_data
		);

		if ( ! empty( $upload['error'] ) ) {

			return new WP_Error(
				'image_upload_error',
				$upload['error']
			);
		}

		$filetype = wp_check_filetype(
			$upload['file'],
			null
		);

		$attachment = array(
			'post_mime_type' => ! empty( $filetype['type'] )
				? $filetype['type']
				: 'image/png',
			'post_title'     => sanitize_text_field( $title ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment(
			$attachment,
			$upload['file']
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$metadata = wp_generate_attachment_metadata(
			$attachment_id,
			$upload['file']
		);

		if ( ! empty( $metadata ) ) {

			wp_update_attachment_metadata(
				$attachment_id,
				$metadata
			);
		}

		return absint( $attachment_id );
	}
}