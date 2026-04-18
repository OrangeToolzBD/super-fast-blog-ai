<?php
/**
 * Google provider — Gemini 2.5 Pro, Gemini 2.0 Flash, Gemini 1.5.
 *
 * Auth: API key as query parameter (?key=…). No Authorization header needed.
 *
 * Pricing (April 2026):
 *   gemini-2.5-pro:   $1.25 / 1M input, $10.00 / 1M output
 *   gemini-2.0-flash: $0.10 / 1M input, $0.40  / 1M output
 *   gemini-1.5-flash: $0.075/ 1M input, $0.30  / 1M output
 *   gemini-1.5-pro:   $1.25 / 1M input, $5.00  / 1M output
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Provider_Google extends SFBA_Provider_Abstract {

	/** Base models endpoint (model is embedded in the path). */
	private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

	/** @inheritDoc */
	public function get_slug(): string {
		return 'google';
	}

	/** @inheritDoc */
	public static function display_name(): string {
		return 'Google (Gemini)';
	}

	// -------------------------------------------------------------------------
	// Abstract implementations.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	protected function endpoint(): string {
		return self::BASE_URL;
	}

	/** Build the blocking-generation endpoint for a given model. */
	private function model_endpoint( string $model ): string {
		return self::BASE_URL . '/' . rawurlencode( $model )
			. ':generateContent?key=' . rawurlencode( $this->api_key );
	}

	/** Build the streaming endpoint for a given model. */
	private function stream_endpoint( string $model ): string {
		return self::BASE_URL . '/' . rawurlencode( $model )
			. ':streamGenerateContent?alt=sse&key=' . rawurlencode( $this->api_key );
	}

	/** @inheritDoc — Google uses query-param auth; no Authorization header. */
	protected function build_headers(): array {
		return [];
	}

	/** @inheritDoc */
	protected function build_body( string $prompt, array $options ): array {
		$contents = [];

		if ( ! empty( $options['system'] ) ) {
			$contents[] = [
				'role'  => 'user',
				'parts' => [ [ 'text' => $options['system'] ] ],
			];
			$contents[] = [
				'role'  => 'model',
				'parts' => [ [ 'text' => 'Understood. I will follow those instructions.' ] ],
			];
		}

		$contents[] = [
			'role'  => 'user',
			'parts' => [ [ 'text' => $prompt ] ],
		];

		return [
			'contents'         => $contents,
			'generationConfig' => [
				'maxOutputTokens' => $options['max_tokens'] ?? 4096,
				'temperature'     => (float) ( $options['temperature'] ?? 0.7 ),
			],
		];
	}

	/** @inheritDoc */
	protected function parse_response( array $body ): array {
		$text = '';
		foreach ( $body['candidates'][0]['content']['parts'] ?? [] as $part ) {
			$text .= $part['text'] ?? '';
		}

		return [
			'text'              => $text,
			'prompt_tokens'     => $body['usageMetadata']['promptTokenCount']     ?? 0,
			'completion_tokens' => $body['usageMetadata']['candidatesTokenCount'] ?? 0,
		];
	}

	/**
	 * @inheritDoc
	 *
	 * Gemini SSE lines (alt=sse):
	 *   data: {"candidates":[{"content":{"parts":[{"text":"Hello"}]}}]}
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

		$finish_reason = $decoded['candidates'][0]['finishReason'] ?? '';
		if ( in_array( $finish_reason, [ 'STOP', 'MAX_TOKENS', 'SAFETY' ], true ) ) {
			$text = '';
			foreach ( $decoded['candidates'][0]['content']['parts'] ?? [] as $part ) {
				$text .= $part['text'] ?? '';
			}
			return $text;
		}

		$text = '';
		foreach ( $decoded['candidates'][0]['content']['parts'] ?? [] as $part ) {
			$text .= $part['text'] ?? '';
		}

		return $text;
	}

	// -------------------------------------------------------------------------
	// Blocking generate — override to inject model into URL path.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	public function generate( string $prompt, array $options = [] ): array|WP_Error {
		$model      = $options['model'] ?? $this->default_model();
		$started_at = microtime( true );

		$response = wp_remote_post( $this->model_endpoint( $model ), [
			'headers'   => [ 'Content-Type' => 'application/json' ],
			'body'      => wp_json_encode( $this->build_body( $prompt, $options ) ),
			'timeout'   => $this->timeout,
			'sslverify' => true,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sfba_http_error', $response->get_error_message() );
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body  = wp_remote_retrieve_body( $response );
		$decoded   = json_decode( $raw_body, true );

		if ( $http_code < 200 || $http_code >= 300 ) {
			$msg = is_array( $decoded ) ? ( $decoded['error']['message'] ?? $raw_body ) : $raw_body;
			return new WP_Error( 'sfba_api_error_' . $http_code, $msg );
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'sfba_parse_error', __( 'Could not parse Gemini response.', 'super-fast-blog-ai' ) );
		}

		$parsed             = $this->parse_response( $decoded );
		$generation_time_ms = (int) round( ( microtime( true ) - $started_at ) * 1000 );
		$cost_usd           = $this->calculate_cost( $parsed['prompt_tokens'], $parsed['completion_tokens'], $model );

		return [
			'text'               => $parsed['text'],
			'prompt_tokens'      => $parsed['prompt_tokens'],
			'completion_tokens'  => $parsed['completion_tokens'],
			'cost_usd'           => $cost_usd,
			'model'              => $model,
			'generation_time_ms' => $generation_time_ms,
		];
	}

	// -------------------------------------------------------------------------
	// Streaming — override to use :streamGenerateContent endpoint.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	public function stream( string $prompt, array $options, callable $on_chunk ): array|WP_Error {
		$model        = $options['model'] ?? $this->default_model();
		$body         = $this->build_body( $prompt, $options );
		$curl_headers = [ 'Content-Type: application/json' ];

		if ( function_exists( 'curl_init' ) ) {
			return $this->curl_stream( $this->stream_endpoint( $model ), $body, $curl_headers, $on_chunk, $model );
		}

		$result = $this->generate( $prompt, $options );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$tokens = preg_split( '/(\s+)/', $result['text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];
		foreach ( $tokens as $token ) {
			$on_chunk( $token );
		}

		return $result;
	}

	/** @inheritDoc */
	protected function default_model(): string {
		return 'gemini-2.0-flash';
	}

	// -------------------------------------------------------------------------
	// Validation + model listing.
	// -------------------------------------------------------------------------

	/** @inheritDoc */
	public function validate_key(): true|WP_Error {
		$url      = self::BASE_URL . '?key=' . rawurlencode( $this->api_key );
		$response = $this->http_get( $url );
		return is_wp_error( $response ) ? $response : true;
	}

	/** @inheritDoc */
	public function get_models(): array|WP_Error {
		return $this->cached_models( 'sfba_google_models', function () {
			$url      = self::BASE_URL . '?key=' . rawurlencode( $this->api_key );
			$response = $this->http_get( $url );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$models = [];
			foreach ( $response['models'] ?? [] as $model ) {
				if ( str_contains( $model['name'] ?? '', 'gemini' ) ) {
					$id       = str_replace( 'models/', '', $model['name'] );
					$models[] = [ 'id' => $id, 'name' => $model['displayName'] ?? $id ];
				}
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
			'gemini-2.5-pro'   => [ 'input' => 1.25,  'output' => 10.00 ],
			'gemini-2.0-flash' => [ 'input' => 0.10,  'output' => 0.40  ],
			'gemini-1.5-flash' => [ 'input' => 0.075, 'output' => 0.30  ],
			'gemini-1.5-pro'   => [ 'input' => 1.25,  'output' => 5.00  ],
		];

		$rates = $pricing['gemini-2.0-flash'];
		foreach ( $pricing as $prefix => $r ) {
			if ( str_starts_with( $model, $prefix ) ) {
				$rates = $r;
				break;
			}
		}

		return round(
			( $prompt_tokens / 1_000_000 ) * $rates['input'] +
			( $completion_tokens / 1_000_000 ) * $rates['output'],
			6
		);
	}
}
