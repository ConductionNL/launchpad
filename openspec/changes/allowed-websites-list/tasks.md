# Tasks: allowed-websites-list

- [x] Admin setting `allowed_sites` (`[{host, embed, fetch}]`) with host normalisation and validation; admin-only write
- [x] `AllowedSitesService::hostsFor('embed'|'fetch')`; the embed widget, image host, live tile, health ping and calendar feed checks read it, still fail-closed
- [x] Repair step: merge the existing per-feature lists into `allowed_sites` with their purpose, once
- [x] General tab block "Allowed websites": list, purpose select, Add website, Save
- [x] Translations in every shipped locale
- [x] Unit tests: purpose per feature (embed-only host refused for fetch and the other way), normalisation, invalid host, the merge on upgrade
- [ ] Playwright: add a host with Fetch, configure a live tile on it, and see an embed on the same host refused
