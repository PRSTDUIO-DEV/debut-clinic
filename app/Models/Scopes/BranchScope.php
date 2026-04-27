<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! function_exists('app')) {
            return;
        }

        if (! app()->bound('branch.id')) {
            return;
        }

        $branchId = app('branch.id');
        if ($branchId) {
            $builder->where($model->getTable().'.branch_id', $branchId);
        }
    }
}
