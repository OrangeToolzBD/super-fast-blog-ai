<?php
/**
 * Anthropic provider — Claude Opus, Sonnet, Haiku.
 *
 * Pricing (April 2026):
 *   claude-opus-4-6:           $15.00 / 1M input, $75.00 / 1M output
 *   claude-sonnet-4-6:         $3.00  / 1M input, $15.00 / 1M output
 *   claude-haiku-4-5-20251001: $0.80  / 1M input, $4.00  / 1M output
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Provider_Anthropic extends SFBA_Provider_Abstract {

	/** Anthropic API version header value. */
	private const API_VERSION = '2023-06-01';

	/** @inheritDoc */
	public function get_slug(): string {
		return 'anthropic';
	}

	/** @inheritDoc */
	public static function display_name(): string {
		return 'Anthropic (Claude)';
	}

	// -------------------------------------------------------------------------
	// Abstract implementations.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	protected function endpoint(): string {
		return 'https://api.anthropic.com/v1/messages';
	}

	/** @inheritDoc */
	protected function build_headers(): array {
		return [
			'x-api-key'         => $this->api_key,
			'anthropic-version' => self::API_VERSION,
		];
	}

	/** @inheritDoc */
	protected function build_body( string $prompt, array $options ): array {
		$body = [
			'model'      => $options['model'] ?? $this->default_model(),
			'max_tokens' => $options['max_tokens'] ?? 4096,
			'messages'   => [
				[ 'role' => 'user', 'content' => $prompt ],
			],
		];

		if ( ! empty( $options['system'] ) ) {
			$body['system'] = $options['system'];
		}

		if ( isset( $options['temperature'] ) ) {
			$body['temperature'] = (float) $options['temperature'];
		}

		if ( ! empty( $options['stream'] ) ) {
			$body['stream'] = true;
		}

		return $body;
	}

	/** @inheritDoc */
	protected function parse_response( array $body ): array {
		$text = '';
		foreach ( $body['content'] ?? [] as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$text .= $block['text'];
			}
		}

		return [
			'text'              => $text,
			'prompt_tokens'     => $body['usage']['input_tokens']  ?? 0,
			'completion_tokens' => $body['usage']['output_tokens'] ?? 0,
		];
	}

	/**
	 * @inheritDoc
	 *
	 * Anthropic SSE:
	 *   event: content_block_delta
	 *   data: {"type":"content_block_delta","delta":{"type":"text_delta","text":"..."}}
	 *   event: message_stop
	 *   data: {"type":"message_stop"}
	 */
	protected function parse_stream_chunk( string $line ): ?string {
		if ( ! str_starts_with( $line, 'data: ' ) ) {
			return '';
		}

		$json    = substr( $line, 6 );
		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			return '';
		}

		$type = $decoded['type'] ?? '';

		if ( 'message_stop' === $type ) {
			return null;
		}

		if ( 'content_block_delta' === $type ) {
			return $decoded['delta']['text'] ?? '';
		}

		return '';
	}

	/** @inheritDoc */
	protected function default_model(): string {
		return 'claude-sonnet-4-6';
	}

	// -------------------------------------------------------------------------
	// Validation + model listing.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	public function validate_key(): bool|WP_Error {
		$response = $this->http_post( $this->endpoint(), [
			'model'      => 'claude-haiku-4-5-20251001',
			'max_tokens' => 1,
			'messages'   => [ [ 'role' => 'user', 'content' => 'Hi' ] ],
		] );
		return is_wp_error( $response ) ? $response : true;
	}

	/** @inheritDoc */
	public function get_models(): array|WP_Error {
		return [
			[ 'id' => 'claude-opus-4-6',          'name' => 'Claude Opus 4.6 — most capable' ],
			[ 'id' => 'claude-sonnet-4-6',         'name' => 'Claude Sonnet 4.6 — balanced' ],
			[ 'id' => 'claude-haiku-4-5-20251001', 'name' => 'Claude Haiku 4.5 — fast & cheap' ],
		];
	}

	// -------------------------------------------------------------------------
	// Cost calculation.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	protected function calculate_cost( int $prompt_tokens, int $completion_tokens, string $model ): float {
		$pricing = [
			'claude-opus-4-6'           => [ 'input' => 15.00, 'output' => 75.00 ],
			'claude-sonnet-4-6'         => [ 'input' => 3.00,  'output' => 15.00 ],
			'claude-haiku-4-5-20251001' => [ 'input' => 0.80,  'output' => 4.00  ],
			'claude-3-opus'             => [ 'input' => 15.00, 'output' => 75.00 ],
			'claude-3-sonnet'           => [ 'input' => 3.00,  'output' => 15.00 ],
			'claude-3-haiku'            => [ 'input' => 0.25,  'output' => 1.25  ],
		];

		if ( isset( $pricing[ $model ] ) ) {
			$rates = $pricing[ $model ];
		} else {
			$rates = $pricing['claude-sonnet-4-6'];
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
