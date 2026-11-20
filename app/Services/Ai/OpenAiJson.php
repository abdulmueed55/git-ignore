<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** OpenAI Chat Completions with a strict JSON-schema response. */
class OpenAiJson implements JsonCompletion
{
    public function __construct(private string $key, private string $model, private string $url, private int $timeout = 25) {}

    public function json(string $system, string $prompt, array $schema): array
    {
        try {
            $res = Http::withToken($this->key)->timeout($this->timeout)->acceptJson()
                ->post(rtrim($this->url, '/').'/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'answer', 'strict' => true, 'schema' => $schema]],
                ]);
        } catch (ConnectionException) {
            throw new AiException('Could not reach OpenAI — check the server internet connection.');
        }

        if ($res->status() === 401) {
            throw new AiException('OpenAI rejected the API key — check OPENAI_API_KEY.');
        }
        if ($res->status() === 429) {
            throw new AiException('OpenAI is busy or the quota is used up — try again in a minute.');
        }
        if ($res->failed()) {
            throw new AiException('OpenAI returned an error ('.$res->status().').');
        }

        $data = json_decode((string) $res->json('choices.0.message.content'), true);
        if (! is_array($data)) {
            throw new AiException('OpenAI returned an unreadable answer.');
        }

        return $data;
    }

    public function label(): string
    {
        return 'OpenAI '.$this->model;
    }
}
