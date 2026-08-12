# Ecommerce: Tax Filament

> The operator surface for `liberusoftware/ecommerce-tax`. It contributes resources, pages, schemas, tables, infolists and actions to an application-owned Filament 5 panel, and delegates authorization, validation, tenancy, persistence and every business rule to that package's public boundary. It owns no schema, computes nothing, and creates no quote.

[Software](https://liberusoftware.com) ·
[Hosting](https://liberuhosting.com) ·
[Services](https://liberuservices.com) ·
[Liberu Group](https://liberugroup.com)

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white) ![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white) ![Filament](https://img.shields.io/badge/Filament-5-FDAE4B)
[![Latest release](https://img.shields.io/github/v/release/liberusoftware/module-ecommerce-tax-filament?sort=semver)](https://github.com/liberusoftware/module-ecommerce-tax-filament/releases/latest) [![Tests](https://github.com/liberusoftware/module-ecommerce-tax-filament/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/liberusoftware/module-ecommerce-tax-filament/actions/workflows/tests.yml)

## Features

- Manage the jurisdictions a store operates in, each with its own sourcing rule.
- Open and close tax registrations. Nexus gates everything: until a registration
  exists, quotes in that jurisdiction carry no tax and say `no_registration`.
- Add rates and **revise** them. Revising closes the current version and inserts a
  successor; there is no edit form and no edit route, because a rate is
  effective-dated and the domain has no update path.
- Read the quote ledger, and read one quote whole — every rate application behind
  every figure, with its jurisdiction, sequence, rounding rule and effective date.
- Read the period total, folded from the ledger three independent ways, one of
  which reproduces every quote from its own recorded evidence and reads no rate
  table at all.
- Rates render as basis points and an exact decimal string — `850 bp (8.5%)`.
  There is no float anywhere in this package and a test asserts it.
- Fully compatible with **Laravel 13**, **PHP 8.5**, **Filament 5** and **Pest 5**.

## The guarantee that is not a policy

**Quotes and rate versions are read-only at the resource level, above the policy
layer.** A guarantee enforced in a policy is defeated by a host
`Gate::before(fn () => true)` — which is exactly what an ordinary superuser role
installs — and this is an audit ledger.

`TaxResource::getAuthorizationResponse()` is the single funnel every `can*()`
method on a Filament resource passes through, and it refuses a named list of
abilities before the gate is consulted at all. The suite installs a permissive
gate, asserts the gate really is answering yes to everything, and then asserts
edit and delete are still closed.

Every unpublished ability is named rather than left to a default, because a model
with no policy is exposed rather than safe, a *present* policy missing a method
is also permissive, and `canAssociate` / `canDissociate` are live on a `hasMany`
and default open.

## What this replaces

Twelve faults in the host application. Each is named in
[`docs/domain.md`](docs/domain.md) of the domain package; this surface is where
the operator-facing half of them is answered.

1. **A percent rate multiplied and divided as a float.** Rates here are integer
   basis points and render through `Rate::decimal()`, a string. `850 bp (8.5%)`.
2. **Mutable, undated rate rows.** There is no edit form for a rate version. The
   only way a rate changes is Revise, which closes a version and inserts a
   successor.
3. **No nexus concept.** Registrations are a first-class screen, and until one
   exists the module charges nothing in that jurisdiction.
4. **A `priority` column that sequenced nothing.** `sequence` is on the create
   form, on the table, on the quote's rate applications, and the arithmetic reads
   it.
5. **Two tax columns on `orders`, one permanently zero.** This surface reads one
   figure, `tax_total_minor`, which is the sum of the quote's lines.
6. **A JSON blob instead of a record.** The quote view shows rate identity,
   jurisdiction, sequence, rounding rule and effective date for every figure.
7. **Twenty-seven real-world VAT rates as a PHP `const`.** This package ships no
   rates and no jurisdictions; they are operator data, entered here.
8. **Inclusive display that disagreed with what was charged.** Each line records
   whether the base it was given was inclusive or exclusive, and the view says so.
9. **One boolean for three legally distinct outcomes.** Taxable, zero-rated and
   exempt are separate treatments with separate reasons, shown per line.
10. **No tenant column.** Every screen scopes to the current Filament tenant, in
    the query rather than in a policy. With no tenant it lists nothing.
11. **A statutory return folded from a cached column by float division.** The
    period report folds the quote ledger, three ways, and says whether they agree.
12. **A live HTTP call inside the checkout path.** Validation is the domain's
    `ValidatesTaxRegistration` seam; this surface reads the validation record
    recorded on the quote and never calls anything.

## Requirements

- **PHP 8.5**, **Laravel 13**, **Composer 2**
- **Filament 5**, with a panel and tenancy configured
- `liberusoftware/ecommerce-tax` ^0.1.0, enabled in `MODULES_ENABLED`

## Quick start

The domain package is not on Packagist, and Composer honours `repositories` only
from the root manifest, so your application needs the entry too:

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-tax" }
]
```

```bash
composer require liberusoftware/ecommerce-tax-filament
```

Installing boots nothing: the package ships no `extra.laravel.providers`. Enable
it, and the domain module it presents:

```dotenv
MODULES_ENABLED=...,ecommerce-tax,ecommerce-tax-filament
```

Then attach the plugin to the panel your operators use. Nothing registers
globally.

```php
use Liberu\Ecommerce\Tax\Filament\TaxPanelPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->id('admin')->plugin(TaxPanelPlugin::make());
}
```

## Documentation

- [The domain](docs/domain.md) — what the surface offers, and why the append-only
  guarantee sits above the policy layer.
- [Adoption](docs/adoption.md) — installing, attaching, tenancy and authorization.
- [Runbook](docs/runbook.md) — changing a rate, deregistering, reading a quote
  that looks wrong, and what to do when the three report figures disagree.
- [Liberu Main Documentation](https://github.com/liberusoftware/documentation)
- [Architecture & Standards Index](https://github.com/liberusoftware/documentation/tree/main/architecture)

## Related Liberu Projects

| Project | Repository | Purpose |
| --- | --- | --- |
| **Boilerplate** | [liberusoftware/boilerplate-laravel](https://github.com/liberusoftware/boilerplate-laravel) | Shared Laravel application foundation and reference composition |
| **CMS** | [liberu-cms/cms-laravel](https://github.com/liberu-cms/cms-laravel) | Structured content, publishing, media, multisite, and headless delivery |
| **CRM** | [liberu-crm/crm-laravel](https://github.com/liberu-crm/crm-laravel) | Customer data, sales, marketing, service, and customer success |
| **Billing** | [liberu-billing/billing-laravel](https://github.com/liberu-billing/billing-laravel) | Products, subscriptions, invoicing, payments, and provisioning |
| **Accounting** | [liberu-accounting/accounting-laravel](https://github.com/liberu-accounting/accounting-laravel) | Ledgers, banking, tax, expenses, close, and financial reporting |
| **Ecommerce** | [liberu-ecommerce/ecommerce-laravel](https://github.com/liberu-ecommerce/ecommerce-laravel) | Catalog, checkout, orders, fulfillment, returns, B2B, and omnichannel commerce |
| **Control Panel** | [liberu-control-panel/control-panel-laravel](https://github.com/liberu-control-panel/control-panel-laravel) | Hosting, infrastructure, DNS, mail, databases, backups, and security operations |
| **Automation** | [liberu-automation/automation-laravel](https://github.com/liberu-automation/automation-laravel) | Governed workflows, provider-neutral AI, approvals, and connectors |

## Security

Please do not report security vulnerabilities through public GitHub issues.
Follow our [Security Policy](https://github.com/liberusoftware/documentation/blob/main/architecture/SECURITY.md) for private reporting and supported versions.

## License

This project is open-source software. You may use, modify, and distribute it
under the terms described in [LICENSE.md](LICENSE.md).

The linked license text is authoritative; this summary is not legal advice.

## Feedback and contributing

Feedback and contributions are welcome. You can help by reporting reproducible
bugs, proposing focused enhancements, improving documentation or translations,
and submitting tested code changes.

Before contributing, please read [CONTRIBUTING.md](https://github.com/liberusoftware/documentation/blob/main/standards/CONTRIBUTING.md) and our
[Code of Conduct](https://github.com/liberusoftware/documentation/blob/main/architecture/CODE_OF_CONDUCT.md). Search existing issues first, then use
the appropriate issue template. Pull requests should explain the problem and
approach, remain focused, include or update tests, pass the required workflows,
and document user-visible or breaking changes.

## Contributors

Thank you to everyone who helps improve Liberu.

<a href="https://github.com/liberusoftware/module-ecommerce-tax-filament/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=liberusoftware/module-ecommerce-tax-filament" alt="Contributors to liberusoftware/module-ecommerce-tax-filament">
</a>

[View the full contributors graph](https://github.com/liberusoftware/module-ecommerce-tax-filament/graphs/contributors).
