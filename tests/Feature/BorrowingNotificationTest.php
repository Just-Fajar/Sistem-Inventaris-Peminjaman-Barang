<?php

namespace Tests\Feature;

use App\Enums\BorrowingStatus;
use App\Enums\ItemCondition;
use App\Jobs\SendBorrowingNotification;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Notifications\BorrowingApprovedNotification;
use App\Notifications\BorrowingRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BorrowingNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;
    protected User $admin;
    protected Category $category;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->category = Category::factory()->create(['name' => 'Elektronik']);
        $this->item = Item::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Monitor Dell UltraSharp',
            'code' => 'ITM-NOTIF-001',
            'stock' => 10,
            'available_stock' => 10,
            'condition' => ItemCondition::Baik,
        ]);
    }

    /* =========================================================================
     * ⚪ WHITE-BOX TESTING (Notification Classes & Queue Job Handling)
     * ========================================================================= */

    public function test_whitebox_job_handles_approved_notification(): void
    {
        Notification::fake();

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Dipinjam,
            'approved_by' => $this->admin->id,
        ]);

        $job = new SendBorrowingNotification($borrowing, 'approved');
        $job->handle();

        Notification::assertSentTo($this->staff, BorrowingApprovedNotification::class, function ($notification) use ($borrowing) {
            $mailData = $notification->toMail($this->staff);
            $arrayData = $notification->toArray($this->staff);

            $this->assertStringContainsString($borrowing->code, $mailData->subject);
            $this->assertEquals('borrowing_approved', $arrayData['type']);
            $this->assertEquals($borrowing->id, $arrayData['borrowing_id']);

            return true;
        });
    }

    public function test_whitebox_job_handles_rejected_notification_with_note(): void
    {
        Notification::fake();

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'status' => BorrowingStatus::Rejected,
            'rejection_note' => 'Stok dialokasikan untuk kegiatan training direksi.',
        ]);

        $job = new SendBorrowingNotification($borrowing, 'rejected');
        $job->handle();

        Notification::assertSentTo($this->staff, BorrowingRejectedNotification::class, function ($notification) use ($borrowing) {
            $mailData = $notification->toMail($this->staff);
            $arrayData = $notification->toArray($this->staff);

            $this->assertStringContainsString($borrowing->code, $mailData->subject);
            $this->assertStringContainsString('Alasan Penolakan', implode(' ', $mailData->introLines));
            $this->assertStringContainsString('Stok dialokasikan untuk kegiatan training direksi.', implode(' ', $mailData->introLines));

            $this->assertEquals('borrowing_rejected', $arrayData['type']);
            $this->assertEquals($borrowing->id, $arrayData['borrowing_id']);
            $this->assertEquals('Stok dialokasikan untuk kegiatan training direksi.', $arrayData['rejection_note']);

            return true;
        });
    }

    public function test_whitebox_job_handles_rejected_notification_without_note(): void
    {
        Notification::fake();

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Rejected,
            'rejection_note' => null,
        ]);

        $job = new SendBorrowingNotification($borrowing, 'rejected');
        $job->handle();

        Notification::assertSentTo($this->staff, BorrowingRejectedNotification::class, function ($notification) {
            $arrayData = $notification->toArray($this->staff);
            $this->assertEquals('borrowing_rejected', $arrayData['type']);
            $this->assertNull($arrayData['rejection_note']);

            return true;
        });
    }

    /* =========================================================================
     * ⚫ BLACK-BOX TESTING (API Endpoints Dispatching Queue Jobs)
     * ========================================================================= */

    public function test_blackbox_approve_endpoint_dispatches_notification_job(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'status' => BorrowingStatus::Pending,
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/approve");

        $response->assertStatus(200);

        Queue::assertPushed(SendBorrowingNotification::class, function ($job) use ($borrowing) {
            return $job->notificationType === 'approved'
                && $job->borrowing->id === $borrowing->id;
        });
    }

    public function test_blackbox_reject_endpoint_dispatches_notification_job(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Pending,
        ]);

        $response = $this->postJson("/api/borrowings/{$borrowing->id}/reject", [
            'rejection_note' => 'Permintaan melebihi batas kuota peminjaman divisi.',
        ]);

        $response->assertStatus(200);

        Queue::assertPushed(SendBorrowingNotification::class, function ($job) use ($borrowing) {
            return $job->notificationType === 'rejected'
                && $job->borrowing->id === $borrowing->id
                && $job->borrowing->rejection_note === 'Permintaan melebihi batas kuota peminjaman divisi.';
        });
    }

    /* =========================================================================
     * 🔘 GREY-BOX TESTING (Database Notifications Table Persistence)
     * ========================================================================= */

    public function test_greybox_rejected_notification_persists_in_database_table(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'status' => BorrowingStatus::Rejected,
            'rejection_note' => 'Database persistence verification note',
        ]);

        // Send notification directly via model
        $this->staff->notify(new BorrowingRejectedNotification($borrowing));

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => get_class($this->staff),
            'notifiable_id' => $this->staff->id,
            'type' => BorrowingRejectedNotification::class,
        ]);

        $dbNotification = $this->staff->notifications()->first();
        $this->assertNotNull($dbNotification);
        $this->assertEquals('borrowing_rejected', $dbNotification->data['type']);
        $this->assertEquals('Database persistence verification note', $dbNotification->data['rejection_note']);
    }

    public function test_greybox_approved_notification_persists_in_database_table(): void
    {
        $borrowing = Borrowing::factory()->create([
            'user_id' => $this->staff->id,
            'item_id' => $this->item->id,
            'quantity' => 3,
            'status' => BorrowingStatus::Dipinjam,
            'approved_by' => $this->admin->id,
        ]);

        $this->staff->notify(new BorrowingApprovedNotification($borrowing));

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => get_class($this->staff),
            'notifiable_id' => $this->staff->id,
            'type' => BorrowingApprovedNotification::class,
        ]);

        $dbNotification = $this->staff->notifications()->first();
        $this->assertNotNull($dbNotification);
        $this->assertEquals('borrowing_approved', $dbNotification->data['type']);
        $this->assertEquals($borrowing->id, $dbNotification->data['borrowing_id']);
    }
}
