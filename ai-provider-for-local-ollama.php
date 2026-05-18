<?php
/**
 * Plugin Name: AI Provider for Local Ollama
 * Description: Registers a local Ollama connector/provider for the WordPress AI and Connectors APIs (no Ollama cloud API Endpoint required).
 * Version: 0.2.0
 * Author: Local
 * Requires at least: 7.0
 * Requires PHP: 7.4
 */

declare(strict_types=1);

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-ollama-provider.php';
require_once __DIR__ . '/includes/class-ollama-text-generation-model.php';
require_once __DIR__ . '/includes/class-ollama-model-metadata-directory.php';

/**
 * Connector setting used by the setup field.
 */
const WPNULL_OLLAMA_LOCAL_SETUP_SETTING = 'connectors_ai_ollama_local_api_key';
const WPNULL_OLLAMA_LOCAL_DEFAULT_MODEL_SETTING = 'connectors_ai_ollama_local_default_model';

/**
 * Normalizes user-provided Ollama host values.
 */
function wpnull_ollama_local_normalize_host( string $host ): string {
	$host = trim( $host );
	if ( '' === $host ) {
		return '';
	}

	if ( ! preg_match( '#^https?://#i', $host ) ) {
		$host = 'http://' . $host;
	}

	$host = esc_url_raw( $host );
	if ( ! is_string( $host ) || '' === $host ) {
		return '';
	}

	$parts = wp_parse_url( $host );
	if ( ! is_array( $parts ) ) {
		return '';
	}

	$scheme = isset( $parts['scheme'] ) && is_string( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
	$host_name = isset( $parts['host'] ) && is_string( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
	$port = isset( $parts['port'] ) ? (int) $parts['port'] : 0;

	if ( '' === $scheme || '' === $host_name ) {
		return '';
	}

	if ( 'http' !== $scheme && 'https' !== $scheme ) {
		return '';
	}

	if ( $port < 0 || $port > 65535 ) {
		return '';
	}

	$normalized = $scheme . '://' . $host_name;
	if ( $port > 0 ) {
		$normalized .= ':' . $port;
	}

	return $normalized;
}

/**
 * Capability gate for mutating connector settings.
 */
function wpnull_ollama_local_can_manage_settings(): bool {
	if ( is_multisite() && current_user_can( 'manage_network_options' ) ) {
		return true;
	}

	return current_user_can( 'manage_options' );
}

/**
 * Validates that the configured Ollama endpoint is healthy.
 *
 * @param string $endpoint Endpoint URL.
 * @return bool
 */
function wpnull_ollama_local_is_endpoint_healthy( string $endpoint ): bool {
	$endpoint = wpnull_ollama_local_normalize_host( $endpoint );
	if ( '' === $endpoint ) {
		return false;
	}

	$cache_key = 'wpnull_ollama_hc_' . md5( $endpoint );
	$cached    = get_transient( $cache_key );
	if ( '1' === $cached ) {
		return true;
	}
	if ( '0' === $cached ) {
		return false;
	}

	$args = array(
		'timeout'     => 3,
		'redirection' => 2,
		'sslverify'   => false,
		'headers'     => array(
			'Accept' => 'application/json,text/plain;q=0.9,*/*;q=0.8',
		),
	);

	$healthy  = false;
	$response = wp_remote_get( $endpoint, $args );
	if ( ! is_wp_error( $response ) ) {
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( $code >= 200 && $code < 300 && false !== stripos( $body, 'Ollama is running' ) ) {
			$healthy = true;
		}
	}

	if ( ! $healthy ) {
		$response = wp_remote_get( $endpoint . '/api/tags', $args );
		if ( ! is_wp_error( $response ) ) {
			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			if ( $code >= 200 && $code < 300 && is_array( $data ) && isset( $data['models'] ) && is_array( $data['models'] ) ) {
				$healthy = true;
			}
		}
	}

	set_transient( $cache_key, $healthy ? '1' : '0', 30 );

	return $healthy;
}

/**
 * Fetches available model IDs from a live Ollama endpoint.
 *
 * @param string $endpoint Endpoint URL.
 * @return array<int, string>
 */
function wpnull_ollama_local_fetch_model_ids( string $endpoint ): array {
	$endpoint = wpnull_ollama_local_normalize_host( $endpoint );
	if ( '' === $endpoint ) {
		return array();
	}

	$cache_key = 'wpnull_ollama_models_' . md5( $endpoint );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return array_values( array_filter( array_map( 'strval', $cached ) ) );
	}

	$response = wp_remote_get(
		$endpoint . '/api/tags',
		array(
			'timeout'     => 5,
			'redirection' => 2,
			'sslverify'   => false,
			'headers'     => array( 'Accept' => 'application/json' ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array();
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		return array();
	}

	$body = (string) wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	if ( ! is_array( $data ) || ! isset( $data['models'] ) || ! is_array( $data['models'] ) ) {
		return array();
	}

	$model_ids = array();
	foreach ( $data['models'] as $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}

		$name = $entry['name'] ?? '';
		if ( is_string( $name ) && '' !== trim( $name ) ) {
			$model_ids[] = trim( $name );
		}
	}

	$model_ids = array_values( array_unique( $model_ids ) );
	set_transient( $cache_key, $model_ids, 30 );

	return $model_ids;
}

/**
 * Resolves and persists a default model ID for the active endpoint.
 *
 * @param string $endpoint Endpoint URL.
 * @return string
 */
function wpnull_ollama_local_resolve_default_model_id( string $endpoint ): string {
	$endpoint = wpnull_ollama_local_normalize_host( $endpoint );
	if ( '' === $endpoint ) {
		return '';
	}

	$current = get_option( WPNULL_OLLAMA_LOCAL_DEFAULT_MODEL_SETTING, '' );
	if ( is_string( $current ) && '' !== trim( $current ) ) {
		return trim( $current );
	}

	$model_ids = wpnull_ollama_local_fetch_model_ids( $endpoint );
	if ( empty( $model_ids ) ) {
		return '';
	}

	$default = $model_ids[0];
	update_option( WPNULL_OLLAMA_LOCAL_DEFAULT_MODEL_SETTING, $default, false );

	return $default;
}

/**
 * Gets configured Ollama host.
 */
function wpnull_ollama_local_get_host(): string {
	$saved = get_option( WPNULL_OLLAMA_LOCAL_SETUP_SETTING, '' );
	if ( is_string( $saved ) ) {
		$saved = wpnull_ollama_local_normalize_host( $saved );
		if ( '' !== $saved ) {
			return $saved;
		}
	}

	$host = getenv( 'OLLAMA_HOST' );
	if ( false !== $host && '' !== $host ) {
		$host = wpnull_ollama_local_normalize_host( (string) $host );
		if ( '' !== $host ) {
			return $host;
		}
	}

	return '';
}

/**
 * Expose OLLAMA_HOST for provider classes.
 */
function wpnull_ollama_local_set_host_env(): void {
	$host = wpnull_ollama_local_get_host();
	if ( '' === $host ) {
		return;
	}

	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required to pass host to provider SDK.
	putenv( 'OLLAMA_HOST=' . $host );
}

/**
 * Registers the local Ollama provider into the core AI client registry.
 */
function wpnull_ollama_local_register_provider(): void {
	if ( ! function_exists( 'wp_supports_ai' ) || ! wp_supports_ai() ) {
		return;
	}

	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	wpnull_ollama_local_set_host_env();

	try {
		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( 'ollama_local' ) ) {
			$registry->registerProvider( WPNull_Ollama_Local_Provider::class );
		}
	} catch ( Throwable $e ) {
		if ( function_exists( 'wp_trigger_error' ) ) {
			wp_trigger_error( 'wpnull_ollama_local_register_provider', $e->getMessage() );
		}
	}
}
add_action( 'init', 'wpnull_ollama_local_register_provider', 5 );

/**
 * Register a fallback connector entry so the local provider always appears.
 *
 * @param WP_Connector_Registry $registry Connector registry instance.
 */
function wpnull_ollama_local_register_connector_fallback( WP_Connector_Registry $registry ): void {
	if ( $registry->is_registered( 'ollama_local' ) ) {
		return;
	}

	$registry->register(
		'ollama_local',
		array(
			'name'        => 'Ollama (Local)',
			'description' => 'Local Ollama server. Use Setup to provide your Ollama URL.',
			'type'        => 'ai_provider',
			'plugin'      => array(
				'file'      => plugin_basename( __FILE__ ),
				'is_active' => static function (): bool {
					return true;
				},
			),
			'authentication' => array(
				'method'        => 'api_key',
				'setting_name'  => WPNULL_OLLAMA_LOCAL_SETUP_SETTING,
				'constant_name' => 'OLLAMA_LOCAL_API_KEY',
				'env_var_name'  => 'OLLAMA_LOCAL_API_KEY',
			),
		)
	);
}
add_action( 'wp_connectors_init', 'wpnull_ollama_local_register_connector_fallback', 20 );

/**
 * Register local Ollama setup setting with URL-specific wording.
 */
function wpnull_ollama_local_register_setup_setting(): void {
	register_setting(
		'connectors',
		WPNULL_OLLAMA_LOCAL_SETUP_SETTING,
		array(
			'type'              => 'string',
			'label'             => __( 'Ollama API Endpoint URL', 'ai-provider-for-local-ollama' ),
			'description'       => __( 'Base URL for your Ollama instance, for example http://localhost:11434', 'ai-provider-for-local-ollama' ),
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => static function ( $value ) {
				return is_string( $value ) ? wpnull_ollama_local_normalize_host( $value ) : '';
			},
		)
	);
}
add_action( 'init', 'wpnull_ollama_local_register_setup_setting', 9 );

/**
 * Registers fallback empty authentication.
 */
function wpnull_ollama_local_register_fallback_auth(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();
	if ( ! $registry->hasProvider( 'ollama_local' ) ) {
		return;
	}

	$auth = $registry->getProviderRequestAuthentication( 'ollama_local' );
	if ( null !== $auth ) {
		return;
	}

	$registry->setProviderRequestAuthentication( 'ollama_local', new ApiKeyRequestAuthentication( '' ) );
}
add_action( 'init', 'wpnull_ollama_local_register_fallback_auth', 15 );

/**
 * Force local provider auth to stay empty after core key pass-through hooks.
 */
function wpnull_ollama_local_force_empty_auth(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();
	if ( ! $registry->hasProvider( 'ollama_local' ) ) {
		return;
	}

	$registry->setProviderRequestAuthentication( 'ollama_local', new ApiKeyRequestAuthentication( '' ) );
}
add_action( 'init', 'wpnull_ollama_local_force_empty_auth', 30 );

/**
 * Ensure the connectors UI sees a configured value without requiring user input.
 */
/**
 * Accept local Ollama key saves even when core AI key validation fails.
 *
 * WordPress currently validates ai_provider API keys by probing provider
 * availability and may clear the option if validation fails. For local Ollama,
 * any placeholder value should be considered acceptable.
 *
 * @param WP_REST_Response $response REST response.
 * @param WP_REST_Server   $server REST server.
 * @param WP_REST_Request  $request REST request.
 * @return WP_REST_Response
 */
function wpnull_ollama_local_force_settings_save( WP_REST_Response $response, WP_REST_Server $server, WP_REST_Request $request ): WP_REST_Response {
	if ( '/wp/v2/settings' !== $request->get_route() ) {
		return $response;
	}

	if ( 'POST' !== $request->get_method() && 'PUT' !== $request->get_method() ) {
		return $response;
	}

	if ( ! wpnull_ollama_local_can_manage_settings() ) {
		return $response;
	}

	$setting_name = WPNULL_OLLAMA_LOCAL_SETUP_SETTING;
	$key          = $request->get_param( $setting_name );

	if ( null === $key ) {
		return $response;
	}

	$key = is_string( $key ) ? wpnull_ollama_local_normalize_host( $key ) : '';
	if ( '' !== $key && ! wpnull_ollama_local_is_endpoint_healthy( $key ) ) {
		// Unhealthy endpoints must not remain connected.
		$key = '';
	}

	if ( '' !== $key ) {
		update_option( $setting_name, $key, false );
		// Refresh default model cache when endpoint is valid.
		delete_option( WPNULL_OLLAMA_LOCAL_DEFAULT_MODEL_SETTING );
		wpnull_ollama_local_resolve_default_model_id( $key );
	} else {
		update_option( $setting_name, '', false );
		delete_option( WPNULL_OLLAMA_LOCAL_DEFAULT_MODEL_SETTING );
	}

	$data = $response->get_data();
	if ( is_array( $data ) ) {
		$data[ $setting_name ] = $key;
		$response->set_data( $data );
	}

	return $response;
}
add_filter( 'rest_post_dispatch', 'wpnull_ollama_local_force_settings_save', 20, 3 );

/**
 * Show the plain endpoint URL value in settings responses (no masking).
 *
 * @param WP_REST_Response $response REST response.
 * @param WP_REST_Server   $server REST server.
 * @param WP_REST_Request  $request REST request.
 * @return WP_REST_Response
 */
function wpnull_ollama_local_unmask_settings_value( WP_REST_Response $response, WP_REST_Server $server, WP_REST_Request $request ): WP_REST_Response {
	if ( '/wp/v2/settings' !== $request->get_route() ) {
		return $response;
	}

	if ( ! wpnull_ollama_local_can_manage_settings() ) {
		return $response;
	}

	$data = $response->get_data();
	if ( ! is_array( $data ) ) {
		return $response;
	}

	$setting_name = WPNULL_OLLAMA_LOCAL_SETUP_SETTING;
	$value        = get_option( $setting_name, '' );
	if ( ! is_string( $value ) ) {
		$value = '';
	}

	$data[ $setting_name ] = $value;
	$response->set_data( $data );

	return $response;
}
add_filter( 'rest_post_dispatch', 'wpnull_ollama_local_unmask_settings_value', 50, 3 );

/**
 * Keep connectors page state stable for local Ollama across refreshes.
 *
 * @param array<string, mixed> $data Script module data.
 * @return array<string, mixed>
 */
function wpnull_ollama_local_force_connectors_ui_state( array $data ): array {
	if ( ! isset( $data['connectors'] ) || ! is_array( $data['connectors'] ) ) {
		return $data;
	}

	if ( ! isset( $data['connectors']['ollama_local'] ) || ! is_array( $data['connectors']['ollama_local'] ) ) {
		return $data;
	}

	$setting_name = WPNULL_OLLAMA_LOCAL_SETUP_SETTING;
	$key          = get_option( $setting_name, '' );
	if ( ! is_string( $key ) ) {
		$key = '';
	}
	$key        = wpnull_ollama_local_normalize_host( $key );
	$connected  = '' !== $key && wpnull_ollama_local_is_endpoint_healthy( $key );

	if ( ! isset( $data['connectors']['ollama_local']['authentication'] ) || ! is_array( $data['connectors']['ollama_local']['authentication'] ) ) {
		$data['connectors']['ollama_local']['authentication'] = array();
	}

	$data['connectors']['ollama_local']['authentication']['method']      = 'api_key';
	$data['connectors']['ollama_local']['authentication']['settingName'] = $setting_name;
	$data['connectors']['ollama_local']['authentication']['keySource']   = $connected ? 'database' : 'none';
	$data['connectors']['ollama_local']['authentication']['isConnected'] = $connected;

	return $data;
}
add_filter( 'script_module_data_options-connectors-wp-admin', 'wpnull_ollama_local_force_connectors_ui_state', 20, 1 );

/**
 * Mark AI credentials as available for non-API-key Ollama connector.
 *
 * @param bool  $has_credentials Current credentials state.
 * @param array $connectors Registered AI connectors.
 * @return bool
 */
function wpnull_ollama_local_has_credentials_filter( bool $has_credentials, array $connectors ): bool {
	if ( isset( $connectors['ollama_local'] ) && wpnull_ollama_local_is_endpoint_healthy( wpnull_ollama_local_get_host() ) ) {
		return true;
	}

	return $has_credentials;
}
add_filter( 'wpai_has_ai_credentials', 'wpnull_ollama_local_has_credentials_filter', 10, 2 );

/**
 * Validate the Ollama provider as configured when reachable.
 *
 * @param bool|null $valid Existing override value.
 * @return bool|null
 */
function wpnull_ollama_local_valid_credentials_filter( $valid ) {
	if ( null !== $valid ) {
		return $valid;
	}

	if ( ! class_exists( AiClient::class ) ) {
		return null;
	}

	try {
		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( 'ollama_local' ) ) {
			return null;
		}

		return wpnull_ollama_local_is_endpoint_healthy( wpnull_ollama_local_get_host() );
	} catch ( Throwable $e ) {
		return false;
	}
}
add_filter( 'wpai_pre_has_valid_credentials_check', 'wpnull_ollama_local_valid_credentials_filter', 10, 1 );

