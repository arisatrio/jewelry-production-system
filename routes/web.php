<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JewelCadRequestController;
use App\Http\Controllers\MsItemController;
use App\Http\Controllers\MsItemVarianceController;
use App\Http\Controllers\MsItemVarianceStoneController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\QuickLoginController;
use App\Http\Controllers\ResinController;
use App\Http\Controllers\SkuMasterController;
use Illuminate\Support\Facades\Route;

Route::post('login/quick', [QuickLoginController::class, 'store'])
    ->middleware(['guest:web', 'throttle:login'])
    ->name('login.quick');

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::redirect('dashboard', '/')->name('dashboard');
    Route::get('spk', [ProductionController::class, 'index'])->name('spk.index');
    Route::get('spk/create-guide', [ProductionController::class, 'createGuide'])
        ->name('spk.create.guide');
    Route::redirect('spk/create/guide', '/spk/create-guide');
    Route::get('spk/create', [ProductionController::class, 'create'])->name('spk.create');
    Route::post('spk', [ProductionController::class, 'store'])->name('spk.store');
    Route::match(['get', 'post'], 'spk/print', [ProductionController::class, 'printPreview'])
        ->name('spk.print');
    Route::get('spk/print/template', [ProductionController::class, 'printTemplate'])
        ->name('spk.print.template');
    Route::get('spk/form/{rowId}', [ProductionController::class, 'form'])
        ->whereNumber('rowId')
        ->name('spk.form');
    Route::get('spk/form/{rowId}/print', [ProductionController::class, 'print'])
        ->whereNumber('rowId')
        ->name('spk.print.show');
    Route::post('spk/form/{rowId}', [ProductionController::class, 'update'])
        ->whereNumber('rowId')
        ->name('spk.update');
    Route::post('spk/form/{rowId}/submit', [ProductionController::class, 'submit'])
        ->whereNumber('rowId')
        ->name('spk.submit');
    Route::post('spk/form/{rowId}/approve', [ProductionController::class, 'approve'])
        ->whereNumber('rowId')
        ->name('spk.approve');
    Route::post('spk/form/{rowId}/manager-approve', [ProductionController::class, 'managerApprove'])
        ->whereNumber('rowId')
        ->name('spk.manager-approve');
    Route::post('spk/form/{rowId}/reject', [ProductionController::class, 'reject'])
        ->whereNumber('rowId')
        ->name('spk.reject');
    Route::delete('spk/form/{rowId}', [ProductionController::class, 'destroy'])
        ->whereNumber('rowId')
        ->name('spk.destroy');
    Route::get('spk/select/request-orders', [ProductionController::class, 'searchRequestOrders'])
        ->name('spk.select.request-orders');
    Route::get('spk/select/reference-spks', [ProductionController::class, 'searchReferenceSpks'])
        ->name('spk.select.reference-spks');
    Route::get('spk/select/frames', [ProductionController::class, 'searchFrames'])
        ->name('spk.select.frames');
    Route::get('spk/status/{statusKey}', [ProductionController::class, 'showByStatus'])
        ->name('spk.show-status');
    Route::get('spk/{production}', [ProductionController::class, 'show'])
        ->where('production', '.*')
        ->name('spk.show');
    Route::get('jewelcad/select/spks', [JewelCadRequestController::class, 'searchSpks'])
        ->name('jewelcad.select.spks');
    Route::get('jewelcad/spk/{rowId}', [JewelCadRequestController::class, 'spkDetail'])
        ->whereNumber('rowId')
        ->name('jewelcad.spk.detail');
    Route::put('jewelcad/spk/{rowId}', [JewelCadRequestController::class, 'syncSpk'])
        ->whereNumber('rowId')
        ->name('jewelcad.spk.sync');
    Route::resource('jewelcad', JewelCadRequestController::class)
        ->except(['show'])
        ->parameters(['jewelcad' => 'jewelcad']);

    Route::get('resin/select/spks', [ResinController::class, 'searchSpks'])
        ->name('resin.select.spks');
    Route::resource('resin', ResinController::class)
        ->except(['show'])
        ->parameters(['resin' => 'resin']);

    Route::prefix('master-data')->name('master-data.')->group(function () {
        Route::resource('tipe-item', MsItemController::class)
            ->except(['show'])
            ->parameters(['tipe-item' => 'msItem']);

        Route::resource('varian-item', MsItemVarianceController::class)
            ->except(['show'])
            ->parameters(['varian-item' => 'msItemVariance']);

        Route::get('varian-item/{msItemVariance}/batu', [MsItemVarianceController::class, 'batu'])
            ->name('varian-item.batu');

        Route::resource('varian-item.stones', MsItemVarianceStoneController::class)
            ->except(['show', 'create', 'edit'])
            ->parameters([
                'varian-item' => 'msItemVariance',
                'stones' => 'msItemVarianceStone',
            ]);

        Route::get('master-sku', [SkuMasterController::class, 'index'])
            ->name('master-sku.index');
    });
});

require __DIR__.'/settings.php';
