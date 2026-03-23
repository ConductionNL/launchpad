---
name: app-create
description: Bootstrap or onboard a Nextcloud app — creates the appspec/ config folder, clones the template for new apps, or uses the template as a reference for existing repos
---

# App Create — Bootstrap or Onboard a Nextcloud App

Sets up a new Nextcloud app from the ConductionNL template, or onboards an existing repo. Always creates an `appspec/` configuration folder that tracks all app decisions and can be evolved over time with `/app-explore` and `/app-apply`.

**Template reference:** `https://github.com/ConductionNL/nextcloud-app-template`

**Input**: Optional argument — the app ID (e.g. `/app-create my-new-app`).

---

## Phase 0: Existing Repo Check

Ask the user using AskUserQuestion:

**"Does a local folder for this app already exist in `apps-extra`?"**

- **Yes** → List directories in `apps-extra/` and let the user select one. Store as `{APP_DIR}`. Load `appspec/app-config.json` from that directory if it already exists (skip questions whose answers are already stored). The template will be used as a **reference** only — comparing its structure against the existing app and proposing files to add or update.
- **No** → Proceed to collect all details from scratch. The template will be cloned as the starting point.

---

## Phase 1: Basic Identity

> Skip any question whose answer is already present in a loaded `app-config.json`.

**"What is the app ID (kebab-case, e.g. `my-new-app`)?"**

Store as `{APP_ID}`. This becomes the Nextcloud app identifier, folder name, and GitHub repo name.

**"What is the human-readable name (e.g. `My New App`)?"**

If the user doesn't have a name yet, offer to help using the **Name Generator**:

### Name Generator (when user says "help me pick a name" or "I don't have a name yet")

1. Ask: **"Describe what the app does in 1-2 sentences."** (use `{APP_GOAL}` if already provided)
2. Generate **10 unique name suggestions** following Conduction's naming pattern:
   - Short: 5-12 characters, 1-2 words
   - Memorable: easy to pronounce in Dutch AND English
   - Functional: hints at what the app does (like Procest=process, Pipelinq=pipeline, Docudesk=document desk)
   - Creative: blend Dutch/English, use wordplay, portmanteaus, or abbreviations
3. For EACH name, verify uniqueness:
   - Check `gh repo list ConductionNL --json name --jq '.[].name'` — not already a repo
   - WebSearch `"<name> software"` — not a major existing product
   - If conflict found, replace with a new unique name (always deliver 10)
4. Present as a table:
   ```
   | # | Name | Rationale | Available? |
   |---|------|-----------|------------|
   | 1 | Procura | procure + cura (care) | Yes |
   ```
5. Let the user pick one, or generate 10 more

Store as `{APP_NAME}`.

**"What is the goal or purpose of this app? Describe what it does and the problem it solves."**

This becomes the long-form description used in `README.md` and the GitHub repository description. Can be multiple sentences.

Store as `{APP_GOAL}`.

**"One-line summary (English, ~100 chars)?"**

Store as `{APP_SUMMARY}`.

**"Which Nextcloud category fits best?"**
- **organization** — Productivity, workflows, case management
- **tools** — Utilities and developer tools
- **integration** — Connects Nextcloud to external services
- **files** — File management and document handling
- **social** — Communication and collaboration
- **office** — Office/document editing

Store as `{APP_CATEGORY}`.

---

## Phase 1b: Competitive Intelligence Check

Before collecting more details, check if competitive research already exists for this app idea.

### Check the Application Roadmap

Read `apps-extra/concurrentie-analyse/application-roadmap.md`. Search for `{APP_ID}` or `{APP_NAME}` in the roadmap entries.

- **If found** → Display the existing roadmap entry (status, description, competitors, market size, revenue model) and ask: _"I found an existing roadmap entry for this app. Should I use this information to pre-fill the configuration?"_ If yes, extract `{APP_GOAL}`, `{APP_SUMMARY}`, `{APP_CATEGORY}` from the entry where possible.
- **If not found** → Note this and offer to add it later (Phase 11).

### Check for Competitor Analysis

Check if `apps-extra/concurrentie-analyse/{APP_ID}/` exists.

