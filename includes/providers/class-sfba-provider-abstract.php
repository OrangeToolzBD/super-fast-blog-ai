<?php
/**
 * Abstract base class for all AI providers.
 *
 * Shared infrastructure:
 *   - Encrypted API key reference.
 *   - wp_remote_post() blocking HTTP with error normalisation.
 *   - Real SSE streaming via cURL with word-by-word fallback.
 *   - Token-cost calculation stub (overridden per provider).
 *   - Transient-backed model-list caching.
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class SFBA_Provider_Abstract implements SFBA_Provider_Interface {

	/**
	 * Decrypted API key.
	 *
	 * @var string
	 */
	protected string $api_key;

	/**
	 * Settings reference.
	 *
	 * @var SFBA_Settings
	 */
	protected SFBA_Settings $settings;

	/**
	 * Default HTTP timeout in seconds.
	 *
	 * @var int
	 */
	protected int $timeout = 90;

	/**
	 * @param string        $api_key  Decrypted key.
	 * @param SFBA_Settings $settings Plugin settings instance.
	 */
	public function __construct( string $api_key, SFBA_Settings $settings ) {
		$this->api_key  = $api_key;
		$this->settings = $settings;
	}

	// -------------------------------------------------------------------------
	// Abstract — concrete providers must implement.
	// -------------------------------------------------------------------------

	abstract protected function endpoint(): string;
	abstract protected function build_headers(): array;
	abstract protected function build_body( string $prompt, array $options ): array;

	/**
	 * @return array{text: string, prompt_tokens: int, completion_tokens: int}
	 */
	abstract protected function parse_response( array $body ): array;

	/**
	 * Return '' to skip, null to signal end-of-stream.
	 */
	abstract protected function parse_stream_chunk( string $line ): ?string;

	// -------------------------------------------------------------------------
	// SFBA_Provider_Interface — shared implementations.
	// -------------------------------------------------------------------------

	/** {@inheritdoc} */
	public function generate( string $prompt, array $options = [] ): array|WP_Error {
		$started_at = microtime( true );

		$response = $this->http_post(
			$this->endpoint(),
			$this->build_body( $prompt, $options )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parsed             = $this->parse_response( $response );
		$generation_time_ms = (int) round( ( microtime( true ) - $started_at ) * 1000 );
		$cost_usd           = $this->calculate_cost(
			$parsed['prompt_tokens'],
			$parsed['completion_tokens'],
			$options['model'] ?? $this->default_model()
		);

		return [
			'text'               => $parsed['text'],
			'prompt_tokens'      => $parsed['prompt_tokens'],
			'completion_tokens'  => $parsed['completion_tokens'],
			'cost_usd'           => $cost_usd,
			'model'              => $options['model'] ?? $this->default_model(),
			'generation_time_ms' => $generation_time_ms,
		];
	}

	/** {@inheritdoc} */
	public function stream( string $prompt, array $options, callable $on_chunk ): array|WP_Error {
		$model       = $options['model'] ?? $this->default_model();
		$stream_body = $this->build_body( $prompt, array_merge( $options, [ 'stream' => true ] ) );

		$curl_headers = [ 'Content-Type: application/json' ];
		foreach ( $this->build_headers() as $k => $v ) {
			$curl_headers[] = "{$k}: {$v}";
		}

		if ( function_exists( 'curl_init' ) ) {
			return $this->curl_stream(
				$this->endpoint(),
				$stream_body,
				$curl_headers,
				$on_chunk,
				$model
			);
		}

		// Fallback: blocking generate + word-by-word emission.
		$result = $this->generate( $prompt, $options );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$tokens = preg_split(
			'/(\s+)/',
			$result['text'],
			-1,
			PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
		) ?: [];

		foreach ( $tokens as $token ) {
			$on_chunk( $token );
		}

		return $result;
	}

	/** {@inheritdoc} */
	public function get_models(): array|WP_Error {
		return [];
	}

	// -------------------------------------------------------------------------
	// HTTP helpers.
	// -------------------------------------------------------------------------

	/**
	 * Authenticated JSON POST via WordPress HTTP API (blocking).
	 *
	 * @param string $url
	 * @param array  $body
	 * @return array|WP_Error
	 */
	protected function http_post( string $url, array $body ): array|WP_Error {
		$response = wp_remote_post( $url, [
			'headers'   => array_merge(
				[ 'Content-Type' => 'application/json' ],
				$this->build_headers()
			),
			'body'      => wp_json_encode( $body ),
			'timeout'   => $this->timeout,
			'sslverify' => true,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sfba_http_error', $response->get_error_message() );
		}

		return $this->parse_http_response( $response );
	}

	/**
	 * Authenticated JSON GET via WordPress HTTP API.
	 *
	 * @param string $url
	 * @return array|WP_Error
	 */
	protected function http_get( string $url ): array|WP_Error {
		$response = wp_remote_get( $url, [
			'headers'   => array_merge(
				[ 'Content-Type' => 'application/json' ],
				$this->build_headers()
			),
			'timeout'   => 20,
			'sslverify' => true,
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sfba_http_error', $response->get_error_message() );
		}

		return $this->parse_http_response( $response );
	}

	/**
	 * Shared HTTP response decoder.
	 *
	 * @param array $response wp_remote_*() result.
	 * @return array|WP_Error
	 */
	private function parse_http_response( array $response ): array|WP_Error {
		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body  = wp_remote_retrieve_body( $response );
		$decoded   = json_decode( $raw_body, true );

		if ( $http_code < 200 || $http_code >= 300 ) {
			$api_message = is_array( $decoded )
				? ( $decoded['error']['message'] ?? $raw_body )
				: $raw_body;

			return new WP_Error(
				'sfba_api_error_' . $http_code,
				sprintf(
					/* translators: 1: HTTP status code, 2: provider error message */
					__( 'API error %1$d: %2$s', 'super-fast-blog-ai' ),
					$http_code,
					$api_message
				),
				[ 'http_code' => $http_code, 'body' => $raw_body ]
			);
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'sfba_parse_error',
				__( 'Could not parse API response as JSON.', 'super-fast-blog-ai' )
			);
		}

		return $decoded;
	}

	// -------------------------------------------------------------------------
	// Real SSE streaming via cURL.
	// -------------------------------------------------------------------------

	/**
	 * Execute a streaming POST request and emit text chunks via $on_chunk.
	 *
	 * @param string   $url
	 * @param array    $body
	 * @param string[] $curl_headers
	 * @param callable $on_chunk
	 * @param string   $model
	 * @return array|WP_Error
	 */
	protected function curl_stream(
		string $url,
		array $body,
		array $curl_headers,
		callable $on_chunk,
		string $model
	): array|WP_Error {

		$full_text   = '';
		$buffer      = '';
		$stream_done = false;
		$started     = microtime( true );

		$ch = curl_init( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		curl_setopt_array( $ch, [ // phpcs:ignore WordPress.WP.AlternativeFunctions
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => wp_json_encode( $body ),
			CURLOPT_HTTPHEADER     => $curl_headers,
			CURLOPT_TIMEOUT        => $this->timeout,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_WRITEFUNCTION  => function ( $ch, $data ) use (
				$on_chunk,
				&$buffer,
				&$full_text,
				&$stream_done
			): int {
				if ( $stream_done ) {
					return strlen( $data );
				}

				$buffer .= $data;
				$lines   = explode( "\n", $buffer );
				$buffer  = array_pop( $lines );

				foreach ( $lines as $raw_line ) {
					$line = trim( $raw_line );

					if ( '' === $line || str_starts_with( $line, ':' ) ) {
						continue;
					}

					$chunk = $this->parse_stream_chunk( $line );

					if ( null === $chunk ) {
						$stream_done = true;
						break;
					}

					if ( '' !== $chunk ) {
						$full_text .= $chunk;
						$on_chunk( $chunk );
					}
				}

				return strlen( $data );
			},
		] );

		curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$curl_err  = curl_error( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		curl_close( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( $curl_err ) {
			return new WP_Error( 'sfba_curl_error', $curl_err );
		}

		if ( $http_code >= 400 ) {
			return new WP_Error(
				'sfba_api_error_' . $http_code,
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'API streaming error %d — check your API key and quota.', 'super-fast-blog-ai' ),
					$http_code
				)
			);
		}

		$gen_ms     = (int) round( ( microtime( true ) - $started ) * 1000 );
		$est_words  = str_word_count( $full_text );
		$est_tokens = (int) ceil( $est_words * 1.33 );
		$cost_usd   = $this->calculate_cost( 0, $est_tokens, $model );

		return [
			'text'               => $full_text,
			'prompt_tokens'      => 0,
			'completion_tokens'  => $est_tokens,
			'cost_usd'           => $cost_usd,
			'model'              => $model,
			'generation_time_ms' => $gen_ms,
		];
	}

	// -------------------------------------------------------------------------
	// Cost + model helpers — override per provider.
	// -------------------------------------------------------------------------

	/**
	 * Calculate generation cost in USD. Override in concrete provider.
	 *
	 * @param int    $prompt_tokens
	 * @param int    $completion_tokens
	 * @param string $model
	 * @return float
	 */
	protected function calculate_cost( int $prompt_tokens, int $completion_tokens, string $model ): float {
		return 0.0;
	}

	/**
	 * Provider's default model identifier. Override in concrete class.
	 *
	 * @return string
	 */
	protected function default_model(): string {
		return '';
	}

	/**
	 * Public accessor for the provider's default model identifier.
	 */
	public function get_default_model(): string {
		return $this->default_model();
	}

	// -------------------------------------------------------------------------
	// Transient model-list cache.
	// -------------------------------------------------------------------------

	/**
	 * Return cached model list or fetch and cache it.
	 *
	 * @param string   $transient_key
	 * @param callable $fetch_callback Returns array|WP_Error.
	 * @param int      $ttl            Cache lifetime in seconds.
	 * @return array|WP_Error
	 */
	protected function cached_models(
		string $transient_key,
		callable $fetch_callback,
		int $ttl = HOUR_IN_SECONDS
	): array|WP_Error {
		$cached = get_transient( $transient_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$models = $fetch_callback();
		if ( is_wp_error( $models ) ) {
			return $models;
		}

		set_transient( $transient_key, $models, $ttl );
		return $models;
	}

	/**
	 * Human-readable provider name. Concrete providers MUST override.
	 *
	 * @return string
	 */
	public static function display_name(): string {
		return 'Unknown Provider';
	}
}
