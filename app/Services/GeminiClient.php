<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gọi Gemini một lượt (không giữ lịch sử). null = không có key, mất mạng, hết lượt hoặc trả về rỗng:
 * nơi gọi tự dùng phương án không AI.
 */
class GeminiClient
{
    public function enabled(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * @param  array<string, mixed>  $generationConfig
     */
    public function generate(string $systemPrompt, string $message, array $generationConfig = [], string $logLabel = 'Gemini'): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            // Gemini hay trả 503 (máy chủ quá tải) hoặc 429 (hết lượt/phút): thử lại một lần sau 1 giây.
            $response = Http::timeout(15)
                ->retry(2, 1000, fn ($exception) => $exception instanceof RequestException && in_array($exception->response->status(), [429, 503], true), throw: false)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.config('services.gemini.model').':generateContent', [
                    'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $message]]]],
                    // Model có "suy nghĩ" tính cả token suy nghĩ vào giới hạn, nên để rộng.
                    'generationConfig' => $generationConfig + ['temperature' => 0.2, 'maxOutputTokens' => 2048],
                ]);
        } catch (ConnectionException $exception) {
            Log::warning("{$logLabel}: không kết nối được Gemini", ['error' => $exception->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning("{$logLabel}: Gemini trả lỗi", ['status' => $response->status()]);

            return null;
        }

        $text = collect($response->json('candidates.0.content.parts', []))
            ->reject(fn (array $part) => $part['thought'] ?? false)
            ->pluck('text')
            ->implode('');

        return trim($text) === '' ? null : trim($text);
    }
}
