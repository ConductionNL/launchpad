# Tasks — rename getSafeToPurgeAutomatically() to isSafeToPurgeAutomatically()

> Approved by Ruben on 19 Nov 2026.
> Spec-first: nothing in section 2 may start until section 1 is merged.

## 1. Spec amendment (must land first, on its own)

- [x] 1.1 `openspec/specs/orphaned-data-cleanup/spec.md` line 227 — rename in the
      normative statement about how a category exposes its tier.
- [x] 1.2 Same file, line 314 — rename in the `CleanupCategoryInterface` method list.

## 2. Implementation (only after 1 is merged)

- [x] 2.1 Rename on `lib/Service/Cleanup/CleanupCategoryInterface.php:79`.
- [x] 2.2 Rename on `ExpiredLocksCategory.php:84`.
- [x] 2.3 Rename on `OrphanedConditionalRulesCategory.php:81`.
- [x] 2.4 Rename on `OrphanedSharesCategory.php:88`.
- [x] 2.5 Rename on `OrphanedWidgetPlacementsCategory.php:80`.
- [x] 2.6 Update the call site in `CategoryRegistryService.php` (~line 133).
- [x] 2.7 Update any test double or assertion using the old name.

## 3. Verification

- [ ] 3.1 Full unit suite green (baseline 1525 tests / 3877 assertions / 3 skipped).
- [x] 3.2 Re-measure PHPMD with the baseline deleted — expect 5 `BooleanGetMethodName`
      findings retired and no new findings.
- [x] 3.3 Remove the corresponding entries from `phpmd.baseline.xml`.
- [x] 3.4 Confirm Tier-A/Tier-B classification behaviour is unchanged (REQ-CLN-008).
