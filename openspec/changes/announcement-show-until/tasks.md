# Tasks: announcement-show-until

- [x] Create and update endpoints: require `endsAt` for a new announcement; refuse an end before the publish time (400); stored announcements without `endsAt` unchanged
- [x] Announcement form: "Show until" required with the explanation, the end time in the preview, the button "Schedule" for a future publish time
- [x] Translations in every shipped locale
- [x] Unit tests: missing end time, end before start, legacy rows without an end time still shown, visibility ends at the end time
- [ ] Playwright: the form refuses to publish without "Show until"; a scheduled announcement shows "Schedule"
