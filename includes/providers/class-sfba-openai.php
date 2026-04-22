<?php
/**
 * OpenAI provider — GPT-4o, GPT-4.1, GPT-4o-mini, o3, o4-mini.
 *
 * Pricing (April 2026):
 *   gpt-4o:       $2.50 / 1M input,  $10.00 / 1M output
 *   gpt-4o-mini:  $0.15 / 1M input,  $0.60  / 1M output
 *   gpt-4.1:      $2.00 / 1M input,  $8.00  / 1M output
 *   gpt-4.1-mini: $0.40 / 1M input,  $1.60  / 1M output
 *   o3:           $10.00 / 1M input, $40.00 / 1M output
 *   o4-mini:      $1.10 / 1M input,  $4.40  / 1M output
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Provider_OpenAI extends SFBA_Provider_Abstract {

	/** @inheritDoc */
	public function get_slug(): string {
		return 'openai';
	}

	/** @inheritDoc */
	public static function display_name(): string {
		return 'OpenAI';
	}

	// -------------------------------------------------------------------------
	// Abstract implementations.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	protected function endpoint(): string {
		return 'https://api.openai.com/v1/chat/completions';
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
			'prompt_tokens'     => $body['usage']['prompt_tokens']    ?? 0,
			'completion_tokens' => $body['usage']['completion_tokens'] ?? 0,
		];
	}

	/**
	 * @inheritDoc
	 *
	 * OpenAI SSE format:
	 *   data: {"choices":[{"delta":{"content":"..."}}]}
	 *   data: [DONE]
	 */
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
		return 'gpt-4o-mini';
	}

	// -------------------------------------------------------------------------
	// Validation + model listing.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	public function validate_key(): bool|WP_Error {
		$response = $this->http_get( 'https://api.openai.com/v1/models' );
		return is_wp_error( $response ) ? $response : true;
	}

	/** @inheritDoc */
	public function get_models(): array|WP_Error {
		return $this->cached_models( 'sfba_openai_models', function () {
			$response = $this->http_get( 'https://api.openai.com/v1/models' );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$models = [];
			foreach ( $response['data'] ?? [] as $model ) {
				$id = $model['id'] ?? '';
				if ( str_starts_with( $id, 'gpt-' ) || preg_match( '/^o\d/', $id ) ) {
					$models[] = [ 'id' => $id, 'name' => $id ];
				}
			}

			usort( $models, fn( $a, $b ) => strcmp( $a['id'], $b['id'] ) );
			return $models;
		} );
	}

	// -------------------------------------------------------------------------
	// Cost calculation.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	protected function calculate_cost( int $prompt_tokens, int $completion_tokens, string $model ): float {
		$pricing = [
			'gpt-4o'        => [ 'input' => 2.50,  'output' => 10.00 ],
			'gpt-4o-mini'   => [ 'input' => 0.15,  'output' => 0.60  ],
			'gpt-4.1'       => [ 'input' => 2.00,  'output' => 8.00  ],
			'gpt-4.1-mini'  => [ 'input' => 0.40,  'output' => 1.60  ],
			'o3'            => [ 'input' => 10.00, 'output' => 40.00 ],
			'o4-mini'       => [ 'input' => 1.10,  'output' => 4.40  ],
			'gpt-3.5-turbo' => [ 'input' => 0.50,  'output' => 1.50  ],
		];

		if ( isset( $pricing[ $model ] ) ) {
			$rates = $pricing[ $model ];
		} else {
			$rates = [ 'input' => 0.15, 'output' => 0.60 ];
			foreach ( $pricing as $prefix => $r ) {
				if ( str_starts_with( $model, $prefix ) ) {
					$rates = $r;
					break;
				}
			}
		}

		return round(
			( $prompt_tokens / 1_000_000 ) * $rates['input'] +
			( $completion_tokens / 1_000_000 ) * $rates['output'],
			6
		);
	}
}
