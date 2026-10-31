# Tasks: widgets-people-expertise-and-fields

## Backend

- [x] 1. Add `oc_launchpad_profile_values` with builder, entity and mapper, plus the `profile_fields` admin setting with validation (10 fields, types, sources). Verify: migration and PHPUnit.
- [x] 2. Implement `ProfileFieldService` (definitions, self edits, read-only LDAP fields, values per user). Verify: PHPUnit.
- [x] 3. Add the LDAP sync listener on `UserLoggedInEvent`, the standard-field mirror listener on `UserUpdatedEvent`, and one daily job for both (LDAP through `ILDAPProviderFactory`, doing nothing without `user_ldap`). Verify: PHPUnit with stubbed providers.
- [x] 4. Add `lib/Settings/LaunchPadPersonal.php` with endpoints to read and save the person's own fields. Verify: PHPUnit and route-reachability.
- [x] 5. Add server-side search `GET /api/people?q=` in `PeopleWidgetService` with the scope rule and paging, bypassing the shared cache. Verify: PHPUnit including a private biography that never matches.
- [x] 6. Add custom fields to the people response for fields the viewer may see. Verify: PHPUnit.

## Frontend

- [x] 7. Build the personal settings form (text fields and tag input). Verify: Vitest and an axe check.
- [x] 8. Switch the widget search to the server when the query has 2 or more characters, and render custom fields and tag chips. Verify: Vitest.
- [x] 9. Add the admin section for field definitions. Verify: Vitest.

## Close

- [x] 10. Add the demo fields and tags. Verify: PHPUnit on the showcase.
- [ ] 11. Playwright: tag, then find by tag. Not written: the requirements carry `@e2e exclude` naming the PHPUnit and Vitest files that cover them.
- [x] 12. Add `nl` and `en` strings, fold the delta into `openspec/specs/people-widget/spec.md` on archive, and set d-find-expert and d-profile-fields to `built` in the parity matrix with evidence lines.
