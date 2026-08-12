# Runbook

## A rate has changed

Rate versions → find the version in force → **Revise**.

The form is prefilled from the current version, so state the *new* figure rather
than a delta. "In force from" is when the new rate takes effect, and it is also
the instant the current version closes — the two are the same instant by
construction, so there is no gap and no overlap.

Nothing is overwritten. Quotes already given keep the rate they were given, and
that is not a courtesy: recomputing an old order against today's rates does not
check the old answer, it produces a different one and tells you nothing.

**Do not** create a new rate version to make a change. Create opens a *first*
version for a jurisdiction and class; using it for a change leaves two open
versions with the same sequence and the calculator will apply both.

## We have registered in a new jurisdiction

1. Jurisdictions → create it, if it is not there, with its sourcing rule.
2. Registrations → **Open a registration**, with `effective_from` set to the date
   registration actually began.
3. Rate versions → create the rates, with `effective_from` set to when each rate
   genuinely took effect.

Until step 2 exists, quotes in that jurisdiction carry no tax and record
`no_registration` as the reason. That is correct: charging tax you are not
registered to collect is a worse failure than not charging it.

## We have deregistered

Registrations → **Close**, with the instant registration ended.

Do not delete the row and do not delete the jurisdiction. "Were we registered in
March?" has to stay answerable in April. Closing is permitted once; a closed
period cannot be reopened, by this surface or by anything else.

## An order's tax looks wrong

Quotes → open the quote the order references.

The view page carries everything the figure depended on: the resolved
jurisdiction and which sourcing rule chose it, the registration relied on, the
rounding strategy, the instant, the validation record behind any exemption, and —
per line — every rate application in the sequence that produced it, each with its
own basis points, effective date and the base it was actually applied to.

If the arithmetic on that page is right and the answer is still wrong, the input
was wrong. This module was given a base; it did not choose one.

**Do not** try to correct a quote. There is no edit and no delete, at any level.
A correction is a new quote plus a supersession, and both are created by whatever
asked for the quote in the first place.

## The period report shows three different numbers

That is the alarm, and it is why three are shown.

- **Folded over the quote ledger** — each operative quote's recorded total, added
  up.
- **Summed across quote lines** — the same period, from the lines.
- **Reproduced from each quote's own evidence** — every quote recomputed from the
  rate applications recorded on it, reading no rate table at all.

If one and two disagree, a quote's total does not match its own lines. If three
disagrees with either, a quote is not reproducible from its evidence. Both mean
the ledger has been written to by something other than the domain package, and
the figure must not be filed. Nothing on this surface can produce either state.

## The report is empty and I expect rows

In order of likelihood:

1. **No tenant.** With no Filament tenant the surface lists nothing, by design.
   Check the panel has tenancy configured and a tenant is set.
2. **Wrong period.** The report defaults to the current calendar month.
3. **Every quote in the period is superseded.** Superseded quotes are excluded
   from all three figures. They are still on the quotes list, with the reason.

## Someone needs to bulk-fix rows

They do not. This surface has no bulk actions, no delete, and no export of
anything writable, and the tables underneath refuse mass updates at the Eloquent
boundary — model events do not fire for `query()->update()`, so the domain
package closes that hole with its own builder rather than with hooks alone.

A raw query builder is outside that boundary by design, which is how the
reproduction check empties the rate tables in a test. If you find yourself
reaching for `DB::table('tax_quotes')->update(...)`, the answer is a new quote and
a supersession.

## Granting access

Four models, four policies, registered by this module's provider. Two abilities
are this module's own:

- `revise` on a rate version — may close it and insert a successor.
- `close` on a registration — may close its period.

Everything destructive is refused above the policy layer and cannot be granted:
delete, force delete, restore, replicate, reorder, and the whole family of
relation abilities. `update` is refused the same way on registrations, rate
versions and quotes; `create` is refused that way on quotes.

A superuser `Gate::before` does not change any of this. There is a test that
installs one.
