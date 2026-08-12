<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Which store's rows the operator is looking at.
 *
 * Every table this module reads carries `tenant_id` from its first migration —
 * the host's `tax_rates` and `tax_classes` carry none at all, so every store on
 * the installation shares one rate table. Scoping is therefore not optional
 * here, and it is not left to a policy either: a policy answers about a row it
 * has already been handed, and a list that hands over the wrong rows has already
 * leaked them.
 */
final class Tenant
{
    public static function id(): ?int
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Model ? (int) $tenant->getKey() : null;
    }

    /**
     * Scope a query to the current tenant, or to nothing at all.
     *
     * Deliberately not `where('tenant_id', $id)` with a null `$id`: that
     * compiles to `tenant_id is null`, which lists exactly the orphan rows an
     * unscoped panel has no business seeing. No tenant means no rows.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scope(Builder $query): Builder
    {
        $id = self::id();

        return $id === null
            ? $query->whereRaw('1 = 0')
            : $query->where($query->getModel()->getTable().'.tenant_id', $id);
    }

    /** Whether a record belongs to the tenant the operator is acting for. */
    public static function owns(Model $record): bool
    {
        $id = self::id();

        return $id !== null && $record->getAttribute('tenant_id') === $id;
    }
}
