# Rename `CleanupCategoryInterface::getSafeToPurgeAutomatically()` to `isSafeToPurgeAutomatically()`

> **STATUS: RAISED FOR HUMAN DECISION — NOT APPROVED, NOT STARTED.**
> Spec-first by design. No code has been changed.

## Why

PHPMD's `rulesets/naming.xml/BooleanGetMethodName` reports 5 findings in
`lib/Service/Cleanup/`, all the same method name on the interface and its four
implementations:

| File | Line |
| --- | --- |
| `lib/Service/Cleanup/CleanupCategoryInterface.php` | 79 |
| `lib/Service/Cleanup/ExpiredLocksCategory.php` | 84 |
| `lib/Service/Cleanup/OrphanedConditionalRulesCategory.php` | 81 |
| `lib/Service/Cleanup/OrphanedSharesCategory.php` | 88 |
| `lib/Service/Cleanup/OrphanedWidgetPlacementsCategory.php` | 80 |

The rule's position is that a method returning `bool` should read as a predicate
(`is*`/`has*`), not as an accessor (`get*`) — `if ($c->isSafeToPurgeAutomatically())`
reads as a question, `if ($c->getSafeToPurgeAutomatically())` reads as a field
fetch. The rule is correct here: the method is a pure predicate with no backing
field, and its one production caller
(`CategoryRegistryService`, REQ-CLN-008) uses it exactly as a question:

```php
if ($category->getSafeToPurgeAutomatically() === true) {
```

## Why this is a proposal and not a patch

**The name is pinned verbatim by the canonical spec.**
`openspec/specs/orphaned-data-cleanup/spec.md` names the method twice, once in a
normative MUST and once in the interface contract:

- line 227: *"Each category exposes its tier through
  `CleanupCategoryInterface::getSafeToPurgeAutomatically()`."*
- line 314: *"GIVEN the `CleanupCategoryInterface` with methods `getName()`,
  `getDisplayName()`, `getSafeToPurgeAutomatically()`, `isAvailable()`, `scan()`,
  and `purge(bool $dryRun = false)`"*

Renaming the method in code without amending the spec would make both statements
false and leave the spec describing an interface that no longer exists. As with
the `ResponseHelper` proposal, the sequencing must be spec-first, and the spec
edit is a decision for a human rather than a mechanical cleanup an agent should
make on its own initiative.

Note the internal inconsistency the rename would also resolve: the same interface
already has `isAvailable()`, so the interface currently uses **both** conventions
for boolean-returning methods. The four implementation classes also already use
the predicate wording in their own docblocks and comments
(`Tier-A ('safeToPurgeAutomatically=true')`).

## What Changes

If approved, in this order:

1. **Amend `openspec/specs/orphaned-data-cleanup/spec.md`** at lines 227 and 314 to
   name `isSafeToPurgeAutomatically()`.
2. **Rename the method** on `CleanupCategoryInterface` and its four implementations.
3. **Update the one production call site** in
   `lib/Service/Cleanup/CategoryRegistryService.php` (~line 133).
4. **Update any test doubles / assertions** that stub or call the old name.
5. **Remove the corresponding entries from `phpmd.baseline.xml`.**

## Capabilities

### New Capabilities

(none)

### Modified Capabilities

- `orphaned-data-cleanup`: the interface method name changes; the tier semantics
  (Tier-A = auto-purgeable, Tier-B = requires admin inspection) are **unchanged**.

## Impact

**Blast radius (measured):**

- **5 declaration sites** (1 interface + 4 implementations)
- **1 production call site** (`CategoryRegistryService`)
- **5 PHPMD findings** retired
- No behaviour change whatsoever — this is a pure rename of a predicate.

**Risk: low.** `CleanupCategoryInterface` is an internal interface, not a public
extension point published to third-party apps, so the rename does not break an
external contract. The only sharp edge is a stale test double implementing the
interface under the old name, which would fail loudly rather than silently.

**Alternative considered and rejected:** adding an `is*` alias and deprecating the
`get*` name. That doubles the interface surface permanently to avoid a
six-call-site rename in a closed interface, and leaves the PHPMD findings in place
until the deprecated name is finally removed. Not worth it at this size.

## Decision requested

Approve, amend, or reject. If approved, the spec amendment lands first and the
rename follows in its own PR.
