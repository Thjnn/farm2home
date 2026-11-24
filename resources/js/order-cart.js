// Giỏ hàng của trang /dat-hang: giữ món đã chọn qua đổi trang / tìm kiếm / tải lại (localStorage).
// Giá ở đây chỉ để xem trước; server tính lại toàn bộ khi gửi đơn.

// Trang cảm ơn (orders/thanks) xoá key này sau khi đơn gửi thành công.
const STORAGE_KEY = 'f2h-cart';
const MAX_QUANTITY = 20;

export default ({ zones, zoneId, preselectedBasket, startAtDelivery }) => ({
    step: startAtDelivery ? 2 : 1,
    type: 'basket',
    basket: null,
    lines: {},
    zoneId: String(zoneId),
    zones,
    hint: '',

    init() {
        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
            if (saved) {
                this.type = saved.type === 'single' ? 'single' : 'basket';
                this.basket = saved.basket ?? null;
                this.lines = saved.lines ?? {};
            }
        } catch (e) {}

        // Link "Đặt giỏ này" (?goi=<id>) thắng giỏ đã lưu.
        if (preselectedBasket) {
            this.type = 'basket';
            this.basket = preselectedBasket;
        }

        this.$watch('type', () => this.save());
        this.$watch('basket', () => this.save());
        this.$watch('lines', () => this.save());
    },

    save() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ type: this.type, basket: this.basket, lines: this.lines }));
        } catch (e) {}
    },

    // ---- Chọn lẻ ----
    quantity(id) {
        return this.lines[id]?.quantity ?? 0;
    },
    change(product, delta) {
        const next = Math.max(0, Math.min(MAX_QUANTITY, this.quantity(product.id) + delta));
        if (next === 0) {
            delete this.lines[product.id];
        } else {
            this.lines[product.id] = { id: product.id, name: product.name, price: product.price, unit: product.unit, imageUrl: product.imageUrl, quantity: next };
        }
        this.hint = '';
    },
    get chosenLines() {
        return Object.values(this.lines);
    },

    // ---- Giỏ ----
    chooseBasket(basket) {
        this.basket = { id: basket.id, name: basket.name, price: basket.price, summary: basket.items.map((line) => `${line.quantity} × ${line.name}`).join(', ') };
        this.hint = '';
    },

    // ---- Tổng ----
    get chosenCount() {
        return this.type === 'basket' ? (this.basket ? 1 : 0) : this.chosenLines.length;
    },
    get countLabel() {
        if (this.type === 'basket') {
            return this.basket ? '1 giỏ' : 'Chưa chọn giỏ';
        }
        return this.chosenCount ? `${this.chosenCount} món` : 'Chưa chọn rau';
    },
    get zone() {
        return this.zones[this.zoneId] || { fee: 0, freeFrom: null, pickup: false };
    },
    get subtotal() {
        if (this.type === 'basket') {
            return this.basket?.price ?? 0;
        }
        return this.chosenLines.reduce((sum, line) => sum + line.price * line.quantity, 0);
    },
    get shipping() {
        if (this.zone.pickup || this.subtotal === 0) {
            return 0;
        }
        return this.zone.freeFrom !== null && this.subtotal >= this.zone.freeFrom ? 0 : this.zone.fee;
    },
    get total() {
        return this.subtotal + this.shipping;
    },

    // ---- Bước ----
    next() {
        if (this.chosenCount === 0) {
            this.hint = this.type === 'basket' ? 'Bạn chọn một giỏ rau trước nhé.' : 'Bạn chọn ít nhất một món rau nhé.';
            return;
        }
        this.goTo(2);
    },
    goTo(step) {
        this.step = step;
        this.$nextTick(() => {
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            this.$refs.top?.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
        });
    },

    money(amount) {
        return new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
    },
});
