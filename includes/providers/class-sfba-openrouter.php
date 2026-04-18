<?php
/**
 * OpenRouter provider — any model via a single OpenAI-compatible endpoint.
 *
 * Routes to 300+ models (Llama, Mistral, Qwen, DeepSeek, etc.).
 * Docs: https://openrouter.ai/docs
 *
 * Pricing: per-model. Generic fallback: $0.50 / 1M input, $1.50 / 1M output.
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Provider_OpenRouter extends SFBA_Provider_Abstract {

	/** @inheritDoc */
	public function get_slug(): string {
		return 'openrouter';
	}

	/** @inheritDoc */
	public static function display_name(): string {
		return 'OpenRouter';
	}

	// -------------------------------------------------------------------------
	// Abstract implementations.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	protected function endpoint(): string {
		return 'https://openrouter.ai/api/v1/chat/completions';
	}

	/** @inheritDoc */
	protected function build_headers(): array {
		return [
			'Authorization' => 'Bearer ' . $this->api_key,
			'HTTP-Referer'  => get_site_url(),
			'X-Title'       => get_bloginfo( 'name' ) . ' (Super Fast Blog AI)',
		];
	}

	/** @inheritDoc */
	protected function build_body( string $prompt, array $options ): array {
		$messages = [];

		if ( ! empty( $options['system'] ) ) {
			$messages[] = [ 'role' => 'system', 'content' => $options['system'] ];
		}

		$messages[] = [ 'role' => 'user', 'content' => $prompt ];

		$body = [
			'model'       => $options['model'] ?? $this->default_model(),
			'messages'    => $messages,
			'max_tokens'  => $options['max_tokens'] ?? 4096,
			'temperature' => (float) ( $options['temperature'] ?? 0.7 ),
		];

		if ( ! empty( $options['stream'] ) ) {
			$body['stream'] = true;
		}

		return $body;
	}

	/** @inheritDoc */
	protected function parse_response( array $body ): array {
		return [
			'text'              => $body['choices'][0]['message']['content'] ?? '',
			'prompt_tokens'     => $body['usage']['prompt_tokens'] ?? 0,
			'completion_tokens' => $body['usage']['completion_tokens'] ?? 0,
		];
	}

	/** @inheritDoc */
	protected function parse_stream_chunk( string $line ): ?string {
		if ( ! str_starts_with( $line, 'data: ' ) ) {
			return '';
		}
		$json = substr( $line, 6 );
		if ( '[DONE]' === trim( $json ) ) {
			return null;
		}
		$decoded = json_decode( $json, true );
		return $decoded['choices'][0]['delta']['content'] ?? '';
	}

	/** @inheritDoc */
	protected function default_model(): string {
		return 'openai/gpt-4o-mini';
	}

	// -------------------------------------------------------------------------
	// Validation + model listing.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	public function validate_key(): true|WP_Error {
		$response = $this->http_get( 'https://openrouter.ai/api/v1/auth/key' );
		return is_wp_error( $response ) ? $response : true;
	}

	/** @inheritDoc */
	public function get_models(): array|WP_Error {
		return $this->cached_models( 'sfba_openrouter_models', function () {
			$response = $this->http_get( 'https://openrouter.ai/api/v1/models' );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$models = [];
			foreach ( $response['data'] ?? [] as $model ) {
				$models[] = [
					'id'   => $model['id'],
					'name' => $model['name'] ?? $model['id'],
				];
			}

			usort( $models, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
			return $models;
		} );
	}

	// -------------------------------------------------------------------------
	// Cost calculation.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	protected function calculate_cost( int $prompt_tokens, int $completion_tokens, string $model ): float {
		return round(
			( $prompt_tokens / 1_000_000 ) * 0.50 +
			( $completion_tokens / 1_000_000 ) * 1.50,
			6
		);
	}
}
