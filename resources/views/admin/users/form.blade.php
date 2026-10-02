<x-layouts.admin :title="$user->exists ? 'Sửa tài khoản' : 'Thêm tài khoản'">
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="grid max-w-2xl gap-4 sm:grid-cols-2">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif
        <x-field name="name" label="Tên" :value="$user->name" required />
        <x-field name="email" label="Email đăng nhập" type="email" :value="$user->email" required />
        <x-field name="phone" label="Số điện thoại" type="tel" inputmode="numeric" :value="$user->phone" />
        <div>
            <label for="role" class="mb-1 block text-sm font-medium text-stone-700">Vai trò</label>
            <select id="role" name="role" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            @error('role')
                <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
            @enderror
        </div>
        <div class="sm:col-span-2">
            <x-field name="password" :label="$user->exists ? 'Mật khẩu mới (để trống nếu không đổi, tối thiểu 8 ký tự)' : 'Mật khẩu (tối thiểu 8 ký tự)'" type="password" autocomplete="new-password" />
        </div>
        <div class="flex gap-3 sm:col-span-2">
            <button class="min-h-11 rounded-lg bg-emerald-700 px-6 font-semibold text-white hover:bg-emerald-800">Lưu</button>
            <a href="{{ route('admin.users.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Hủy</a>
        </div>
    </form>
</x-layouts.admin>
