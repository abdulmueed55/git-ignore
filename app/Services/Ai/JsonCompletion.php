<?php

namespace App\Services\Ai;

/** One request in, one JSON object out. Each provider implements this. */
interface JsonCompletion
{
    /**
     * @param  array<string, mixed>  $schema  JSON schema the answer must follow
     * @return array<string, mixed>
     *
     * @throws AiException when the provider fails or returns something unusable
     */
    public function json(string $system, string $prompt, array $schema): array;

    /** Short label shown to users, e.g. "OpenAI gpt-4.1-mini". */
    public function label(): string;
}
