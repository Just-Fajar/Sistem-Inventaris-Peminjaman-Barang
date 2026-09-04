<?php

namespace Tests\Feature;

use App\Http\Requests\StoreBorrowingRequest;
use App\Http\Requests\UpdateBorrowingRequest;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BorrowingFormRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected Category $category;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff@example.com',
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Alat Elektronik',
        ]);

        $this->item = Item::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Kamera DSLR Canon',
            'code' => 'ITM-2026-0099',
            'stock' => 5,
            'available_stock' => 5,
            'condition' => 'baik',
        ]);
    }

    /* =========================================================================
     * ⚪ WHITE-BOX TESTING (Rules, Messages & PrepareForValidation)
     * ========================================================================= */

    public function test_whitebox_store_borrowing_request_rules_and_custom_messages(): void
    {
        $request = new StoreBorrowingRequest();
        $rules = $request->rules();
        $messages = $request->messages();

        $this->assertTrue($request->authorize());

        // Validate required fields
        $this->assertArrayHasKey('item_id', $rules);
        $this->assertArrayHasKey('quantity', $rules);
        $this->assertArrayHasKey('borrow_date', $rules);
        $this->assertArrayHasKey('due_date', $rules);
        $this->assertArrayHasKey('notes', $rules);

        // Validate error messages
        $validator = Validator::make([], $rules, $messages);
        $this->assertTrue($validator->fails());

        $errors = $validator->errors()->messages();
        $this->assertContains('Barang wajib dipilih', $errors['item_id'] ?? []);
        $this->assertContains('Jumlah wajib diisi', $errors['quantity'] ?? []);
        $this->assertContains('Tanggal pinjam wajib diisi', $errors['borrow_date'] ?? []);
        $this->assertContains('Tanggal kembali wajib diisi', $errors['due_date'] ?? []);
    }

    public function test_whitebox_store_borrowing_request_validates_quantity_and_dates(): void
    {
        $request = new StoreBorrowingRequest();
        $rules = $request->rules();
        $messages = $request->messages();

        // Quantity < 1
        $validator = Validator::make([
            'item_id' => $this->item->id,
            'quantity' => 0,
            'borrow_date' => '2026-09-01',
            'due_date' => '2026-09-05',
        ], $rules, $messages);

        $this->assertTrue($validator->fails());
        $this->assertContains('Jumlah minimal 1', $validator->errors()->get('quantity'));

        // Non-integer quantity
        $validatorQty = Validator::make([
            'item_id' => $this->item->id,
            'quantity' => 'dua',
            'borrow_date' => '2026-09-01',
            'due_date' => '2026-09-05',
        ], $rules, $messages);

        $this->assertTrue($validatorQty->fails());
        $this->assertContains('Jumlah harus berupa angka', $validatorQty->errors()->get('quantity'));

        // Due date before borrow date
        $validatorDates = Validator::make([
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => '2026-09-10',
            'due_date' => '2026-09-05',
        ], $rules, $messages);

        $this->assertTrue($validatorDates->fails());
        $this->assertContains('Tanggal kembali harus setelah atau sama dengan tanggal pinjam', $validatorDates->errors()->get('due_date'));
    }

    public function test_whitebox_update_borrowing_request_rules_and_messages(): void
    {
        $request = new UpdateBorrowingRequest();
        $rules = $request->rules();
        $messages = $request->messages();

        $this->assertTrue($request->authorize());

        // Validate status rule
        $validator = Validator::make([
            'status' => 'status_tidak_dikenal',
        ], $rules, $messages);

        $this->assertTrue($validator->fails());
        $this->assertContains('Status tidak valid', $validator->errors()->get('status'));

        // Validate valid partial update (notes only)
        $validatorValid = Validator::make([
            'notes' => 'Catatan perpanjangan peminjaman',
        ], $rules, $messages);

        $this->assertFalse($validatorValid->fails());
    }

    /* =========================================================================
     * ⚫ BLACK-BOX TESTING (HTTP Endpoints & Validation Behavior)
     * ========================================================================= */

    public function test_blackbox_store_borrowing_fails_with_validation_errors_when_empty(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/borrowings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['item_id', 'quantity', 'borrow_date', 'due_date'])
            ->assertJsonFragment(['item_id' => ['Barang wajib dipilih']])
            ->assertJsonFragment(['quantity' => ['Jumlah wajib diisi']])
            ->assertJsonFragment(['borrow_date' => ['Tanggal pinjam wajib diisi']])
            ->assertJsonFragment(['due_date' => ['Tanggal kembali wajib diisi']]);
    }

    public function test_blackbox_store_borrowing_fails_when_item_does_not_exist(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/borrowings', [
            'item_id' => 99999,
            'quantity' => 1,
            'borrow_date' => '2026-09-01',
            'due_date' => '2026-09-05',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['item_id'])
            ->assertJsonFragment(['item_id' => ['Barang tidak ditemukan']]);
    }

    public function test_blackbox_store_borrowing_succeeds_with_valid_form_request_data(): void
    {
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/borrowings', [
            'item_id' => $this->item->id,
            'quantity' => 2,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
            'notes' => 'Peminjaman inventaris kantor untuk sesi dokumentasi',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Borrowing created successfully',
            ])
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'borrow_code',
                    'quantity',
                    'status',
                    'notes',
                ],
            ]);
    }

    public function test_blackbox_update_borrowing_validates_due_date(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'borrow_date' => '2026-09-01',
            'due_date' => '2026-09-05',
            'status' => 'dipinjam',
        ]);

        // Attempt to update due_date earlier than borrow_date
        $response = $this->putJson("/api/borrowings/{$borrowing->id}", [
            'due_date' => '2026-08-25',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    }

    public function test_blackbox_update_borrowing_succeeds_with_valid_data(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'borrow_date' => '2026-09-01',
            'due_date' => '2026-09-05',
            'status' => 'dipinjam',
            'notes' => 'Catatan awal',
        ]);

        $response = $this->putJson("/api/borrowings/{$borrowing->id}", [
            'notes' => 'Catatan telah diperbarui oleh admin',
            'due_date' => '2026-09-15',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Borrowing updated successfully',
                'data' => [
                    'id' => $borrowing->id,
                    'notes' => 'Catatan telah diperbarui oleh admin',
                ],
            ]);
    }

    public function test_blackbox_update_borrowing_fails_if_already_returned(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'status' => 'dikembalikan',
            'return_date' => '2026-09-02',
        ]);

        $response = $this->putJson("/api/borrowings/{$borrowing->id}", [
            'notes' => 'Mencoba update yang sudah dikembalikan',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot update returned borrowing',
            ]);
    }

    /* =========================================================================
     * 🔘 GREY-BOX TESTING (Database Integrity & Side-Effects)
     * ========================================================================= */

    public function test_greybox_validation_failure_prevents_database_mutation_and_stock_deduction(): void
    {
        Sanctum::actingAs($this->staff);

        $initialStock = $this->item->fresh()->available_stock;
        $initialBorrowingsCount = Borrowing::count();

        // Invalid request (missing quantity and invalid item_id)
        $response = $this->postJson('/api/borrowings', [
            'item_id' => 999999,
            'borrow_date' => '2026-09-01',
            'due_date' => '2026-09-05',
        ]);

        $response->assertStatus(422);

        // Verify no borrowing record created
        $this->assertEquals($initialBorrowingsCount, Borrowing::count());

        // Verify available stock unchanged
        $this->assertEquals($initialStock, $this->item->fresh()->available_stock);
    }

    public function test_greybox_update_borrowing_persists_only_allowed_attributes(): void
    {
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'borrow_date' => '2026-09-01',
            'due_date' => '2026-09-05',
            'status' => 'dipinjam',
            'notes' => 'Catatan awal',
        ]);

        $originalCode = $borrowing->borrow_code;
        $originalUserId = $borrowing->user_id;

        $response = $this->putJson("/api/borrowings/{$borrowing->id}", [
            'notes' => 'Catatan revisi',
            'due_date' => '2026-09-12',
        ]);

        $response->assertStatus(200);

        $borrowing->refresh();

        $this->assertEquals('Catatan revisi', $borrowing->notes);
        $this->assertEquals('2026-09-12', $borrowing->due_date->format('Y-m-d'));
        // Core identifiers remain unchanged
        $this->assertEquals($originalCode, $borrowing->borrow_code);
        $this->assertEquals($originalUserId, $borrowing->user_id);
    }
}
