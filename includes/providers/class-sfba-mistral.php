<?php
/**
 * Mistral AI provider — Mistral Large, Small, Nemo, Codestral.
 *
 * Pricing (April 2026):
 *   mistral-large-latest: $2.00 / 1M input, $6.00 / 1M output
 *   mistral-small-latest: $0.10 / 1M input, $0.30 / 1M output
 *   open-mistral-nemo:    $0.15 / 1M input, $0.15 / 1M output
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Provider_Mistral extends SFBA_Provider_Abstract {

	/** @inheritDoc */
	public function get_slug(): string {
		return 'mistral';
	}

	/** @inheritDoc */
	public static function display_name(): string {
		return 'Mistral AI';
	}

	// -------------------------------------------------------------------------
	// Abstract implementations.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	protected function endpoint(): string {
		return 'https://api.mistral.ai/v1/chat/completions';
	}

	/** @inheritDoc */
	protected function build_headers(): array {
		return [
			'Authorization' => 'Bearer ' . $this->api_key,
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
		return 'mistral-small-latest';
	}

	// -------------------------------------------------------------------------
	// Validation + model listing.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	public function validate_key(): bool|WP_Error {
		$response = $this->http_get( 'https://api.mistral.ai/v1/models' );
		return is_wp_error( $response ) ? $response : true;
	}

	/** @inheritDoc */
	public function get_models(): array|WP_Error {
		return $this->cached_models( 'sfba_mistral_models', function () {
			$response = $this->http_get( 'https://api.mistral.ai/v1/models' );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$models = [];
			foreach ( $response['data'] ?? [] as $model ) {
				$models[] = [ 'id' => $model['id'], 'name' => $model['id'] ];
			}
			return $models;
		} );
	}

	// -------------------------------------------------------------------------
	// Cost calculation.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	protected function calculate_cost( int $prompt_tokens, int $completion_tokens, string $model ): float {
		$pricing = [
			'mistral-large-latest' => [ 'input' => 2.00, 'output' => 6.00 ],
			'mistral-small-latest' => [ 'input' => 0.10, 'output' => 0.30 ],
			'open-mistral-nemo'    => [ 'input' => 0.15, 'output' => 0.15 ],
		];

		$rates = $pricing[ $model ] ?? $pricing['mistral-small-latest'];

		return round(
			( $prompt_tokens / 1_000_000 ) * $rates['input'] +
			( $completion_tokens / 1_000_000 ) * $rates['output'],
			6
		);
	}
}
