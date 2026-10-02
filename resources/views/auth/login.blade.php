<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Đăng nhập · FARM2HOME</title>
</head>
<body>
    <h1>Đăng nhập</h1>
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}">
        @error('email')
            <p>{{ $message }}</p>
        @enderror
        <label for="password">Mật khẩu</label>
        <input id="password" name="password" type="password">
        <label><input type="checkbox" name="remember" value="1"> Ghi nhớ đăng nhập</label>
        <button>Đăng nhập</button>
    </form>
</body>
</html>
