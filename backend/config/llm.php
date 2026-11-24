<?php

return [
    'base_url' => env('LLM_BASE_URL', 'http://localhost:11434/v1'),
    'api_key' => env('LLM_API_KEY', 'ollama'),
    'model' => env('LLM_MODEL', 'aya-expanse:8b'),
    'timeout' => (int) env('LLM_TIMEOUT', 300),
];