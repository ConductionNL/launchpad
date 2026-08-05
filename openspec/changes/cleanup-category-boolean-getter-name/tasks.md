# Tasks — rename getSafeToPurgeAutomatically() to isSafeToPurgeAutomatically()

> **BLOCKED: awaiting a human decision on `proposal.md`.**
> Spec-first: nothing in section 2 may start until section 1 is merged.

## 1. Spec amendment (must land first, on its own)

- [ ] 1.1 `openspec/specs/orphaned-data-cleanup/spec.md` line 227 — rename in the
      normative statement about how a category exposes its tier.
- [ ] 1.2 Same file, line 314 — rename in the `CleanupCategoryInterface` method list.

## 2. Implementation (only after 1 is merged)

- [ ] 2.1 Rename on `lib/Service/Cleanup/CleanupCategoryInterface.php:79`.
- [ ] 2.2 Rename on `ExpiredLocksCategory.php:84`.
- [ ] 2.3 Rename on `OrphanedConditionalRulesCategory.php:81`.
- [ ] 2.4 Rename on `OrphanedSharesCategory.php:88`.
- [ ] 2.5 Rename on `OrphanedWidgetPlacementsCategory.php:80`.
- [ ] 2.6 Update the call site in `CategoryRegistryService.php` (~line 133).
- [ ] 2.7 Update any test double or assertion using the old name.

## 3. Verification

- [ ] 3.1 Full unit suite green (baseline 1525 tests / 3877 assertions / 3 skipped).
- [ ] 3.2 Re-measure PHPMD with the baseline deleted — expect 5 `BooleanGetMethodName`
      findings retired and no new findings.
- [ ] 3.3 Remove the corresponding entries from `phpmd.baseline.xml`.
- [ ] 3.4 Confirm Tier-A/Tier-B classification behaviour is unchanged (REQ-CLN-008).
