<?php

use App\Http\Controllers\PharmacyInventoryController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;
use Kreait\Laravel\Firebase\Facades\Firebase;

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
Route::post('/pharmacy/transfers', [TransferController::class, 'store'])
    ->name('pharmacy.transfers.store');

// Print flow — POST creates the record, GET reprint renders the PDF.
// Must be registered BEFORE any /transfers/{transfer} routes below,
// otherwise "print" gets swallowed as a route-model-bound {transfer} param.
Route::post('/transfers/print', [TransferController::class, 'print']);
Route::get('/print-history', [TransferController::class, 'printHistory']);
Route::get('/print-history/{referenceId}/reprint', [TransferController::class, 'reprint']);

// Wildcard/parameterized routes go LAST
Route::post('/transfers/{transfer}', [TransferController::class, 'update'])
    ->name('transfers.update');
Route::post('/transfers/{transfer}', [TransferController::class, 'update'])
    ->name('transfers.update');
Route::delete('/transfers/{transfer}', [TransferController::class, 'destroy'])
    ->name('transfers.destroy');
Route::get('/firebase-test', function () {
    $database = Firebase::database();

    return response()->json([
        'message' => 'Firebase Connected Successfully!',
    ]);
});