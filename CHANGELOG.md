# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this package adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-12

### Added

- `TaxPanelPlugin`, attached per panel by the application. Nothing registers
  globally.
- Jurisdictions: create and correct the jurisdictions a store operates in, each
  with its own sourcing rule. Deleting one is refused — the registrations and
  rate versions below it cascade.
- Registrations: open a registration, and close its period. No edit form.
- Rate versions: create a first version, and revise a rate by closing the current
  version and inserting a successor. No edit form and no edit route.
- Quotes: read the ledger, and read one quote whole — its lines, their treatments
  and reasons, and every rate application behind every figure.
- Period tax report: the period total folded from the quote ledger three
  independent ways, including reproduction from each quote's own evidence, with a
  statement of whether the three agree.
- Four policies, each answering every ability by name.

### Security

- Quotes and rate versions are read-only **at the resource level**, above the
  policy layer. A permissive `Gate::before` does not reach them, and a test
  installs one to prove it.
- Every unpublished ability is refused by name, including `associate`,
  `dissociate`, `attach` and `detach`, which are live on a `hasMany` and default
  open.
- Every query is scoped to the current Filament tenant. With no tenant the
  surface lists nothing rather than every orphan row.
- Registration numbers are neither searchable nor filterable, and the quote
  reference is neither, because search terms and filter state both persist into
  the query string.

[0.1.0]: https://github.com/liberusoftware/module-ecommerce-tax-filament/releases/tag/0.1.0
