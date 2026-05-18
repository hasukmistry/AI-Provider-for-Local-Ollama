<?php

declare(strict_types=1);

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discovers available Ollama models and maps their AI-client metadata.
 */
final class WPNull_Ollama_Local_Model_Metadata_Directory extends AbstractApiBasedModelMetadataDirectory {

	/**
	 * @var string
	 */
	private string $base_url;

	/**
	 * @param string $base_url Ollama base URL.
	 */
	public function __construct( string $base_url ) {
		$this->base_url = rtrim( $base_url, '/' );
	}

	/**
	 * Sends request to Ollama tags endpoint.
	 *
	 * @return array<string, ModelMetadata>
	 */
	protected function sendListModelsRequest(): array {
		try {
			$request  = $this->createRequest( HttpMethodEnum::GET(), '/api/tags' );
			$response = $this->getHttpTransporter()->send( $request );
			ResponseUtil::throwIfNotSuccessful( $response );
		} catch ( Throwable $e ) {
			return $this->cached_model_map();
		}

		$data = $response->getData();
		if ( ! is_array( $data ) ) {
			return $this->cached_model_map();
		}

		$models = $data['models'] ?? null;
		if ( ! is_array( $models ) ) {
			return $this->cached_model_map();
		}

		$map = array();
		foreach ( $models as $model ) {
			if ( ! is_array( $model ) ) {
				continue;
			}

			$model_id = $model['name'] ?? '';
			if ( ! is_string( $model_id ) || '' === $model_id ) {
				continue;
			}

			$map[ $model_id ] = $this->build_text_model_metadata( $model_id );
		}

		if ( empty( $map ) ) {
			return $this->cached_model_map();
		}

		ksort( $map );
		$first_model_id = (string) array_key_first( $map );
		if ( '' !== $first_model_id ) {
			update_option( 'connectors_ai_ollama_local_default_model', $first_model_id, false );
		}

		return $map;
	}

	/**
	 * Creates Request instance.
	 */
	private function createRequest( HttpMethodEnum $method, string $path ): Request {
		return new Request(
			$method,
			$this->base_url . '/' . ltrim( $path, '/' ),
			array(
				'Accept' => 'application/json',
			)
		);
	}

	/**
	 * @param string $model_id
	 */
	private function build_text_model_metadata( string $model_id ): ModelMetadata {
		return new ModelMetadata(
			$model_id,
			$model_id,
			array(
				CapabilityEnum::textGeneration(),
				CapabilityEnum::chatHistory(),
			),
			array(
				new SupportedOption( OptionEnum::systemInstruction() ),
				new SupportedOption( OptionEnum::candidateCount() ),
				new SupportedOption( OptionEnum::maxTokens() ),
				new SupportedOption( OptionEnum::temperature() ),
				new SupportedOption( OptionEnum::topP() ),
				new SupportedOption( OptionEnum::topK() ),
				new SupportedOption( OptionEnum::stopSequences() ),
				new SupportedOption( OptionEnum::presencePenalty() ),
				new SupportedOption( OptionEnum::frequencyPenalty() ),
				new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
				new SupportedOption( OptionEnum::outputSchema() ),
				new SupportedOption( OptionEnum::functionDeclarations() ),
				new SupportedOption( OptionEnum::customOptions() ),
				new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
				new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			)
		);
	}

	/**
	 * Falls back to the last discovered model when live discovery is unavailable.
	 *
	 * @return array<string, ModelMetadata>
	 */
	private function cached_model_map(): array {
		$model_id = get_option( 'connectors_ai_ollama_local_default_model', '' );
		if ( ! is_string( $model_id ) || '' === trim( $model_id ) ) {
			return array();
		}

		$model_id = trim( $model_id );

		return array(
			$model_id => $this->build_text_model_metadata( $model_id ),
		);
	}
}
