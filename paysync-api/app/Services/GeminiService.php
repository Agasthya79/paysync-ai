<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    public function explain(array $facts): ?string
    {
        $key = config('services.gemini.key');
        if (!$key) {
            Log::warning('Gemini skipped: GEMINI_API_KEY is not set');
            return null;
        }

        $model = config('services.gemini.model', 'gemini-2.5-flash');

        $prompt = "You explain invoice reconciliation results to a finance clerk.\n"
            . "Use ONLY the facts in the JSON below. Do not change the status, do not invent numbers, "
            . "names or dates, and do not give legal or financial advice.\n"
            . "Write 2 to 3 short sentences in plain English: what happened, why the engine reached this result, "
            . "and what the clerk should do next. Amounts are already formatted; copy them exactly.\n\n"
            . "FACTS:\n" . json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        try {
            $res = Http::withHeaders(['x-goog-api-key' => $key])
                ->acceptJson()
                ->timeout(40)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 800],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Gemini exception (' . $model . '): ' . substr($e->getMessage(), 0, 200));
            return null;
        }

        if ($res->failed()) {
            Log::warning('Gemini failed (' . $model . '): HTTP ' . $res->status() . ' ' . substr($res->body(), 0, 300));
            return null;
        }

        $text = trim((string) $res->json('candidates.0.content.parts.0.text'));

        if ($text === '') {
            Log::warning('Gemini returned no text (' . $model . '): ' . substr($res->body(), 0, 300));
            return null;
        }

        return $text;
    }
}