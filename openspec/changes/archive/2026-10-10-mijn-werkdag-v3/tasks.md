# Tasks: mijn-werkdag-v3

- [x] 1. `hideWhenUnavailable`: the page asks OpenRegister once per source and leaves a 404 list out, outside edit mode. Verify: `ViewsHideUnavailableSource.spec.js`.
- [x] 2. Template version 3 with "Mijn tickets" and "Wacht op uw stem", every list hideable. Verify: `ShippedTemplateServiceTest::testTheShippedDefinitionIsWellFormed`, `::testAShippedTemplateRendersAsInstalled` with the two new register fixtures.
- [x] 3. Listing, Templates page and command name the registers the lists read. Verify: `ShippedTemplateServiceTest::testTheListingNamesWidgetsNothingRegisters`, `TemplatesPage.shipped.spec.js`, `TemplateInstallCommandTest::testInstallsForAGroup`.
- [ ] 4. Live: update an instance from version 2 with `--update`, see a member's page without pipelinq and decidiq. See the PR body. (live pass, decision 139)
