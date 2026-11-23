# Tasks: phone-install-banner

- [ ] Banner component in the workspace shell: shown below 768 px, not in standalone display mode, not in kiosk or public views; Install uses the captured `beforeinstallprompt`, else shows the iOS steps; Not now stored in the browser for 30 days (read and write wrapped so a blocked storage only means the banner shows)
- [ ] Translations in every shipped locale
- [ ] Unit tests: shown and hidden per width, display mode, kiosk and public; Install with and without a prompt; Not now for 30 days
- [ ] Playwright: at 390 px the banner shows and Not now hides it after a reload
