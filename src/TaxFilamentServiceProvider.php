<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Tax\Filament;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Liberu\Ecommerce\Tax\Filament\Policies\JurisdictionPolicy;
use Liberu\Ecommerce\Tax\Filament\Policies\QuotePolicy;
use Liberu\Ecommerce\Tax\Filament\Policies\RateVersionPolicy;
use Liberu\Ecommerce\Tax\Filament\Policies\RegistrationPolicy;
use Liberu\Ecommerce\Tax\Models\Jurisdiction;
use Liberu\Ecommerce\Tax\Models\Quote;
use Liberu\Ecommerce\Tax\Models\RateVersion;
use Liberu\Ecommerce\Tax\Models\Registration;

/**
 * Contributes no panel component of its own.
 *
 * Everything this package renders is attached by the host, per panel, through
 * {@see TaxPanelPlugin}. The package ships no `extra.laravel.providers`, so
 * Composer installing it boots nothing; the host's ModuleManagerServiceProvider
 * registers this provider only when `MODULES_ENABLED` names the module.
 *
 * The one thing registered here is the policy map, and it is registered here
 * rather than on the plugin on purpose. Laravel discovers policies by a naming
 * convention rooted in the application's namespace, which no package can
 * satisfy, so an unregistered policy is not a stricter default — it is an open
 * gate. Authorization is not a property of a panel, and binding it to one would
 * leave the models unguarded for every other caller in the application.
 *
 * The policies are the ordinary half of the story. The guarantee that a quote or
 * a rate version cannot be revised does not live in them: see
 * {@see Resources\TaxResource}, which refuses those abilities below the policy
 * layer entirely.
 */
class TaxFilamentServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const POLICIES = [
        Jurisdiction::class => JurisdictionPolicy::class,
        Registration::class => RegistrationPolicy::class,
        RateVersion::class => RateVersionPolicy::class,
        Quote::class => QuotePolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
