<x-layouts.admin :title="'Đơn '.$order->code">
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="rounded-xl border border-stone-200 bg-white p-4 lg:col-span-2">
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <x-status-badge :status="$order->status" />
                <span class="text-sm text-stone-600">{{ $order->order_type->label() }} · kênh {{ $order->channel->label() }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[520px] text-left text-sm">
                    <thead class="border-b border-stone-200 text-stone-600">
                        <tr>
                            <th class="py-2 pr-3 font-medium">Rau</th>
                            <th class="py-2 pr-3 text-right font-medium">Số gói</th>
                            <th class="py-2 pr-3 text-right font-medium">Khối lượng</th>
                            <th class="py-2 pr-3 text-right font-medium">Thành tiền</th>
                            <th class="py-2 pr-3 text-right font-medium">Thực giao</th>
                            <th class="py-2 font-medium">Lô</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="py-2 pr-3">
                                    {{ $item->product->name }}
                                    @if ($item->is_gift)
                                        <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-900">Quà đổi điểm</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-3 text-right">{{ $item->quantity }}</td>
                                <td class="py-2 pr-3 text-right">@kg($item->weight_kg)</td>
                                <td class="py-2 pr-3 text-right">@vnd($item->line_total)</td>
                                <td class="py-2 pr-3 text-right">{{ $item->fulfilled_quantity ?? '—' }}</td>
                                <td class="py-2">{{ $item->batches->pluck('code')->join(', ') ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <dl class="mt-3 ml-auto max-w-xs space-y-1 text-sm">
                <div class="flex justify-between"><dt>Tiền hàng</dt><dd>@vnd($order->subtotal)</dd></div>
                <div class="flex justify-between"><dt>Phí giao</dt><dd>@vnd($order->shipping_fee)</dd></div>
                @if ($order->discount > 0)
                    <div class="flex justify-between"><dt>Giảm giá</dt><dd>−@vnd($order->discount)</dd></div>
                @endif
                <div class="flex justify-between border-t border-stone-200 pt-1 text-base font-semibold"><dt>Tổng</dt><dd>@vnd($order->total)</dd></div>
            </dl>
        </section>

        <aside class="space-y-4">
            <section class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                <h2 class="mb-2 font-semibold">Khách và giao hàng</h2>
                <dl class="space-y-1">
                    <div><dt class="inline text-stone-600">Khách:</dt> <dd class="inline">{{ $order->customer->name }}</dd></div>
                    <div><dt class="inline text-stone-600">Điện thoại:</dt> <dd class="inline">{{ $order->customer->phone }}</dd></div>
                    <div><dt class="inline text-stone-600">Ngày giao:</dt> <dd class="inline">{{ $order->delivery_date->format('d/m/Y') }} · {{ $order->time_slot }}</dd></div>
                    <div><dt class="inline text-stone-600">Khu vực:</dt> <dd class="inline">{{ $order->zone?->name ?? '—' }}</dd></div>
                    <div><dt class="inline text-stone-600">Giao đến:</dt> <dd class="inline">{{ $order->delivery_address ?? 'Nhận tại điểm' }}</dd></div>
                    <div><dt class="inline text-stone-600">Thanh toán:</dt> <dd class="inline">{{ $order->payment_method->label() }} · {{ $order->payment_status->label() }}</dd></div>
                    @if ($order->note)
                        <div><dt class="inline text-stone-600">Ghi chú:</dt> <dd class="inline">{{ $order->note }}</dd></div>
                    @endif
                    @if ($order->cancel_reason)
                        <div><dt class="inline text-stone-600">Lý do hủy:</dt> <dd class="inline">{{ $order->cancel_reason }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                <h2 class="mb-2 font-semibold">Điểm thành viên</h2>
                <p>Số dư: <span class="font-semibold">{{ $points }} điểm</span></p>

                @if ($requestedReward)
                    <p class="mt-1 text-stone-700">Khách muốn đổi: <span class="font-medium">{{ $requestedReward->label }}</span> ({{ $requestedReward->points }} điểm)</p>
                @endif

                @if ($redemption)
                    <p class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-emerald-900">
                        Đã áp dụng: {{ $appliedReward?->label ?? $redemption->reward }} ({{ $redemption->points }} điểm){{ $redemption->creator ? ' · '.$redemption->creator->name : '' }}
                    </p>
                @elseif (! $canRedeem)
                    <p class="mt-1 text-stone-600">Chỉ đổi quà được trước khi đóng gói.</p>
                @else
                    @if ($requestedReward && $points < $requestedReward->points)
                        <p class="mt-1 text-rose-700">Chưa đủ điểm cho quà khách chọn (thiếu {{ $requestedReward->points - $points }} điểm).</p>
                    @endif

                    @if (! $anyAffordable)
                        <p class="mt-1 text-stone-600">Chưa đủ điểm để đổi quà.</p>
                    @else
                        <form method="POST" action="{{ route('admin.orders.redeem', $order) }}" class="mt-3 space-y-3" x-data="{ reward: @js($defaultReward), packs: @js($packsByReward) }">
                            @csrf
                            <fieldset class="space-y-2">
                                <legend class="mb-1 text-stone-700">Chọn quà</legend>
                                @foreach ($rewards as $reward)
                                    @php($short = $reward->points - $points)
                                    <label @class(['flex min-h-11 items-center gap-2 rounded-lg border border-stone-300 px-3', 'opacity-50' => $short > 0])>
                                        <input type="radio" name="reward" value="{{ $reward->key }}" x-model="reward" @disabled($short > 0)>
                                        <span>{{ $reward->label }} · {{ $reward->points }} điểm{{ $short > 0 ? ' (thiếu '.$short.')' : '' }}</span>
                                    </label>
                                @endforeach
                            </fieldset>

                            <div x-show="(packs[reward] ?? 0) > 0" class="space-y-2">
                                <p class="text-stone-700">Chọn rau tặng</p>
                                @for ($i = 1; $i <= $maxPacks; $i++)
                                    <select name="products[]" aria-label="Rau tặng {{ $i }}" x-show="(packs[reward] ?? 0) >= {{ $i }}" :disabled="(packs[reward] ?? 0) < {{ $i }}" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                                        <option value="">— Chọn loại rau —</option>
                                        @foreach ($giftProducts as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                @endfor
                                @if ($harvestPlanLocked)
                                    <p class="text-amber-800">Kế hoạch thu hoạch ngày này đã chốt: rau tặng sẽ lấy từ phần biên an toàn hoặc hàng dư.</p>
                                @endif
                            </div>

                            <button class="min-h-11 w-full rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Áp dụng quà</button>
                        </form>
                    @endif
                @endif
            </section>

            @if ($order->status->next() !== [])
                <section class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                    <h2 class="mb-2 font-semibold">Chuyển trạng thái</h2>
                    <div class="space-y-3">
                        @foreach ($order->status->next() as $next)
                            <form method="POST" action="{{ route('admin.orders.transition', $order) }}" class="space-y-2">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $next->value }}">
                                @if ($next === \App\Enums\OrderStatus::Cancelled)
                                    <label for="cancel_reason" class="block text-stone-700">Lý do hủy</label>
                                    <input id="cancel_reason" name="cancel_reason" class="block min-h-11 w-full rounded-lg border border-stone-300 px-3">
                                    <button class="min-h-11 w-full rounded-lg border border-rose-300 px-4 font-medium text-rose-800 hover:bg-rose-50">{{ $next->label() }}</button>
                                @else
                                    <button class="min-h-11 w-full rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">{{ $next->label() }}</button>
                                @endif
                            </form>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>

    <p class="mt-4 text-sm"><a href="{{ route('admin.orders.index', ['delivery_date' => $order->delivery_date->toDateString()]) }}" class="text-emerald-800 underline">← Danh sách đơn ngày {{ $order->delivery_date->format('d/m/Y') }}</a></p>
</x-layouts.admin>
