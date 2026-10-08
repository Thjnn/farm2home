{{-- Chatbot hỏi đáp theo kịch bản. Trả lời do ChatbotService tạo từ dữ liệu thật. --}}
<div x-data="{
        open: false,
        sending: false,
        draft: '',
        messages: [{ from: 'bot', text: 'Chào bạn! Bạn có thể hỏi về giá rau, giỏ rau, phí ship, giờ giao, thanh toán hoặc điểm thưởng.' }],
        async send() {
            const text = this.draft.trim();
            if (text === '' || this.sending) return;
            this.messages.push({ from: 'me', text });
            this.draft = '';
            this.sending = true;
            try {
                const response = await fetch('{{ route('chatbot.reply') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ message: text }),
                });
                const data = await response.json();
                this.messages.push({ from: 'bot', text: response.ok ? data.reply : 'Bạn hỏi ngắn gọn hơn giúp mình nhé (tối đa 300 ký tự).' });
            } catch (error) {
                this.messages.push({ from: 'bot', text: 'Mất kết nối, bạn thử lại sau nhé.' });
            } finally {
                this.sending = false;
                this.$nextTick(() => this.$refs.log.scrollTop = this.$refs.log.scrollHeight);
            }
        },
    }" class="fixed bottom-4 right-4 z-40 flex flex-col items-end">
    <section x-show="open" x-cloak x-transition
             class="mb-3 flex h-[70vh] max-h-[480px] w-[calc(100vw-2rem)] max-w-sm flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xl"
             aria-label="Hỏi đáp nhanh">
        <header class="flex items-center justify-between bg-emerald-700 px-4 py-2 text-white">
            <h2 class="font-semibold">Hỏi đáp nhanh</h2>
            <button type="button" @click="open = false" class="flex size-11 items-center justify-center rounded-full hover:bg-emerald-800" aria-label="Đóng hỏi đáp">✕</button>
        </header>
        <div x-ref="log" class="flex-1 space-y-2 overflow-y-auto bg-stone-50 p-3 text-sm" aria-live="polite">
            <template x-for="(message, index) in messages" :key="index">
                <p x-text="message.text"
                   :class="message.from === 'me' ? 'ml-auto bg-emerald-700 text-white rounded-br-sm' : 'bg-white ring-1 ring-stone-200 rounded-bl-sm'"
                   class="w-fit max-w-[85%] whitespace-pre-line rounded-2xl px-3 py-2"></p>
            </template>
            <p x-show="sending" class="text-xs text-stone-500">Đang trả lời…</p>
        </div>
        <form @submit.prevent="send()" class="flex gap-2 border-t border-stone-200 p-2">
            <label for="chatbot-input" class="sr-only">Câu hỏi của bạn</label>
            <input id="chatbot-input" x-model="draft" maxlength="300" autocomplete="off" placeholder="VD: phí ship bao nhiêu?"
                   class="min-h-11 flex-1 rounded-lg border border-stone-300 px-3 text-base">
            <button class="min-h-11 rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800 disabled:opacity-50" :disabled="sending">Gửi</button>
        </form>
    </section>
    <button type="button" @click="open = !open; if (open) $nextTick(() => document.getElementById('chatbot-input').focus())"
            class="flex min-h-12 items-center gap-2 rounded-full bg-emerald-700 px-4 font-semibold text-white shadow-lg hover:bg-emerald-800"
            :aria-expanded="open.toString()">
        <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 5a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H8l-4 4v-4.2A3 3 0 0 1 2 11V5Z"/></svg>
        Hỏi đáp
    </button>
</div>
