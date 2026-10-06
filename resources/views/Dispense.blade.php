<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $dispense->reference_no }} – Dispense Slip</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; margin: 0; padding: 28px; background: #f1f5f9; }
        .sheet { max-width: 780px; margin: 0 auto; background: #fff; padding: 28px; }
        .toolbar { max-width: 780px; margin: 0 auto 12px; display: flex; gap: 8px; justify-content: flex-end; }
        .toolbar button { height: 32px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; cursor: pointer; font-size: 12px; }
        .toolbar button.primary { background: #1e40af; border-color: #1e40af; color: #fff; }

        /* .head { text-align: center; border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 14px; }
        .head h1 { font-size: 16px; margin: 0 0 2px; }
        .head h2 { font-size: 13px; margin: 0; letter-spacing: .08em; text-transform: uppercase; font-weight: 600; } */
     .head { 
    display: flex; 
    align-items: center; 
    justify-content: center; /* Group everything in the center */
    gap: 16px;              /* Controls the space between logos and text (adjust as needed) */
    border-bottom: 2px solid #111; 
    padding-bottom: 10px; 
    margin-bottom: 14px; 
}
.head-logo {
    width: 50px; 
    height: 50px; 
    border-radius: 50%;
    object-fit: contain;
    flex-shrink: 0;         /* Prevents logos from shrinking on narrow screens */
}
.head-title {
    text-align: center;
    /* flex-grow removed so it stays compact */
}
.head-title h1 { 
    font-size: 16px; 
    margin: 0 0 2px; 
}
.head-title h2 { 
    font-size: 13px; 
    margin: 0; 
    letter-spacing: .08em; 
    text-transform: uppercase; 
    font-weight: 600; 
}
        .ref { display: flex; justify-content: space-between; margin-bottom: 12px; }
        .ref b { font-family: monospace; font-size: 13px; }

        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; margin-bottom: 14px; }
        .grid span { display: block; font-size: 10px; color: #555; text-transform: uppercase; letter-spacing: .05em; }
        .grid strong { font-size: 12px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #eee; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; }
        .r { text-align: right; }
        .sm { font-size: 10px; color: #555; }
        .total { text-align: right; font-weight: 700; margin-bottom: 26px; }

        /* REVISED SIGNATURE SECTION STYLING */
        .sign { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 24px; 
            page-break-inside: avoid;
        }
        .sign-box { 
            text-align: center; 
            font-size: 11px; 
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        .sign-space {
            min-height: 45px; /* Reserves clear space for physical signature */
        }
        .sign-line { 
            border-bottom: 1px solid #111; 
            font-weight: 700; 
            padding-bottom: 4px; 
            margin-bottom: 4px; 
            text-transform: uppercase;
            font-size: 11px;
        }
        .sign-box small { 
            display: block; 
            color: #555; 
            font-size: 9.5px; 
            line-height: 1.2;
        }

        .foot { margin-top: 24px; font-size: 10px; color: #666; text-align: center; }

        @page { margin: 12mm; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { padding: 0; max-width: none; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="primary" onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>

    <div class="sheet">
        <div class="head">
            <img src="/images/Picture3.png" alt="Logo Left" class="head-logo">
            <div class="head-title">
                <h1>CONCEPCION RURAL HEALTH UNIT I</h1>
                <h2>Dispensing Slip</h2>
            </div>
            <img src="/images/Picture2.png" alt="Logo Right" class="head-logo">
        </div>

        <div class="ref">
            <div>Reference No.: <b>{{ $dispense->reference_no ?? '—' }}</b></div>
            <div>Date: <b>{{ $dispense->created_at->format('M d, Y') }}</b></div>
        </div>

        <div class="grid">
            <div><span>Patient</span><strong>{{ $dispense->full_name }}</strong></div>
            <div><span>Barangay</span><strong>{{ $dispense->brgy ?: '—' }}</strong></div>
            <div>
                <span>Date of birth / Age</span>
                <strong>
                    @if($dispense->date_of_birth)
                        {{ $dispense->date_of_birth->format('M d, Y') }} ({{ $dispense->date_of_birth->age }} yrs old)
                    @else
                        —
                    @endif
                </strong>
            </div>
            <div><span>Sex</span><strong>{{ $dispense->sex ?: '—' }}</strong></div>
            <div>
                <span>PhilHealth</span>
                <strong>{{ $dispense->has_philhealth ? ($dispense->philhealth_number ?: 'Yes') : 'No' }}</strong>
            </div>
            <div>
                <span>PhilHealth facility</span>
                <strong>{{ $dispense->has_philhealth ? ($dispense->philhealth_facility ?: '—') : '—' }}</strong>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:30px">#</th>
                    <th>Item</th>
                    <th class="r" style="width:70px">Qty</th>
                    <th style="width:70px">Unit</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dispense->dispenseItems as $i => $line)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            {{ $line->item->name ?? 'Deleted item' }}
                            @php $detail = collect([$line->item->brand ?? null])->filter()->implode(' · '); @endphp
                            @if($detail)<div class="sm">{{ $detail }}</div>@endif
                        </td>
                        <td class="r">{{ $line->qty }}</td>
                        <td>{{ $line->item->unit ?? 'pcs' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No items</td></tr>
                @endforelse
            </tbody>
        </table>

        <!-- REVISED SIGNATURE SECTION -->
        <div class="sign">
            <div class="sign-box">
                <div class="sign-space"></div>
                <div class="sign-line">{{ $dispense->dispense_by ?: '—' }}</div>
                <small>
                    @switch($dispense->dispense_by)
                        @case('DIANA I. CUNANAN')
                            Pharmacist I
                            @break
                        @case('JUSTINE GAIL T. PABALAN')
                            Nursing Attendant I
                            @break
                        @case('MICAH LAINE L. SABALBIRINO')
                            Administrative Aide III
                            @break
                        @case('CRISTEL ANN B. CASTRO')
                            Administrative Aide I
                            @break
                        @default
                            Dispensed by
                    @endswitch
                </small>
            </div>
            <div class="sign-box">
                <div class="sign-space"></div>
                <div class="sign-line">DIANA I. CUNANAN</div>
                <small> Pharmacist I</small>
            </div>
            
            <div class="sign-box">
                <div class="sign-space"></div>
                <div class="sign-line">
                {{ $dispense->received_by ?: '—' }}
                @if($dispense->received_by && $dispense->full_name && strtolower(trim($dispense->received_by)) !== strtolower(trim($dispense->full_name)))
                    @if($dispense->receiver_relationship)
                        ({{ $dispense->receiver_relationship }})
                    @endif
                @endif
            </div>
                <small>Received by</small>
            </div>
            
        </div>

    </div>

    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>