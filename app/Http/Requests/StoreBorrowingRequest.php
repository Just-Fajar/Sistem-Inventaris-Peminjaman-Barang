<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBorrowingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|integer|min:1',
            'borrow_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:borrow_date',
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
            'notes.max' => 'Catatan maksimal 500 karakter',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $item = \App\Models\Item::find($this->item_id);
            
            if ($item && $this->quantity > $item->available_stock) {
                $validator->errors()->add(
                    'quantity',
                    "Stok tidak mencukupi. Stok tersedia: {$item->available_stock}"
                );
            }
        });
    }
}
