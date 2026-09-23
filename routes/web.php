<?php

use App\Http\Controllers\CoranController;
use App\Http\Controllers\CraftsmanPerformanceDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiamondMountingController;
use App\Http\Controllers\FinishingController;
use App\Http\Controllers\GoldMaterialTransactionController;
use App\Http\Controllers\JewelCadRequestController;
use App\Http\Controllers\MaterialYieldDashboardController;
use App\Http\Controllers\MsItemController;
use App\Http\Controllers\MsItemVarianceController;
use App\Http\Controllers\MsItemVarianceStoneController;
use App\Http\Controllers\PolishFinishedGoodController;
use App\Http\Controllers\PolishFrameController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\QuickLoginController;
use App\Http\Controllers\ResinController;
use App\Http\Controllers\ShopFloorDashboardController;
use App\Http\Controllers\SkuMasterController;
use App\Http\Controllers\SkuOutputDashboardController;
use App\Http\Controllers\SpkProcessSlaController;
use App\Http\Controllers\StoneTransactionController;
use Illuminate\Support\Facades\Route;

Route::post('login/quick', [QuickLoginController::class, 'store'])
    ->middleware(['guest:web', 'throttle:login'])
    ->name('login.quick');

Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::redirect('dashboard', '/')->name('dashboard');
    Route::redirect('dashboard/work-order', '/analytics/work-order');
    Route::redirect('dashboard/material-yield', '/analytics/material-yield');
    Route::get('analytics/work-order', [DashboardController::class, 'workOrder'])
        ->name('analytics.work-order');
    Route::get('analytics/shop-floor', [ShopFloorDashboardController::class, 'index'])
        ->name('analytics.shop-floor');
    Route::get('analytics/material-yield', [MaterialYieldDashboardController::class, 'index'])
        ->name('analytics.material-yield');
    Route::get('analytics/craftsman-performance', [CraftsmanPerformanceDashboardController::class, 'index'])
        ->name('analytics.craftsman-performance');
    Route::get('analytics/sku-output', [SkuOutputDashboardController::class, 'index'])
        ->name('analytics.sku-output');
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
    Route::get('spk/select/suggestions', [ProductionController::class, 'searchSuggestions'])
        ->name('spk.select.suggestions');
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
    Route::post('jewelcad/{jewelcad}/submit', [JewelCadRequestController::class, 'submit'])
        ->name('jewelcad.submit');
    Route::post('jewelcad/{jewelcad}/manager-approve', [JewelCadRequestController::class, 'managerApprove'])
        ->name('jewelcad.manager-approve');
    Route::post('jewelcad/{jewelcad}/complete', [JewelCadRequestController::class, 'complete'])
        ->name('jewelcad.complete');
    Route::resource('jewelcad', JewelCadRequestController::class)
        ->parameters(['jewelcad' => 'jewelcad']);

    Route::get('resin/select/spks', [ResinController::class, 'searchSpks'])
        ->name('resin.select.spks');
    Route::post('resin/{resin}/submit', [ResinController::class, 'submit'])
        ->name('resin.submit');
    Route::post('resin/{resin}/manager-approve', [ResinController::class, 'managerApprove'])
        ->name('resin.manager-approve');
    Route::post('resin/{resin}/complete', [ResinController::class, 'complete'])
        ->name('resin.complete');
    Route::put('resin/{resin}/progress', [ResinController::class, 'updateProgress'])
        ->name('resin.update-progress');
    Route::resource('resin', ResinController::class)
        ->parameters(['resin' => 'resin']);

    Route::get('coran/select/spks', [CoranController::class, 'searchSpks'])
        ->name('coran.select.spks');
    Route::get('coran/create', [CoranController::class, 'create'])->name('coran.create');
    Route::post('coran', [CoranController::class, 'store'])->name('coran.store');
    Route::get('coran/{coran}/edit', [CoranController::class, 'edit'])
        ->whereNumber('coran')
        ->name('coran.edit');
    Route::put('coran/{coran}', [CoranController::class, 'update'])
        ->whereNumber('coran')
        ->name('coran.update');
    Route::delete('coran/{coran}', [CoranController::class, 'destroy'])
        ->whereNumber('coran')
        ->name('coran.destroy');
    Route::post('coran/{coran}/submit', [CoranController::class, 'submit'])
        ->whereNumber('coran')
        ->name('coran.submit');
    Route::post('coran/{coran}/manager-approve', [CoranController::class, 'managerApprove'])
        ->whereNumber('coran')
        ->name('coran.manager-approve');
    Route::post('coran/{coran}/complete', [CoranController::class, 'complete'])
        ->whereNumber('coran')
        ->name('coran.complete');
    Route::get('coran', [CoranController::class, 'index'])->name('coran.index');
    Route::get('coran/{coran}', [CoranController::class, 'show'])
        ->whereNumber('coran')
        ->name('coran.show');

    Route::get('finishing/select/spks', [FinishingController::class, 'searchSpks'])
        ->name('finishing.select.spks');
    Route::get('finishing/create', [FinishingController::class, 'create'])->name('finishing.create');
    Route::post('finishing', [FinishingController::class, 'store'])->name('finishing.store');
    Route::get('finishing/{finishing}/edit', [FinishingController::class, 'edit'])
        ->whereNumber('finishing')
        ->name('finishing.edit');
    Route::put('finishing/{finishing}', [FinishingController::class, 'update'])
        ->whereNumber('finishing')
        ->name('finishing.update');
    Route::post('finishing/{finishing}/submit', [FinishingController::class, 'submit'])
        ->whereNumber('finishing')
        ->name('finishing.submit');
    Route::post('finishing/{finishing}/manager-approve', [FinishingController::class, 'managerApprove'])
        ->whereNumber('finishing')
        ->name('finishing.manager-approve');
    Route::post('finishing/{finishing}/complete', [FinishingController::class, 'complete'])
        ->whereNumber('finishing')
        ->name('finishing.complete');
    Route::get('finishing', [FinishingController::class, 'index'])->name('finishing.index');
    Route::get('finishing/{finishing}', [FinishingController::class, 'show'])
        ->whereNumber('finishing')
        ->name('finishing.show');

    Route::get('poles-rangka/select/spks', [PolishFrameController::class, 'searchSpks'])
        ->name('poles-rangka.select.spks');
    Route::get('poles-rangka/create', [PolishFrameController::class, 'create'])->name('poles-rangka.create');
    Route::post('poles-rangka', [PolishFrameController::class, 'store'])->name('poles-rangka.store');
    Route::get('poles-rangka/{polesRangka}/edit', [PolishFrameController::class, 'edit'])
        ->whereNumber('polesRangka')
        ->name('poles-rangka.edit');
    Route::put('poles-rangka/{polesRangka}', [PolishFrameController::class, 'update'])
        ->whereNumber('polesRangka')
        ->name('poles-rangka.update');
    Route::post('poles-rangka/{polesRangka}/submit', [PolishFrameController::class, 'submit'])
        ->whereNumber('polesRangka')
        ->name('poles-rangka.submit');
    Route::post('poles-rangka/{polesRangka}/manager-approve', [PolishFrameController::class, 'managerApprove'])
        ->whereNumber('polesRangka')
        ->name('poles-rangka.manager-approve');
    Route::post('poles-rangka/{polesRangka}/complete', [PolishFrameController::class, 'complete'])
        ->whereNumber('polesRangka')
        ->name('poles-rangka.complete');
    Route::get('poles-rangka', [PolishFrameController::class, 'index'])->name('poles-rangka.index');
    Route::get('poles-rangka/{polesRangka}', [PolishFrameController::class, 'show'])
        ->whereNumber('polesRangka')
        ->name('poles-rangka.show');

    Route::get('pasang-batu/select/spks', [DiamondMountingController::class, 'searchSpks'])
        ->name('pasang-batu.select.spks');
    Route::get('pasang-batu/create', [DiamondMountingController::class, 'create'])->name('pasang-batu.create');
    Route::post('pasang-batu', [DiamondMountingController::class, 'store'])->name('pasang-batu.store');
    Route::get('pasang-batu/{pasangBatu}/edit', [DiamondMountingController::class, 'edit'])
        ->whereNumber('pasangBatu')
        ->name('pasang-batu.edit');
    Route::put('pasang-batu/{pasangBatu}', [DiamondMountingController::class, 'update'])
        ->whereNumber('pasangBatu')
        ->name('pasang-batu.update');
    Route::post('pasang-batu/{pasangBatu}/submit', [DiamondMountingController::class, 'submit'])
        ->whereNumber('pasangBatu')
        ->name('pasang-batu.submit');
    Route::post('pasang-batu/{pasangBatu}/manager-approve', [DiamondMountingController::class, 'managerApprove'])
        ->whereNumber('pasangBatu')
        ->name('pasang-batu.manager-approve');
    Route::post('pasang-batu/{pasangBatu}/complete', [DiamondMountingController::class, 'complete'])
        ->whereNumber('pasangBatu')
        ->name('pasang-batu.complete');
    Route::get('pasang-batu', [DiamondMountingController::class, 'index'])->name('pasang-batu.index');
    Route::get('pasang-batu/{pasangBatu}', [DiamondMountingController::class, 'show'])
        ->whereNumber('pasangBatu')
        ->name('pasang-batu.show');

    Route::get('poles-chrome/select/spks', [PolishFinishedGoodController::class, 'searchSpks'])
        ->name('poles-chrome.select.spks');
    Route::get('poles-chrome/create', [PolishFinishedGoodController::class, 'create'])->name('poles-chrome.create');
    Route::post('poles-chrome', [PolishFinishedGoodController::class, 'store'])->name('poles-chrome.store');
    Route::get('poles-chrome/{polesChrome}/edit', [PolishFinishedGoodController::class, 'edit'])
        ->whereNumber('polesChrome')
        ->name('poles-chrome.edit');
    Route::put('poles-chrome/{polesChrome}', [PolishFinishedGoodController::class, 'update'])
        ->whereNumber('polesChrome')
        ->name('poles-chrome.update');
    Route::post('poles-chrome/{polesChrome}/submit', [PolishFinishedGoodController::class, 'submit'])
        ->whereNumber('polesChrome')
        ->name('poles-chrome.submit');
    Route::post('poles-chrome/{polesChrome}/manager-approve', [PolishFinishedGoodController::class, 'managerApprove'])
        ->whereNumber('polesChrome')
        ->name('poles-chrome.manager-approve');
    Route::post('poles-chrome/{polesChrome}/complete', [PolishFinishedGoodController::class, 'complete'])
        ->whereNumber('polesChrome')
        ->name('poles-chrome.complete');
    Route::get('poles-chrome', [PolishFinishedGoodController::class, 'index'])->name('poles-chrome.index');
    Route::get('poles-chrome/{polesChrome}', [PolishFinishedGoodController::class, 'show'])
        ->whereNumber('polesChrome')
        ->name('poles-chrome.show');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('transaksi-bahan-emas', [GoldMaterialTransactionController::class, 'index'])
            ->name('gold-material-transactions.index');
        Route::post('transaksi-bahan-emas', [GoldMaterialTransactionController::class, 'store'])
            ->name('gold-material-transactions.store');

        Route::get('transaksi-batu', [StoneTransactionController::class, 'index'])
            ->name('stone-transactions.index');
        Route::post('transaksi-batu', [StoneTransactionController::class, 'store'])
            ->name('stone-transactions.store');
    });

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

        Route::get('spk-process-sla', [SpkProcessSlaController::class, 'edit'])
            ->name('spk-process-sla.edit');
        Route::put('spk-process-sla', [SpkProcessSlaController::class, 'update'])
            ->name('spk-process-sla.update');
    });
});

require __DIR__.'/settings.php';
