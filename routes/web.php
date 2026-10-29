<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Rute Publik (Tamu)
Route::middleware('guest')->group(function () {
    Route::get('/', function () {
        return redirect()->route('login');
    });

    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Rute Terotentikasi (Semua Peran: Admin & Super Admin)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Menu Transaksi & Persediaan (Admin & Super Admin)
    Route::prefix('penjualan')->name('sales.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SaleController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\SaleController::class, 'store'])->name('store');
    });

    Route::prefix('pembelian')->name('purchases.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PurchaseController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\PurchaseController::class, 'store'])->name('store');
    });

    Route::prefix('stok')->name('stock.')->group(function () {
        Route::get('/', [\App\Http\Controllers\StockController::class, 'index'])->name('index');
    });

    Route::prefix('rekomendasi')->name('recommendations.')->group(function () {
        Route::get('/', [\App\Http\Controllers\RecommendationController::class, 'index'])->name('index');
    });

    // Rute Khusus Super Admin (Owner)
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('analisis-fsn-rop')->name('analysis.')->group(function () {
            Route::get('/', [\App\Http\Controllers\AnalysisController::class, 'index'])->name('index');
            Route::post('/terapkan', [\App\Http\Controllers\AnalysisController::class, 'apply'])->name('apply');
        });

        Route::prefix('laporan')->name('reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ReportController::class, 'index'])->name('index');
        });

        Route::prefix('barang')->name('items.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ItemController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\ItemController::class, 'store'])->name('store');
            Route::put('/{item}', [\App\Http\Controllers\ItemController::class, 'update'])->name('update');
            Route::delete('/{item}', [\App\Http\Controllers\ItemController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('pemasok')->name('suppliers.')->group(function () {
            Route::get('/', [\App\Http\Controllers\SupplierController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\SupplierController::class, 'store'])->name('store');
            Route::put('/{supplier}', [\App\Http\Controllers\SupplierController::class, 'update'])->name('update');
            Route::delete('/{supplier}', [\App\Http\Controllers\SupplierController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('impor')->name('imports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ImportController::class, 'index'])->name('index');
            Route::get('/template', [\App\Http\Controllers\ImportController::class, 'template'])->name('template');
            Route::post('/pratinjau', [\App\Http\Controllers\ImportController::class, 'preview'])->name('preview');
            Route::post('/konfirmasi', [\App\Http\Controllers\ImportController::class, 'confirm'])->name('confirm');
            Route::get('/hasil', [\App\Http\Controllers\ImportController::class, 'result'])->name('result');
        });
    });
});
