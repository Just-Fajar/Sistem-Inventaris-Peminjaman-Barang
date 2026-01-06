<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
            'description' => 'nullable|string|max:1000',
            'stock' => 'sometimes|required|integer|min:0|max:999999',
            'condition' => 'sometimes|required|in:baik,rusak',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];
    }

    /**
     * Handle after validation
     */
    protected function prepareForValidation(): void
    {
        // Ensure that stock changes are validated properly
        if ($this->has('stock')) {
            $currentItem = $this->route('item');
            if ($currentItem) {
                $difference = $this->input('stock') - $currentItem->stock;
                $newAvailableStock = $currentItem->available_stock + $difference;
                
                // Validate that new available stock won't be negative
                $this->merge([
                    '_calculated_available_stock' => $newAvailableStock,
                ]);
            }
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('_calculated_available_stock') && $this->_calculated_available_stock < 0) {
                $validator->errors()->add('stock', 'Stock tidak dapat dikurangi karena akan membuat available stock menjadi negatif. Ada ' . abs($this->_calculated_available_stock) . ' item yang sedang dipinjam.');
            }
        });
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama barang wajib diisi',
            'name.max' => 'Nama barang maksimal 255 karakter',
            'category_id.required' => 'Kategori wajib dipilih',
            'category_id.exists' => 'Kategori tidak valid',
            'stock.required' => 'Stok wajib diisi',
            'stock.integer' => 'Stok harus berupa angka',
            'stock.min' => 'Stok minimal 0',
            'condition.required' => 'Kondisi barang wajib dipilih',
            'condition.in' => 'Kondisi barang tidak valid',
            'image.image' => 'File harus berupa gambar',
            'image.mimes' => 'Format gambar harus jpeg, png, atau jpg',
            'image.max' => 'Ukuran gambar maksimal 2MB',
        ];
    }
}
