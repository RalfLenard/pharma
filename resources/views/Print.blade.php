<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Transfer Slip - {{ $reference_id }}</title>

<style>

@page{
    size: 13in 8.5in;
   
}

*{
    box-sizing:border-box;
}

body{
    font-family:Arial, Helvetica, sans-serif;
    font-size:12px;
    color:#222;
    margin:0.25in;
}

.sheet{
    width:100%;
}

.header-info{
    margin-bottom:15px;
}

.header-row{
    display:flex;
    justify-content:space-between;
    align-items:baseline;
    margin-bottom:8px;
}

.header-row:last-child{
    margin-bottom:0;
}

.field{
    font-size:13px;
}

.field .fill{
    display:inline-block;
    min-width:220px;
   
    margin-left:4px;
}

.ref-no{
    font-size:13px;
    font-weight:bold;
    white-space:nowrap;
}

h1{
    margin:20px 0 20px;
    font-size:22px;
    text-align:center;
    letter-spacing:1px;
}

table{
    width:100%;
    border-collapse:collapse;
}

table th{
    background:#f2f2f2;
    border:1px solid #000;
    padding:8px;
    font-size:11px;
    text-transform:uppercase;
}

table td{
    border:1px solid #000;
    padding:8px;
    font-size:12px;
}

tbody tr:nth-child(even){
    background:#fafafa;
}

.empty{
    text-align:center;
    padding:20px;
}

/* Footer */

.footer{
    margin-top:70px;
    display:flex;
    justify-content:space-between;
}

.signature{
    width:280px;
    text-align:center;
}

.signature .line{
    border-top:1px solid #000;
    margin-bottom:8px;
}

.signature strong{
    display:block;
    font-size:14px;
}

.signature span{
    display:block;
    font-size:12px;
    color:#555;
}

.signature .label{
    margin-top:10px;
    font-size:12px;
}

/* Noted */

.noted{
    width:100%;
    text-align:center;
    margin-top:70px;
}

.noted .line{
    width:320px;
    border-top:1px solid #000;
    margin:0 auto 8px;
}

.noted strong{
    display:block;
    font-size:14px;
}

.noted span{
    display:block;
    font-size:12px;
    color:#555;
}

</style>

</head>
<body>

<div class="sheet">

    <div class="header-info">

        <div class="header-row">
            <div class="field">Date: <span class="fill">&nbsp;</span></div>
            <div class="ref-no">REF. NO. {{ $reference_id }}</div>
        </div>

        <div class="header-row">
            <div class="field">To: {{ $remarks ?? 'All' }}</div>
        </div>

        <div class="header-row">
            <div class="field">From: <span class="fill">{{ $from_location ?? 'RHU-I' }}</span></div>
        </div>

    </div>

    <h1>STOCK TRANSFER</h1>

    <table>

        <thead>

            <tr>

                <th width="40">ITEM NO.</th>
                <th width="110">QTY</th>
                <th width="80">Unit</th>
                <th>ITEM NAME</th>
                <th>BRAND NAME</th>
                <th width="130">BN/LN</th>
                <th>ED</th>
                <th>Remarks</th>

            </tr>

        </thead>

        <tbody>

        @forelse($transfers as $transfer)

            <tr>

                <td align="center">
                    {{ $loop->iteration }}
                </td>

                 <td align="center">
                    {{ $transfer->qty }}
                </td>
                <td align="center">
                    {{ $transfer->item->unit ?? 'pcs' }}
                </td>
                 <td align="center">
                    {{ $transfer->item->name ?? '—' }}
                </td>
                 <td align="center">
                    {{ $transfer->item->brand ?? '—' }}
                </td>
               <td align="center">
                    {{ $transfer->item->lot ?? '—' }}
                </td>

                <td align="center">
                    {{ \Carbon\Carbon::parse($transfer->exp)->format('n/Y') }}
                </td>
                <td align="center">
                    {{ $transfer->remarks ?? '—' }}
                </td>

            </tr>

        @empty

            <tr>

                <td colspan="7" class="empty">
                    No transfers found.
                </td>

            </tr>

        @endforelse

        </tbody>

    </table>

    <div class="footer">

        <div class="signature">

          

            <strong>{{ $prepared_by }}</strong>

            <span>{{ $prepared_by_position }}</span>

           

        </div>

        <div class="signature">

           

            <br>

            <div class="label">
                Received By / DATE:   {{ \Carbon\Carbon::parse($transfer->date)->format('M d, Y') }}

            </div>

        </div>

    </div>

    <div class="noted">

        

        <strong>Diana I. Cunanan</strong>

        <span>Pharmacist</span>

    </div>

</div>

</body>
</html>