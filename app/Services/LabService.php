<?php

namespace App\Services;

use App\Models\LabOrder;
use App\Models\LabResultValue;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LabService
{
    public function __construct(
        private LabOrderNumberGenerator $orderNumbers,
        private string $disk = 'public',
    ) {}

    /**
     * Create a lab order with given test ids.
     *
     * @param array<int> $testIds
     */
    public function createOrder(
        Patient $patient,
        array $testIds,
        ?int $visitId = null,
        ?User $user = null,
        ?string $notes = null,
        string $status = 'draft',
    ): LabOrder {
        if (empty($testIds)) {
            throw ValidationException::withMessages(['tests' => 'ต้องเลือกการตรวจอย่างน้อย 1 รายการ']);
        }

        return DB::transaction(function () use ($patient, $testIds, $visitId, $user, $notes, $status) {
            $tests = LabTest::query()
                ->where('branch_id', $patient->branch_id)
                ->whereIn('id', $testIds)
                ->where('is_active', true)
                ->get();
            if ($tests->count() !== count(array_unique($testIds))) {
                throw ValidationException::withMessages(['tests' => 'มีการตรวจที่ไม่พบ/ไม่ active ในสาขานี้']);
            }

            $order = LabOrder::create([
                'branch_id' => $patient->branch_id,
                'patient_id' => $patient->id,
                'visit_id' => $visitId,
                'order_no' => $this->orderNumbers->next($patient->branch_id),
                'ordered_at' => now(),
                'ordered_by' => $user?->id,
                'status' => $status,
                'notes' => $notes,
            ]);

            foreach ($tests as $test) {
                $order->items()->create([
                    'lab_test_id' => $test->id,
                    'price' => $test->price,
                ]);
            }

            return $order->fresh(['items.test']);
        });
    }

    /**
     * Record/update result values for an order.
     * Auto-flags numeric values against ref_min/ref_max.
     *
     * @param array<int, array{lab_test_id:int, value_numeric?:float|null, value_text?:string|null, abnormal_flag?:string|null, notes?:string|null}> $rows
     */
    public function recordResults(LabOrder $order, array $rows, ?User $user = null, ?string $resultDate = null): LabOrder
    {
        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages(['order' => 'order ถูกยกเลิกแล้ว']);
        }

        return DB::transaction(function () use ($order, $rows, $user, $resultDate) {
            $tests = LabTest::query()
                ->whereIn('id', collect($rows)->pluck('lab_test_id')->all())
                ->get()
                ->keyBy('id');

            foreach ($rows as $row) {
                $test = $tests->get($row['lab_test_id']);
                if (! $test) {
                    throw ValidationException::withMessages(['tests' => 'lab_test_id '.$row['lab_test_id'].' ไม่พบ']);
                }
                $valueNumeric = $row['value_numeric'] ?? null;
                $flag = $row['abnormal_flag'] ?? $this->autoFlag($test, $valueNumeric);

                LabResultValue::updateOrCreate(
                    ['lab_order_id' => $order->id, 'lab_test_id' => $test->id],
                    [
                        'value_numeric' => $valueNumeric,
                        'value_text' => $row['value_text'] ?? null,
                        'abnormal_flag' => $flag,
                        'notes' => $row['notes'] ?? null,
                        'measured_at' => $row['measured_at'] ?? now(),
                        'recorded_by' => $user?->id,
                    ],
                );
            }

            $order->status = 'completed';
            $order->result_date = $resultDate ?? now()->toDateString();
            $order->save();

            return $order->fresh(['items.test', 'results.test']);
        });
    }

    public function attachReport(LabOrder $order, UploadedFile $file): LabOrder
    {
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($file->getMimeType(), $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'ต้องเป็น PDF/JPEG/PNG/WebP']);
        }
        if ($file->getSize() > 12 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'ไฟล์ใหญ่เกิน 12 MB']);
        }

        $folder = sprintf('lab/reports/%d/%d', $order->branch_id, $order->patient_id);
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->getMimeType() === 'application/pdf' ? 'pdf' : 'bin'));
        $name = Str::uuid().'.'.$ext;
        Storage::disk($this->disk)->putFileAs($folder, $file, $name);

        $order->report_path = $folder.'/'.$name;
        $order->save();

        return $order;
    }

    public function cancel(LabOrder $order, string $reason): LabOrder
    {
        if ($order->status === 'completed') {
            throw ValidationException::withMessages(['order' => 'order ที่ completed แล้วยกเลิกไม่ได้ (ใช้ void แทน)']);
        }
        $order->status = 'cancelled';
        $order->notes = trim(($order->notes ? $order->notes."\n" : '').'[CANCEL] '.$reason);
        $order->save();

        return $order;
    }

    private function autoFlag(LabTest $test, ?float $value): string
    {
        if ($value === null) {
            return 'normal';
        }
        if ($test->ref_min !== null && $value < (float) $test->ref_min) {
            return 'low';
        }
        if ($test->ref_max !== null && $value > (float) $test->ref_max) {
            return 'high';
        }

        return 'normal';
    }
}
