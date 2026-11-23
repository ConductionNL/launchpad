# Tasks: announcement-show-until

- [ ] Create and update endpoints: require `endsAt` for a new announcement; refuse an end before the publish time (400); stored announcements without `endsAt` unchanged
- [x] Announcement form: "Show until" required with the explanation, the end time in the preview, the button "Schedule" for a future publish time
- [ ] Translations in every shipped locale
- [ ] Unit tests: missing end time, end before start, legacy rows without an end time still shown, visibility ends at the end time
- [ ] Playwright: the form refuses to publish without "Show until"; a scheduled announcement shows "Schedule"
