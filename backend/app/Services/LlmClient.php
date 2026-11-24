<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class LlmClient
{
    public function chat(string $system, string $user): string
    {
        $response = Http::withToken(config('llm.api_key'))
            ->timeout(config('llm.timeout'))
            ->post(rtrim(config('llm.base_url'), '/') . '/chat/completions', [
                'model' => config('llm.model'),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'temperature' => 0.3,
                'stream' => false,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'LLM request failed: ' . $response->status() . ' ' . $response->body()
            );
        }

        return $response->json('choices.0.message.content')
            ?? throw new RuntimeException('LLM returned an empty response.');
    }
}