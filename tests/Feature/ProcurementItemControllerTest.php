<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Buyer;
use App\Models\Department;
use App\Models\Status;
use App\Models\ProcurementItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementItemControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $buyer;
    protected Buyer $buyerRecord;
    protected Department $department;
    protected Status $status;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->department = Department::create([
            'name' => 'IT Department',
            'description' => 'Information Technology',
        ]);
        
        $this->status = Status::create([
            'name' => 'In Progress',
            'bg_color' => '#ffffff',
            'text_color' => '#000000',
        ]);
        
        $this->admin = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        
        $this->buyer = User::create([
            'name' => 'Buyer User',
            'username' => 'buyer',
            'email' => 'buyer@example.com',
            'password' => bcrypt('password'),
            'role' => 'buyer',
        ]);

        $this->buyerRecord = Buyer::create([
            'name' => 'Buyer User',
            'code' => 'BYR',
            'user_id' => $this->buyer->id,
        ]);
    }

    public function test_admin_can_list_all_procurement_items(): void
    {
        ProcurementItem::create([
            'no_pr' => 'PR-001',
            'nama_barang' => 'Test Item 1',
            'department_id' => $this->department->id,
            'created_by' => $this->admin->id,
        ]);
        
        ProcurementItem::create([
            'no_pr' => 'PR-002',
            'nama_barang' => 'Test Item 2',
            'department_id' => $this->department->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/procurement-items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'no_pr', 'nama_barang'],
                ],
                'meta' => ['current_page', 'last_page', 'total'],
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_procurement_item(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/procurement-items', [
                'no_pr' => 'PR-NEW-001',
                'nama_barang' => 'New Test Item',
                'department_id' => $this->department->id,
                'qty' => 10,
                'um' => 'pcs',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.no_pr', 'PR-NEW-001')
            ->assertJsonPath('data.nama_barang', 'New Test Item');

        $this->assertDatabaseHas('procurement_items', [
            'no_pr' => 'PR-NEW-001',
            'nama_barang' => 'New Test Item',
        ]);
    }

    public function test_admin_cannot_create_duplicate_no_pr(): void
    {
        ProcurementItem::create([
            'no_pr' => 'PR-DUPLICATE',
            'nama_barang' => 'Existing Item',
            'department_id' => $this->department->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/procurement-items', [
                'no_pr' => 'PR-DUPLICATE',
                'nama_barang' => 'New Item',
                'department_id' => $this->department->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['no_pr']);
    }

    public function test_admin_can_update_procurement_item(): void
    {
        $item = ProcurementItem::create([
            'no_pr' => 'PR-UPDATE',
            'nama_barang' => 'Original Name',
            'department_id' => $this->department->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/procurement-items/{$item->id}", [
                'nama_barang' => 'Updated Name',
                'status_id' => $this->status->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.nama_barang', 'Updated Name');

        $this->assertDatabaseHas('procurement_items', [
            'id' => $item->id,
            'nama_barang' => 'Updated Name',
        ]);
    }

    public function test_admin_can_delete_procurement_item(): void
    {
        $item = ProcurementItem::create([
            'no_pr' => 'PR-DELETE',
            'nama_barang' => 'To Delete',
            'department_id' => $this->department->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/procurement-items/{$item->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('procurement_items', [
            'id' => $item->id,
        ]);
    }

    public function test_buyer_can_only_update_allowed_fields(): void
    {
        $item = ProcurementItem::create([
            'no_pr' => 'PR-BUYER',
            'nama_barang' => 'Original Name',
            'department_id' => $this->department->id,
            'buyer_id' => $this->buyerRecord->id,
            'created_by' => $this->admin->id,
        ]);

        // Buyer should be able to update allowed buyer fields (status, PO info)
        $response = $this->actingAs($this->buyer, 'sanctum')
            ->putJson("/api/procurement-items/{$item->id}", [
                'status_id' => $this->status->id,
                'no_po' => 'PO-123',
                'nama_vendor' => 'Vendor A',
                // Should be ignored for buyers
                'keterangan' => 'Test Note',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('procurement_items', [
            'id' => $item->id,
            'status_id' => $this->status->id,
            'no_po' => 'PO-123',
            'nama_vendor' => 'Vendor A',
        ]);

        // Fields not allowed for buyer should remain unchanged
        $this->assertDatabaseHas('procurement_items', [
            'id' => $item->id,
            'keterangan' => null,
        ]);
    }

    public function test_search_filter_works(): void
    {
        ProcurementItem::create([
            'no_pr' => 'PR-SEARCH-001',
            'nama_barang' => 'Laptop Computer',
            'department_id' => $this->department->id,
            'created_by' => $this->admin->id,
        ]);
        
        ProcurementItem::create([
            'no_pr' => 'PR-SEARCH-002',
            'nama_barang' => 'Desktop Monitor',
            'department_id' => $this->department->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/procurement-items?search=Laptop');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_barang', 'Laptop Computer');
    }

    public function test_status_filter_works(): void
    {
        ProcurementItem::create([
            'no_pr' => 'PR-STATUS-001',
            'nama_barang' => 'Item With Status',
            'department_id' => $this->department->id,
            'status_id' => $this->status->id,
            'created_by' => $this->admin->id,
        ]);
        
        ProcurementItem::create([
            'no_pr' => 'PR-STATUS-002',
            'nama_barang' => 'Item Without Status',
            'department_id' => $this->department->id,
            'status_id' => null,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/procurement-items?status_id={$this->status->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.no_pr', 'PR-STATUS-001');
    }

    public function test_unauthenticated_user_cannot_access_items(): void
    {
        $response = $this->getJson('/api/procurement-items');

        $response->assertStatus(401);
    }
}
