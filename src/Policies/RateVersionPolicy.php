<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Liberu\Ecommerce\Tax\Filament\Support\Tenant;
use Liberu\Ecommerce\Tax\Models\RateVersion;

/**
 * A rate version is written once. `update` is false here and refused above here
 * as well, because the host's mutable `tax_rates` row is fault two: editing it
 * retroactively changed what every historical order would recompute to, and left
 * "what rate did we actually charge in March?" with no answer but a number
 * cached on the order.
 *
 * `revise` is the ability that replaces it. Granting it does not grant an edit:
 * the action behind it closes the current version and inserts a successor, which
 * is the domain's only route and remains so however this answers.
 */
class RateVersionPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, RateVersion $rateVersion): bool
    {
        return Tenant::owns($rateVersion);
    }

    public function create(Authenticatable $user): bool
    {
        return Tenant::id() !== null;
    }

    public function revise(Authenticatable $user, RateVersion $rateVersion): bool
    {
        return Tenant::owns($rateVersion) && $rateVersion->effective_to === null;
    }

    public function update(Authenticatable $user, RateVersion $rateVersion): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, RateVersion $rateVersion): bool
    {
        return false;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function forceDelete(Authenticatable $user, RateVersion $rateVersion): bool
    {
        return false;
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function restore(Authenticatable $user, RateVersion $rateVersion): bool
    {
        return false;
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return false;
    }

    public function replicate(Authenticatable $user, RateVersion $rateVersion): bool
    {
        return false;
    }

    public function reorder(Authenticatable $user): bool
    {
        return false;
    }
}
