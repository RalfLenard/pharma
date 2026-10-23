<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Item Dispense Report – {{ $itemName }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }

        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111;
            margin: 0;
            padding: 16px;
        }

        .toolbar { text-align: right; margin-bottom: 12px; }
        .toolbar button {
            padding: 8px 16px; border: 0; border-radius: 6px;
            background: #1e40af; color: #fff; font-size: 13px; cursor: pointer;
        }

        .head { text-align: center; margin-bottom: 14px; }
        .head h1 { margin: 0; font-size: 18px; text-transform: uppercase; letter-spacing: .04em; }
        .head h2 { margin: 4px 0 0; font-size: 14px; font-weight: 600; }
        .item-name { margin-top: 6px; font-size: 15px; font-weight: 700; }
        .meta { margin-top: 6px; font-size: 11px; color: #444; }

        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px 8px; text-align: left; vertical-align: middle; }
        th { background: #e5e7eb; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; }
        td.c, th.c { text-align: center; }
        tr { page-break-inside: avoid; }
        thead { display: table-header-group; }

        tfoot td { font-weight: 700; background: #f3f4f6; }
        .empty { text-align: center; padding: 20px; color: #666; }

        .foot { margin-top: 10px; font-size: 10px; color: #555; }

        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="head">
       
        <div class="item-name">{{ $itemName }}</div>
       
    </div>

    <table>
        <thead>
            <tr>
                <th class="c" style="width:4%;">No.</th>
                <th style="width:11%;">Date Dispensed</th>
                <th style="width:13%;">Reference No.</th>
                <th>Name of Patient</th>
                <th class="c" style="width:5%;">Age</th>
                <th style="width:13%;">Barangay</th>
                <th class="c" style="width:7%;">Quantity</th>
                <th style="width:14%;">Received By</th>
                <th style="width:13%;">Relationship to Patient</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="c">{{ $loop->iteration }}</td>
                    <td>{{ $r['date'] }}</td>
                    <td>{{ $r['reference_no'] }}</td>
                    <td>{{ $r['patient'] }}</td>
                    <td class="c">{{ $r['age'] }}</td>
                    <td>{{ $r['brgy'] }}</td>
                    <td class="c">{{ $r['qty'] }}</td>
                    <td>{{ $r['received_by'] }}</td>
                    <td>{{ $r['relationship'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty">No dispense records found for this item.</td></tr>
            @endforelse
        </tbody>
        @if ($rows->count())
            <tfoot>
                <tr>
                    <td colspan="6" style="text-align:right;">Total quantity</td>
                    <td class="c">{{ $totalQty }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        @endif
    </table>

   
</body>
</html>