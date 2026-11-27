{{-- Chatbot hỏi đáp theo kịch bản. Trả lời do ChatbotService tạo từ dữ liệu thật. --}}
<script>
    // Escape hết rồi chỉ biến link zalo.me thành thẻ a, để câu trả lời không chèn được HTML hay link lạ.
    function chatbotLinkify(text) {
        const div = document.createElement('div');
        div.textContent = text;

        return div.innerHTML.replace(/https:\/\/zalo\.me\/0\d{9}/g,
            (url) => `<a href="${url}" target="_blank" rel="noopener" class="font-semibold text-emerald-800 underline">${url.replace('https://', '')}</a>`);
    }
</script>
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
                this.messages.push(response.ok
                    ? { from: 'bot', text: data.reply, products: data.products ?? [] }
                    : { from: 'bot', text: 'Bạn hỏi ngắn gọn hơn giúp mình nhé (tối đa 300 ký tự).' });
            } catch (error) {
                this.messages.push({ from: 'bot', text: 'Mất kết nối, bạn thử lại sau nhé.' });
            } finally {
                this.sending = false;
                this.$nextTick(() => this.$refs.log.scrollTop = this.$refs.log.scrollHeight);
            }
        },
        {{-- Trên điện thoại khung chat phủ đúng phần màn hình còn thấy, để bàn phím bật lên không che/đẩy mất đầu khung. --}}
        syncViewport() {
            const viewport = window.visualViewport;
            if (!viewport) return;
            this.$el.style.setProperty('--vv-h', viewport.height + 'px');
            this.$el.style.setProperty('--vv-top', viewport.offsetTop + 'px');
            if (this.open) this.$refs.log.scrollTop = this.$refs.log.scrollHeight;
        },
    }" x-init="syncViewport(); window.visualViewport?.addEventListener('resize', () => syncViewport()); window.visualViewport?.addEventListener('scroll', () => syncViewport())"
    class="fixed {{ $chatbotPosition ?? 'bottom-4' }} right-4 z-40 flex flex-col items-end">
    <section x-show="open" x-cloak x-transition
             class="mb-3 flex h-[70vh] max-h-[480px] w-[calc(100vw-2rem)] max-w-sm flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xl
                    max-sm:fixed max-sm:inset-x-0 max-sm:top-[var(--vv-top,0px)] max-sm:mb-0 max-sm:h-[var(--vv-h,100dvh)] max-sm:max-h-none max-sm:w-full max-sm:max-w-none max-sm:rounded-none max-sm:border-0"
             aria-label="Hỏi đáp nhanh">
        <header class="flex items-center justify-between bg-emerald-700 px-4 py-2 text-white">
            <h2 class="flex items-center gap-2 font-semibold">
                <span class="flex size-10 items-center justify-center rounded-full bg-white/90">
                    <x-bot-avatar class="size-8" ::class="sending && 'is-thinking'" />
                </span>
                Hỏi đáp nhanh
            </h2>
            <button type="button" @click="open = false" class="flex size-11 items-center justify-center rounded-full hover:bg-emerald-800" aria-label="Đóng hỏi đáp">✕</button>
        </header>
        <div x-ref="log" class="flex-1 space-y-2 overflow-y-auto bg-stone-50 p-3 text-sm" aria-live="polite">
            <template x-for="(message, index) in messages" :key="index">
                <div class="space-y-2">
                    <p x-html="chatbotLinkify(message.text)"
                       :class="message.from === 'me' ? 'ml-auto bg-emerald-700 text-white rounded-br-sm' : 'bg-white ring-1 ring-stone-200 rounded-bl-sm'"
                       class="w-fit max-w-[85%] whitespace-pre-line rounded-2xl px-3 py-2"></p>
                    {{-- Thẻ rau dựng từ dữ liệu server (ChatbotService::productCards), không chèn HTML. --}}
                    <div x-show="message.products?.length" class="flex gap-2 overflow-x-auto pb-1">
                        <template x-for="product in message.products ?? []" :key="product.id">
                            <div class="flex w-36 shrink-0 flex-col overflow-hidden rounded-xl bg-white ring-1 ring-stone-200">
                                <template x-if="product.imageUrl">
                                    <img :src="product.imageUrl" alt="" loading="lazy" class="h-20 w-full bg-stone-100 object-cover">
                                </template>
                                <template x-if="!product.imageUrl">
                                    <span aria-hidden="true" class="block h-20 w-full bg-emerald-50"></span>
                                </template>
                                <div class="flex flex-1 flex-col p-2">
                                    <span class="font-medium" x-text="product.name"></span>
                                    <span class="text-xs text-stone-600" x-text="product.price.toLocaleString('vi-VN') + 'đ / ' + product.unit"></span>
                                    <a :href="product.orderUrl" class="mt-2 inline-flex min-h-11 items-center justify-center rounded-lg bg-emerald-700 px-2 text-sm font-semibold text-white hover:bg-emerald-800">Đặt rau này</a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
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
            :class="open && 'max-sm:hidden'"
            class="flex min-h-12 items-center gap-2 rounded-full bg-emerald-700 px-4 font-semibold text-white shadow-lg hover:bg-emerald-800"
            :aria-expanded="open.toString()">
        <span class="-ml-2 flex size-10 items-center justify-center rounded-full bg-white/90">
            <x-bot-avatar class="size-8" />
        </span>
        Hỏi đáp
    </button>
</div>
