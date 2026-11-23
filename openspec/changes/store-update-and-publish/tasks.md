# Tasks: store-update-and-publish

- [ ] `POST /api/store/items/{slug}/update` (admin only): fetch the newer version through the store plane, update the installed dashboards in place with the REQ-TMPL-020 pairing, keep person-added widgets, re-sync copies with merge, store the new version in the install record, write the version note "Updated from the marketplace"
- [ ] Store page: Update action on cards with status Update, for administrators
- [ ] Unit tests: in-place update keeps id, shares and own widgets; paired widgets keep their rows; 403 for non-admins; install still creates
- [ ] Playwright: with the registry stub offering a newer version, Update changes the installed dashboard and the card shows Installed
- [ ] BLOCKED on OpenRegister `store-over-federated-config`: publish through the store plane, admin only, export without personal data, higher version required, register answer passed through
- [ ] BLOCKED on the same: "Publish dashboard" action and form on the store page, shown only with a connected register that accepts publications
- [ ] BLOCKED on the same: unit tests for publish (export content, version rule, 403) and a Playwright run against the registry stub
