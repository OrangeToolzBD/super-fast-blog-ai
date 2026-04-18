<?php
/**
 * Ollama provider — local AI models (Llama 3, Mistral, Phi, Qwen, etc.).
 *
 * Connects to a locally-running Ollama instance.
 * Default base URL: http://localhost:11434 (configurable in settings).
 *
 * Key differences from cloud providers:
 *   - No API key required (optional bearer token for secured remote instances).
 *   - Zero API cost — all inference runs locally.
 *   - Privacy-first: no data leaves the user's server.
 *
 * Docs: https://github.com/ollama/ollama/blob/main/docs/api.md
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Provider_Ollama extends SFBA_Provider_Abstract {

	/** @inheritDoc */
	public function get_slug(): string {
		return 'ollama';
	}

	/** @inheritDoc */
	public static function display_name(): string {
		return 'Ollama (Local)';
	}

	// -------------------------------------------------------------------------
	// Abstract implementations.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	protected function endpoint(): string {
		$base = $this->settings->get( 'providers.ollama.base_url', 'http://localhost:11434' );
		return rtrim( (string) $base, '/' ) . '/api/chat';
	}

	/**
	 * @inheritDoc
	 *
	 * Ollama does not require authentication by default.
	 * Optional bearer token for secured remote Ollama instances.
	 */
	protected function build_headers(): array {
		$headers = [];
		if ( '' !== $this->api_key ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}
		return $headers;
	}

	/** @inheritDoc */
	protected function build_body( string $prompt, array $options ): array {
		$messages = [];

		if ( ! empty( $options['system'] ) ) {
			$messages[] = [ 'role' => 'system', 'content' => $options['system'] ];
		}

		$messages[] = [ 'role' => 'user', 'content' => $prompt ];

		return [
			'model'    => $options['model'] ?? $this->default_model(),
			'messages' => $messages,
			'stream'   => false,
			'options'  => [
				'num_predict' => $options['max_tokens'] ?? 4096,
				'temperature' => $options['temperature'] ?? 0.7,
			],
		];
	}

	/** @inheritDoc */
	protected function parse_response( array $body ): array {
		return [
			'text'              => $body['message']['content'] ?? '',
			'prompt_tokens'     => $body['prompt_eval_count'] ?? 0,
			'completion_tokens' => $body['eval_count'] ?? 0,
		];
	}

	/** @inheritDoc */
	protected function parse_stream_chunk( string $line ): ?string {
		$decoded = json_decode( $line, true );
		if ( true === ( $decoded['done'] ?? false ) ) {
			return null;
		}
		return $decoded['message']['content'] ?? '';
	}

	/** @inheritDoc */
	protected function default_model(): string {
		return 'llama3.3';
	}

	// -------------------------------------------------------------------------
	// Validation + model listing.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	public function validate_key(): true|WP_Error {
		$base     = $this->settings->get( 'providers.ollama.base_url', 'http://localhost:11434' );
		$url      = rtrim( (string) $base, '/' ) . '/api/tags';
		$response = $this->http_get( $url );
		return is_wp_error( $response ) ? $response : true;
	}

	/** @inheritDoc */
	public function get_models(): array|WP_Error {
		return $this->cached_models( 'sfba_ollama_models', function () {
			$base     = $this->settings->get( 'providers.ollama.base_url', 'http://localhost:11434' );
			$url      = rtrim( (string) $base, '/' ) . '/api/tags';
			$response = $this->http_get( $url );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$models = [];
			foreach ( $response['models'] ?? [] as $model ) {
				$models[] = [
					'id'   => $model['name'],
					'name' => $model['name'],
				];
			}
			return $models;
		}, 5 * MINUTE_IN_SECONDS ); // Shorter TTL — local models change more often.
	}

	// -------------------------------------------------------------------------
	// Cost calculation.
	// -------------------------------------------------------------------------

	/** @inheritDoc — Ollama is always free (local inference). */
	protected function calculate_cost( int $prompt_tokens, int $completion_tokens, string $model ): float {
		return 0.0;
	}
}
