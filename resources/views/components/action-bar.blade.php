{{--
    Thanh nút dính đáy cho form dài ở trang admin (Lưu / Chốt...): luôn thấy nút mà không phải cuộn xuống cuối.
    Đặt ở cuối form, thẳng mép với nội dung; nút Lên đầu trang được đẩy lên trên thanh này.
--}}
<div {{ $attributes->class('sticky bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-20 mt-6 rounded-xl border border-stone-200 bg-white/95 px-4 py-3 shadow-[0_4px_20px_rgb(0_0_0/0.08)] backdrop-blur print:hidden') }}>
    <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
</div>
<style>:root { --back-to-top-offset: 6.5rem; }</style>
