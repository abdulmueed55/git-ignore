<?php

namespace App\Services\Ai;

/** Picks the configured AI provider (or none). */
class Ai
{
    public static function provider(): ?string
    {
        $chosen = strtolower((string) config('ai.provider', 'auto'));
        $has = fn (string $p) => filled(config("ai.{$p}.key"));

        if ($chosen !== 'auto') {
            return in_array($chosen, ['openai', 'gemini', 'anthropic'], true) && $has($chosen) ? $chosen : null;
        }
        foreach (['openai', 'gemini', 'anthropic'] as $p) {
            if ($has($p)) {
                return $p;
            }
        }

        return null;
    }

    public static function client(): ?JsonCompletion
    {
        $timeout = (int) config('ai.timeout', 25);

        return match (self::provider()) {
            'openai' => new OpenAiJson(config('ai.openai.key'), config('ai.openai.model'), config('ai.openai.url'), $timeout),
            'gemini' => new GeminiJson(config('ai.gemini.key'), config('ai.gemini.model'), config('ai.gemini.url'), $timeout),
            'anthropic' => new ClaudeJson(config('ai.anthropic.key'), config('ai.anthropic.model')),
            default => null,
        };
    }
}
