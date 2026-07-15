# LaunchPad Walkthrough Findings — 2026-04-29

## Console findings — initial dashboard load (/apps/launchpad/ as launchpad-admin)

### LaunchPad bugs (in launchpad-main.js)
- **[WARN] NcButton missing `text` or `ariaLabel`** — 3 occurrences (accessibility)
- **[WARN] NcInputField missing `label` prop** — 1 occurrence
- **[vue-select warn] Label key "option.Icon" does not exist** — icon picker uses wrong label key (likely tile create modal)

### External (NOT launchpad bugs, logging for awareness)
- opencatalogi widgets emit 6 errors (`appName`/`appVersion` not set in @nextcloud/vue) — opencatalogi bug
