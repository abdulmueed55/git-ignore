<?php

/*
| AI assistant used by "Speak attendance" (and other short text tasks).
| Set AI_PROVIDER to openai, gemini or anthropic and add that provider's key.
| Left on "auto", the first provider that has a key is used. Without any key,
| attendance still works with the built-in name / roll-number matcher.
*/

return [
    'provider' => env('AI_PROVIDER', 'auto'),

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('AI_ASSISTANT_MODEL', 'claude-opus-5-5'),
    ],

    'timeout' => (int) env('AI_TIMEOUT', 25),
];
