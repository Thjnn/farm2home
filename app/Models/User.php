<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'role', 'password', 'off_date'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'off_date' => 'date',
        ];
    }

    public function isShipper(): bool
    {
        return $this->role === UserRole::Shipper;
    }

    /**
     * Trang mở đầu sau khi đăng nhập: người giao vào màn hình giao hàng, còn lại vào quản trị.
     */
    public function homeUrl(): string
    {
        return $this->isShipper() ? route('shipper.index') : route('admin.dashboard');
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeShippers(Builder $query): void
    {
        $query->where('role', UserRole::Shipper)->orderBy('id');
    }

    /**
     * Số của tài khoản người vận hành đầu tiên có điền số (admin nhập ở màn Tài khoản); không có thì lấy số admin.
     */
    public static function operatorPhone(): ?string
    {
        return static::where('role', UserRole::Operator)->whereNotNull('phone')->orderBy('id')->value('phone')
            ?? static::where('role', UserRole::Admin)->whereNotNull('phone')->orderBy('id')->value('phone');
    }
}
