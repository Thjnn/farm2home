{{--
    Thanh nút dính đáy cho form dài ở trang admin (Lưu / Chốt...): luôn thấy nút mà không phải cuộn xuống cuối.
    Đặt ở cuối form; nút Lên đầu trang được đẩy lên trên thanh này.
--}}
<div {{ $attributes->class('sticky bottom-0 z-20 -mx-4 mt-6 border-t border-stone-200 bg-white/95 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-4px_16px_rgb(0_0_0/0.05)] backdrop-blur print:hidden') }}>
    <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
</div>
<style>:root { --back-to-top-offset: 5.5rem; }</style>
