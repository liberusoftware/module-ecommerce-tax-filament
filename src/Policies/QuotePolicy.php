<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Liberu\Ecommerce\Tax\Filament\Resources\TaxResource;
use Liberu\Ecommerce\Tax\Filament\Support\Tenant;
use Liberu\Ecommerce\Tax\Models\Quote;

/**
 * A quote is read, and only read.
 *
 * Every method here answers false except the two reads, and the refusals are
 * written out rather than left off: a policy that is present but missing a
 * method is *permissive*, because Filament's `get_authorization_response()`
 * returns allow when it cannot find the method it asked for.
 *
 * None of this is the guarantee. The guarantee is in
 * {@see TaxResource}, which refuses the
 * same abilities before the gate is consulted at all. This policy is what an
 * ordinary application configuration would reach; that one is what a permissive
 * `Gate::before` cannot get past.
 */
class QuotePolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, Quote $quote): bool
    {
        return Tenant::owns($quote);
    }

    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, Quote $quote): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, Quote $quote): bool
    {
        return false;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function forceDelete(Authenticatable $user, Quote $quote): bool
    {
        return false;
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function restore(Authenticatable $user, Quote $quote): bool
    {
        return false;
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return false;
    }

    public function replicate(Authenticatable $user, Quote $quote): bool
    {
        return false;
    }

    public function reorder(Authenticatable $user): bool
    {
        return false;
    }
}
