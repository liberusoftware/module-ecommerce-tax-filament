<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Liberu\Ecommerce\Tax\Filament\Support\Tenant;
use Liberu\Ecommerce\Tax\Models\Jurisdiction;

/**
 * A jurisdiction is operator data and is the one thing here that is genuinely
 * editable: it is not evidence, and a quote denormalises the code and the
 * sourcing rule that applied to it, so renaming one cannot rewrite a past
 * figure.
 *
 * Deleting one is refused all the same. `tax_registrations` and
 * `tax_rate_versions` both cascade on its delete, so removing a jurisdiction
 * would take the evidence of every registration and rate with it. A jurisdiction
 * you no longer operate in is retired by closing its registration, which is a
 * fact about a period rather than the erasure of one.
 */
class JurisdictionPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, Jurisdiction $jurisdiction): bool
    {
        return Tenant::owns($jurisdiction);
    }

    public function create(Authenticatable $user): bool
    {
        return Tenant::id() !== null;
    }

    public function update(Authenticatable $user, Jurisdiction $jurisdiction): bool
    {
        return Tenant::owns($jurisdiction);
    }

    public function delete(Authenticatable $user, Jurisdiction $jurisdiction): bool
    {
        return false;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function forceDelete(Authenticatable $user, Jurisdiction $jurisdiction): bool
    {
        return false;
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function restore(Authenticatable $user, Jurisdiction $jurisdiction): bool
    {
        return false;
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return false;
    }

    public function replicate(Authenticatable $user, Jurisdiction $jurisdiction): bool
    {
        return false;
    }

    public function reorder(Authenticatable $user): bool
    {
        return false;
    }
}
