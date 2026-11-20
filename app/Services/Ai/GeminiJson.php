<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Google Gemini generateContent with a JSON response. */
class GeminiJson implements JsonCompletion
{
    public function __construct(private string $key, private string $model, private string $url, private int $timeout = 25) {}

    public function json(string $system, string $prompt, array $schema): array
    {
        try {
            $res = Http::timeout($this->timeout)->acceptJson()
                ->withHeaders(['x-goog-api-key' => $this->key])
                ->post(rtrim($this->url, '/').'/models/'.$this->model.':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt."\n\nAnswer with one JSON object that matches this JSON Schema exactly:\n".json_encode($schema)]]]],
                    'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0],
                ]);
        } catch (ConnectionException) {
            throw new AiException('Could not reach Gemini — check the server internet connection.');
        }

        if (in_array($res->status(), [401, 403], true)) {
            throw new AiException('Gemini rejected the API key — check GEMINI_API_KEY.');
        }
        if ($res->status() === 429) {
            throw new AiException('Gemini is busy or the quota is used up — try again in a minute.');
        }
        if ($res->failed()) {
            throw new AiException('Gemini returned an error ('.$res->status().').');
        }

        $text = (string) $res->json('candidates.0.content.parts.0.text');
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($text));
        $data = json_decode($text, true);
        if (! is_array($data)) {
            throw new AiException('Gemini returned an unreadable answer.');
        }

        return $data;
    }

    public function label(): string
    {
        return 'Gemini '.$this->model;
    }
}
