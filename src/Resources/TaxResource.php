<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\Tax\Filament\Support\Tenant;
use UnitEnum;

/**
 * Where the append-only guarantee is enforced for the operator surface.
 *
 * Wave 8's lesson, restated because this is an audit ledger: a guarantee that
 * lives in a policy is defeated by a host `Gate::before` that answers yes to
 * everything. The policy layer is the application's to configure, and a shield
 * the application can switch off is not a guarantee — so the refusal sits
 * *below* it. {@see getAuthorizationResponse()} answers before the gate is ever
 * consulted, and there is no configuration, role or ability that reaches past
 * it.
 *
 * Three further reasons this is a list of names rather than a default:
 *
 * - a model with no policy is exposed, not safe: Laravel's unanswered gate is
 *   permissive;
 * - Filament's `get_authorization_response()` returns **allow** when a policy
 *   that *is* present lacks the method it asked about;
 * - `canAssociate` and `canDissociate` are live on a `hasMany` and default open.
 *
 * So every ability this surface does not publish is named, and a test asserts
 * each one is refused with a permissive `Gate::before` installed.
 */
abstract class TaxResource extends Resource
{
    /**
     * Abilities that destroy or resurrect evidence. Closed on every resource
     * here without exception: nothing this module owns is ever deleted, and a
     * replica of a quote would be a second piece of evidence for one event.
     */
    public const DESTRUCTIVE = [
        'delete',
        'deleteAny',
        'forceDelete',
        'forceDeleteAny',
        'restore',
        'restoreAny',
        'replicate',
        'reorder',
    ];

    /**
     * The relation-manager abilities. This package ships no relation manager,
     * and these are closed so that adding one later cannot quietly open them:
     * they are live on a `hasMany` and they default open.
     */
    public const RELATIONAL = [
        'attach',
        'attachAny',
        'detach',
        'detachAny',
        'associate',
        'associateAny',
        'dissociate',
        'dissociateAny',
    ];

    /** Revising a row in place. Closed wherever {@see isReadOnly()} holds. */
    public const REVISING = ['update'];

    /**
     * Whether this resource's rows are evidence, revisable only by writing a
     * new row.
     */
    public static function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Every ability refused before the gate is consulted.
     *
     * @return list<string>
     */
    public static function closedAbilities(): array
    {
        return [
            ...self::DESTRUCTIVE,
            ...self::RELATIONAL,
            ...(static::isReadOnly() ? self::REVISING : []),
        ];
    }

    /**
     * The one funnel every `can*()` method on a Filament resource goes through.
     *
     * Overriding it here rather than overriding fifteen `can*()` methods is not
     * brevity: a `can*()` method added by a future Filament release would route
     * through this too, whereas a list of overrides would silently not cover it.
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $ability = match (true) {
            $action instanceof BackedEnum => (string) $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };

        if (in_array($ability, static::closedAbilities(), true)) {
            return Response::deny(
                "[{$ability}] is not available on ".static::getPluralModelLabel().': these rows are evidence.',
            );
        }

        return parent::getAuthorizationResponse($action, $record);
    }

    public static function getEloquentQuery(): Builder
    {
        return Tenant::scope(parent::getEloquentQuery());
    }
}
