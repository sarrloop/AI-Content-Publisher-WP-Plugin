<?php

defined( 'ABSPATH' ) || exit;

/**
 * Content generator.
 */
class Sarrloop_AI_Content {

	/**
	 * Generate article.
	 *
	 * @param string $topic Topic.
	 * @return array|WP_Error
	 */
	public static function generate( $topic = '' ) {

		$settings = Sarrloop_AI_Settings::get();

		if ( '' === trim( $topic ) ) {
			$topic = self::get_topic();
		}

		if ( is_wp_error( $topic ) ) {
			return $topic;
		}

		$prompt = self::build_prompt( $topic );

		$provider = $settings['provider'];

		if ( 'gemini' === $provider ) {
			$response = Sarrloop_AI_Gemini::text( $prompt );
		} else {
			$response = Sarrloop_AI_OpenAI::text( $prompt );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = self::parse_response( $response );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$data['topic'] = $topic;

		return $data;
	}

	/**
	 * Get topic.
	 *
	 * @return string|WP_Error
	 */
	public static function get_topic() {

		$settings = Sarrloop_AI_Settings::get();

		$topics = array();

		if ( ! empty( $settings['topics'] ) ) {

			foreach ( $settings['topics'] as $topic ) {

				if (
					! empty( $topic['active'] ) &&
					! empty( $topic['name'] )
				) {
					$topics[] = sanitize_text_field( $topic['name'] );
				}
			}
		}

		/*
		 * User topics only.
		 */
		if ( 'my_topics' === $settings['topic_mode'] ) {

			if ( empty( $topics ) ) {
				return new WP_Error(
					'no_topics',
					__( 'No active topics have been configured.', 'sarrloop-ai-content-publisher' )
				);
			}

			return $topics[ array_rand( $topics ) ];
		}

		/*
		 * AI topic.
		 */
		if ( 'ai_topics' === $settings['topic_mode'] ) {
			return self::generate_ai_topic();
		}

		/*
		 * Mixed.
		 */
		if ( ! empty( $topics ) && wp_rand( 0, 1 ) === 0 ) {
			return $topics[ array_rand( $topics ) ];
		}

		return self::generate_ai_topic();
	}

	/**
	 * Generate topic using AI.
	 *
	 * @return string|WP_Error
	 */
	private static function generate_ai_topic() {

		$settings  = Sarrloop_AI_Settings::get();
		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );
		$niche     = ! empty( $settings['site_niche'] )
			? $settings['site_niche']
			: ( $site_desc ? $site_desc : $site_name );

		$categories  = get_categories( array( 'number' => 8, 'hide_empty' => false ) );
		$cat_list    = ! empty( $categories ) ? implode( ', ', wp_list_pluck( $categories, 'name' ) ) : '';
		$cat_context = $cat_list ? 'Categories on this site: ' . $cat_list . '.' : '';

		$prompt = '
You are an expert SEO content strategist for the website "' . esc_html( $site_name ) . '".
Website Niche/Focus: ' . esc_html( $niche ) . '.
' . ( $cat_context ? esc_html( $cat_context ) : '' ) . '

Generate ONE engaging, high-interest blog article topic suitable for this website.

Requirements:
- Specific, practical and highly relevant to the website niche.
- Suitable for real readers and search engine ranking.
- Avoid clickbait.
- Do not use quotation marks.
- Return only the topic text and nothing else.
';

		if ( 'gemini' === $settings['provider'] ) {
			$result = Sarrloop_AI_Gemini::text( $prompt );
		} else {
			$result = Sarrloop_AI_OpenAI::text( $prompt );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return sanitize_text_field( trim( $result ) );
	}

	/**
	 * Build article prompt.
	 *
	 * @param string $topic Topic.
	 * @return string
	 */
	private static function build_prompt( $topic ) {

		$settings = Sarrloop_AI_Settings::get();

		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );
		$niche     = ! empty( $settings['site_niche'] )
			? $settings['site_niche']
			: ( $site_desc ? $site_desc : 'Informational & Educational' );

		$length   = absint( $settings['article_length'] );
		$language = sanitize_text_field( $settings['language'] );

		$internal_links = '';

		if ( ! empty( $settings['add_internal_links'] ) ) {
			$internal_links = '
- Include natural places where internal links can be inserted.
- Do not invent URLs.
';
		}

		$promo = '';

		if ( ! empty( $settings['add_promo_link'] ) ) {
			$promo = '
- Include a natural promotional CTA near the end of the article.
- The promotional URL will be inserted by WordPress.
- Do not invent a promotional URL.
';
		}

		return '
You are an expert SEO content writer for "' . esc_html( $site_name ) . '" (Website Niche/Topic Focus: ' . esc_html( $niche ) . ').

Write a high-quality original article about:

TOPIC:
' . $topic . '

LANGUAGE:
' . $language . '

TARGET LENGTH:
Approximately ' . $length . ' words.

Requirements:

1. Create a useful, detailed article.
2. Write for real website visitors, customers, and readers.
3. Use clear, engaging and professional language.
4. Avoid unnecessary filler or repetition.
5. Do not make unsupported claims.
6. Do not mention that AI wrote the article.
7. Do not include a title using H1 inside the article.
8. Use H2 and H3 headings.
9. Use short paragraphs.
10. Use bullet lists where useful.
11. Include practical tips or examples where appropriate.
12. Make the article SEO-friendly without keyword stuffing.
13. Create a compelling SEO title.
14. Create a meta description between approximately 140 and 155 characters.
15. Create 5-10 relevant tags.
16. Create a focus keyword.
17. Create an excerpt.
' . $internal_links . '
' . $promo . '

Return ONLY valid JSON in this exact structure:

{
  "title": "Article title",
  "content": "<p>Article HTML...</p>",
  "excerpt": "Short excerpt",
  "meta_description": "SEO meta description",
  "focus_keyword": "focus keyword",
  "tags": ["tag 1", "tag 2", "tag 3"]
}

Important:
- content must contain valid WordPress HTML.
- Do not use Markdown.
- Do not wrap the JSON inside markdown code fences.
';
	}

