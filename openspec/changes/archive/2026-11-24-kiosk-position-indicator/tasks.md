# Tasks: kiosk-position-indicator

- [x] Kiosk playlist player: position line (index of total, seconds to the next) bottom right, updated every second, hidden for a single dashboard, AA contrast on the dashboard background
- [x] A polite live region that names the new dashboard when the playlist moves on
- [x] Translations in every shipped locale
- [x] Unit tests: the line's text over a dwell time, absent for one dashboard, the announcement on change
- [x] Playwright: a two-dashboard playlist shows "1 van 2" and then "2 van 2" (written in `tests/e2e/kiosk-position-indicator.spec.ts`, runs in nightly)