/**
 * Report connector configured state in AI dashboard widgets.
 *
 * @param bool $configured Existing state.
 * @return bool
 */
function wpnull_ollama_local_is_connector_configured_filter( bool $configured ): bool {
	if ( ! class_exists( AiClient::class ) ) {
		return $configured;
	}

	try {
		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( 'ollama_local' ) ) {
			return $configured;
		}

		return wpnull_ollama_local_is_endpoint_healthy( wpnull_ollama_local_get_host() );
	} catch ( Throwable $e ) {
		return false;
	}
}
add_filter( 'wpai_is_ollama_local_connector_configured', 'wpnull_ollama_local_is_connector_configured_filter', 10, 1 );

/**
 * Prefer the local Ollama model for text-generation-first features.
 *
 * @param array<int, array{string, string}> $models Existing preferred models.
 * @return array<int, array{string, string}>
 */
function wpnull_ollama_local_preferred_text_models_filter( array $models ): array {
	$host = wpnull_ollama_local_get_host();
	if ( '' === $host || ! wpnull_ollama_local_is_endpoint_healthy( $host ) ) {
		return $models;
	}

	$model = wpnull_ollama_local_resolve_default_model_id( $host );
	if ( '' !== $model ) {
		array_unshift( $models, array( 'ollama_local', $model ) );
	}

	return $models;
}
add_filter( 'wpai_preferred_text_models', 'wpnull_ollama_local_preferred_text_models_filter', 10, 1 );

