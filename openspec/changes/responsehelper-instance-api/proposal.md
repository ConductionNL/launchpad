# ResponseHelper: migrate from an all-static helper to an injected instance API

> **STATUS: RAISED FOR HUMAN DECISION — NOT APPROVED, NOT STARTED.**
> This change is deliberately spec-first. No code has been changed. See
> "Why this is a proposal and not a patch" below.

## Why

`ResponseHelper` is the single largest source of PHPMD debt in this repository, by a
wide margin.

Measured on `development` at commit `d086d16` with the PHPMD baseline deleted
(PHP 8.4, `phpmd.xml` + `phpmd-unusedparams.xml`):

| Measure | Count |
| --- | --- |
| Total findings, baseline deleted | 443 |
| …of which `StaticAccess` | 271 |
| …of which `StaticAccess` on `ResponseHelper::*` | **219 (49% of the repo total)** |

After the complexity burndown that accompanies this proposal the repository total
is 349, and `ResponseHelper` accounts for **218 of the 270 remaining
`StaticAccess` findings — 62% of everything left**. No other single change comes
close: retiring it would take launchpad from 349 findings to roughly 131.

The rule is not arbitrary. `rulesets/cleancode.xml/StaticAccess` fires because a
static call is a hard-coded, non-substitutable dependency: a controller that calls
`ResponseHelper::error()` cannot have that collaborator replaced in a test, cannot
have it decorated, and cannot have its behaviour varied per deployment. That is a
real design constraint, and it is the reason the project enabled the rule.

There is also a latent correctness issue the static form makes worse. The spec
itself records it (REQ-DASH-041, Notes):

> The "logger optional" path in `ResponseHelper::error()` is a latent risk: a
> caller that forgets to wire the logger gets silently dropped exceptions with no
> audit trail. Future-tightening TODO: make `LoggerInterface` non-nullable in a
> follow-up change once every call site is converted.

That future-tightening is **not achievable in the static form**. A static method
cannot hold an injected logger, so every call site must pass one explicitly, which
is exactly the thing callers forget. An injected instance receives its
`LoggerInterface` once, from the DI container, and the "forgot the logger" failure
mode disappears by construction. This proposal is therefore the enabling step for
a fix the spec has already asked for.

## Why this is a proposal and not a patch

**The static form is pinned by the canonical spec.** `openspec/specs/dashboards/spec.md`
does not merely describe the current implementation — it makes the static call
shape normative and then asserts it in scenarios:

- REQ-DASH-040: *"List responses MUST be serialized via `ResponseHelper::serializeList()`"*
- REQ-DASH-040, scenario: *"WHEN a controller calls `ResponseHelper::unauthorized()`"*
- REQ-DASH-040, scenario: *"WHEN a controller calls `ResponseHelper::forbidden('Dashboard creation not allowed')`"*
- REQ-DASH-040, scenario: *"WHEN a controller calls `ResponseHelper::success(['id' => 1], 201)`"*
- REQ-DASH-040, scenario: *"WHEN a controller calls `ResponseHelper::serializeList($dashboards)`"*
- REQ-DASH-041: *"MUST translate caught exceptions … via `ResponseHelper::error()`"*
- REQ-DASH-041, scenario: *"WHEN the controller calls `ResponseHelper::error($e, 500, $logger, 'Could not save dashboard')`"*

Unilaterally refactoring the code to `$this->responseHelper->error(...)` would put
the implementation in **direct contradiction with its own normative contract** —
seven spec statements would become false about the shipped code, and the spec would
silently become a description of a system that no longer exists. Quietly editing
the spec to match a refactor inverts the whole point of spec-driven development:
the spec would stop being a decision record and become a changelog.

So the correct sequencing is **spec first, code second**, and the spec change needs
a human decision because it is a deliberate architectural choice, not a mechanical
cleanup. That decision is what this document asks for.

## What Changes

If approved, in this order:

1. **Amend `openspec/specs/dashboards/spec.md`** — REQ-DASH-040 and REQ-DASH-041 are
   restated in terms of an injected `ResponseHelper` instance. The envelope
   contract (bodies, status codes, no-message-leak guarantee) is **unchanged**;
   only the call shape changes, `ResponseHelper::x()` → `$this->responseHelper->x()`.
2. **Convert `ResponseHelper` to an instance class**, registered in
   `lib/AppInfo/Application.php` and injected into the 18 `lib/` files that use it.
   `LoggerInterface` becomes a constructor dependency.
3. **Update the 224 call sites** across those 18 files.
4. **Update the 2 test files** that reference `ResponseHelper`.
5. **Tighten `error()`** so `LoggerInterface` is no longer an optional per-call
   argument — discharging the standing TODO in the REQ-DASH-041 notes.
6. **Remove the corresponding entries from `phpmd.baseline.xml`.**

### Deliberately NOT in scope

- No change to any response body, status code, or header. This is a call-shape
  migration; every envelope stays byte-identical, and the existing scenarios
  (rewritten for the instance form) are the regression net.
- No change to the other 52 non-`ResponseHelper` `StaticAccess` findings; 24 of
  those are `*TableBuilder::create($schema)` in `lib/Migration`, which are
  structurally static because NC's migrator instantiates migration steps itself
  rather than through DI, and cannot be injected.

## Capabilities

### New Capabilities

(none)

### Modified Capabilities

- `dashboards`: REQ-DASH-040 and REQ-DASH-041 restated for an injected instance API.

## Impact

**Blast radius (measured, not estimated):**

- **18 files** under `lib/` reference `ResponseHelper::`
- **224 static call sites** in `lib/`
- **2 test files** reference `ResponseHelper`
- **218 PHPMD `StaticAccess` findings** retired — 62% of everything currently left
- `ResponseHelper` itself is small: **169 lines, 6 public static methods**
  (`unauthorized`, `forbidden`, `error`, `quotaExceeded`, `success`, `serializeList`)

**Risk:** low-to-moderate and almost entirely mechanical. The class is tiny and
stateless, and the change is uniform. The two genuine risks are:

1. **Constructor churn in 18 controllers/services.** Several already carry
   `@SuppressWarnings(PHPMD.CouplingBetweenObjects)` for constructor width; adding
   one more collaborator will push some past `ExcessiveParameterList` /
   `CouplingBetweenObjects` thresholds. Net PHPMD effect must be re-measured —
   trading 218 `StaticAccess` for a handful of coupling findings is still a large
   win, but the number should be measured, not assumed.
2. **`serializeList()` is a pure function** with no dependencies. Making it an
   instance method is the least defensible part of the change on its own merits;
   the argument for it is uniformity of the call shape, not testability. A
   reviewer may reasonably prefer to leave `serializeList()` static, which would
   leave a residue of `StaticAccess` findings. That trade-off is called out here
   rather than decided unilaterally.

**Alternative considered and rejected:** adding `ResponseHelper` to a PHPMD
`StaticAccess` exceptions list. That would make the 218 findings disappear without
changing anything real, would grant a permanent open licence for all future static
use of the class, and would leave the REQ-DASH-041 logger TODO permanently
unachievable. It is a suppression, not a fix.

## Decision requested

Approve, amend, or reject the migration. If approved, the spec amendment (step 1)
lands first and the code follows in its own PR.
