<x-layouts.admin title="Tài khoản">
    <p class="mb-4"><a href="{{ route('admin.users.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Thêm tài khoản</a></p>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[520px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Tên</th>
                    <th class="px-3 py-2 font-medium">Email</th>
                    <th class="px-3 py-2 font-medium">Điện thoại</th>
                    <th class="px-3 py-2 font-medium">Vai trò</th>
                    <th class="px-3 py-2"><span class="sr-only">Thao tác</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-3 py-2 font-semibold">{{ $user->name }}</td>
                        <td class="px-3 py-2">{{ $user->email }}</td>
                        <td class="px-3 py-2">{{ $user->phone ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $user->role->label() }}</td>
                        <td class="px-3 py-2 text-right"><a href="{{ route('admin.users.edit', $user) }}" class="text-emerald-800 underline">Sửa</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
