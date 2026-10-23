---
kind: code
depends_on: []
---

# Find colleagues by expertise and show extra profile fields such as office location

## Why

The people widget shows colleagues with their standard Nextcloud profile
fields (`lib/Service/PeopleWidgetService.php:91-103`: phone, address,
organisation, role, headline, biography, pronouns, birthdate). It filters by
group only (`extractGroupFilter()`, line 462), and its search box matches name
and email on the current page only (REQ-PPL-011). Nobody can find "the colleague
who knows about subsidies", and an organisation cannot add fields such as
office location or cost centre.

This change covers two rows of the LaunchPad parity matrix
(`openspec/parity/capabilities.json`). Both live in the people widget and its
service, so they are one change.

**d-find-expert**, "Find a colleague by the expertise listed on their profile." Rated `no`, `built.state` `none`.

- Demand: tender, https://www.tenderned.nl/aankondigingen/overzicht/418890. Gemeente Noordwijk 2026: "Onze persoonlijke trefwoorden (profiel tags) op ons intranet-profiel zijn volledig en actueel", so KCC colleagues can find them.
- Workspace 365, yes: https://support.workspace365.net/en/articles/175651-address-book users search colleagues by "names, job titles, email address, departments and office locations, skills and expertise, interests, and more".
- Microsoft Viva, yes: https://learn.microsoft.com/en-us/copilot/microsoft-365/people-skills-overview GA "Skills in Traditional People Search (SharePoint people search)".

**d-profile-fields**, "Colleague profiles show extra fields such as office location taken from the identity provider." Rated `partial`, `built.state` `built` (standard fields, `PeopleWidgetService.php:91`). This change specifies the missing half: custom fields.

- Demand: changelog, https://support.workspace365.net/en/articles/766599-release-notes-workspace-365-v4-46.
- Workspace 365, yes: v4.46 (2026-08-19) "map up to five fields from Entra ID to custom fields in Workspace 365".
- Nextcloud dashboard, yes: the profile page shows address, organisation, role and headline, and the shipped `user_ldap` fills them from directory attributes (`apps/user_ldap/lib/Configuration.php:78-83`).

## What changes

- **Custom profile fields.** An administrator defines up to 10 extra fields (for example "Office location", "Cost centre", "Expertise"), each either filled by the person on their LaunchPad personal settings, or read from an LDAP attribute when the account comes from LDAP.
- **Expertise tags.** A field can be of type tags, so people list their subjects ("subsidies", "Omgevingswet", "Excel").
- **Search that finds people.** The people widget's search asks the server and matches name, email, role, headline, biography and every searchable custom field across the whole directory, a page at a time.
- **Privacy.** Search and display use only fields the viewer may see under the person's Nextcloud profile visibility, so a private biography is never matched.

## Capabilities

### Modified capabilities

- `people-widget`: adds custom fields, expertise tags and server-side search.

## Impact

- New table `oc_launchpad_profile_values`, new `ProfileFieldService`, a personal settings section (`lib/Settings/LaunchPadPersonal.php`), an admin section for field definitions
- `lib/Service/PeopleWidgetService.php` and `lib/Controller/PeopleWidgetController.php` (`GET /api/people?q=`)
- A login listener and a daily job for LDAP-sourced fields, through `OCP\LDAP\ILDAPProviderFactory`

## Out of scope

- Reading claims from an OpenID Connect provider. `user_oidc` does not expose arbitrary claims to other apps; organisations on Entra ID with LDAP sync, or with people filling the fields themselves, are covered.
