<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;

/** Claude Messages API with structured outputs. */
class ClaudeJson implements JsonCompletion
{
    public function __construct(private string $key, private string $model = 'claude-opus-5-5', private ?Client $client = null) {}

    public function json(string $system, string $prompt, array $schema): array
    {
        try {
            $message = ($this->client ??= new Client(apiKey: $this->key, requestOptions: Ai::claudeRequestOptions()))->messages->create(
                model: $this->model,
                maxTokens: 4096,
                system: $system,
                messages: [['role' => 'user', 'content' => $prompt]],
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
            );
        } catch (AuthenticationException) {
            throw new AiException('Claude rejected the API key — check ANTHROPIC_API_KEY.');
        } catch (RateLimitException) {
            throw new AiException('Claude is busy right now — try again in a minute.');
        } catch (APIConnectionException) {
            throw new AiException('Could not reach Claude — check the server internet connection.');
        } catch (AnthropicException|\Throwable $e) {
            report($e);
            throw new AiException('Claude returned an error.');
        }

        if (in_array($message->stopReason, ['refusal', 'max_tokens'], true)) {
            throw new AiException('Claude could not complete this request.');
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        throw new AiException('Claude returned an unreadable answer.');
    }

    public function label(): string
    {
        return 'Claude '.$this->model;
    }
}
