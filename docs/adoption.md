# Adoption

## Installing

The domain package is not on Packagist, and Composer honours `repositories` only
from the **root** manifest. The entry in this package's `composer.json` works for
this repository's own CI, where this package is root; it does nothing for you.
Add the same entry to your application's `composer.json`:

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-tax" }
]
```

Then:

```bash
composer require liberusoftware/ecommerce-tax-filament
```

The package ships no `extra.laravel.providers`, so Composer installing it boots
nothing. The host's `ModuleManagerServiceProvider` globs the configured module
paths for each package's `module.json` and registers only the modules named in
`MODULES_ENABLED`:

```dotenv
MODULES_ENABLED=...,ecommerce-tax,ecommerce-tax-filament
```

Both are needed. This package renders the domain package's tables and would have
nothing to render without it.

```bash
php artisan migrate
```

Eight tables are created — by `ecommerce-tax`. This package ships no migration
and owns no schema; a boundary test asserts there is no `database/` directory
here at all.

## Attaching the surface

Nothing registers globally. The application attaches the plugin to whichever
panel its operators use:

```php
use Liberu\Ecommerce\Tax\Filament\TaxPanelPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->plugin(TaxPanelPlugin::make());
}
```

`module.json` names `TaxPanelPlugin` under `presentation.filament.admin`, which
is a statement about where it is *expected*, not a registration. A panel that
does not attach it has no tax surface.

## Tenancy is required

Every screen scopes to `Filament::getTenant()` and its key is compared against
`tenant_id`. **A panel with no tenancy configured shows nothing**, on purpose:
the alternative is a panel that shows every store's rates to every operator,
which is fault ten of the host restated as a UI.

If your panel does not use Filament tenancy, configure it before attaching this
plugin. The tenant model needs nothing beyond a key.

## Authorization

The service provider registers a policy for each of the four models it exposes.
Laravel discovers policies by a naming convention rooted in the application's
namespace, which no package can satisfy, so an unregistered policy would not be a
stricter default — it would be an open gate.

If your application already maps one of those models to its own policy, register
yours after this module boots and it wins. Two things to know before you do:

- every ability must have a method. A policy that is present but missing a method
  is **permissive** — Filament's `get_authorization_response()` returns allow.
- refusing to edit or delete a quote or a rate version is not the policy's job
  and cannot be undone by replacing it. `TaxResource` refuses those below the
  gate.

Two abilities are this module's own and are yours to grant or refuse:

| Ability | On | Means |
|---|---|---|
| `revise` | `RateVersion` | may close this version and insert a successor |
| `close` | `Registration` | may close this registration's period |

Neither widens what is possible. The domain permits exactly one column to move on
either model, once, from null.

## What to do about the host's tax

Follow `docs/adoption.md` in `ecommerce-tax`. In short: create jurisdictions,
then registrations, then rate versions with `effective_from` set to when each
rate genuinely took effect — not to today, or every historical reproduction is
dated wrong. Until a registration exists, this module charges nothing in that
jurisdiction, deliberately.

This surface is where all three are entered. It imports nothing and seeds
nothing: a rate is a statement about the law, and importing one silently is the
wrong shape of mistake to make.

## Requirements

- PHP 8.5.
- Laravel 13.
- Filament 5, with a panel and tenancy configured.
- `liberusoftware/ecommerce-tax` ^0.1.0, enabled in `MODULES_ENABLED`.