- **If found** → Scan for `overview.md`, `MERGED-ANALYSIS.md`, and `specs/` files. Summarize:
  - How many competitors were analyzed
  - Key differentiators identified
  - Feature gaps or opportunities noted

  Display this summary and note: _"This competitive intelligence will be available during `/app-explore` to inform feature planning."_

- **If not found** → Check if a similar folder exists (e.g., the app might be under a different name). If nothing found, note: _"No competitive analysis found. Consider running a competitor analysis in `concurrentie-analyse/{APP_ID}/` before feature planning."_

---

## Phase 2: Dependencies

**"Does this app require OpenRegister as a foundation? (OpenRegister provides the data storage layer used by most ConductionNL apps)"**

- **Yes** → Set `{REQUIRES_OPENREGISTER}` = `true`
- **No** → Set `{REQUIRES_OPENREGISTER}` = `false`

**"Are there additional app dependencies for the CI code-quality workflow? (other apps that must be installed alongside this one for tests to pass)"**

- **Yes** → Ask: `"Enter each dependency as 'Organisation/repo-name' (e.g. ConductionNL/openregister). Separate multiple with commas."` Parse into a JSON array of `{"repo":"...","app":"...","ref":"main"}` objects. Store as `{CI_ADDITIONAL_APPS}`.
- **No** → Set `{CI_ADDITIONAL_APPS}` = `[]`.

If `{REQUIRES_OPENREGISTER}` is `true`, automatically include `{"repo":"ConductionNL/openregister","app":"openregister","ref":"main"}` in `{CI_ADDITIONAL_APPS}` unless the user already added it.

---

## Phase 3: Derive Name Variants

From `{APP_ID}`, derive the following variants used throughout the codebase:

| Variable | Rule | Example (`my-new-app`) |
|---|---|---|
| `{APP_ID}` | raw | `my-new-app` |
| `{APP_ID_SNAKE}` | replace `-` with `_` | `my_new_app` |
| `{APP_NAMESPACE}` | PascalCase each segment | `MyNewApp` |
| `{APP_NAME_UPPER}` | ALL_CAPS with `_` | `MY_NEW_APP` |

---

## Phase 4: Create `appspec/` Configuration Folder

Always create (or update) the `appspec/` folder. Write `apps-extra/{APP_ID}/appspec/app-config.json`:

```json
{
  "id": "{APP_ID}",
  "name": "{APP_NAME}",
  "namespace": "{APP_NAMESPACE}",
  "summary": "{APP_SUMMARY}",
  "goal": "{APP_GOAL}",
  "category": "{APP_CATEGORY}",
  "version": "0.1.0",
  "license": "EUPL-1.2",
  "author": "Conduction B.V.",
  "repository": "https://github.com/{GITHUB_ORG}/{APP_ID}",
  "dependencies": {
    "requiresOpenRegister": {REQUIRES_OPENREGISTER},
    "additionalCiApps": {CI_ADDITIONAL_APPS}
  },
  "cicd": {
    "phpVersions": ["8.3", "8.4"],
    "nextcloudRefs": ["stable31", "stable32"],
    "enableNewman": false
  },
  "createdAt": "{TODAY}",
  "updatedAt": "{TODAY}"
}
```

Note: `{GITHUB_ORG}` will be set in Phase 6. Use a placeholder value for now if not yet known.

Also write `apps-extra/{APP_ID}/appspec/README.md`:

```markdown
# {APP_NAME} — App Specification

This folder contains the configuration and specification for {APP_NAME}.

## Goal

{APP_GOAL}

## Structure

| File / Folder | Purpose |
|---|---|
| `app-config.json` | Core app configuration — all choices from `/app-create` and `/app-explore` |
| `features/` | Feature definitions (created during `/app-explore` sessions) |

## Commands

- `/app-explore` — Think through and update app configuration interactively
- `/app-apply` — Apply `app-config.json` changes to the actual app files
```

---

## Phase 5: Scaffold the App

### New app (no existing folder)

1. Clone the template:
   ```bash
   git clone https://github.com/ConductionNL/nextcloud-app-template apps-extra/{APP_ID}
   ```
2. Remove template git history:
   ```bash
   rm -rf apps-extra/{APP_ID}/.git
   ```

### Existing folder

1. Clone the template to a temporary location for comparison:
   ```bash
   git clone https://github.com/ConductionNL/nextcloud-app-template /tmp/nextcloud-app-template-ref
   ```
