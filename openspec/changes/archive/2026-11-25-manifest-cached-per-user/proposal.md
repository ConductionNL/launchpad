# Proposal: manifest-cached-per-user

## Why

Speed budget (hydra ADR-124): every main page usable within 300 ms warm. Every workspace load asked `GET /api/manifest`, which reads OpenRegister for the user's dashboard objects. On a busy instance it was the slowest request of the page (900 to 1450 ms measured on the local test instance under load) and so the last thing the page waited for. A warm reload asks the same question seconds after the last answer.

## What changes

- `initial-state-contract`: ADDED REQ-INIT-009. `GET /api/manifest` keeps the manifest it builds per user and app version for 60 seconds and answers from it within that time. The workspace page carries the kept manifest as the optional key `runtimeManifest`, and the page takes it instead of asking. The page never builds a manifest itself.
- A caller the action matrix refuses `manifest.index` gets no key and meets the same refusal from the API.
- A dashboard shared with the user in OpenRegister reaches their menu at most 60 seconds later.

## Impact

- `lib/Controller/ManifestCache.php`, `lib/Controller/ManifestFirstLoad.php` (new), `ManifestTools`, `ManifestController::index`, `PageSources`, `PageController::index`, `InitialStateBuilder::setRuntimeManifest`.
- `src/utils/loadInitialState.js` (`takeRuntimeManifest`), `src/utils/runtimeManifestRequest.js`.
- The key is optional, so the schema version stays 3.
