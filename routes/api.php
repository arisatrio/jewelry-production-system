<?php

use App\Http\Controllers\FakturBarangJadiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('faktur-barang-jadi/spk/sent', [FakturBarangJadiController::class, 'sentSpkList'])
        ->name('api.faktur-barang-jadi.spk.sent');
});
