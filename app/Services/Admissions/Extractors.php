<?php

namespace App\Services\Admissions;

class Extractors
{
    /** Container binding wins (tests); otherwise Claude when a key exists, else manual entry. */
    public static function resolve(): DocumentExtractor
    {
        if (app()->bound(DocumentExtractor::class)) {
            return app(DocumentExtractor::class);
        }
        $key = config('services.anthropic.key');

        return filled($key)
            ? new ClaudeDocumentExtractor($key, config('services.anthropic.model') ?: 'claude-opus-5-5')
            : new ManualExtractor;
    }

    public static function aiEnabled(): bool
    {
        return app()->bound(DocumentExtractor::class) || filled(config('services.anthropic.key'));
    }
}
