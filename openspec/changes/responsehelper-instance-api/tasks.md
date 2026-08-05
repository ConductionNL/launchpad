# Tasks — ResponseHelper instance API

> **BLOCKED: awaiting a human decision on `proposal.md`.**
> Nothing below may start until the proposal is approved. The sequencing is
> spec-first by design — see "Why this is a proposal and not a patch".

## 1. Spec amendment (must land first, on its own)

- [ ] 1.1 Amend `openspec/specs/dashboards/spec.md` REQ-DASH-040: restate the
      envelope contract for an injected instance; rewrite the four scenarios that
      name `ResponseHelper::unauthorized()`, `::forbidden()`, `::success()` and
      `::serializeList()`.
- [ ] 1.2 Amend REQ-DASH-041: restate for `$this->responseHelper->error()`; rewrite
      both scenarios.
- [ ] 1.3 Decide and record whether `serializeList()` stays static (it is a pure
      function with no dependencies — see Impact risk 2).

## 2. Implementation (only after 1 is merged)

- [ ] 2.1 Convert `lib/Controller/ResponseHelper.php` to an instance class with a
      constructor-injected `LoggerInterface`.
- [ ] 2.2 Register it in `lib/AppInfo/Application.php`.
- [ ] 2.3 Inject it into the 18 `lib/` files that use it; convert all 224 call sites.
- [ ] 2.4 Make `LoggerInterface` non-optional in `error()`, discharging the standing
      TODO in the REQ-DASH-041 notes.
- [ ] 2.5 Update the 2 test files referencing `ResponseHelper`.

## 3. Verification

- [ ] 3.1 Full unit suite green (baseline 1525 tests / 3877 assertions / 3 skipped).
- [ ] 3.2 Re-measure PHPMD with the baseline deleted. Expect ~218 `StaticAccess`
      findings retired. **Measure the net** — constructor widening may add
      `ExcessiveParameterList` / `CouplingBetweenObjects` findings (Impact risk 1).
- [ ] 3.3 Remove the corresponding entries from `phpmd.baseline.xml`.
- [ ] 3.4 Confirm no response body, status code or header changed.
