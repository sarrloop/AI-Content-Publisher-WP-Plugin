=== Sarrloop AI Content Publisher ===
Contributors: sarrloop
Tags: ai content, ai writer, content generator, openai, gemini
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate WordPress articles using OpenAI or Google Gemini with configurable topics, schedules, promotional links and featured images.

== Description ==

Sarrloop AI Content Publisher helps WordPress site owners create and publish AI-assisted articles with SEO metadata and featured images.

Features include:

* OpenAI integration (GPT-4o, GPT-4o-mini, DALL-E 3)
* Google Gemini integration (Gemini 1.5 Flash, Imagen 3)
* Configurable AI text and image models
* Customizable website focus / niche for tailored content
* Multiple content topics & custom topic modes (My Topics, AI Generated, or Mixed)
* Multiple publishing schedules with individual active/pause controls
* Automated background publishing via native WordPress Cron or external webhook
* Draft, pending review, or published post status
* Automatic AI featured image generation saved to the WordPress Media Library
* SEO metadata generation (Yoast SEO, Rank Math, and All in One SEO compatible)
* Automatic internal linking to recent relevant posts
* Optional promotional call-to-action blocks with custom links
* Activity logging for troubleshooting and auditing
* One-click manual article generation from the dashboard
* Full WordPress timezone support

== External Services ==

This plugin connects to external third-party AI services when configured by the site administrator:

1. **OpenAI API**
   * Purpose: Generates article content, SEO metadata, and featured images.
   * Data Sent: Article topic, site niche, language, target word count, and image prompt.
   * Service Website: https://openai.com/
   * Terms of Use: https://openai.com/policies/terms-of-use/
   * Privacy Policy: https://openai.com/policies/privacy-policy/

2. **Google Gemini API**
   * Purpose: Generates article content, SEO metadata, and featured images.
   * Data Sent: Article topic, site niche, language, target word count, and image prompt.
   * Service Website: https://ai.google.dev/
   * Terms of Service: https://policies.google.com/terms
   * Privacy Policy: https://policies.google.com/privacy

Data is only sent to the selected provider when an administrator or scheduled cron triggers article or image generation.

== Installation ==

1. Upload the `sarrloop-ai-content-publisher` folder to the `/wp-content/plugins/` directory, or install it via the WordPress Plugins menu.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Sarrloop AI** in the WordPress admin menu.
4. Choose your preferred AI provider (OpenAI or Google Gemini) and enter your API key.
5. Configure your content preferences, topics, schedules, and optional promotional links.
6. Click **Save Settings**.

== Frequently Asked Questions ==

= Do I need an API key to use this plugin? =
Yes. You need an active API key from either OpenAI (https://platform.openai.com) or Google Gemini (https://aistudio.google.com).

= Does scheduled publishing require a separate cron server? =
No. Sarrloop AI Content Publisher automatically uses WordPress's built-in WP-Cron system. For high-traffic sites or hosts with WP-Cron disabled, a private external REST endpoint is also provided.

= Which SEO plugins are supported? =
Generated meta descriptions and focus keywords are automatically mapped to Yoast SEO, Rank Math, and All in One SEO (AIOSEO), as well as saved in custom post metadata.

= Are featured images stored locally in WordPress? =
Yes. All AI-generated images are downloaded and stored directly in your WordPress Media Library and attached as the featured image of the post.

== Screenshots ==

1. Plugin settings page with AI provider configuration, content settings, topics, and schedule manager.
2. Activity log showing real-time background article and image publishing events.

== Changelog ==

= 1.0.0 =
* Initial release on WordPress.org.
* Support for OpenAI and Google Gemini text and image generation.
* Automated scheduling with native WP-Cron and external REST endpoint.
* Yoast SEO, Rank Math, and AIOSEO metadata support.
* Media library featured image integration.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
