<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBorrowingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $borrowing = $this->route('borrowing');
        if ($borrowing && !$this->has('borrow_date')) {
            $borrowDate = $borrowing instanceof \App\Models\Borrowing
                ? $borrowing->borrow_date
                : \App\Models\Borrowing::find($borrowing)?->borrow_date;

            if ($borrowDate) {
                $this->merge([
                    'borrow_date' => \Carbon\Carbon::parse($borrowDate)->toDateString(),
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'item_id' => 'sometimes|required|exists:items,id',
            'quantity' => 'sometimes|required|integer|min:1',
            'borrow_date' => 'sometimes|required|date',
            'due_date' => 'sometimes|required|date|after_or_equal:borrow_date',
            'return_date' => 'nullable|date',
            'status' => 'sometimes|required|in:pending,dipinjam,dikembalikan,terlambat',
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_id.required' => 'Barang wajib dipilih',
            'item_id.exists' => 'Barang tidak ditemukan',
            'quantity.required' => 'Jumlah wajib diisi',
            'quantity.integer' => 'Jumlah harus berupa angka',
            'quantity.min' => 'Jumlah minimal 1',
            'borrow_date.required' => 'Tanggal pinjam wajib diisi',
            'borrow_date.date' => 'Format tanggal pinjam tidak valid',
            'due_date.required' => 'Tanggal kembali wajib diisi',
            'due_date.date' => 'Format tanggal kembali tidak valid',
            'due_date.after_or_equal' => 'Tanggal kembali harus setelah atau sama dengan tanggal pinjam',
            'return_date.date' => 'Format tanggal pengembalian tidak valid',
            'status.required' => 'Status wajib dipilih',
            'status.in' => 'Status tidak valid',
            'notes.max' => 'Catatan maksimal 500 karakter',
        ];
    }
}
