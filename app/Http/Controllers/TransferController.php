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

        // Create Out Transaction first so we can link it
        $transaction = Transaction::create([
            'item_id' => $item->id,
            'type' => 'out',
            'qty' => $validated['qty'],
            'date' => $validated['date'],
            'note' => "Transfer | {$validated['date']} | {$validated['remarks']}",
        ]);

        // Create Transfer Record, linked to its transaction
        $transfer = Transfer::create([
            'item_id' => $validated['item_id'],
            'qty' => $validated['qty'],
            'remarks' => $validated['remarks'],
            'date' => $validated['date'],
            'created_by' => auth()->id(),
            'transaction_id' => $transaction->id,
        ]);

        return redirect()->back()->with('success', 'Item transferred successfully.');
    }

    public function update(Request $request, Transfer $transfer)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'qty' => 'required|integer|min:1',
            'remarks' => 'required|string|max:255',
            'date' => 'required|date',
        ]);

        $newItem = Item::findOrFail($validated['item_id']);
        $oldItem = $transfer->item_id == $validated['item_id']
            ? $newItem
            : Item::findOrFail($transfer->item_id);

        // Figure out stock as if this transfer hadn't happened yet,
        // then check if the new qty fits.
        if ($oldItem->id === $newItem->id) {
            // Same item: add back the old qty before checking the new one
            $stockExcludingThisTransfer = $this->getCurrentStock($newItem) + $transfer->qty;
        } else {
            // Item changed: old item gets its stock back, new item is checked fresh
            $stockExcludingThisTransfer = $this->getCurrentStock($newItem);
        }

        if ($stockExcludingThisTransfer < $validated['qty']) {
            return back()->withErrors([
                'qty' => 'Insufficient stock. Available: ' . $stockExcludingThisTransfer,
            ]);
        }

        // Update the linked Transaction (ledger entry) to match
        $transaction = $transfer->transaction_id
            ? Transaction::find($transfer->transaction_id)
            : null;

        if ($transaction) {
            $transaction->update([
                'item_id' => $validated['item_id'],
                'type' => 'out',
                'qty' => $validated['qty'],
                'date' => $validated['date'],
                'note' => "Transfer | {$validated['date']} | {$validated['remarks']}",
            ]);
        } else {
            // Fallback: no linked transaction found (e.g. legacy row), create one
            $transaction = Transaction::create([
                'item_id' => $validated['item_id'],
                'type' => 'out',
                'qty' => $validated['qty'],
                'date' => $validated['date'],
                'note' => "Transfer | {$validated['date']} | {$validated['remarks']}",
            ]);
        }

        // Update the Transfer record itself
        $transfer->update([
            'item_id' => $validated['item_id'],
            'qty' => $validated['qty'],
            'remarks' => $validated['remarks'],
            'date' => $validated['date'],
            'transaction_id' => $transaction->id,
        ]);

        return redirect()->back()->with('success', 'Transfer updated successfully.');
    }

    public function destroy(Transfer $transfer)
    {
        // Remove the linked ledger entry so stock isn't permanently reduced
        if ($transfer->transaction_id) {
            Transaction::where('id', $transfer->transaction_id)->delete();
        }

        $transfer->delete();

        return redirect()->back()->with('success', 'Transfer deleted successfully.');
    }

    private function getCurrentStock($item)
    {
        $in = $item->transactions()->where('type', 'in')->sum('qty') + ($item->init_in ?? 0);
        $out = $item->transactions()->where('type', 'out')->sum('qty') + ($item->init_out ?? 0);
        return $in - $out;
    }
public function print(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'prepared_by' => ['required', 'string', 'max:255'],
            'prepared_by_position' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $referenceId = $this->generateReferenceId();

        PrintTransfer::create([
            'reference_id' => $referenceId,
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'prepared_by' => $validated['prepared_by'],
            'prepared_by_position' => $validated['prepared_by_position'],
            'remarks' => $validated['remarks'] ?? null,
            'printed_by' => auth()->id(),
            'printed_at' => now(),
        ]);

        return response()->json([
            'reference_id' => $referenceId,
        ]);
    }

    public function reprint(string $referenceId)
    {
        $printTransfer = PrintTransfer::where('reference_id', $referenceId)->firstOrFail();

        $transfers = Transfer::with('item')
            ->whereBetween('created_at', [
                $printTransfer->date_from->format('Y-m-d') . ' 00:00:00',
                $printTransfer->date_to->format('Y-m-d') . ' 23:59:59',
            ])
            ->when(
                filled($printTransfer->remarks),
                fn($q) => $q->where('remarks', $printTransfer->remarks)
            )
            ->latest()
            ->get();

        return Pdf::view('Print', [
            'transfers' => $transfers,
            'reference_id' => $printTransfer->reference_id,
            'date_from' => $printTransfer->date_from,
            'date_to' => $printTransfer->date_to,
            'prepared_by' => $printTransfer->prepared_by,
            'prepared_by_position' => $printTransfer->prepared_by_position,
            'remarks' => $printTransfer->remarks,
        ])
            ->landscape()
            ->format('A4')
            ->name($printTransfer->reference_id . '.pdf');
    }

    public function printHistory(Request $request)
    {
        $history = PrintTransfer::with('printedBy')
            ->orderByDesc('printed_at')
            ->paginate(15);

        return response()->json($history);
    }

    private function generateReferenceId(): string
    {
        $year = now()->year;
        $prefix = $year . '-';

        $last = PrintTransfer::where('reference_id', 'like', $prefix . '%')
            ->orderByDesc('reference_id')
            ->first();

        $nextNumber = $last ? ((int) substr($last->reference_id, -4)) + 1 : 1;

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}