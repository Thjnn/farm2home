{{--
    Alpine component for the order forms: live subtotal / shipping / total preview.
    The server recomputes everything; this is only a preview.
    Expects: $baskets, $products, $zones
--}}
@php
    $orderFormCatalog = [
        'baskets' => $baskets->mapWithKeys(fn ($basket) => [$basket->id => $basket->price]),
        'products' => $products->mapWithKeys(fn ($product) => [$product->id => $product->price]),
        'zones' => $zones->mapWithKeys(fn ($zone) => [$zone->id => [
            'fee' => $zone->shipping_fee,
            'freeFrom' => $zone->free_ship_from,
            'pickup' => $zone->isPickup(),
        ]]),
    ];
    $orderFormOld = [
        'type' => old('order_type', 'basket'),
        'basketId' => old('basket_id', $baskets->first()?->id),
        'zoneId' => old('delivery_zone_id', $zones->first()?->id),
        'qty' => collect(old('items', []))->mapWithKeys(fn ($line) => [(int) ($line['product_id'] ?? 0) => (int) ($line['quantity'] ?? 0)]),
    ];
@endphp
<script>
    function orderForm(catalog, old) {
        const qty = {};
        Object.keys(catalog.products).forEach((id) => { qty[id] = Number(old.qty[id] || 0); });

        return {
            type: old.type,
            basketId: old.basketId,
            zoneId: old.zoneId,
            qty,
            get zone() { return catalog.zones[this.zoneId] || { fee: 0, freeFrom: null, pickup: false }; },
            get subtotal() {
                if (this.type === 'basket') {
                    return catalog.baskets[this.basketId] || 0;
                }
                return Object.entries(this.qty).reduce((sum, [id, n]) => sum + (catalog.products[id] || 0) * (Number(n) || 0), 0);
            },
            get shipping() {
                if (this.zone.pickup) return 0;
                if (this.zone.freeFrom !== null && this.subtotal >= this.zone.freeFrom) return 0;
                return this.zone.fee;
            },
            get total() { return this.subtotal + this.shipping; },
            money(amount) { return new Intl.NumberFormat('vi-VN').format(amount) + 'đ'; },
        };
    }
    window.orderFormData = [@js($orderFormCatalog), @js($orderFormOld)];
</script>
