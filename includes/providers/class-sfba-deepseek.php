<?php
/**
 * DeepSeek provider — DeepSeek-V3, DeepSeek-R1.
 *
 * Pricing (April 2026):
 *   deepseek-chat (V3): $0.27 / 1M input, $1.10 / 1M output
 *   deepseek-reasoner:  $0.55 / 1M input, $2.19 / 1M output
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Provider_DeepSeek extends SFBA_Provider_Abstract {

	/** @inheritDoc */
	public function get_slug(): string {
		return 'deepseek';
	}

	/** @inheritDoc */
	public static function display_name(): string {
		return 'DeepSeek';
	}

	// -------------------------------------------------------------------------
	// Abstract implementations.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	protected function endpoint(): string {
		return 'https://api.deepseek.com/chat/completions';
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
		return 'deepseek-chat';
	}

	// -------------------------------------------------------------------------
	// Validation + model listing.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	public function validate_key(): true|WP_Error {
		$response = $this->http_get( 'https://api.deepseek.com/models' );
		return is_wp_error( $response ) ? $response : true;
	}

	/** @inheritDoc */
	public function get_models(): array|WP_Error {
		return [
			[ 'id' => 'deepseek-chat',     'name' => 'DeepSeek V3 (deepseek-chat)' ],
			[ 'id' => 'deepseek-reasoner', 'name' => 'DeepSeek R1 (deepseek-reasoner)' ],
		];
	}

	// -------------------------------------------------------------------------
	// Cost calculation.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	protected function calculate_cost( int $prompt_tokens, int $completion_tokens, string $model ): float {
		$pricing = [
			'deepseek-chat'     => [ 'input' => 0.27, 'output' => 1.10 ],
			'deepseek-reasoner' => [ 'input' => 0.55, 'output' => 2.19 ],
		];

		$rates = $pricing[ $model ] ?? $pricing['deepseek-chat'];

		return round(
			( $prompt_tokens / 1_000_000 ) * $rates['input'] +
			( $completion_tokens / 1_000_000 ) * $rates['output'],
			6
		);
	}
}
