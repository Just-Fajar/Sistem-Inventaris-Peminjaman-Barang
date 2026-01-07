<?php

namespace App\Exports;

use App\Models\Borrowing;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BorrowingsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = Borrowing::with(['user', 'item.category', 'approver']);

        if (isset($this->filters['start_date'])) {
            $query->whereDate('borrow_date', '>=', $this->filters['start_date']);
        }
        if (isset($this->filters['end_date'])) {
            $query->whereDate('borrow_date', '<=', $this->filters['end_date']);
        }
        if (isset($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        return $query->orderBy('borrow_date', 'desc')->get();
    }

    /**
     * Define headings
     */
    public function headings(): array
    {
        return [
            'Kode Peminjaman',
            'Nama Peminjam',
            'Nama Barang',
            'Kategori',
            'Jumlah',
            'Tanggal Pinjam',
            'Tanggal Jatuh Tempo',
            'Tanggal Kembali',
            'Status',
            'Disetujui Oleh',
            'Catatan',
        ];
    }

    /**
     * Map data to columns
     */
    public function map($borrowing): array
    {
        return [
            $borrowing->code,
            $borrowing->user->name,
            $borrowing->item->name,
            $borrowing->item->category->name,
            $borrowing->quantity,
            $borrowing->borrow_date->format('d/m/Y'),
            $borrowing->due_date->format('d/m/Y'),
            $borrowing->return_date ? $borrowing->return_date->format('d/m/Y') : '-',
            ucfirst($borrowing->status),
            $borrowing->approver ? $borrowing->approver->name : '-',
            $borrowing->notes ?? '-',
        ];
    }

    /**
     * Apply styles
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold
            1 => ['font' => ['bold' => true]],
        ];
    }
}