2. Compare the template structure against `apps-extra/{APP_DIR}/`. List files present in the template but missing from the existing repo.
3. Present the list and ask the user which files to copy over. Do **not** overwrite existing files without approval.
4. Copy only approved files.

---

## Phase 6: GitHub Repository

Ask the user using AskUserQuestion:

**"Does a GitHub repository for this app already exist?"**

### If no repository exists yet

Ask:

**"Should I create a new GitHub repository? If yes, which organisation should it be created under? (e.g. `ConductionNL`)"**

- **Yes + org name** → Store org as `{GITHUB_ORG}`. Create the repository:
  ```bash
  gh repo create {GITHUB_ORG}/{APP_ID} \
    --public \
    --description "{APP_SUMMARY}" \
    --confirm
  ```
  If `gh` is not available or the command fails, show the user the manual URL to create the repo: `https://github.com/organizations/{GITHUB_ORG}/repositories/new`

- **No** → Skip remote setup. Note in the final report that this step is still pending.

### Connect the local folder to the remote

After the repository exists (created now or already existed):

```bash
cd apps-extra/{APP_ID}
git init
git add -A
git commit -m "Initial scaffold from nextcloud-app-template"
git remote add origin https://github.com/{GITHUB_ORG}/{APP_ID}.git
git push -u origin main
```

### Ensure `development` and `beta` branches exist

```bash
git checkout -b development
git push -u origin development
git checkout -b beta
git push -u origin beta
git checkout main
```

### Set branch protection rules

Ask the user using AskUserQuestion:

**"Set branch protection on `main`? (Requires pull requests, blocks direct pushes)"**

If yes:
```bash
printf '{"required_status_checks":null,"enforce_admins":false,"required_pull_request_reviews":{"required_approving_review_count":0},"restrictions":null}' | \
  gh api --method PUT /repos/{GITHUB_ORG}/{APP_ID}/branches/main/protection --input -
```

### Team access

List the available teams in the organisation:
```bash
gh api orgs/{GITHUB_ORG}/teams --jq '.[] | "\(.slug) — \(.name)"'
```

If the organisation is `ConductionNL`, suggest adding the `developers` team as maintainer:

**"Add the `developers` team as maintainer to this repo?"**

If yes:
```bash
gh api --method PUT /orgs/ConductionNL/teams/developers/repos/ConductionNL/{APP_ID} \
  -f permission=maintain
```

Then ask:

**"Any other teams to add? (Leave blank to skip)"**

For each additional team the user names, ask which role (`pull`, `push`, `maintain`, `admin`) and run:
```bash
gh api --method PUT /orgs/{GITHUB_ORG}/teams/{team_slug}/repos/{GITHUB_ORG}/{APP_ID} \
  -f permission={role}
```

Update `appspec/app-config.json` with the correct `repository` value now that `{GITHUB_ORG}` is known.

---

## Phase 7: Replace All Template Placeholders

For every file in `apps-extra/{APP_ID}/` (excluding `node_modules/`, `vendor/`, `.git/`, `appspec/`), replace all occurrences of the following:

| Template value | Replace with |
|---|---|
| `app-template` | `{APP_ID}` |
| `app_template` | `{APP_ID_SNAKE}` |
| `AppTemplate` | `{APP_NAMESPACE}` |
| `APP_TEMPLATE` | `{APP_NAME_UPPER}` |
| `Nextcloud App Template` | `{APP_NAME}` |
| `A template for creating new Nextcloud apps` | `{APP_SUMMARY}` |
| `A starting point for building Nextcloud apps following ConductionNL conventions` | `{APP_GOAL}` |
| `ConductionNL/nextcloud-app-template` | `{GITHUB_ORG}/{APP_ID}` |

**Read every file before editing. Use the Edit tool only — never use sed or awk.**

Work through these files in order:

