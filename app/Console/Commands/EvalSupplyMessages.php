<?php

namespace App\Console\Commands;

use App\Services\GeminiClient;
use App\Services\SupplyMessageService;
use App\Support\SupplyMessageScore;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('supply:eval-messages
    {--split=test : Phần bộ tin để chấm: test, dev hoặc all}
    {--file=database/data/tin-nhan-zalo-mau.json : File bộ tin mẫu}
    {--delay=4 : Số giây nghỉ giữa hai lần gọi AI (gói miễn phí giới hạn số lần/phút)}
    {--details : In từng tin bị xử lý sai}')]
#[Description('Chấm cách đọc tin nhắn Zalo báo sản lượng: AI so với cách dò từ khóa')]
class EvalSupplyMessages extends Command
{
    public function handle(SupplyMessageService $reader, GeminiClient $gemini): int
    {
        $path = base_path($this->option('file'));
        $messages = collect(json_decode((string) @file_get_contents($path), true)['messages'] ?? [])
            ->when($this->option('split') !== 'all', fn ($all) => $all->where('split', $this->option('split')))
            ->values();

        if ($messages->isEmpty()) {
            $this->error("Không có tin nào trong {$path} (split = {$this->option('split')}).");

            return self::FAILURE;
        }

        $methods = ['rules' => 'Dò từ khóa (cách đơn giản)'];
        if ($gemini->enabled()) {
            $methods['ai'] = 'AI (Gemini '.config('services.gemini.model').')';
        } else {
            $this->warn('Chưa có GEMINI_API_KEY: chỉ chấm cách dò từ khóa.');
        }

        $rows = [];
        foreach ($methods as $method => $label) {
            $cases = [];
            $aiFailures = 0;

            foreach ($messages as $index => $message) {
                $now = CarbonImmutable::parse($message['sent_at']);
                if ($method === 'ai' && $index > 0) {
                    sleep((int) $this->option('delay'));
                }

                $predicted = $method === 'ai' ? $reader->readWithAi($message['text'], $now) : $reader->readWithRules($message['text'], $now);
                $aiFailures += $predicted === null ? 1 : 0;
                $predicted ??= [];

                $handedOff = $predicted === [] || collect($predicted)->contains(fn (array $item) => $reader->reviewReason($item, $now) !== null);
                $case = ['expected' => $message['expected'] ?? [], 'handoff' => (bool) ($message['handoff'] ?? false), 'predicted' => $predicted, 'handed_off' => $handedOff];
                $cases[] = $case;

                if ($this->option('details') && SupplyMessageScore::score([$case])['correct'] === 0) {
                    $this->line("  [{$label}] #{$message['id']} \"{$message['text']}\" → ".json_encode($predicted, JSON_UNESCAPED_UNICODE).($handedOff ? ' (chuyển người)' : ''));
                }
            }

            $score = SupplyMessageScore::score($cases);
            $rows[] = [
                $label,
                "{$score['correct']}/{$score['n']} ({$score['correct_pct']}%)",
                "{$score['wrong_auto']} ({$score['wrong_auto_pct']}%)",
                "{$score['handed_off']} ({$score['handed_off_pct']}%)",
                "{$score['read_exact']}/{$score['complete']} ({$score['read_exact_pct']}%)",
            ];

            if ($aiFailures > 0) {
                $this->warn("AI không trả lời được {$aiFailures} tin (tính là chuyển người).");
            }
        }

        $this->info('Bộ tin: '.basename($path).", phần {$this->option('split')}, {$messages->count()} tin. Chạy lúc ".now()->format('d/m/Y H:i').'.');
        $this->table(['Cách đọc', 'Xử lý đúng', 'Tự đi tiếp nhưng sai', 'Chuyển người', 'Đọc đúng nội dung (tin đủ thông tin)'], $rows);

        return self::SUCCESS;
    }
}
