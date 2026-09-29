<?php

namespace App\Http\Controllers;

use App\Models\Dispense;
use App\Models\Item;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DispenseController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);

        DB::transaction(function () use ($data, $lines) {
            $dispense = Dispense::create($data + [
                'qty' => collect($lines)->sum('qty'),   // total of all item lines
            ]);

            $dispense->dispenseItems()->createMany($lines);

            // Log a stock "out" transaction for every dispensed item
            $this->recordStockOut($dispense, $lines);
        });

        return back()->with('success', 'Dispense record saved.');
    }

    public function update(Request $request, Dispense $dispense): RedirectResponse
    {
        [$data, $lines] = $this->validated($request);

        DB::transaction(function () use ($dispense, $data, $lines) {
            $dispense->update($data + ['qty' => collect($lines)->sum('qty')]);

            $dispense->dispenseItems()->delete();
            $dispense->dispenseItems()->createMany($lines);

            // Remove the old log entries, then log the new ones.
            // If stock is insufficient, everything (including the delete) is rolled back.
            $this->clearStockOut($dispense);
            $this->recordStockOut($dispense, $lines);
        });

        return back()->with('success', 'Dispense record updated.');
    }

    public function destroy(Dispense $dispense): RedirectResponse
    {
        DB::transaction(function () use ($dispense) {
            $this->clearStockOut($dispense);   // stock goes back to the shelf
            $dispense->delete();               // dispense_items rows cascade
        });

        return back()->with('success', 'Dispense record deleted.');
    }

    /* ───────────── Transaction log helpers ───────────── */

    /**
     * Unique marker stored at the end of the transaction note so we can
     * find/remove the transactions that belong to one dispense record.
     */
    private function marker(Dispense $dispense): string
    {
        return "(Dispense #{$dispense->id})";
    }

    private function clearStockOut(Dispense $dispense): void
    {
        Transaction::where('type', 'out')
            ->where('note', 'like', '%' . $this->marker($dispense))
            ->delete();
    }

    /**
     * Create one "out" transaction per line, after checking stock.
     *
     * @param array<int, array{item_id:int, qty:int}> $lines
     */
    private function recordStockOut(Dispense $dispense, array $lines): void
    {
        $items = Item::whereIn('id', collect($lines)->pluck('item_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $date = $dispense->created_at->toDateString();

        foreach ($lines as $i => $line) {
            $item      = $items[$line['item_id']];
            $available = $this->availableStock($item);

            if ($line['qty'] > $available) {
                throw ValidationException::withMessages([
                    'items' => "Not enough stock for \"{$item->name}\" (available: {$available}, requested: {$line['qty']}).",
                ]);
            }

            Transaction::create([
                'item_id'      => $item->id,
                'type'         => 'out',
                'qty'          => $line['qty'],
                'date'         => $date,
                'performed_by' => $dispense->dispense_by,
                'note'         => "Dispensed to {$dispense->full_name} " . $this->marker($dispense),
            ]);
        }
    }

    /**
     * Same formula the inventory page uses:
     * (init_in + Σ in) − (init_out + Σ out)
     */
    private function availableStock(Item $item): int
    {
        $in  = (int) Transaction::where('item_id', $item->id)->where('type', 'in')->sum('qty');
        $out = (int) Transaction::where('item_id', $item->id)->where('type', 'out')->sum('qty');

        return (int) $item->init_in + $in - (int) $item->init_out - $out;
    }

    /* ───────────── Validation ───────────── */

    /**
     * @return array{0: array, 1: array<int, array{item_id:int, qty:int}>}
     */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            // Patient information
            'full_name'           => ['required', 'string', 'max:255'],
            'brgy'                => ['nullable', 'string', 'max:255'],
            'date_of_birth'       => ['nullable', 'date', 'before_or_equal:today'],
            'sex'                 => ['nullable', Rule::in(['Male', 'Female'])],

            // PhilHealth
            'has_philhealth'      => ['boolean'],
            'philhealth_number'   => ['nullable', 'string', 'regex:/^\d{2}-?\d{9}-?\d$/'],
            'philhealth_facility' => ['nullable', 'string', 'max:255'],

            // Items
            'items'               => ['required', 'array', 'min:1'],
            'items.*.item_id'     => ['required', 'integer', 'distinct', 'exists:items,id'],
            'items.*.qty'         => ['required', 'integer', 'min:1'],

            // Dispensing
            'dispense_by'           => ['required', 'string', 'max:255'],
            'received_by'           => ['required', 'string', 'max:255'],
            'receiver_relationship' => ['nullable', 'string', 'max:255'],
        ], [
            'items.required' => 'Add at least one item to dispense.',
            'items.min'      => 'Add at least one item to dispense.',
        ]);

        $lines = collect($v['items'])
            ->map(fn ($l) => ['item_id' => (int) $l['item_id'], 'qty' => (int) $l['qty']])
            ->values()
            ->all();

        unset($v['items']);

        $v['has_philhealth'] = (bool) ($v['has_philhealth'] ?? false);
        if (! $v['has_philhealth']) {
            $v['philhealth_number']   = null;
            $v['philhealth_facility'] = null;
        }

        return [$v, $lines];
    }
}