### Core metadata
1. **`appinfo/info.xml`**
   - `<id>` → `{APP_ID}`
   - `<name lang="en">` and `<name lang="nl">` → `{APP_NAME}`
   - `<summary lang="en">` → `{APP_SUMMARY}`
   - `<summary lang="nl">` → translated or same as EN
   - `<description lang="en">` → rewrite using `{APP_GOAL}` as the opening paragraph; keep the support/install footer
   - `<description lang="nl">` → rewrite NL version
   - `<namespace>` → `{APP_NAMESPACE}`
   - `<category>` → `{APP_CATEGORY}`
   - `<id>` inside `<navigation>` → `{APP_ID}`
   - `<route>` → `{APP_ID_SNAKE}.dashboard.page`
   - `<repository>`, `<bugs>`, `<website>`, `<discussion>` → `https://github.com/{GITHUB_ORG}/{APP_ID}` (and `/issues`, `/discussions` suffixes)
   - `<screenshot>` URL → `https://raw.githubusercontent.com/{GITHUB_ORG}/{APP_ID}/main/img/app-store.svg`

2. **`appinfo/routes.php`** — no app-specific strings, but verify namespace references are correct

3. **`README.md`**
   - The `<h1>` title → `{APP_NAME}`
   - The `<strong>` tagline → `{APP_SUMMARY}`
   - All badge URLs → `https://github.com/{GITHUB_ORG}/{APP_ID}`
   - Opening description paragraph → `{APP_GOAL}`
   - All `app-template` references → `{APP_ID}`
   - All `Nextcloud App Template` references → `{APP_NAME}`
   - Related Apps section → remove the template entry; leave placeholder for the user to populate

### PHP backend
4. **`composer.json`**
   - `"name"` → `"conductionnl/{APP_ID}"`
   - PSR-4 key → `"OCA\\{APP_NAMESPACE}\\":`

5. **`lib/AppInfo/Application.php`**
   - Namespace → `OCA\{APP_NAMESPACE}\AppInfo`
   - `APP_ID` constant → `'{APP_ID}'`
   - Class docblock → update app name and link

6. **`lib/Controller/DashboardController.php`**
   - Namespace → `OCA\{APP_NAMESPACE}\Controller`
   - `use OCA\{APP_NAMESPACE}\AppInfo\Application;`
   - Class docblock → update app name

7. **`lib/Settings/AdminSettings.php`**
   - Namespace → `OCA\{APP_NAMESPACE}\Settings`
   - `use OCA\{APP_NAMESPACE}\AppInfo\Application;`
   - `getSection()` return value → `'{APP_ID}'`
   - Class docblock → update app name

8. **`lib/Sections/SettingsSection.php`**
   - Namespace → `OCA\{APP_NAMESPACE}\Sections`
   - `getID()` return value → `'{APP_ID}'`
   - `getName()` return value → `$this->l->t('{APP_NAME}')`
   - `getIcon()` appName argument → `'{APP_ID}'`
   - Class docblock → update app name

### Templates
9. **`templates/index.php`**
   - `use OCA\{APP_NAMESPACE}\AppInfo\Application;`

10. **`templates/settings/admin.php`**
    - `use OCA\{APP_NAMESPACE}\AppInfo\Application;`
    - `id` attribute on div → `'{APP_ID_SNAKE}-settings'`

### Frontend
11. **`package.json`**
    - `"name"` → `"{APP_ID}"`

12. **`webpack.config.js`**
    - `const appId = '{APP_ID}'`

13. **`src/main.js`**
    - `loadTranslations('{APP_ID}', ...)`

14. **`src/settings.js`**
    - `loadTranslations('{APP_ID}', ...)`
    - `.$mount('#{APP_ID_SNAKE}-settings')`

15. **`src/App.vue`**
    - `app-name="{APP_ID}"`
    - All `t('{APP_ID}', ...)` translation calls
    - `imagePath('{APP_ID}', ...)`
    - `loadTranslations('{APP_ID}', ...)`
    - If `{REQUIRES_OPENREGISTER}` is `false`: remove the entire OpenRegister gate section (`v-if="!hasOpenRegisters"` block and its associated computed props)

16. **`src/views/settings/AdminRoot.vue`**
    - `document.getElementById('{APP_ID_SNAKE}-settings')`
    - All `t('{APP_ID}', ...)` translation calls
    - Class name on root div → `.{APP_ID_SNAKE}-admin`

17. **`src/views/settings/Settings.vue`**
    - All `t('{APP_ID}', ...)` translation calls

18. **`src/views/Dashboard.vue`**
    - All `t('{APP_ID}', ...)` translation calls

19. **`src/router/index.js`**
    - `generateUrl('/apps/{APP_ID}')`

