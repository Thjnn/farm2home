<?php

use App\Http\Controllers\Admin\BatchController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\ForecastController;
use App\Http\Controllers\Admin\HarvestPlanController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PackagingReturnController;
use App\Http\Controllers\Admin\SupplyController;
use App\Http\Controllers\Admin\WasteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Farmer\FarmerPortalController;
use App\Http\Controllers\Public\BatchPageController;
use App\Http\Controllers\Public\FeedbackController as PublicFeedbackController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\OrderController as PublicOrderController;
use App\Http\Controllers\Public\OrderLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/dat-hang', [PublicOrderController::class, 'create'])->name('orders.create');
Route::post('/dat-hang', [PublicOrderController::class, 'store'])->middleware('throttle:10,1')->name('orders.store');
Route::get('/dat-hang/cam-on', [PublicOrderController::class, 'thanks'])->name('orders.thanks');

Route::get('/lo/{batch:code}', BatchPageController::class)->name('batches.public');
Route::post('/lo/{batch:code}/phan-hoi', [PublicFeedbackController::class, 'store'])->middleware('throttle:5,1')->name('feedbacks.store');

Route::get('/tra-cuu', [OrderLookupController::class, 'show'])->name('lookup.show');
Route::post('/tra-cuu', [OrderLookupController::class, 'search'])->middleware('throttle:10,1')->name('lookup.search');

Route::prefix('nong-ho/{farmer:access_token}')->name('farmer.')->middleware('throttle:30,1')->group(function () {
    Route::get('/', [FarmerPortalController::class, 'show'])->name('show');
    Route::post('/san-luong', [FarmerPortalController::class, 'storeSupply'])->name('supply.store');
    Route::post('/thu-hoach/{plan}', [FarmerPortalController::class, 'confirmHarvest'])->name('harvest.confirm');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin,operator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('orders', AdminOrderController::class)->only(['index', 'create', 'store', 'show']);
    Route::patch('orders/{order}/status', [AdminOrderController::class, 'transition'])->name('orders.transition');

    Route::get('supply', [SupplyController::class, 'index'])->name('supply.index');
    Route::post('supply', [SupplyController::class, 'store'])->name('supply.store');

    Route::get('forecasts', [ForecastController::class, 'index'])->name('forecasts.index');
    Route::post('forecasts/run', [ForecastController::class, 'run'])->name('forecasts.run');
    Route::post('forecasts/backfill', [ForecastController::class, 'backfill'])->name('forecasts.backfill');

    Route::get('harvest-plans', [HarvestPlanController::class, 'index'])->name('harvest-plans.index');
    Route::post('harvest-plans/generate', [HarvestPlanController::class, 'generate'])->name('harvest-plans.generate');
    Route::get('harvest-plans/{plan}', [HarvestPlanController::class, 'show'])->name('harvest-plans.show');
    Route::post('harvest-plans/{plan}/confirm', [HarvestPlanController::class, 'confirm'])->name('harvest-plans.confirm');
    Route::post('harvest-plans/{plan}/sent', [HarvestPlanController::class, 'sent'])->name('harvest-plans.sent');
    Route::post('harvest-plans/{plan}/complete', [HarvestPlanController::class, 'complete'])->name('harvest-plans.complete');
    Route::patch('harvest-plan-items/{item}', [HarvestPlanController::class, 'updateItem'])->name('harvest-plan-items.update');
    Route::post('harvest-plan-items/{item}/receive', [HarvestPlanController::class, 'receive'])->name('harvest-plan-items.receive');

    Route::get('batches', [BatchController::class, 'index'])->name('batches.index');
    Route::post('batches/allocate', [BatchController::class, 'allocate'])->name('batches.allocate');
    Route::get('batches/{batch}', [BatchController::class, 'show'])->name('batches.show');
    Route::patch('batches/{batch}', [BatchController::class, 'update'])->name('batches.update');
    Route::get('batches/{batch}/labels', [BatchController::class, 'labels'])->name('batches.labels');

    Route::get('feedbacks', [AdminFeedbackController::class, 'index'])->name('feedbacks.index');
    Route::patch('feedbacks/{feedback}', [AdminFeedbackController::class, 'update'])->name('feedbacks.update');

    Route::get('waste', [WasteController::class, 'index'])->name('waste.index');
    Route::post('waste', [WasteController::class, 'store'])->name('waste.store');
    Route::get('packaging-returns', [PackagingReturnController::class, 'index'])->name('packaging-returns.index');
    Route::post('packaging-returns', [PackagingReturnController::class, 'store'])->name('packaging-returns.store');
});