	/**
	 * Parse AI response.
	 *
	 * @param string $response Response.
	 * @return array|WP_Error
	 */
	private static function parse_response( $response ) {

		$response = trim( $response );

		/*
		 * Remove accidental code fences.
		 */
		$response = preg_replace(
			'/^```(?:json)?\s*/i',
			'',
			$response
		);

		$response = preg_replace(
			'/\s*```$/',
			'',
			$response
		);

		$data = json_decode( $response, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {

			/*
			 * Try extracting the JSON object.
			 */
			$start = strpos( $response, '{' );
			$end   = strrpos( $response, '}' );

			if ( false !== $start && false !== $end && $end > $start ) {

				$json = substr(
					$response,
					$start,
					$end - $start + 1
				);

				$data = json_decode( $json, true );

				if ( ! is_array( $data ) ) {
					$clean_json = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $json );
					$data       = json_decode( $clean_json, true );
				}
			}
		}

		if ( ! is_array( $data ) ) {

			return new WP_Error(
				'invalid_ai_json',
				__( 'The AI returned an invalid article format.', 'sarrloop-ai-content-publisher' )
			);
		}

		$title = isset( $data['title'] )
			? sanitize_text_field( $data['title'] )
			: '';

		$content = isset( $data['content'] )
			? wp_kses_post( $data['content'] )
			: '';

		$excerpt = isset( $data['excerpt'] )
			? sanitize_textarea_field( $data['excerpt'] )
			: '';

		$meta_description = isset( $data['meta_description'] )
			? sanitize_text_field( $data['meta_description'] )
			: '';

		$focus_keyword = isset( $data['focus_keyword'] )
			? sanitize_text_field( $data['focus_keyword'] )
			: '';

		$tags = array();

		if ( ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {

			foreach ( $data['tags'] as $tag ) {

				$tag = sanitize_text_field( $tag );

				if ( '' !== $tag ) {
					$tags[] = $tag;
				}
			}
		}

		if ( '' === $title || '' === $content ) {

			return new WP_Error(
				'invalid_article',
				__( 'The generated article is missing required fields.', 'sarrloop-ai-content-publisher' )
			);
		}

		return array(
			'title'            => $title,
			'content'          => $content,
			'excerpt'          => $excerpt,
			'meta_description' => $meta_description,
			'focus_keyword'    => $focus_keyword,
			'tags'             => array_unique( $tags ),
		);
	}
}