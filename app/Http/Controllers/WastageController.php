<?php

namespace App\Http\Controllers;

use App\Models\Wastage;
use App\Models\Item;
use Illuminate\Http\Request;

class WastageController extends Controller
{
    /**
     * Record new wastage
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'type' => 'required|in:expired,spoiled,broken,other',
            'qty' => 'required|integer|min:1',
            'date' => 'required|date',
            'reason' => 'required|string',
            'performed_by' => 'nullable|string',
        ]);

        $item = Item::findOrFail($validated['item_id']);

        $wastage = Wastage::create([
            'item_id' => $validated['item_id'],
            'type' => $validated['type'],
            'qty' => $validated['qty'],
            'date' => $validated['date'],
            'reason' => $validated['reason'],
            'performed_by' => $validated['performed_by'] ?? 'System',
        ]);

        // Also record as stock out transaction with wastage flag
        $item->transactions()->create([
            'type' => 'out',
            'qty' => $validated['qty'],
            'date' => $validated['date'],
            'performed_by' => $wastage->performed_by,
            'note' => "[WASTAGE/{$validated['type']}] {$validated['reason']}",
        ]);

        $item->refreshCurrentStock();

        return response()->json([
            'message' => 'Wastage recorded successfully',
            'wastage' => $wastage
        ], 201);
    }

    /**
     * List wastage records
     */
    public function index(Request $request)
    {
        $query = Wastage::with('item');

        if ($request->has('search')) {
            $query->whereHas('item', fn($q) => $q->where('name', 'LIKE', "%{$request->search}%"))
                  ->orWhere('reason', 'LIKE', "%{$request->search}%");
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('month')) {
            $query->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$request->month]);
        }

        $wastages = $query->latest()->paginate(30);

        return response()->json($wastages);
    }

    /**
     * Delete wastage record
     */
    public function destroy(Wastage $wastage)
    {
        $item = $wastage->item;

        // Delete associated transaction if exists
        $item->transactions()
             ->where('note', 'LIKE', "[WASTAGE/{$wastage->type}]%")
             ->where('date', $wastage->date)
             ->where('qty', $wastage->qty)
             ->delete();

        $wastage->delete();
        $item->refreshCurrentStock();

        return response()->json(['message' => 'Wastage record deleted']);
    }
}