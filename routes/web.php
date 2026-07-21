<?php

use App\Http\Controllers\PharmacyInventoryController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

// Include this file from routes/web.php, e.g.:
//   require __DIR__.'/pharmacy.php';
// or copy the group below directly into web.php

Route::middleware(['web', ])->prefix('/')->name('pharmacy.')->group(function () {
    Route::get('/', [PharmacyInventoryController::class, 'index'])->name('index');

    Route::post('/items', [PharmacyInventoryController::class, 'storeItem'])->name('items.store');
    Route::put('/items/{item}', [PharmacyInventoryController::class, 'updateItem'])->name('items.update');
    Route::delete('/items/{item}', [PharmacyInventoryController::class, 'destroyItem'])->name('items.destroy');
    Route::patch('/items/{item}/archive', [PharmacyInventoryController::class, 'archiveItem'])->name('items.archive');

    Route::post('/transactions', [PharmacyInventoryController::class, 'storeTransaction'])->name('transactions.store');
    Route::put('/transactions/{transaction}', [PharmacyInventoryController::class, 'updateTransaction'])->name('transactions.update');
    Route::delete('/transactions/{transaction}', [PharmacyInventoryController::class, 'destroyTransaction'])->name('transactions.destroy');

    Route::post('/wastage', [PharmacyInventoryController::class, 'storeWastage'])->name('wastage.store');
    Route::delete('/wastage/{wastageRecord}', [PharmacyInventoryController::class, 'destroyWastage'])->name('wastage.destroy');

    Route::put('/settings', [PharmacyInventoryController::class, 'updateSettings'])->name('settings.update');
   
});

 Route::post('/pharmacy/transfers', [App\Http\Controllers\TransferController::class, 'store'])
     ->name('pharmacy.transfers.store');

    Route::get('/pharmacy/transfers/print', [TransferController::class, 'print']);