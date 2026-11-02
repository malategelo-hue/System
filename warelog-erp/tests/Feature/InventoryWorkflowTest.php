<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Delivery;
use App\Models\PaymentTransaction;
use App\Models\StockMovement;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('local');
    }

    public function test_admin_stock_in_updates_quantity_and_records_movement(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $item = $this->createItem();

        $response = $this->actingAs($staff)->post(route('inventory.stockin.store'), [
            'item_id' => $item->id,
            'qty' => 4,
            'supplier' => 'Test supplier',
        ]);

        $response->assertRedirect(route('inventory.stockin'))->assertSessionHas('labels_for');
        $this->assertDatabaseHas('items', ['id' => $item->id, 'qty' => 14]);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'type' => 'in',
            'qty' => 4,
            'supplier' => 'Test supplier',
            'user_id' => $staff->id,
        ]);
    }

    public function test_admin_cannot_stock_out_more_than_available(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $item = $this->createItem();

        $response = $this->actingAs($staff)->post(route('inventory.stockout.store'), [
            'item_id' => $item->id,
            'qty' => 11,
            'issued_to' => 'Test department',
        ]);

        $response->assertSessionHasErrors('qty');
        $this->assertDatabaseHas('items', ['id' => $item->id, 'qty' => 10]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_admin_session_is_redirected_to_staff_login_for_user_portal_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('logistics'))->assertRedirect(route('login'));
    }

    public function test_staff_cannot_open_admin_inventory_pages(): void
    {
        $staff = User::factory()->create(['role' => 'user']);

        $this->actingAs($staff)->get(route('inventory.stockin'))->assertRedirect(route('login'));
        $this->actingAs($staff)->get(route('inventory.stockout'))->assertRedirect(route('login'));
        $this->actingAs($staff)->get(route('reports'))->assertRedirect(route('login'));
    }

    public function test_admin_dispatch_reduces_stock_and_inventory_qr_confirmation_marks_delivery(): void
    {
        $staff = User::factory()->create(['role' => 'user']);
        $item = $this->createItem();

        $response = $this->actingAs($this->adminUser())->post(route('admin.deliveries.store'), [
            'driver_id' => $staff->id,
            'item_id' => $item->id,
            'qty' => 3,
            'destination' => 'Test destination',
            'driver' => 'Test driver',
            'vehicle' => 'Van 1',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'payment_method' => 'cod',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['id' => $item->id, 'qty' => 7]);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'type' => 'out',
            'qty' => 3,
            'issued_to' => 'Test destination',
        ]);

        $delivery = Delivery::firstOrFail();
        $this->assertSame($staff->id, (int) $delivery->assigned_to);
        $this->actingAs($staff)->get(route('logistics'))
            ->assertOk()
            ->assertSee('My Deliveries')
            ->assertSee('Scan item QR')
            ->assertSee('data-item-code="'.$item->code.'"', false)
            ->assertDontSee('api.qrserver.com/v1/create-qr-code/', false);

        $this->actingAs($staff)->post(route('logistics.verify'), $this->proofPayload($delivery, $item->code))->assertRedirect(route('logistics'));

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'amount' => 4.5,
        ]);
        $delivery->refresh();
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNotNull($delivery->paid_at);
        $this->assertNotNull($delivery->proof_photo_path);
        Storage::disk('local')->assertExists($delivery->proof_photo_path);
        $this->assertEquals(14.5995, $delivery->proof_lat);
        $this->assertSame($item->code, $delivery->scanned_code);
        $this->assertDatabaseHas('payment_transactions', [
            'delivery_id' => $delivery->id,
            'payment_method' => 'cod',
            'amount' => 4.5,
            'currency' => 'PHP',
            'status' => 'pending_remittance',
        ]);
    }

    public function test_admin_payment_ledger_tracks_cod_until_it_is_received_by_admin(): void
    {
        $staff = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->createItem();

        $this->actingAs($this->adminUser())->post(route('admin.deliveries.store'), [
            'driver_id' => $staff->id,
            'item_id' => $item->id,
            'qty' => 2,
            'destination' => 'Ledger destination',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'payment_method' => 'cod',
        ])->assertRedirect();

        $delivery = Delivery::firstOrFail();
        $this->actingAs($staff)->post(route('logistics.verify'), $this->proofPayload($delivery, $item->code))->assertRedirect(route('logistics'));
        $payment = PaymentTransaction::where('delivery_id', $delivery->id)->firstOrFail();

        $this->get(route('payments.index'))->assertRedirect(route('login'));
        $this->actingAs($admin)->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Admin Cash Wallet')
            ->assertSee('Cash Wallet balance')
            ->assertSee('COD cash with staff')
            ->assertSee('₱3.00');

        $this->post(route('payments.receive-cod', $payment))
            ->assertRedirect(route('payments.index'));

        $this->assertDatabaseHas('payment_transactions', [
            'id' => $payment->id,
            'status' => 'received',
            'remitted_by' => $admin->id,
        ]);
        $this->assertNotNull($payment->fresh()->remitted_at);
        $this->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Cash Wallet balance')
            ->assertSee('Received by '.$admin->name);
    }

    public function test_paymongo_checkout_confirms_payment_before_completing_delivery(): void
    {
        $staff = User::factory()->create(['role' => 'user']);
        $item = $this->createItem();
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_delivery_123',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_test_delivery_123'],
                ],
            ]),
            'api.paymongo.com/v1/checkout_sessions/cs_test_delivery_123' => Http::response([
                'data' => [
                    'id' => 'cs_test_delivery_123',
                    'attributes' => ['status' => 'paid'],
                ],
            ]),
        ]);
        config(['services.paymongo.secret_key' => 'sk_test_configured']);

        $this->actingAs($this->adminUser())->post(route('admin.deliveries.store'), [
            'driver_id' => $staff->id,
            'item_id' => $item->id,
            'qty' => 3,
            'destination' => 'Test destination',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'payment_method' => 'paymongo',
        ])->assertRedirect();

        $delivery = Delivery::firstOrFail();
        $this->assertSame('4.50', $delivery->amount);
        $this->assertSame('pending', $delivery->payment_status);

        $this->actingAs($staff)->post(route('logistics.verify'), $this->proofPayload($delivery, $item->code))->assertRedirect('https://checkout.paymongo.com/cs_test_delivery_123');

        $delivery->refresh();
        $this->assertSame('in_transit', $delivery->status);
        $this->assertNotNull($delivery->scanned_at);

        $this->get(route('logistics.paymongo.return', $delivery))
            ->assertRedirect(route('logistics'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => 'delivered',
            'payment_method' => 'paymongo',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'delivery_id' => $delivery->id,
            'payment_method' => 'paymongo',
            'amount' => 4.5,
            'currency' => 'PHP',
            'status' => 'received',
            'payment_reference' => 'cs_test_delivery_123',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.paymongo.com/v1/checkout_sessions'
            && $request['data']['attributes']['line_items'][0]['amount'] === 450);
    }

    public function test_online_delivery_is_not_marked_delivered_before_paymongo_confirms_payment(): void
    {
        $staff = User::factory()->create(['role' => 'user']);
        $item = $this->createItem();
        config(['services.paymongo.secret_key' => 'sk_test_configured']);
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_pending_123',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_test_pending_123'],
                ],
            ]),
            'api.paymongo.com/v1/checkout_sessions/cs_test_pending_123' => Http::response([
                'data' => [
                    'id' => 'cs_test_pending_123',
                    'attributes' => ['status' => 'active'],
                ],
            ]),
        ]);

        $this->actingAs($this->adminUser())->post(route('admin.deliveries.store'), [
            'driver_id' => $staff->id,
            'item_id' => $item->id,
            'qty' => 1,
            'destination' => 'Test destination',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'payment_method' => 'paymongo',
        ])->assertRedirect(route('logistics'));
        $delivery = Delivery::firstOrFail();

        $this->actingAs($staff)->post(route('logistics.verify'), $this->proofPayload($delivery, $item->code))->assertRedirect('https://checkout.paymongo.com/cs_test_pending_123');

        $this->get(route('logistics.paymongo.return', $delivery))
            ->assertRedirect(route('logistics'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => 'in_transit',
            'payment_status' => 'pending',
        ]);
    }

    public function test_paymongo_webhook_requires_a_valid_signature(): void
    {
        config([
            'services.paymongo.webhook_secret' => 'whsk_test_secret',
            'services.paymongo.webhook_tolerance' => 300,
        ]);
        $timestamp = (string) time();
        $payload = json_encode([
            'data' => [
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'data' => ['id' => 'cs_test_missing'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsk_test_secret');

        $this->call('POST', route('webhooks.paymongo'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature}",
        ], $payload)->assertNoContent();

        $this->call('POST', route('webhooks.paymongo'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te=invalid",
        ], $payload)->assertForbidden();
    }

    public function test_valid_paymongo_webhook_records_payment_and_completes_scanned_delivery(): void
    {
        $delivery = $this->createDelivery();
        $delivery->update([
            'payment_method' => 'paymongo',
            'payment_status' => 'pending',
            'amount' => 1.50,
            'paymongo_checkout_session_id' => 'cs_test_webhook_123',
            'scanned_at' => now(),
        ]);
        config([
            'services.paymongo.secret_key' => 'sk_test_configured',
            'services.paymongo.webhook_secret' => 'whsk_test_secret',
            'services.paymongo.webhook_tolerance' => 300,
        ]);
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/cs_test_webhook_123' => Http::response([
                'data' => [
                    'id' => 'cs_test_webhook_123',
                    'attributes' => ['status' => 'paid'],
                ],
            ]),
        ]);
        $timestamp = (string) time();
        $payload = json_encode([
            'data' => [
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'data' => ['id' => 'cs_test_webhook_123'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsk_test_secret');

        $this->call('POST', route('webhooks.paymongo'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature}",
        ], $payload)->assertNoContent();

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => 'delivered',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'delivery_id' => $delivery->id,
            'payment_method' => 'paymongo',
            'status' => 'received',
            'payment_reference' => 'cs_test_webhook_123',
        ]);
        $this->assertNotNull($delivery->fresh()->paid_at);
    }

    public function test_delivery_confirmation_rejects_a_scanned_code_for_another_item(): void
    {
        $staff = User::factory()->create(['role' => 'user']);
        $delivery = $this->createDelivery();

        $this->actingAs($staff)->post(route('logistics.verify'), $this->proofPayload($delivery, 'OTHER-ITEM'))->assertSessionHasErrors('delivery_id');

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => 'in_transit',
        ]);
    }

    public function test_stock_movement_uses_the_current_transaction_timestamp(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $item = $this->createItem();
        $this->travelTo(Carbon::parse('2026-10-20 13:45:00', 'UTC'));

        $this->actingAs($staff)->post(route('inventory.stockin.store'), [
            'item_id' => $item->id,
            'qty' => 2,
        ])->assertRedirect(route('inventory.stockin'));

        $this->assertSame(
            '2026-10-20 13:45:00',
            StockMovement::firstOrFail()->created_at->utc()->format('Y-m-d H:i:s'),
        );

        $this->travelBack();
    }

    public function test_stock_movement_history_is_paginated_and_keeps_older_dates_accessible(): void
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $item = $this->createItem();
        foreach (range(1, 21) as $index) {
            $movement = StockMovement::create([
                'type' => 'in',
                'item_id' => $item->id,
                'item_name' => $item->name,
                'qty' => 1,
            ]);
            $movement->forceFill([
                'created_at' => Carbon::parse('2020-01-01 00:00:00', 'UTC')->addDays($index),
            ])->save();
        }

        $this->actingAs($staff)->get(route('inventory.stockin'))
            ->assertOk()
            ->assertSee('2020');
        $this->get(route('inventory.stockin', ['page' => 2]))
            ->assertOk()
            ->assertSee('01/02/2020');
    }

    public function test_item_code_check_warns_about_similar_codes_and_identifies_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createItem();

        $this->actingAs($admin)->get(route('inventory.code-check', ['code' => 'TEST-002']))
            ->assertOk()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('similar.0', 'TEST-001');

        $this->get(route('inventory.code-check', ['code' => 'test-001']))
            ->assertOk()
            ->assertJsonPath('duplicate', true);
    }

    public function test_duplicate_item_codes_are_rejected_case_insensitively(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->createItem();

        $this->actingAs($admin)->post(route('inventory.add'), [
            'code' => 'test-001',
            'name' => 'Duplicate item',
            'category' => 'Supplies',
            'qty' => 0,
            'unit' => 'pcs',
            'price' => 1,
            'location' => 'Warehouse A',
        ])->assertSessionHasErrors('code');

        $this->assertDatabaseCount('items', 1);
    }

    public function test_item_code_is_generated_when_auto_code_setting_is_enabled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Item::create([
            'code' => 'ITM-00004',
            'name' => 'Previously generated item',
            'category' => 'Supplies',
            'qty' => 0,
            'unit' => 'pcs',
            'price' => 1,
            'location' => 'Warehouse A',
        ]);

        $this->actingAs($admin)->post(route('inventory.add'), [
            'name' => 'Generated item',
            'category' => 'Supplies',
            'qty' => 0,
            'unit' => 'pcs',
            'price' => 1,
            'location' => 'Warehouse A',
        ])->assertRedirect(route('inventory'));

        $this->assertDatabaseHas('items', ['code' => 'ITM-00005']);
    }

    public function test_dashboard_chart_tracks_stock_movements_in_the_current_calendar_month(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->createItem();
        $this->travelTo(Carbon::parse('2026-10-21 08:00:00', 'UTC'));
        StockMovement::create([
            'type' => 'in',
            'item_id' => $item->id,
            'item_name' => $item->name,
            'qty' => 4,
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('const months = ["May","Jun","Jul","Aug","Sep","Oct"];', false)
            ->assertSee('const stockInData = [0,0,0,0,0,4];', false);

        $this->travelBack();
    }

    public function test_admin_dashboard_stock_value_endpoint_returns_updated_inventory_value(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->createItem();

        $this->actingAs($admin)->get(route('dashboard.total-stock-value'))
            ->assertOk()
            ->assertJsonPath('display', '₱15.00');

        $item->update(['qty' => 7]);

        $this->get(route('dashboard.total-stock-value'))
            ->assertOk()
            ->assertJsonPath('display', '₱10.50');

        SystemSetting::saveValues(['currency' => 'USD', 'show_decimals' => '1']);
        $item->update(['qty' => 6]);

        $this->get(route('dashboard.total-stock-value'))
            ->assertOk()
            ->assertJsonPath('display', '$9.00');
    }

    public function test_delivery_summary_reports_pending_completed_and_on_time_deliveries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->createItem();
        $scheduledAt = Carbon::parse('2026-10-21 12:00:00', 'UTC');

        Delivery::create([
            'reference' => 'DISP-PENDING',
            'item_id' => $item->id,
            'qty' => 1,
            'destination' => 'Pending destination',
            'scheduled_at' => $scheduledAt,
            'status' => 'in_transit',
        ]);
        Delivery::create([
            'reference' => 'DISP-ONTIME',
            'item_id' => $item->id,
            'qty' => 1,
            'destination' => 'On-time destination',
            'scheduled_at' => $scheduledAt,
            'delivered_at' => $scheduledAt->copy()->subMinute(),
            'status' => 'delivered',
        ]);
        Delivery::create([
            'reference' => 'DISP-LATE',
            'item_id' => $item->id,
            'qty' => 1,
            'destination' => 'Late destination',
            'scheduled_at' => $scheduledAt,
            'delivered_at' => $scheduledAt->copy()->addMinute(),
            'status' => 'delivered',
        ]);

        $this->actingAs($admin)->get(route('reports'))
            ->assertOk()
            ->assertSee('Pending Deliveries')
            ->assertSee('Completed Deliveries')
            ->assertSee('On-Time Rate')
            ->assertSee('Late Deliveries')
            ->assertSee('50%')
            ->assertSee('DISP-PENDING')
            ->assertSee('Delivered on time')
            ->assertSee('Delivered late');
    }

    public function test_settings_are_saved_persistently_and_apply_to_inventory_display(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('settings.save'), [
            'system_name' => 'North Warehouse',
            'company_name' => 'North Supply',
            'warehouse_name' => 'North Depot',
            'address' => 'Test address',
            'contact_no' => '123456',
            'low_stock_threshold' => 5,
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'timezone' => 'UTC',
            'session_timeout' => 30,
            'auto_logout' => '1',
            'auto_codes' => '1',
            'backup_daily' => '1',
        ])->assertRedirect(route('settings'));

        $this->assertSame('North Warehouse', SystemSetting::values()['system_name']);
        $this->assertSame('0', SystemSetting::values()['dark_mode']);
        $this->assertSame('1', SystemSetting::values()['backup_daily']);
        $this->get(route('settings'))->assertOk()->assertSee('North Warehouse');
        $this->assertSame(30, config('session.lifetime'));
        $this->get(route('inventory'))->assertOk()->assertSee('Unit Price ($)');
        $this->get(route('reports'))->assertOk();
    }

    public function test_password_settings_form_changes_password_after_current_password_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('settings.password'), [
            'current_password' => 'password',
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ])->assertRedirect(route('settings'));

        $this->assertTrue(Hash::check('new-password', $admin->fresh()->password));
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function proofPayload(Delivery $delivery, string $code): array
    {
        return [
            'delivery_id' => $delivery->id,
            'scanned_item_code' => $code,
            'proof_photo' => UploadedFile::fake()->image('proof.jpg', 640, 480),
            'latitude' => 14.5995,
            'longitude' => 120.9842,
            'accuracy' => 12,
        ];
    }

    public function test_delivery_proof_requires_a_photo_and_gps_location(): void
    {
        $staff = User::factory()->create(['role' => 'user']);
        $delivery = $this->createDelivery();
        $code = $delivery->item->code;

        $payload = $this->proofPayload($delivery, $code);
        unset($payload['proof_photo']);
        $this->actingAs($staff)->post(route('logistics.verify'), $payload)->assertSessionHasErrors('proof_photo');

        $payload = $this->proofPayload($delivery, $code);
        unset($payload['latitude'], $payload['longitude']);
        $this->post(route('logistics.verify'), $payload)->assertSessionHasErrors('latitude');

        $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'in_transit']);

        SystemSetting::saveValues(['require_gps_proof' => '0']);
        $payload = $this->proofPayload($delivery, $code);
        unset($payload['latitude'], $payload['longitude']);
        $this->post(route('logistics.verify'), $payload)->assertRedirect(route('logistics'));
        $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'delivered']);
    }

    public function test_admin_assigns_a_driver_and_only_that_driver_sees_the_delivery(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'user', 'name' => 'Driver One']);
        $other = User::factory()->create(['role' => 'user']);
        $item = $this->createItem();

        $this->actingAs($admin)->post(route('admin.deliveries.store'), [
            'item_id' => $item->id,
            'qty' => 2,
            'destination' => 'Assigned destination',
            'driver_id' => $driver->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'payment_method' => 'cod',
        ])->assertRedirect();

        $delivery = Delivery::firstOrFail();
        $this->assertSame($driver->id, (int) $delivery->assigned_to);
        $this->assertSame('Driver One', $delivery->driver);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'qty' => 8]);
        $this->assertDatabaseHas('tracking_events', ['delivery_id' => $delivery->id, 'type' => 'dispatched']);

        $this->actingAs($driver)->get(route('logistics'))
            ->assertOk()
            ->assertSee($delivery->reference)
            ->assertSee('delivery na naka-assign sa iyo');
        $this->getJson(route('logistics.notifications'))
            ->assertOk()
            ->assertJsonCount(1, 'deliveries')
            ->assertJsonPath('deliveries.0.reference', $delivery->reference);
        $this->actingAs($driver)->get(route('logistics.show', $delivery))->assertOk();
        $this->actingAs($other)->get(route('logistics.show', $delivery))->assertForbidden();
        $this->getJson(route('logistics.notifications'))->assertJsonCount(0, 'deliveries');
    }

    public function test_driver_trip_pings_feed_the_admin_live_tracking_snapshot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'user']);
        $delivery = $this->createDelivery();
        $delivery->update(['assigned_to' => $driver->id, 'driver' => $driver->name]);

        $this->actingAs($driver)->post(route('logistics.start', $delivery))->assertRedirect(route('logistics.show', $delivery));
        $this->postJson(route('logistics.ping', $delivery), ['latitude' => 14.6, 'longitude' => 121.0, 'accuracy' => 8])
            ->assertOk()->assertJsonPath('stored', true);

        $this->assertDatabaseHas('delivery_locations', ['delivery_id' => $delivery->id]);

        $this->actingAs($admin)->getJson(route('tracking.live'))
            ->assertOk()
            ->assertJsonPath('counts.on_the_way', 1)
            ->assertJsonPath('deliveries.0.reference', $delivery->reference)
            ->assertJsonPath('deliveries.0.stage', 'on_the_way')
            ->assertJsonPath('deliveries.0.lat', 14.6);

    }

    public function test_item_trace_and_qr_labels_use_the_item_code(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->createItem();

        $this->actingAs($admin)->getJson(route('tracking.item', ['code' => $item->code.' | Test item | Warehouse A']))
            ->assertOk()
            ->assertJsonPath('item.code', $item->code)
            ->assertJsonPath('item.on_hand', 10);
        $this->getJson(route('tracking.item', ['code' => 'NOPE']))->assertNotFound();

        $this->get(route('inventory.labels', ['item' => $item->id, 'copies' => 3]))
            ->assertOk()
            ->assertSee('data-qr="'.$item->code.'"', false);
        $this->get(route('inventory'))->assertOk()->assertDontSee('api.qrserver.com', false);
    }

    public function test_admin_receives_stock_and_is_offered_qr_labels(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = $this->createItem();

        $this->actingAs($admin)->post(route('inventory.receive', $item), ['qty' => 5, 'supplier' => 'ACME'])
            ->assertRedirect(route('inventory'))
            ->assertSessionHas('labels_for');

        $this->assertDatabaseHas('items', ['id' => $item->id, 'qty' => 15]);
        $this->assertDatabaseHas('tracking_events', ['item_id' => $item->id, 'type' => 'stock_in']);
    }

    public function test_admin_can_approve_driver_signups(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->post(route('register'), [
            'name' => 'New Driver',
            'email' => 'driver@example.com',
            'password' => 'Str0ng-pass-123',
            'password_confirmation' => 'Str0ng-pass-123',
            'vehicle' => 'Van 7',
        ])->assertRedirect(route('login'));

        $driver = User::where('email', 'driver@example.com')->firstOrFail();
        $this->assertSame('user', $driver->role);
        $this->assertFalse((bool) $driver->is_active);

        $this->actingAs($admin)->post(route('admin.users.toggle', $driver))->assertRedirect(route('admin.users.index'));
        $this->assertTrue((bool) $driver->fresh()->is_active);
    }

    private function createItem(): Item
    {
        return Item::create([
            'code' => 'TEST-001',
            'name' => 'Test item',
            'category' => 'Supplies',
            'qty' => 10,
            'unit' => 'pcs',
            'price' => 1.50,
            'location' => 'Warehouse A',
        ]);
    }

    private function createDelivery(): Delivery
    {
        $item = $this->createItem();

        return Delivery::create([
            'reference' => 'DISP-'.strtoupper(bin2hex(random_bytes(4))),
            'item_id' => $item->id,
            'qty' => 1,
            'destination' => 'Test destination',
            'scheduled_at' => now(),
            'status' => 'in_transit',
        ]);
    }
}