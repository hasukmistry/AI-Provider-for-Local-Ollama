<?php

declare(strict_types=1);

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Text generation model backed by Ollama OpenAI-compatible endpoint.
 */
final class WPNull_Ollama_Local_Text_Generation_Model extends AbstractApiBasedModel implements TextGenerationModelInterface {

	/**
	 * @var string
	 */
	private string $base_url;

	/**
	 * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata   $metadata
	 * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata        $provider_metadata
	 * @param string                                                         $base_url
	 */
	public function __construct( $metadata, $provider_metadata, string $base_url ) {
		parent::__construct( $metadata, $provider_metadata );
		$this->base_url = rtrim( $base_url, '/' );
	}

	/**
	 * Generates text from prompt messages.
	 *
	 * @param list<Message> $prompt
	 */
	public function generateTextResult( array $prompt ): GenerativeAiResult {
		$request  = $this->create_request( $prompt );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		$data = $response->getData();
		if ( ! is_array( $data ) ) {
			throw new InvalidArgumentException( 'Invalid Ollama response payload.' );
		}

		$choices = $data['choices'] ?? null;
		if ( ! is_array( $choices ) || empty( $choices ) || ! is_array( $choices[0] ?? null ) ) {
			throw new InvalidArgumentException( 'Missing choices in Ollama response payload.' );
		}

		$choice = $choices[0];
		$message = $choice['message'] ?? null;
		$content = '';
		if ( is_array( $message ) && is_string( $message['content'] ?? null ) ) {
			$content = $message['content'];
		}

		$finish_reason = FinishReasonEnum::stop();
		if ( isset( $choice['finish_reason'] ) && 'length' === $choice['finish_reason'] ) {
			$finish_reason = FinishReasonEnum::length();
		}

		$candidate = new Candidate(
			new Message( MessageRoleEnum::model(), array( new MessagePart( $content ) ) ),
			$finish_reason
		);

		$usage = $data['usage'] ?? array();
		$token_usage = new TokenUsage(
			(int) ( $usage['prompt_tokens'] ?? 0 ),
			(int) ( $usage['completion_tokens'] ?? 0 ),
			(int) ( $usage['total_tokens'] ?? 0 )
		);

		return new GenerativeAiResult(
			is_string( $data['id'] ?? null ) ? $data['id'] : '',
			array( $candidate ),
			$token_usage,
			$this->providerMetadata(),
			$this->metadata(),
			array()
		);
	}

	/**
	 * @param list<Message> $prompt
	 */
	private function create_request( array $prompt ): Request {
		$config = $this->getConfig();
		$system_instruction = $this->enforce_english_instruction( $config->getSystemInstruction() );
		$payload = array(
			'model'    => $this->metadata()->getId(),
			'messages' => $this->prepare_messages( $prompt, $system_instruction ),
		);

		if ( null !== $config->getTemperature() ) {
			$payload['temperature'] = $config->getTemperature();
		}
		if ( null !== $config->getMaxTokens() ) {
			$payload['max_tokens'] = $config->getMaxTokens();
		}
		if ( null !== $config->getTopP() ) {
			$payload['top_p'] = $config->getTopP();
		}
		if ( null !== $config->getCandidateCount() ) {
			$payload['n'] = max( 1, $config->getCandidateCount() );
		}

		$stop_sequences = $config->getStopSequences();
		if ( is_array( $stop_sequences ) && ! empty( $stop_sequences ) ) {
			$payload['stop'] = $stop_sequences;
		}

		if ( 'application/json' === $config->getOutputMimeType() ) {
			$payload['response_format'] = array( 'type' => 'json_object' );
		}

		return new Request(
			HttpMethodEnum::POST(),
			$this->base_url . '/v1/chat/completions',
			array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			$payload,
			$this->getRequestOptions()
		);
	}

	/**
	 * Ensures model output remains in English across all AI features.
	 */
	private function enforce_english_instruction( ?string $instruction ): string {
		$english_guardrail = 'Always respond in English. If the input is in another language, still produce the final answer in English.';

		if ( is_string( $instruction ) && '' !== trim( $instruction ) ) {
			return rtrim( $instruction ) . "\n\n" . $english_guardrail;
		}

		return $english_guardrail;
	}

	/**
	 * @param list<Message>  $messages
	 * @param string|null    $system_instruction
	 * @return list<array{role:string,content:string}>
	 */
	private function prepare_messages( array $messages, ?string $system_instruction ): array {
		$prepared = array();

		if ( is_string( $system_instruction ) && '' !== trim( $system_instruction ) ) {
			$prepared[] = array(
				'role'    => 'system',
				'content' => $system_instruction,
			);
		}

		foreach ( $messages as $message ) {
			$role = $message->getRole()->isModel() ? 'assistant' : 'user';
			$text = $this->extract_text_from_parts( $message->getParts() );

			if ( '' === $text ) {
				continue;
			}

			$prepared[] = array(
				'role'    => $role,
				'content' => $text,
			);
		}

		if ( empty( $prepared ) ) {
			$prepared[] = array(
				'role'    => 'user',
				'content' => '',
			);
		}

		return $prepared;
	}

	/**
	 * @param list<MessagePart> $parts
	 */
	private function extract_text_from_parts( array $parts ): string {
		$chunks = array();

		foreach ( $parts as $part ) {
			if ( ! $part->getType()->isText() ) {
				continue;
			}

			if ( $part->getChannel()->isThought() ) {
				continue;
			}

			$chunks[] = $part->getText();
		}

		return implode( "\n", $chunks );
	}
}
