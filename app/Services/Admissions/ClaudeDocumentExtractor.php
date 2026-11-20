<?php

namespace App\Services\Admissions;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use Illuminate\Support\Facades\Log;

/**
 * Reads CNIC / B-Form, BISE result cards, domicile and character certificates
 * with Claude vision and returns schema-validated JSON (structured outputs).
 */
class ClaudeDocumentExtractor implements DocumentExtractor
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-opus-5-5',
        private ?Client $client = null,
    ) {}

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: $this->apiKey);
    }

    public function extract(string $type, string $binary, string $mime): array
    {
        $by = $this->model;
        $fail = fn (string $error) => ['status' => 'failed', 'data' => null, 'confidence' => null, 'error' => $error, 'by' => $by];

        $source = ['type' => 'base64', 'mediaType' => $mime, 'data' => base64_encode($binary)];
        $block = $mime === 'application/pdf'
            ? ['type' => 'document', 'source' => $source]
            : ['type' => 'image', 'source' => $source];

        try {
            $message = $this->client()->messages->create(
                model: $this->model,
                maxTokens: 4096,
                messages: [[
                    'role' => 'user',
                    'content' => [$block, ['type' => 'text', 'text' => DocumentSchemas::prompt($type)]],
                ]],
                outputConfig: [
                    'effort' => 'low',
                    'format' => ['type' => 'json_schema', 'schema' => DocumentSchemas::for($type)],
                ],
            );
        } catch (AuthenticationException) {
            return $fail('AI service rejected the API key — check ANTHROPIC_API_KEY.');
        } catch (RateLimitException) {
            return $fail('AI service is busy right now — retry in a minute.');
        } catch (APIStatusException $e) {
            Log::warning('Document extraction API error', ['type' => $type, 'error' => $e->getMessage()]);

            return $fail('AI service error ('.($e->type?->value ?? 'unknown').').');
        } catch (APIConnectionException) {
            return $fail('Could not reach the AI service — check the server internet connection.');
        } catch (AnthropicException $e) {
            Log::warning('Document extraction failed', ['type' => $type, 'error' => $e->getMessage()]);

            return $fail('AI reading failed.');
        }

        if ($message->stopReason === 'refusal') {
            return $fail('The AI declined to read this file. Please enter details manually.');
        }
        if ($message->stopReason === 'max_tokens') {
            return $fail('The AI response was cut off. Retry reading this document.');
        }

        $data = null;
        foreach ($message->content as $content) {
            if ($content->type === 'text') {
                $data = json_decode($content->text, true);
                break;
            }
        }
        if (! is_array($data)) {
            return $fail('The AI returned an unreadable answer.');
        }

        $confidence = (int) max(0, min(100, $data['confidence'] ?? 0));

        return ['status' => 'done', 'data' => $data, 'confidence' => $confidence, 'error' => null, 'by' => $by];
    }
}