20. **`src/store/modules/settings.js`**
    - Fetch URL → `generateUrl('/apps/{APP_ID}/api/settings')`
    - If `{REQUIRES_OPENREGISTER}` is `false`: remove `hasOpenRegisters` state and the openregisters check from `fetchSettings`

21. **`src/assets/app.css`**
    - Comment reference → `{APP_ID}`

### Quality config
22. **`phpstan.neon`**
    - Remove any Procest/AppTemplate-specific `ignoreErrors` entries (keep only generic ones)

23. **`phpcs.xml`**
    - `<description>` → `Coding standard for {APP_NAME}, based on the Conduction/OpenRegister standard.`

24. **`phpmd.xml`**
    - `<ruleset name="{APP_NAME} Nextcloud Rules">`
    - `<description>` → `This is a custom ruleset for {APP_NAME} Nextcloud.`

### CI/CD workflows
25. **`.github/workflows/code-quality.yml`**
    - `app-name: {APP_ID}`
    - If `{CI_ADDITIONAL_APPS}` is non-empty: set `additional-apps: '{CI_ADDITIONAL_APPS_JSON}'`
    - If `{CI_ADDITIONAL_APPS}` is empty: ensure the `additional-apps` line is commented out

26. **`.github/workflows/release-beta.yml`**
    - `app-name: {APP_ID}`

27. **`.github/workflows/release-stable.yml`**
    - `app-name: {APP_ID}`

28. **`.github/workflows/documentation.yml`**
    - `cname: {APP_ID}.app` (or remove if no docs site planned)

29. **`.github/workflows/issue-triage.yml`**
    - `app-name: {APP_ID}`

30. **`.github/workflows/openspec-sync.yml`**
    - `app-name: {APP_ID}`

### Backend services and listeners

31. **`lib/Controller/SettingsController.php`**
    - Namespace → `OCA\{APP_NAMESPACE}\Controller`
    - `use OCA\{APP_NAMESPACE}\AppInfo\Application;`
    - `use OCA\{APP_NAMESPACE}\Service\SettingsService;`
    - Class docblock → update app name and link

32. **`lib/Service/SettingsService.php`**
    - Namespace → `OCA\{APP_NAMESPACE}\Service`
    - `use OCA\{APP_NAMESPACE}\AppInfo\Application;`
    - All log messages → replace `AppTemplate:` with `{APP_NAMESPACE}:`
    - Config file path → `{APP_ID_SNAKE}_register.json`
    - Class docblock → update app name

33. **`lib/Listener/DeepLinkRegistrationListener.php`**
    - Namespace → `OCA\{APP_NAMESPACE}\Listener`
    - `appId:` parameter → `'{APP_ID}'`
    - `registerSlug:` → `'{APP_ID}'`
    - `urlTemplate:` → `'/apps/{APP_ID}/#/examples/{uuid}'`
    - Class docblock → update app name

34. **`lib/Repair/InitializeSettings.php`**
    - Namespace → `OCA\{APP_NAMESPACE}\Repair`
    - `use OCA\{APP_NAMESPACE}\Service\SettingsService;`
    - `getName()` return → `'Initialize {APP_NAME} register and schemas via ConfigurationService'`
    - All log/output messages → replace `AppTemplate` with `{APP_NAMESPACE}`
    - Class docblock → update app name

35. **`lib/Settings/app_template_register.json`**
    - **Rename file** to `{APP_ID_SNAKE}_register.json`
    - `"title"` → `"{APP_NAME} Register"`
    - `"app"` → `"{APP_ID}"`
    - `"description"` → `"{APP_SUMMARY}"`

### Frontend navigation and user settings

36. **`src/navigation/MainMenu.vue`**
    - All `t('{APP_ID}', ...)` translation calls

37. **`src/views/settings/UserSettings.vue`**
    - All `t('{APP_ID}', ...)` translation calls
    - Fetch URL → `'/apps/{APP_ID}/api/user/settings'`

38. **`src/store/store.js`**
    - No app-specific strings, but verify object type registration comments reference the correct register JSON name

### Support files

39. **`l10n/en.json`**
    - Replace all `"App Template"` values with `"{APP_NAME}"`
    - Replace all `"app-template"` references with `"{APP_ID}"`

40. **`l10n/nl.json`**
    - Same replacements as `en.json`

