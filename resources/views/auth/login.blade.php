<x-layouts.public title="Đăng nhập">
    <h1 class="mb-4 text-xl font-semibold">Đăng nhập</h1>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4 rounded-xl border border-stone-200 bg-white p-4">
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="username" required autofocus />
        <x-field name="password" label="Mật khẩu" type="password" autocomplete="current-password" required />
        <label class="flex min-h-11 items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" class="size-5 rounded border-stone-300"> Ghi nhớ đăng nhập
        </label>
        <button class="min-h-12 w-full rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Đăng nhập</button>
    </form>
</x-layouts.public>
