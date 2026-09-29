<?php

namespace App\Http\Controllers\Concerns;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait BuildsDataTables
{
    protected function phoneFilter(string $column): Closure
    {
        return function (Builder $query, string $keyword) use ($column): void {
            $fragment = PhoneNumber::searchFragment($keyword);

            if ($fragment === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where($column, 'like', '%'.$fragment.'%');
        };
    }

    protected function hasRequestedOrder(Request $request): bool
    {
        return ! empty($request->input('order'));
    }
}