41. **`tests/bootstrap.php`**
    - Namespace in docblock → `OCA\{APP_NAMESPACE}\Tests`
    - Class docblock → update app name

42. **`tests/unit/Controller/SettingsControllerTest.php`**
    - Namespace → `OCA\{APP_NAMESPACE}\Tests\Unit\Controller`
    - `use OCA\{APP_NAMESPACE}\Controller\SettingsController;`
    - `use OCA\{APP_NAMESPACE}\Service\SettingsService;`
    - Class docblock → update app name

43. **`openspec/config.yaml`**
    - `Project:` → `{APP_NAME}`
    - `Repo:` → `{GITHUB_ORG}/{APP_ID}`
    - `Description:` → `{APP_GOAL}`
    - `Mount path:` → `/var/www/html/custom_apps/{APP_ID}`

44. **`project.md`**
    - Title → `{APP_NAME}`
    - All `app-template` references → `{APP_ID}`

45. **`phpunit.xml`**
    - No app-specific strings, but verify it references `tests/bootstrap.php`

After all replacements are done, verify by searching for any remaining occurrences of `app-template`, `AppTemplate`, `app_template`, or `APP_TEMPLATE` (excluding `appspec/` and binary files):

```bash
grep -r "app-template\|AppTemplate\|app_template\|APP_TEMPLATE" apps-extra/{APP_ID} \
  --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=.git --exclude-dir=appspec \
  -l
```

If any files still contain placeholders, read and fix them before continuing.

---

## Phase 8: Commit All Changes

```bash
cd apps-extra/{APP_ID}
git add -A
git commit -m "Apply {APP_NAME} identity — replace all template placeholders"
git push origin main
git push origin development
git push origin beta
```

---

## Phase 9: Optional — Install Dependencies

Ask the user using AskUserQuestion:

**"Install Composer and npm dependencies now?"**

If yes:
```bash
cd apps-extra/{APP_ID} && composer install --no-dev && npm install
```

---

## Phase 10: Optional — Enable in Nextcloud

Ask the user using AskUserQuestion:

**"Enable the app in the local Nextcloud environment now? (requires Docker at localhost:8080)"**

If yes:
```bash
docker exec nextcloud php occ app:enable {APP_ID}
docker exec nextcloud php occ app:list | grep {APP_ID}
```

---

## Phase 11: Update Application Roadmap

Read `apps-extra/concurrentie-analyse/application-roadmap.md`.

- **If the app already has an entry:** Update its `Status` to `In Development` (or keep the existing status if already more advanced).
- **If the app does NOT have an entry:** Ask the user: _"Should I add {APP_NAME} to the application roadmap?"_ If yes, append a new entry using the roadmap template with the information gathered during this session (`{APP_GOAL}`, `{APP_SUMMARY}`, `{APP_CATEGORY}`, competitor info from Phase 1b if available).

---

## Phase 12: Report

```
✓ appspec/app-config.json — configuration saved
✓ App folder: apps-extra/{APP_ID}
✓ All template placeholders replaced with {APP_NAMESPACE}
✓ CI/CD workflows configured (OpenRegister: {yes/no}, Additional apps: {count})
✓ GitHub repository: https://github.com/{GITHUB_ORG}/{APP_ID}
✓ Branches: main + beta + development
✓ Branch protection on main: {yes/no}
✓ Team access configured: {teams or 'pending — see manual steps below'}
[ ] Dependencies installed: {yes/no}
[ ] Enabled in Nextcloud: {yes/no}

⚠️  Important next step: Run /app-explore {APP_ID} before building anything.
   Exploration defines the features and ADRs that guide all implementation work.
   Without it, you will be building without a blueprint.

Manual steps (if not completed above):
  □ Add teams to the GitHub repository:
    https://github.com/{GITHUB_ORG}/{APP_ID}/settings/access
    → Recommended: add 'developers' team with Maintain role (ConductionNL org)
  □ Set up branch protection rules for main:
    https://github.com/{GITHUB_ORG}/{APP_ID}/settings/branches

Next steps:
1. Define features and ADRs:                  /app-explore {APP_ID}   ← START HERE
2. Apply config changes to code:              /app-apply {APP_ID}
3. Verify app files match config:             /app-verify {APP_ID}
4. Implement planned features:                /opsx:ff {feature-name}
```
