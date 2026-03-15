<?php

declare(strict_types=1);

namespace AgenticOrchestrator\Providers;

use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\ToolResult;

/**
 * Manages LLM provider interactions via Prism PHP.
 *
 * This class provides a unified interface for all LLM providers,
 * abstracting away the differences between OpenAI, Anthropic, etc.
 */
class ProviderManager
{
    /**
     * @param  array<string, array<string, mixed>>  $providers  Provider configurations
     * @param  string  $defaultProvider  Default provider name
     */
    public function __construct(
        protected array $providers = [],
        protected string $defaultProvider = 'openai',
    ) {}

    /**
     * Send a chat request to an LLM provider.
     *
     * @param  string  $provider  Provider name
     * @param  string  $model  Model identifier
     * @param  array<int, array{role: string, content: string, tool_calls?: array, tool_call_id?: string}>  $messages
     * @param  array<int, array{type: string, function: array}>  $tools  Tool schemas
     * @param  float  $temperature  Temperature setting
     * @param  int|null  $maxTokens  Max tokens for response
     * @return array{content: string, tool_calls: array, usage: array, metadata: array, latency: float, finish_reason: string|null}
     */
    public function chat(
        string $provider,
        string $model,
        array $messages,
        array $tools = [],
        float $temperature = 0.7,
        ?int $maxTokens = null,
    ): array {
        $startTime = microtime(true);

        try {
            $prism = $this->buildPrismRequest($provider, $model, $messages, $tools, $temperature, $maxTokens);
            $response = $prism->asText();

            $latency = (microtime(true) - $startTime) * 1000;

            return [
                'content' => $response->text ?? '',
                'tool_calls' => $this->extractToolCalls($response),
                'usage' => [
                    'prompt_tokens' => $response->usage->promptTokens ?? 0,
                    'completion_tokens' => $response->usage->completionTokens ?? 0,
                    'total_tokens' => ($response->usage->promptTokens ?? 0) + ($response->usage->completionTokens ?? 0),
                ],
                'metadata' => [
                    'provider' => $provider,
                    'model' => $model,
                    'response_id' => $response->id ?? null,
                ],
                'latency' => $latency,
                'finish_reason' => $response->finishReason->value ?? null,
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Provider {$provider} request failed: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Build a Prism request.
     */
    protected function buildPrismRequest(
        string $provider,
        string $model,
        array $messages,
        array $tools,
        float $temperature,
        ?int $maxTokens,
    ): PendingRequest {
        $prismProvider = $this->mapProviderName($provider);

        $prism = Prism::text()
            ->using($prismProvider, $model)
            ->withMessages($this->convertMessages($messages))
            ->usingTemperature($temperature);

        if ($maxTokens !== null) {
            $prism->withMaxTokens($maxTokens);
        }

        if (! empty($tools)) {
            $prism->withTools($this->convertTools($tools));
        }

        return $prism;
    }

    /**
     * Map provider name to Prism Provider enum.
     */
    protected function mapProviderName(string $provider): Provider
    {
        return match (strtolower($provider)) {
            'openai' => Provider::OpenAI,
            'anthropic' => Provider::Anthropic,
            'gemini', 'google' => Provider::Gemini,
            'mistral' => Provider::Mistral,
            'ollama' => Provider::Ollama,
            'groq' => Provider::Groq,
            'xai' => Provider::XAI,
            'deepseek' => Provider::DeepSeek,
            default => Provider::OpenAI,
        };
    }

    /**
     * Convert messages array to Prism message objects.
     *
     * @param  array<int, array{role: string, content: string, tool_calls?: array, tool_call_id?: string}>  $messages
     * @return array<int, SystemMessage|UserMessage|AssistantMessage|ToolResultMessage>
     */
    protected function convertMessages(array $messages): array
    {
        $converted = [];

        foreach ($messages as $message) {
            $converted[] = match ($message['role']) {
                'system' => new SystemMessage($message['content']),
                'user' => new UserMessage($message['content']),
                'assistant' => new AssistantMessage($message['content'], $message['tool_calls'] ?? []),
                'tool' => new ToolResultMessage([
                    new ToolResult(
                        toolCallId: $message['tool_call_id'] ?? '',
                        toolName: $message['tool_name'] ?? 'unknown',
                        args: [],
                        result: $message['content'],
                    ),
                ]),
                default => new UserMessage($message['content']),
            };
        }

        return $converted;
    }

    /**
     * Convert tool schemas to Prism tool format.
     *
     * @param  array<int, array{type: string, function: array}>  $tools
     * @return array<int, Tool>
     */
    protected function convertTools(array $tools): array
    {
        $converted = [];

        foreach ($tools as $tool) {
            $function = $tool['function'];

            $prismTool = (new Tool)
                ->as($function['name'])
                ->for($function['description'])
                ->using(fn () => null); // Actual execution handled by agent

            // Add parameters from the schema if provided
            $parameters = $function['parameters'] ?? [];
            if (isset($parameters['properties']) && is_array($parameters['properties'])) {
                $required = $parameters['required'] ?? [];
                foreach ($parameters['properties'] as $name => $schema) {
                    $description = $schema['description'] ?? '';
                    $isRequired = in_array($name, $required, true);

                    match ($schema['type'] ?? 'string') {
                        'string' => $prismTool->withStringParameter($name, $description, $isRequired),
                        'number', 'integer' => $prismTool->withNumberParameter($name, $description, $isRequired),
                        'boolean' => $prismTool->withBooleanParameter($name, $description, $isRequired),
                        default => $prismTool->withStringParameter($name, $description, $isRequired),
                    };
                }
            }

            $converted[] = $prismTool;
        }

        return $converted;
    }

    /**
     * Extract tool calls from Prism response.
     *
     * @return array<int, array{id: string, type: string, function: array{name: string, arguments: string}}>
     */
    protected function extractToolCalls(object $response): array
    {
        if (! isset($response->toolCalls) || empty($response->toolCalls)) {
            return [];
        }

        $toolCalls = [];

        foreach ($response->toolCalls as $toolCall) {
            $toolCalls[] = [
                'id' => $toolCall->id ?? uniqid('call_'),
                'type' => 'function',
                'function' => [
                    'name' => $toolCall->name,
                    'arguments' => is_string($toolCall->arguments)
                        ? $toolCall->arguments
                        : json_encode($toolCall->arguments),
                ],
            ];
        }

        return $toolCalls;
    }

    /**
     * Get the default provider name.
     */
    public function getDefaultProvider(): string
    {
        return $this->defaultProvider;
    }

    /**
     * Check if a provider is configured.
     */
    public function hasProvider(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * Get provider configuration.
     *
     * @return array<string, mixed>|null
     */
    public function getProviderConfig(string $name): ?array
    {
        return $this->providers[$name] ?? null;
    }
}
