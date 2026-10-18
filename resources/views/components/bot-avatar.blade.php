{{-- Robot đầu TV, ăng-ten là mầm lá. Thêm class "is-thinking" để mắt đảo qua lại khi bot đang trả lời. --}}
<svg {{ $attributes->merge(['class' => 'bot-avatar']) }} viewBox="0 0 48 48" aria-hidden="true">
    <g class="bot-sprout">
        <path d="M24 11V6" stroke="#047857" stroke-width="2" stroke-linecap="round"/>
        <path d="M24 7c0-3 2.5-5 6-5 0 3-2.5 5-6 5Z" fill="#84cc16"/>
        <path d="M24 8c0-2.5-2-4-5-4 0 2.5 2 4 5 4Z" fill="#a3e635"/>
    </g>
    <rect x="3" y="22" width="5" height="10" rx="2.5" fill="#d6d3d1"/>
    <rect x="40" y="22" width="5" height="10" rx="2.5" fill="#d6d3d1"/>
    <rect x="6" y="11" width="36" height="31" rx="10" fill="#10b981"/>
    <rect x="6" y="11" width="36" height="31" rx="10" fill="none" stroke="#047857" stroke-width="2"/>
    <rect x="11" y="16" width="26" height="20" rx="6" fill="#064e3b"/>
    <g class="bot-eyes" fill="#ecfccb">
        <rect class="bot-eye" x="16" y="21" width="5" height="7" rx="2.5"/>
        <rect class="bot-eye" x="27" y="21" width="5" height="7" rx="2.5"/>
    </g>
    <path d="M21 31.5q3 2 6 0" stroke="#ecfccb" stroke-width="1.6" stroke-linecap="round" fill="none"/>
</svg>
