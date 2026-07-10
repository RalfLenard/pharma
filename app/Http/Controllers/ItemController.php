<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    /**
     * Display a listing of items.
     */
    public function index(Request $request)
    {
        $query = Item::with('latestTransaction');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('brand', 'LIKE', "%{$search}%")
                  ->orWhere('lot_number', 'LIKE', "%{$search}%");
        }

        if ($request->has('section')) {
            $query->where('section', $request->section);
        }

        if ($request->has('status')) {
            // You can implement status filtering logic here
        }

        $items = $query->active()->paginate(50);

        return response()->json($items);
    }

    /**
     * Store a newly created item.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'vol' => 'nullable|string',
            'brand' => 'nullable|string',
            'section' => 'nullable|string',
            'lot_number' => 'nullable|string',
            'expiry' => 'nullable|date',
            'unit' => 'required|string',
            'min_stock' => 'integer|min:0',
            'fund_source' => 'nullable|string',
            'initial_stock_in' => 'integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            $item = Item::create($validated);

            // Record initial stock transaction if any
            if ($request->initial_stock_in > 0) {
                $item->transactions()->create([
                    'type' => 'in',
                    'qty' => $request->initial_stock_in,
                    'date' => now(),
                    'performed_by' => auth()->user()?->name ?? 'System',
                    'note' => 'Initial stock entry',
                ]);
            }

            DB::commit();

            return response()->json($item, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified item.
     */
    public function show(Item $item)
    {
        $item->load('transactions');
        return response()->json($item);
    }

    /**
     * Update the specified item.
     */
    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'vol' => 'nullable|string',
            'brand' => 'nullable|string',
            'section' => 'nullable|string',
            'lot_number' => 'nullable|string',
            'expiry' => 'nullable|date',
            'unit' => 'required|string',
            'min_stock' => 'integer|min:0',
            'fund_source' => 'nullable|string',
            'order_qty' => 'integer|min:0',
        ]);

        $item->update($validated);

        return response()->json($item);
    }

    /**
     * Remove (Archive) the specified item.
     */
    public function destroy(Item $item)
    {
        $item->update([
            'archived' => true,
            'archived_date' => now(),
            'archive_reason' => request('reason')
        ]);

        return response()->json(['message' => 'Item archived successfully']);
    }

    /**
     * Restore archived item
     */
    public function restore(Item $item)
    {
        $item->update([
            'archived' => false,
            'archived_date' => null,
            'archive_reason' => null
        ]);

        return response()->json(['message' => 'Item restored successfully']);
    }
}