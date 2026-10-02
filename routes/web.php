<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\OrderController as PublicOrderController;
use App\Http\Controllers\Public\OrderLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/dat-hang', [PublicOrderController::class, 'create'])->name('orders.create');
Route::post('/dat-hang', [PublicOrderController::class, 'store'])->middleware('throttle:10,1')->name('orders.store');
Route::get('/dat-hang/cam-on', [PublicOrderController::class, 'thanks'])->name('orders.thanks');

Route::get('/tra-cuu', [OrderLookupController::class, 'show'])->name('lookup.show');
Route::post('/tra-cuu', [OrderLookupController::class, 'search'])->middleware('throttle:10,1')->name('lookup.search');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin,operator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('orders', AdminOrderController::class)->only(['index', 'create', 'store', 'show']);
    Route::patch('orders/{order}/status', [AdminOrderController::class, 'transition'])->name('orders.transition');
});
