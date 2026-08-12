<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Liberu\Ecommerce\Tax\Filament\Support\Tenant;
use Liberu\Ecommerce\Tax\Models\Registration;

/**
 * A registration is opened and closed; it is never edited and never deleted.
 * "Were we registered in March?" has to stay answerable in April.
 *
 * `close` is an ability of this module's own, named because the operator surface
 * offers it as an action. The domain permits exactly one column to move —
 * `effective_to`, once, from null — and refuses the rest at the Eloquent
 * boundary, so this answer cannot widen what is possible; it only decides who is
 * offered the button.
 */
class RegistrationPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, Registration $registration): bool
    {
        return Tenant::owns($registration);
    }

    public function create(Authenticatable $user): bool
    {
        return Tenant::id() !== null;
    }

    public function close(Authenticatable $user, Registration $registration): bool
    {
        return Tenant::owns($registration) && $registration->effective_to === null;
    }

    public function update(Authenticatable $user, Registration $registration): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, Registration $registration): bool
    {
        return false;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function forceDelete(Authenticatable $user, Registration $registration): bool
    {
        return false;
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function restore(Authenticatable $user, Registration $registration): bool
    {
        return false;
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return false;
    }

    public function replicate(Authenticatable $user, Registration $registration): bool
    {
        return false;
    }

    public function reorder(Authenticatable $user): bool
    {
        return false;
    }
}
