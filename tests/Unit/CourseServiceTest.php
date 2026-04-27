<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Visit;
use App\Services\CourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CourseServiceTest extends TestCase
{
    use RefreshDatabase;

    private function bootEnv(): array
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        return [$branch, $patient];
    }

    private function fakeInvoiceItem(Branch $branch, Patient $patient, Procedure $proc): InvoiceItem
    {
        $visit = Visit::factory()->create(['branch_id' => $branch->id, 'patient_id' => $patient->id]);
        $invoice = Invoice::create([
            'branch_id' => $branch->id, 'visit_id' => $visit->id, 'patient_id' => $patient->id,
            'invoice_number' => 'INV-'.Str::random(6),
            'invoice_date' => now()->toDateString(),
            'subtotal' => 0, 'discount_amount' => 0, 'vat_amount' => 0,
            'total_amount' => 0, 'total_cogs' => 0,
            'status' => 'draft',
        ]);

        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => 'procedure', 'item_id' => $proc->id,
            'item_name' => $proc->name, 'quantity' => 1,
            'unit_price' => $proc->price, 'discount' => 0, 'total' => $proc->price, 'cost_price' => 0,
        ]);
    }

    public function test_purchase_from_invoice_item_creates_course(): void
    {
        [$branch, $patient] = $this->bootEnv();
        $proc = Procedure::factory()->create([
            'branch_id' => $branch->id, 'price' => 12000,
            'is_package' => true, 'package_sessions' => 6, 'package_validity_days' => 365,
        ]);
        $item = $this->fakeInvoiceItem($branch, $patient, $proc);

        $course = $this->app->make(CourseService::class)->purchaseFromInvoiceItem($item, $patient->id, $branch->id);

        $this->assertNotNull($course);
        $this->assertSame(6, (int) $course->total_sessions);
        $this->assertSame(0, (int) $course->used_sessions);
        $this->assertSame(6, (int) $course->remaining_sessions);
        $this->assertSame('active', $course->status);
        $this->assertNotNull($course->expires_at);
    }

    public function test_purchase_returns_null_when_procedure_is_not_package(): void
    {
        [$branch, $patient] = $this->bootEnv();
        $proc = Procedure::factory()->create(['branch_id' => $branch->id, 'is_package' => false]);
        $item = $this->fakeInvoiceItem($branch, $patient, $proc);
        $this->assertNull($this->app->make(CourseService::class)->purchaseFromInvoiceItem($item, $patient->id, $branch->id));
    }

    public function test_use_session_decrements_remaining_and_marks_completed(): void
    {
        [$branch, $patient] = $this->bootEnv();
        $course = Course::create([
            'branch_id' => $branch->id, 'patient_id' => $patient->id,
            'name' => 'Pkg', 'total_sessions' => 2, 'used_sessions' => 0, 'remaining_sessions' => 2,
            'status' => 'active',
        ]);
        $visit = Visit::factory()->create(['branch_id' => $branch->id, 'patient_id' => $patient->id]);
        $svc = $this->app->make(CourseService::class);

        $svc->useSession($course, $visit);
        $svc->useSession($course->fresh(), $visit);
        $course->refresh();

        $this->assertSame(2, (int) $course->used_sessions);
        $this->assertSame(0, (int) $course->remaining_sessions);
        $this->assertSame('completed', $course->status);
    }

    public function test_use_session_throws_when_remaining_zero(): void
    {
        [$branch, $patient] = $this->bootEnv();
        $course = Course::create([
            'branch_id' => $branch->id, 'patient_id' => $patient->id,
            'name' => 'Pkg', 'total_sessions' => 1, 'used_sessions' => 1, 'remaining_sessions' => 0,
            'status' => 'completed',
        ]);
        $visit = Visit::factory()->create(['branch_id' => $branch->id, 'patient_id' => $patient->id]);

        $this->expectException(ValidationException::class);
        $this->app->make(CourseService::class)->useSession($course, $visit);
    }

    public function test_expire_expired_marks_past_dates_as_expired(): void
    {
        [$branch, $patient] = $this->bootEnv();
        Course::create([
            'branch_id' => $branch->id, 'patient_id' => $patient->id,
            'name' => 'Old', 'total_sessions' => 1, 'used_sessions' => 0, 'remaining_sessions' => 1,
            'status' => 'active', 'expires_at' => now()->subDays(2),
        ]);
        Course::create([
            'branch_id' => $branch->id, 'patient_id' => $patient->id,
            'name' => 'New', 'total_sessions' => 1, 'used_sessions' => 0, 'remaining_sessions' => 1,
            'status' => 'active', 'expires_at' => now()->addDays(10),
        ]);

        $count = $this->app->make(CourseService::class)->expireExpired();
        $this->assertSame(1, $count);
    }
}
