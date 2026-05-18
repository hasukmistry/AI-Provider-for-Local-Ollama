<?php

declare(strict_types=1);

use WordPress\AiClient\Providers\AbstractProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ollama provider registration for WordPress AI Client.
 */
final class WPNull_Ollama_Local_Provider extends AbstractProvider {

	/**
	 * Resolve host from environment fallback.
	 */
	protected static function base_url(): string {
		if ( function_exists( 'wpnull_ollama_local_get_host' ) ) {
			$host = wpnull_ollama_local_get_host();
			if ( '' !== $host ) {
				return $host;
			}
		}

		$host = getenv( 'OLLAMA_HOST' );
		if ( false !== $host && '' !== $host ) {
			return rtrim( (string) $host, '/' );
		}

		return '';
	}

	/**
	 * Returns provider metadata.
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			'ollama_local',
			'Ollama (Local)',
			ProviderTypeEnum::server(),
			null,
			RequestAuthenticationMethod::apiKey(),
			'Local Ollama server using OpenAI-compatible chat completions. Please provide your Ollama endpoint URL for localhost usage.'
		);
	}

	/**
	 * Creates provider availability checker.
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new ListModelsApiBasedProviderAvailability( static::createModelMetadataDirectory() );
	}

	/**
	 * Creates model metadata directory.
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new WPNull_Ollama_Local_Model_Metadata_Directory( self::base_url() );
	}

	/**
	 * Creates model instance.
	 */
	protected static function createModel( ModelMetadata $model_metadata, ProviderMetadata $provider_metadata ): ModelInterface {
		return new WPNull_Ollama_Local_Text_Generation_Model( $model_metadata, $provider_metadata, self::base_url() );
	}
}
