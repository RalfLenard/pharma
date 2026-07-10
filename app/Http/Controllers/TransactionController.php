<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Item;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TransactionController extends Controller
{
    /**
     * Record a new stock transaction (In / Out / Adjustment)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'type' => 'required|in:in,out,adj',
            'qty' => 'required|integer|min:1',
            'date' => 'required|date',
            'performed_by' => 'nullable|string',
            'note' => 'nullable|string',
            'new_lot_number' => 'nullable|string',
            'new_expiry' => 'nullable|date',
        ]);

        $item = Item::findOrFail($validated['item_id']);

        // Update lot/expiry if provided during stock-in
        if ($validated['type'] === 'in') {
            if (!empty($validated['new_lot_number'])) {
                $item->lot_number = $validated['new_lot_number'];
            }
            if (!empty($validated['new_expiry'])) {
                $item->expiry = $validated['new_expiry'];
            }
            $item->save();
        }

        $transaction = Transaction::create([
            'item_id' => $validated['item_id'],
            'type' => $validated['type'],
            'qty' => $validated['qty'],
            'date' => $validated['date'],
            'performed_by' => $validated['performed_by'] ?? auth()->user()?->name ?? 'System',
            'note' => $validated['note'],
        ]);

        // Update current stock in Item model
        $item->refreshCurrentStock();

        return response()->json([
            'message' => 'Transaction recorded successfully',
            'transaction' => $transaction->load('item')
        ], 201);
    }

    /**
     * Get transaction log with filters
     */
    public function index(Request $request)
    {
        $query = Transaction::with('item');

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('item', function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%");
            })->orWhere('note', 'LIKE', "%{$search}%");
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('month')) {
            $date = Carbon::createFromFormat('Y-m', $request->month);
            $query->whereYear('date', $date->year)
                  ->whereMonth('date', $date->month);
        }

        $transactions = $query->latest()->paginate(50);

        return response()->json($transactions);
    }

    /**
     * Delete a transaction
     */
    public function destroy(Transaction $transaction)
    {
        $item = $transaction->item;
        $transaction->delete();
        $item->refreshCurrentStock();

        return response()->json(['message' => 'Transaction deleted successfully']);
    }
}