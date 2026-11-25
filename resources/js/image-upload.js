// Ô chọn ảnh/file (components/image-upload): kéo thả, xem trước, xoá file vừa chọn.
// Input file thật vẫn nằm trong form; mọi thay đổi được ghi lại vào input.files qua DataTransfer.
export default ({ multiple = false, current = [], accept = '' }) => ({
    multiple,
    current,
    files: [],
    dragging: false,
    error: '',

    get preview() {
        return this.files[0] ?? null;
    },

    get hasContent() {
        return this.files.length > 0 || this.current.length > 0;
    },

    // Ảnh đơn: có ảnh để xem trước (ảnh vừa chọn, hoặc ảnh đang lưu khi chưa chọn gì).
    get showsImage() {
        return !this.multiple && (this.preview ? this.preview.isImage : this.current.length > 0);
    },

    get imageSrc() {
        return this.preview?.url ?? this.current[0] ?? '';
    },

    // Vùng thả file ẩn đi (chỉ ẩn bằng sr-only để input vẫn nằm trong form) khi đã có phần xem trước.
    get dropHidden() {
        return this.multiple ? this.hasContent : this.showsImage;
    },

    accepts(file) {
        const rules = accept.split(',').map((rule) => rule.trim().toLowerCase()).filter(Boolean);
        if (rules.length === 0) {
            return true;
        }
        const name = file.name.toLowerCase();
        const type = (file.type || '').toLowerCase();

        return rules.some((rule) => {
            if (rule.startsWith('.')) {
                return name.endsWith(rule);
            }
            if (rule.endsWith('/*')) {
                return type.startsWith(rule.slice(0, -1));
            }
            return type === rule;
        });
    },

    add(fileList) {
        const incoming = Array.from(fileList);
        const rejected = incoming.filter((file) => !this.accepts(file));
        const accepted = incoming.filter((file) => this.accepts(file));
        this.error = rejected.length ? `Bỏ qua ${rejected.map((file) => `"${file.name}"`).join(', ')}: không đúng định dạng.` : '';

        if (accepted.length === 0) {
            this.sync();
            return;
        }
        const entries = accepted.map((file) => ({
            file,
            name: file.name,
            isImage: file.type.startsWith('image/'),
            url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
        }));

        if (this.multiple) {
            this.files = [...this.files, ...entries];
        } else {
            this.files.forEach((entry) => entry.url && URL.revokeObjectURL(entry.url));
            this.files = entries.slice(0, 1);
            entries.slice(1).forEach((entry) => entry.url && URL.revokeObjectURL(entry.url));
        }
        this.sync();
    },

    sync() {
        const transfer = new DataTransfer();
        this.files.forEach((entry) => transfer.items.add(entry.file));
        this.$refs.input.files = transfer.files;
    },

    pick(event) {
        // Với nhiều ảnh, lần chọn sau cộng thêm vào danh sách thay vì thay thế.
        this.add(event.target.files);
    },

    drop(event) {
        this.dragging = false;
        if (event.dataTransfer?.files?.length) {
            this.add(event.dataTransfer.files);
        }
    },

    leave(event) {
        if (!event.currentTarget.contains(event.relatedTarget)) {
            this.dragging = false;
        }
    },

    remove(index) {
        const [entry] = this.files.splice(index, 1);
        if (entry?.url) {
            URL.revokeObjectURL(entry.url);
        }
        this.error = '';
        if (this.files.length === 0) {
            this.$refs.input.value = '';
        } else {
            this.sync();
        }
    },

    clear() {
        this.files.forEach((entry) => entry.url && URL.revokeObjectURL(entry.url));
        this.files = [];
        this.error = '';
        this.$refs.input.value = '';
    },

    browse() {
        this.$refs.input.click();
    },
});
