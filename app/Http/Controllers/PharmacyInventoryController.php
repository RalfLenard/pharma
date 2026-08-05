<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\LabSetting;
use App\Models\Transaction;
use App\Models\WastageRecord;
use App\Models\Transfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PharmacyInventoryController extends Controller
{
    /**
     * Main page — ships all items, transactions, wastage records and lab
     * settings as props. The Vue side mirrors the original app's approach
     * of computing stock levels / stats / reports client-side from this
     * dataset (see resources/js/Composables/usePharmacyCalc.js).
     */
    public function index(): Response
    {
        return Inertia::render('Pharmacy/Index', [
            'items' => Item::orderBy('name')->get(),
            'transactions' => Transaction::orderBy('date')->get(),
            'wastageRecords' => WastageRecord::orderBy('date')->get(),
             'transfers' => Transfer::latest()->get(),
            'labSettings' => LabSetting::current(),
        ]);
    }

    /* ───────────────────────── ITEMS ───────────────────────── */

 public function storeItem(Request $request): RedirectResponse
{
    $data = $request->validate([
        'name'              => 'required|string|max:255',
        'vol'               => 'nullable|string|max:100',
        'brand'             => 'nullable|string|max:255',
        'sec'               => 'nullable|string|max:100',
        'lot'               => 'nullable|string|max:100',
        'exp'               => 'nullable|date',
        'unit'              => 'required|string|max:50',
        'min'               => 'nullable|integer|min:0',
        'fund'              => 'nullable|string|max:100',
        'added_date'        => 'nullable|date',
        'by'                => 'nullable|string|max:255',
        'init_in'           => 'nullable|integer|min:0',
        'init_out'          => 'nullable|integer|min:0',
        'quarter_delivered' => 'nullable|string|max:20',
    ]);

    $addedDate = $data['added_date'] ?? now()->toDateString();
    $performedBy = $data['by'] ?? 'System';

    $initIn = (int) ($data['init_in'] ?? 0);
    $initOut = (int) ($data['init_out'] ?? 0);

    // Prevent stock out when there is no stock
    if ($initIn === 0 && $initOut > 0) {
        return back()
            ->withErrors([
                'init_out' => 'You cannot stock out an item that has no stock.'
            ])
            ->withInput();
    }

    // Prevent stock out greater than stock in
    if ($initOut > $initIn) {
        return back()
            ->withErrors([
                'init_out' => 'Initial stock out cannot be greater than the initial stock in.'
            ])
            ->withInput();
    }

    $item = Item::create([
        'name'               => $data['name'],
        'vol'                => $data['vol'] ?? null,
        'brand'              => $data['brand'] ?? null,
        'sec'                => $data['sec'] ?? null,
        'lot'                => $data['lot'] ?? null,
        'exp'                => $data['exp'] ?? null,
        'unit'               => $data['unit'],
        'min'                => $data['min'] ?? 0,
        'fund'               => $data['fund'] ?? null,
        'order_qty'          => 0,
        'notes'              => '',
        'added_date'         => $addedDate,
        'quarter_delivered'  => $data['quarter_delivered'] ?? null,
    ]);

    // Initial Stock In
    if ($initIn > 0) {
        $item->transactions()->create([
            'type'         => 'in',
            'qty'          => $initIn,
            'date'         => $addedDate,
            'performed_by' => $performedBy,
            'note'         => 'Initial stock in',
        ]);
    }

    // Initial Stock Out
    if ($initOut > 0) {
        $item->transactions()->create([
            'type'         => 'out',
            'qty'          => $initOut,
            'date'         => $addedDate,
            'performed_by' => $performedBy,
            'note'         => 'Initial stock out',
        ]);
    }

    // If both are zero, just record the item creation
    if ($initIn === 0 && $initOut === 0) {
        $item->transactions()->create([
            'type'         => 'in',
            'qty'          => 0,
            'date'         => $addedDate,
            'performed_by' => $performedBy,
            'note'         => 'Item added',
        ]);
    }

    return back()->with('success', 'Item added successfully.');
}

public function updateItem(Request $request, Item $item): RedirectResponse
{
    $data = $request->validate([
        'name'              => 'required|string|max:255',
        'vol'               => 'nullable|string|max:100',
        'brand'             => 'nullable|string|max:255',
        'sec'               => 'nullable|string|max:100',
        'lot'               => 'nullable|string|max:100',
        'exp'               => 'nullable|date',
        'unit'              => 'required|string|max:50',
        'min'               => 'nullable|integer|min:0',
        'order_qty'         => 'nullable|integer|min:0',
        'fund'              => 'nullable|string|max:100',
        'add_in'            => 'nullable|integer|min:0',
        'add_out'           => 'nullable|integer|min:0',
        'quarter_delivered' => 'nullable|string|max:20',
    ]);

    $addIn = (int) ($data['add_in'] ?? 0);
    $addOut = (int) ($data['add_out'] ?? 0);

    // Current stock before this update
    $currentStock = $item->stock;

    // Prevent stock out if there is no stock
    if ($currentStock <= 0 && $addOut > 0) {
        return back()
            ->withErrors([
                'add_out' => 'You cannot stock out an item that has no stock.'
            ])
            ->withInput();
    }

    // Prevent stock out greater than available stock
    if ($addOut > $currentStock) {
        return back()
            ->withErrors([
                'add_out' => "Only {$currentStock} item(s) are available in stock."
            ])
            ->withInput();
    }

    $item->update([
        'name'              => $data['name'],
        'vol'               => $data['vol'] ?? null,
        'brand'             => $data['brand'] ?? null,
        'sec'               => $data['sec'] ?? null,
        'lot'               => $data['lot'] ?? null,
        'exp'               => $data['exp'] ?? null,
        'unit'              => $data['unit'],
        'min'               => $data['min'] ?? 0,
        'order_qty'         => $data['order_qty'] ?? 0,
        'fund'              => $data['fund'] ?? null,
        'quarter_delivered' => $data['quarter_delivered'] ?? $item->quarter_delivered,
    ]);

    $today = now()->toDateString();

    if ($addIn > 0) {
        $item->transactions()->create([
            'type'         => 'in',
            'qty'          => $addIn,
            'date'         => $today,
            'performed_by' => 'System',
            'note'         => 'Stock in (edit)',
        ]);
    }

    if ($addOut > 0) {
        $item->transactions()->create([
            'type'         => 'out',
            'qty'          => $addOut,
            'date'         => $today,
            'performed_by' => 'System',
            'note'         => 'Stock out (edit)',
        ]);
    }

    return back()->with('success', 'Item updated successfully.');
}

    public function destroyItem(Item $item): RedirectResponse
    {
        $item->delete(); // cascades to transactions via FK
        return back();
    }

    public function archiveItem(Request $request, Item $item): RedirectResponse
    {
        $data = $request->validate([
            'archived' => 'required|boolean',
            'reason' => 'nullable|string|max:255',
        ]);

        $item->update([
            'archived' => $data['archived'],
            'archived_date' => $data['archived'] ? now()->toDateString() : null,
            'archived_reason' => $data['archived'] ? ($data['reason'] ?? null) : null,
        ]);

        return back();
    }

    /* ───────────────────────── TRANSACTIONS ───────────────────────── */

    public function storeTransaction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'type' => 'required|in:in,out,adj',
            'qty' => 'required|integer|min:1',
            'date' => 'required|date',
            'by' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:255',
        ]);

        Transaction::create([
            'item_id' => $data['item_id'],
            'type' => $data['type'],
            'qty' => $data['qty'],
            'date' => $data['date'],
            'by' => $data['by'] ?? 'System',
            'note' => $data['note'] ?? null,
        ]);

        return back();
    }

    public function updateTransaction(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|in:in,out,adj',
            'qty' => 'required|integer|min:1',
            'date' => 'required|date',
            'by' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:255',
        ]);

        $transaction->update($data);

        return back();
    }

    public function destroyTransaction(Transaction $transaction): RedirectResponse
    {
        $transaction->delete();
        return back();
    }

    /* ───────────────────────── WASTAGE ───────────────────────── */

    public function storeWastage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'type' => 'required|in:expired,spoiled,broken,other',
            'qty' => 'required|integer|min:1',
            'date' => 'required|date',
            'by' => 'nullable|string|max:255',
            'reason' => 'required|string|max:1000',
        ]);

        $item = Item::findOrFail($data['item_id']);

        // Record the physical stock-out first so it reflects in balances.
        $txn = $item->transactions()->create([
            'type' => 'out',
            'qty' => $data['qty'],
            'date' => $data['date'],
            'by' => $data['by'] ?? 'System',
            'note' => '[WASTAGE/'.strtoupper($data['type']).'] '.$data['reason'],
        ]);

        WastageRecord::create([
            'item_id' => $item->id,
            'transaction_id' => $txn->id,
            'item_name' => $item->name,
            'item_unit' => $item->unit,
            'item_lot' => $item->lot,
            'item_sec' => $item->sec,
            'type' => $data['type'],
            'qty' => $data['qty'],
            'date' => $data['date'],
            'by' => $data['by'] ?? 'System',
            'reason' => $data['reason'],
        ]);

        return back();
    }

    public function destroyWastage(WastageRecord $wastageRecord): RedirectResponse
    {
        // Remove the linked stock-out transaction (if still present) then the record.
        if ($wastageRecord->transaction_id) {
            Transaction::where('id', $wastageRecord->transaction_id)->delete();
        }
        $wastageRecord->delete();

        return back();
    }

    /* ───────────────────────── SETTINGS ───────────────────────── */

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'contact' => 'nullable|string|max:100',
            'logo_data_url' => 'nullable|string',
        ]);

        $settings = LabSetting::current();
        $settings->update($data);

        return back();
    }
}