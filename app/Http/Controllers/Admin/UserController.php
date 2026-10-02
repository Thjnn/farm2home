<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', ['users' => User::orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(['role' => UserRole::Operator]), 'roles' => UserRole::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        User::create($this->validated($request, new User));

        return redirect()->route('admin.users.index')->with('status', 'Đã thêm tài khoản.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user, 'roles' => UserRole::cases()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $user->update($this->validated($request, $user));

        return redirect()->route('admin.users.index')->with('status', 'Đã cập nhật tài khoản.');
    }

    /**
     * Mật khẩu để trống khi sửa thì giữ nguyên; admin không tự đổi vai trò của chính mình.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validated(Request $request, User $user): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'regex:/^0\d{9}$/'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$user->exists ? 'nullable' : 'required', 'string', 'min:8'],
        ]);

        if ($user->exists && $user->is($request->user()) && $data['role'] !== $user->role->value) {
            throw ValidationException::withMessages(['role' => 'Bạn không thể tự đổi vai trò của chính mình.']);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }
}
