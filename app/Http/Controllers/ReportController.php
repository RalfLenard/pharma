<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\Wastage;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Monthly Consumption Report
     */
    public function monthly(Request $request)
    {
        $month = $request->get('month', Carbon::now()->format('Y-m'));
        $section = $request->get('section');
        $fund = $request->get('fund');

        $date = Carbon::createFromFormat('Y-m', $month);
        $prevMonth = $date->copy()->subMonth();

        $items = Item::when($section, fn($q) => $q->where('section', $section))
            ->when($fund, fn($q) => $q->where('fund_source', $fund))
            ->active()
            ->get();

        $report = $items->map(function ($item) use ($date, $prevMonth) {
            $begBalance = $item->getStockAtEndOfMonth($prevMonth);
            $received = $item->transactions()
                ->where('type', 'in')
                ->whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->sum('qty');

            $consumed = $item->transactions()
                ->where('type', 'out')
                ->whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->where('note', 'NOT LIKE', '[WASTAGE/%]')
                ->sum('qty');

            $wastage = Wastage::where('item_id', $item->id)
                ->whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->sum('qty');

            $endBalance = max(0, $begBalance + $received - $consumed - $wastage);

            return [
                'item' => $item,
                'beg_balance' => $begBalance,
                'received' => $received,
                'consumed' => $consumed,
                'wastage' => $wastage,
                'end_balance' => $endBalance,
                'remarks' => $this->generateRemarks($endBalance, $item->min_stock, $wastage)
            ];
        });

        return response()->json([
            'month' => $month,
            'section' => $section,
            'report' => $report
        ]);
    }

    private function generateRemarks($endBalance, $minStock, $wastage)
    {
        $remarks = [];
        if ($endBalance === 0) $remarks[] = "Out of stock";
        elseif ($endBalance < $minStock) $remarks[] = "Low stock";
        if ($wastage > 0) $remarks[] = "{$wastage} units wasted";
        return implode(' | ', $remarks) ?: '—';
    }

    /**
     * Export Monthly Report as Excel (basic)
     */
    public function exportMonthlyExcel(Request $request)
    {
        // You can use Maatwebsite/Excel package for better Excel export
        // For now, returning JSON - you can implement full export later
        return $this->monthly($request);
    }
}