/**
 * Allow outbound requests to Ollama host.
 *
 * @param bool   $external Existing external-host allowance result.
 * @param string $host Request host.
 * @param string $url Request URL.
 * @return bool
 */
function wpnull_ollama_local_allow_external_host( $external, string $host, string $url ): bool {
	$ollama_host = wpnull_ollama_local_get_host();
	if ( '' !== $ollama_host && false !== strpos( $url, $ollama_host ) ) {
		return true;
	}

	return (bool) $external;
}
add_filter( 'http_request_host_is_external', 'wpnull_ollama_local_allow_external_host', 10, 3 );

/**
 * Allow the Ollama port in safe HTTP requests.
 *
 * @param array<int> $ports Allowed safe ports.
 * @return array<int>
 */
function wpnull_ollama_local_allow_safe_ports( array $ports ): array {
	$port = wp_parse_url( wpnull_ollama_local_get_host(), PHP_URL_PORT );
	if ( ! $port ) {
		return $ports;
	}

	$ports[] = (int) $port;
	return array_values( array_unique( $ports ) );
}
add_filter( 'http_allowed_safe_ports', 'wpnull_ollama_local_allow_safe_ports', 10, 1 );

/**
 * Enqueue connectors admin UI copy customization script.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 */
function wpnull_ollama_local_enqueue_admin_connectors_script( string $hook_suffix ): void {
	$screen_id = function_exists( 'get_current_screen' ) && get_current_screen() ? get_current_screen()->id : '';
	$is_connectors_screen =
		'options-connectors.php' === $hook_suffix
		|| 'options-connectors' === $screen_id
		|| 'settings_page_options-connectors-wp-admin' === $screen_id
		|| ( isset( $_GET['page'] ) && 'options-connectors-wp-admin' === (string) $_GET['page'] );

	if ( ! $is_connectors_screen ) {
		return;
	}

	wp_enqueue_script(
		'wpnull-ollama-local-connectors-ui',
		plugins_url( 'includes/admin-connectors-ui.js', __FILE__ ),
		array(),
		'0.2.1',
		true
	);
}
add_action( 'admin_enqueue_scripts', 'wpnull_ollama_local_enqueue_admin_connectors_script', 100 );
