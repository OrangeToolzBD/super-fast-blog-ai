<?php
/**
 * Contract that every AI provider must satisfy.
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface SFBA_Provider_Interface
 *
 * All concrete provider classes (OpenAI, Anthropic, Google, etc.) must
 * implement this interface. Doing so guarantees that SFBA_Content_Generator
 * and SFBA_Providers can call any provider interchangeably.
 *
 * Implementing classes must also expose a static display_name() method
 * (called on the class, not an instance) for admin UI labels. PHP interfaces
 * cannot declare static methods, so this is an enforced convention:
 *
 *   public static function display_name(): string
 */
interface SFBA_Provider_Interface {

	/**
	 * Send a prompt and return the full generated text (blocking).
	 *
	 * @param string $prompt  The complete prompt string to send.
	 * @param array  $options {
	 *     @type string $model        Model identifier (e.g. 'gpt-4o').
	 *     @type int    $max_tokens   Maximum tokens to generate.
	 *     @type float  $temperature  Sampling temperature (0.0–2.0).
	 *     @type string $system       Optional system message.
	 * }
	 * @return array{
	 *     text: string,
	 *     prompt_tokens: int,
	 *     completion_tokens: int,
	 *     cost_usd: float,
	 *     model: string,
	 *     generation_time_ms: int
	 * }|WP_Error
	 */
	public function generate( string $prompt, array $options = [] ): array|WP_Error;

	/**
	 * Stream a prompt response token-by-token via a callback.
	 *
	 * @param string   $prompt   The complete prompt string.
	 * @param array    $options  Same keys as generate().
	 * @param callable $on_chunk Callback: function( string|null $text_delta ): void
	 * @return array{prompt_tokens: int, completion_tokens: int, cost_usd: float}|WP_Error
	 */
	public function stream( string $prompt, array $options, callable $on_chunk ): array|WP_Error;

	/**
	 * Test whether the stored API key is valid.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function validate_key(): bool|WP_Error;

	/**
	 * Return the list of models available for this provider.
	 *
	 * Results should be cached in a transient (TTL: 1 hour).
	 *
	 * @return array<int, array{id: string, name: string}>|WP_Error
	 */
	public function get_models(): array|WP_Error;

	/**
	 * Return the provider's slug (matches registry key in SFBA_Providers).
	 *
	 * @return string e.g. 'openai'
	 */
	public function get_slug(): string;
}
