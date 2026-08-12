# The domain

This package owns no domain. It is an operator surface for one that already
exists: [`liberusoftware/ecommerce-tax`](https://github.com/liberusoftware/module-ecommerce-tax)
owns rate determination, the tax arithmetic and the evidence that both happened.
Everything here delegates authorization, validation, tenancy, persistence and
business rules to that package's public boundary.

## What the surface offers

| Screen | What it does |
|---|---|
| Jurisdictions | Create and correct the jurisdictions the store operates in, each with its own sourcing rule. Never deletes one. |
| Registrations | Open a registration in a jurisdiction, and later close the period. Never edits or deletes one. |
| Rate versions | Add the first version of a rate; revise a rate by closing the current version and inserting a successor. No edit form exists. |
| Quotes | Read the ledger, and read one quote whole: its lines, their treatments and reasons, and every rate application behind every figure. |
| Period tax report | The period total, folded from the quote ledger three independent ways, with a statement of whether they agree. |

The module ships no jurisdictions and no rates, and it never will. A rate baked
into a release is a rate that goes stale between releases, and the fleet has no
mechanism to hot-fix twenty-seven of them.

## The one guarantee that is not a policy

**Quotes and rate versions are read-only at the resource level, not merely by
policy.**

`TaxResource::getAuthorizationResponse()` is the single funnel every `can*()`
method on a Filament resource goes through. It refuses a named list of abilities
before the gate is consulted at all, so nothing an application configures reaches
past it — including `Gate::before(fn () => true)`, which is what an ordinary
superuser role installs. `tests/Feature/AuthorizationTest.php` installs exactly
that gate, asserts the gate really is answering yes, and then asserts the
refusals hold.

The list is written out rather than left to a default, for three reasons:

- a model with no policy is exposed, not safe — Laravel's unanswered gate is
  permissive;
- Filament's `get_authorization_response()` returns **allow** when a policy that
  *is* present lacks the method it was asked about;
- `canAssociate` and `canDissociate` are live on a `hasMany` and default open.

`TaxResource::RELATIONAL` names the relation-manager abilities even though this
package ships no relation manager, so that adding one later cannot quietly open
them. Nothing in the policies is named after one of those abilities, because a
policy method named for a relation ability answers it.

Policies are still shipped, and every ability has a method on every one of them.
They are what an ordinary application configuration reaches; the resource is what
a permissive gate cannot get past.

## Revising a rate

There is no edit page and no edit route on `RateVersionResource`. The Revise
action prefills from the current version, so the operator states the new rate
rather than a delta, and what happens is `ReviseRate`: the current version's
`effective_to` closes at the stated instant and a successor is inserted from it.
Both rows survive. A March quote still reproduces to the March figure in April,
which is the whole point of the module.

The same shape applies to a registration: it is opened, and later closed.
`effective_to` is the one column the domain lets move, once, from null, and the
Close action is the only thing on this surface that moves it.

## Tenancy

The current tenant is Filament's — `Filament::getTenant()` — and its key is the
`tenant_id` every table the module reads carries from its first migration.

Scoping happens in `Resource::getEloquentQuery()`, not in a policy. A policy
answers about a row it has already been handed, and a list that hands over the
wrong rows has leaked them before anybody is asked.

With no tenant, the surface lists **nothing**. It deliberately does not write
`where('tenant_id', null)`, which compiles to `tenant_id is null` and lists
exactly the orphan rows an unscoped panel has no business seeing.

## Money and rates

Money is integer minor units and renders through the domain's `Money::decimal()`,
which is a string. A rate is integer basis points and renders through
`Rate::decimal()`, also a string: the tables show `850 bp (8.5%)`, so the stored
figure is the one on screen. There is no float in this package — no `round(`, no
`(float)`, no `number_format`, no division by 100 — and a test asserts it over
the source with comments stripped.

The period report is quoted in minor units and carries no currency, because a
ledger can hold more than one and a total summed across currencies would be a
worse answer than an honest count.

## What is not searchable and not filterable

Search terms and filter state both persist into the query string.

- A tax **registration number** is neither a column nor a filter on the
  registrations table.
- A **quote reference** is a column but is not searchable and not filterable. It
  is an opaque unguessable handle by design; a quote is reached from its own URL,
  held by whatever recorded it.

Jurisdiction codes, treatments and periods are filterable, and none of them is
sensitive.

## What this package does not do

- It does not create a quote. A quote is evidence that something already
  happened, and `create` is closed on the resource.
- It does not correct a quote. A correction is a new quote plus a supersession,
  both of which are the domain's.
- It does not compute anything. Every figure on screen is read from the ledger or
  produced by `PeriodTaxReport`, which is the domain's own query.
- It does not file, submit, or know what a return looks like.
