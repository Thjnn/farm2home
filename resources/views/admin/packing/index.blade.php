<x-layouts.admin title="Đóng gói">
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <form method="GET" action="{{ route('admin.packing.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="date" class="mb-1 block text-sm font-medium text-stone-700">Ngày giao</label>
                <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
            </div>
            <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Xem</button>
        </form>
        <div class="ml-auto flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.batches.allocate') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <button class="min-h-11 rounded-lg border border-emerald-700 bg-white px-4 font-semibold text-emerald-800 hover:bg-emerald-50">Gán lô cho ngày này</button>
            </form>
            @if ($groups->isNotEmpty())
                <a href="{{ route('admin.packing.slips', ['date' => $date->toDateString()]) }}" target="_blank" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">In phiếu cả ngày</a>
            @endif
        </div>
    </div>

    @if ($groups->isEmpty())
        <p class="rounded-xl border border-stone-200 bg-white px-4 py-8 text-center text-stone-500">Chưa có đơn đã xác nhận cho ngày này.</p>
    @else
        @if ($pickList->sum('allocated') === 0)
            <p class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">Chưa gán lô — bấm "Gán lô cho ngày này" sau khi rau sơ chế và đạt kiểm tra.</p>
        @endif

        <section class="mb-6 rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="mb-3 font-semibold text-stone-800">Tổng soạn theo rau</h2>
            <ul class="divide-y divide-stone-100">
                @foreach ($pickList as $line)
                    <li class="flex items-center gap-3 py-2">
                        <x-product-image :product="$line['product']" class="size-10 rounded-lg" />
                        <span class="font-medium">{{ $line['product']->name }}</span>
                        <span class="ml-auto text-sm tabular-nums text-stone-700">{{ $line['packs'] }} gói · @kg($line['kg'])</span>
                        @if ($line['short'] > 0)
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Thiếu {{ $line['short'] }} gói</span>
                        @else
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Đủ lô</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        @foreach ($groups as $label => $orders)
            @php($packedCount = $orders->filter(fn ($order) => $bags[$order->id]->isNotEmpty() && $bags[$order->id]->every(fn ($bag) => $bag['packed']))->count())
            <h2 class="mb-2 mt-6 text-sm font-semibold uppercase tracking-wide text-stone-600">{{ $label }} <span class="font-normal normal-case">({{ $orders->count() }} đơn, {{ $packedCount }} đủ túi)</span></h2>

            <div class="space-y-3">
                @foreach ($orders as $order)
                    @php($orderBags = $bags[$order->id])
                    @php($fullBags = $orderBags->reject(fn ($bag) => $bag['short']))
                    @php($isFull = $orderBags->isNotEmpty() && $orderBags->every(fn ($bag) => $bag['packed']))
                    <article id="order-{{ $order->id }}" @class(['scroll-mt-20 rounded-xl border bg-white p-3', 'border-emerald-400' => $isFull, 'border-stone-200' => ! $isFull])>
                        <header class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="font-semibold">{{ $order->code }}</span>
                            <span class="text-stone-700">{{ $order->customer->name }}</span>
                            <span class="text-sm text-stone-500">{{ $order->order_type->label() }}</span>
                            <span class="ml-auto text-sm tabular-nums text-stone-700">Túi {{ $fullBags->where('packed', true)->count() }}/{{ $fullBags->count() }}</span>
                            @if ($isFull)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Đủ túi · sẵn sàng giao</span>
                            @endif
                        </header>

                        <ul class="space-y-1">
                            @foreach ($orderBags as $bag)
                                <li>
                                    @if ($bag['short'])
                                        <div class="flex min-h-11 items-center gap-3 rounded-lg bg-red-50 px-3 text-sm text-red-800">
                                            <span class="font-semibold">✕ Thiếu</span>
                                            <span>{{ $bag['product']->name }} {{ $bag['packs'] }} gói — chưa có lô</span>
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('admin.packing.bags.toggle', [$order, $bag['batch']]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="packed" value="{{ $bag['packed'] ? 0 : 1 }}">
                                            <button @class(['flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-left text-sm', 'bg-emerald-50' => $bag['packed'], 'bg-stone-50 hover:bg-stone-100' => ! $bag['packed']]) aria-pressed="{{ $bag['packed'] ? 'true' : 'false' }}">
                                                <span @class(['grid size-6 shrink-0 place-items-center rounded border-2', 'border-emerald-700 bg-emerald-700 text-white' => $bag['packed'], 'border-stone-400 bg-white' => ! $bag['packed']])>{{ $bag['packed'] ? '✓' : '' }}</span>
                                                <span class="font-medium">Túi {{ $loop->iteration }}</span>
                                                <x-product-image :product="$bag['product']" class="size-8 rounded" />
                                                <span>{{ $bag['product']->name }}</span>
                                                <span class="tabular-nums text-stone-600">{{ $bag['packs'] }} gói · @kg($bag['kg'])</span>
                                                <span class="ml-auto hidden font-mono text-xs text-stone-500 sm:inline">{{ $bag['batch']->code }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        <footer class="mt-2 flex flex-wrap gap-2">
                            @if (! $isFull && $fullBags->where('packed', false)->isNotEmpty())
                                <form method="POST" action="{{ route('admin.packing.orders.pack', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 text-sm hover:bg-stone-100">Đóng cả đơn</button>
                                </form>
                            @endif
                            @if ($fullBags->isNotEmpty())
                                <a href="{{ route('admin.packing.slips', ['order' => $order->id]) }}" target="_blank" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-4 text-sm hover:bg-stone-100">In phiếu đơn này</a>
                            @endif
                        </footer>
                    </article>
                @endforeach
            </div>
        @endforeach
    @endif
</x-layouts.admin>
