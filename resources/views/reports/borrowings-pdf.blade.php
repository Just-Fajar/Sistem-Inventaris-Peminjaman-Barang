<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Peminjaman Barang</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 10px;
            color: #666;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-section table {
            width: 100%;
            margin-bottom: 10px;
        }
        .info-section td {
            padding: 3px;
        }
        .info-section td:first-child {
            width: 150px;
            font-weight: bold;
        }
        .statistics {
            background-color: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .statistics h3 {
            font-size: 14px;
            margin-bottom: 10px;
        }
        .statistics table {
            width: 100%;
        }
        .statistics td {
            padding: 5px;
        }
        .statistics td:first-child {
            width: 200px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .data-table thead {
            background-color: #333;
            color: white;
        }
        .data-table th,
        .data-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .data-table th {
            font-size: 11px;
            font-weight: bold;
        }
        .data-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .status {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-dipinjam {
            background-color: #bfdbfe;
            color: #1e40af;
        }
        .status-dikembalikan {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-terlambat {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>LAPORAN PEMINJAMAN BARANG</h1>
        <p>Sistem Inventaris & Peminjaman Barang</p>
    </div>

    <div class="info-section">
        <table>
            <tr>
                <td>Tanggal Cetak</td>
                <td>: {{ $generated_at }}</td>
            </tr>
            @if(isset($filters['start_date']) || isset($filters['end_date']))
            <tr>
                <td>Periode</td>
                <td>: 
                    @if(isset($filters['start_date']))
                        {{ \Carbon\Carbon::parse($filters['start_date'])->format('d/m/Y') }}
                    @else
                        -
                    @endif
                    s/d 
                    @if(isset($filters['end_date']))
                        {{ \Carbon\Carbon::parse($filters['end_date'])->format('d/m/Y') }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @endif
            @if(isset($filters['status']))
            <tr>
                <td>Status</td>
                <td>: {{ ucfirst($filters['status']) }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="statistics">
        <h3>Ringkasan</h3>
        <table>
            <tr>
                <td>Total Peminjaman</td>
                <td>: {{ $statistics['total'] }}</td>
            </tr>
            <tr>
                <td>Sedang Dipinjam</td>
                <td>: {{ $statistics['active'] }}</td>
            </tr>
            <tr>
                <td>Sudah Dikembalikan</td>
                <td>: {{ $statistics['returned'] }}</td>
            </tr>
            <tr>
                <td>Terlambat</td>
                <td>: {{ $statistics['overdue'] }}</td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 60px;">No</th>
                <th>Kode</th>
                <th>Peminjam</th>
                <th>Barang</th>
                <th style="width: 40px;">Qty</th>
                <th style="width: 70px;">Tgl Pinjam</th>
                <th style="width: 70px;">Jatuh Tempo</th>
                <th style="width: 80px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($borrowings as $index => $borrowing)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td>{{ $borrowing->code }}</td>
                <td>{{ $borrowing->user->name }}</td>
                <td>{{ $borrowing->item->name }}</td>
                <td style="text-align: center;">{{ $borrowing->quantity }}</td>
                <td>{{ $borrowing->borrow_date->format('d/m/Y') }}</td>
                <td>{{ $borrowing->due_date->format('d/m/Y') }}</td>
                <td>
                    <span class="status status-{{ $borrowing->status }}">
                        {{ ucfirst($borrowing->status) }}
                    </span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align: center; padding: 20px;">
                    Tidak ada data peminjaman
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>Dokumen ini dibuat secara otomatis oleh Sistem Inventaris & Peminjaman Barang</p>
        <p>{{ $generated_at }}</p>
    </div>
</body>
</html>
