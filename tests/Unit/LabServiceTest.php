<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\LabTest;
use App\Models\Patient;
use App\Services\LabService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LabServiceTest extends TestCase
{
    use RefreshDatabase;

    private function bootEnv(): array
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $cbc = LabTest::create([
            'branch_id' => $branch->id, 'code' => 'CBC', 'name' => 'CBC',
            'unit' => '×10^9/L', 'ref_min' => 4.0, 'ref_max' => 10.0,
            'price' => 250, 'is_active' => true,
        ]);
        $fbs = LabTest::create([
            'branch_id' => $branch->id, 'code' => 'FBS', 'name' => 'Fasting Blood Sugar',
            'unit' => 'mg/dL', 'ref_min' => 70, 'ref_max' => 110,
            'price' => 80, 'is_active' => true,
        ]);

        return [$branch, $patient, $cbc, $fbs];
    }

    public function test_create_order_generates_unique_order_no_with_items(): void
    {
        [, $patient, $cbc, $fbs] = $this->bootEnv();
        $svc = $this->app->make(LabService::class);

        $order = $svc->createOrder($patient, [$cbc->id, $fbs->id]);

        $this->assertStringStartsWith('LAB-', $order->order_no);
        $this->assertCount(2, $order->items);
        $this->assertSame('draft', $order->status);
    }

    public function test_create_order_rejects_empty_tests(): void
    {
        [, $patient] = $this->bootEnv();
        $this->expectException(ValidationException::class);
        $this->app->make(LabService::class)->createOrder($patient, []);
    }

    public function test_create_order_rejects_cross_branch_tests(): void
    {
        [, $patient] = $this->bootEnv();
        $otherBranch = Branch::factory()->create();
        $alien = LabTest::create([
            'branch_id' => $otherBranch->id, 'code' => 'X', 'name' => 'X',
            'is_active' => true, 'price' => 100,
        ]);

        $this->expectException(ValidationException::class);
        $this->app->make(LabService::class)->createOrder($patient, [$alien->id]);
    }

    public function test_record_results_auto_flags_low_high_normal(): void
    {
        [, $patient, $cbc, $fbs] = $this->bootEnv();
        $svc = $this->app->make(LabService::class);
        $order = $svc->createOrder($patient, [$cbc->id, $fbs->id]);

        $svc->recordResults($order, [
            ['lab_test_id' => $cbc->id, 'value_numeric' => 7.5],
            ['lab_test_id' => $fbs->id, 'value_numeric' => 140],
        ]);

        $order->refresh()->load('results.test');
        $this->assertSame('completed', $order->status);
        $byCode = $order->results->keyBy('lab_test_id');
        $this->assertSame('normal', $byCode[$cbc->id]->abnormal_flag);
        $this->assertSame('high', $byCode[$fbs->id]->abnormal_flag);
    }

    public function test_record_results_low_flag_triggered(): void
    {
        [, $patient, $cbc] = $this->bootEnv();
        $svc = $this->app->make(LabService::class);
        $order = $svc->createOrder($patient, [$cbc->id]);
        $svc->recordResults($order, [['lab_test_id' => $cbc->id, 'value_numeric' => 2.5]]);
        $this->assertSame('low', $order->fresh()->results()->first()->abnormal_flag);
    }

    public function test_cancel_blocks_completed_orders(): void
    {
        [, $patient, $cbc] = $this->bootEnv();
        $svc = $this->app->make(LabService::class);
        $order = $svc->createOrder($patient, [$cbc->id]);
        $svc->recordResults($order, [['lab_test_id' => $cbc->id, 'value_numeric' => 5.0]]);

        $this->expectException(ValidationException::class);
        $svc->cancel($order->fresh(), 'oops');
    }

    public function test_order_no_increments_per_branch_per_day(): void
    {
        [, $patient, $cbc] = $this->bootEnv();
        $svc = $this->app->make(LabService::class);
        $a = $svc->createOrder($patient, [$cbc->id]);
        $b = $svc->createOrder($patient, [$cbc->id]);

        $this->assertNotSame($a->order_no, $b->order_no);
        $aSeq = (int) substr($a->order_no, -4);
        $bSeq = (int) substr($b->order_no, -4);
        $this->assertSame($aSeq + 1, $bSeq);
    }
}
