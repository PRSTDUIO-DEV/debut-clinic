<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apply this trait to any tenant-scoped model.
 * It ensures:
 * - branch_id is auto-filled from the active branch context on create
 * - queries are filtered by branch via the BranchScope global scope
 * - relation accessor is available
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model) {
            if (empty($model->branch_id) && function_exists('app')) {
                $branchId = app()->bound('branch.id') ? app('branch.id') : null;
                if ($branchId) {
                    $model->branch_id = $branchId;
                }
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
