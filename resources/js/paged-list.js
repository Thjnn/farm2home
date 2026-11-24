// Danh sách có phân trang ở trang khách: trang 1 nhúng sẵn từ server, đổi trang thì gọi /api/* và chỉ vẽ lại danh sách.
// Số trang nằm trên URL (mỗi danh sách một key) để Back / tải lại / chia sẻ link không mất trang.

/**
 * Tối đa 5 nút số (5 khi ít trang, 4 khi phải lược) để vừa màn 360px; phần bị lược thay bằng "…".
 * Ví dụ 8 trang: [1,2,3,'…',8], [1,'…',5,'…',8], [1,'…',6,7,8].
 */
export function pageNumbers(current, total) {
    if (total <= 5) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }
    if (current <= 3) {
        return [1, 2, 3, '…', total];
    }
    if (current >= total - 2) {
        return [1, '…', total - 2, total - 1, total];
    }
    return [1, '…', current, '…', total];
}

// urlKeys: tên bộ lọc → key trên URL, vd { q: 'q', category: 'danh_muc' }.
export default ({ endpoint, key, initial, filters = {}, urlKeys = {} }) => ({
    items: initial.items,
    page: initial.page,
    limit: initial.limit,
    total: initial.total,
    totalPages: initial.totalPages,
    filters,
    loading: false,
    request: 0,

    init() {
        // ?trang=99 đã được server đưa về trang cuối: sửa URL cho khớp.
        this.writeUrl('replaceState');
        window.addEventListener('popstate', () => {
            const query = new URL(location.href).searchParams;
            const page = Number(query.get(key)) || 1;
            let changed = page !== this.page;
            Object.entries(urlKeys).forEach(([name, urlKey]) => {
                const value = query.get(urlKey) ?? '';
                changed ||= value !== (this.filters[name] ?? '');
                this.filters[name] = value;
            });
            if (changed) {
                this.load(page);
            }
        });
    },

    get pages() {
        return pageNumbers(this.page, this.totalPages);
    },

    async go(page) {
        if (page < 1 || page > this.totalPages || page === this.page || this.loading) {
            return;
        }
        await this.load(page);
        this.writeUrl('pushState');
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.$refs.heading?.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
    },

    /** Đổi từ khoá / danh mục: về trang 1. */
    async refilter() {
        await this.load(1);
        this.writeUrl('replaceState');
    },

    async load(page) {
        const request = ++this.request;
        this.loading = true;
        const params = new URLSearchParams({ page, limit: this.limit });
        Object.entries(this.filters).forEach(([name, value]) => value && params.set(name, value));
        try {
            const response = await fetch(`${endpoint}?${params}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                throw new Error(response.status);
            }
            const data = await response.json();
            if (request === this.request) {
                Object.assign(this, { items: data.items, page: data.page, total: data.total, totalPages: data.totalPages });
            }
        } catch (e) {
            // Mất mạng: giữ nguyên trang đang xem.
        } finally {
            if (request === this.request) {
                this.loading = false;
            }
        }
    },

    writeUrl(method) {
        const url = new URL(location.href);
        if (this.page > 1) {
            url.searchParams.set(key, this.page);
        } else {
            url.searchParams.delete(key);
        }
        Object.entries(urlKeys).forEach(([name, urlKey]) => {
            const value = (this.filters[name] ?? '').trim();
            value ? url.searchParams.set(urlKey, value) : url.searchParams.delete(urlKey);
        });
        if (url.href !== location.href) {
            history[method](history.state, '', url);
        }
    },

    money(amount) {
        return new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
    },
});
