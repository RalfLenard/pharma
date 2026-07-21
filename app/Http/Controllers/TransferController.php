<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use App\Models\PrintTransfer;
use App\Models\Item;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf;

class TransferController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'qty' => 'required|integer|min:1',
            'remarks' => 'required|string|max:255',
            'date' => 'required|date',
        ]);

        $item = Item::findOrFail($validated['item_id']);

        // Check sufficient stock
        $currentStock = $this->getCurrentStock($item);
        if ($currentStock < $validated['qty']) {
            return back()->withErrors(['qty' => 'Insufficient stock. Current stock: ' . $currentStock]);
        }

        // Create Transfer Record
        $transfer = Transfer::create([
            'item_id' => $validated['item_id'],
            'qty' => $validated['qty'],
            'remarks' => $validated['remarks'],
            'date' => $validated['date'],
            'created_by' => auth()->id(),
        ]);

        // Create Out Transaction
        Transaction::create([
            'item_id' => $item->id,
            'type' => 'out',
            'qty' => $validated['qty'],
            'date' => $validated['date'],
            'note' => "Transfer | {$validated['date']} | {$validated['remarks']}",
        ]);

        return redirect()->back()->with('success', 'Item transferred successfully.');
    }

    private function getCurrentStock($item)
    {
        $in = $item->transactions()->where('type', 'in')->sum('qty') + ($item->init_in ?? 0);
        $out = $item->transactions()->where('type', 'out')->sum('qty') + ($item->init_out ?? 0);
        return $in - $out;
    }
    public function print(Request $request)
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date'],
            'prepared_by' => ['required', 'string', 'max:255'],
            'prepared_by_position' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $transfers = Transfer::with('item')
            ->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59',
            ])
            ->when(
                $request->filled('remarks'),
                fn($q) =>
                $q->where('remarks', $request->remarks)
            )
            ->latest()
            ->get();

        $referenceId = $this->generateReferenceId();

        PrintTransfer::create([
            'reference_id' => $referenceId,
            'prepared_by' => $request->prepared_by,
            'prepared_by_position' => $request->prepared_by_position,
            'printed_by' => auth()->id(),
            'printed_at' => now(),
        ]);

        return Pdf::view('Print', [
            'transfers' => $transfers,
            'reference_id' => $referenceId,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'prepared_by' => $request->prepared_by,
            'prepared_by_position' => $request->prepared_by_position,
            'remarks' => $request->remarks,
        ])
            ->landscape()
            ->format('A4')
            ->name($referenceId . '.pdf');
    }
    private function generateReferenceId()
    {
        $year = now()->year;
        $prefix = $year . '-';

        $last = PrintTransfer::where('reference_id', 'like', $prefix . '%')
            ->orderByDesc('reference_id')
            ->first();

        if ($last) {
            $lastNumber = (int) substr($last->reference_id, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}