# Design: cross-instance dashboard sharing over the AppHost store plane

## Phase 1 findings

This is the investigation that produced the proposal. It is written down because
three of its findings are defects that exist on `development` today, and one of
them contradicts the brief this work started from.

### Finding 1: LaunchPad already renders a Store page that cannot answer

`src/manifest.json` declares a page `{"id": "Store", "type": "store"}`. That type
dispatches to `CnStorePage` in `@conduction/nextcloud-vue`, which fetches
`/apps/launchpad/api/store/items` on mount.

`appinfo/routes.php` declares no such route. LaunchPad hand-writes its route
table rather than adopting `OCA\OpenRegister\AppHost\Routes::standard()`, so the
two store routes never register. The page is in the navigation and its only
request 404s.

This is a variant of a failure OpenRegister already recorded on 2026-09-03, where
decidiq, filinq and planninq each returned HTTP 500 on `/api/store/items`. Those
apps took the route table and skipped the controller binding. LaunchPad took
neither, so it 404s instead of 500ing. Same cause: the store surface is wired in
one place and not the other.

### Finding 2: the declared store block describes somebody else's store

The `store` block on `development` is:

```json
{ "types": ["openregister.configset", "openregister.flows"], "localRegister": "launchpad" }
```

`types` being non-empty selects FEDERATED discovery in
`StoreDescriptor::isFederated()`, which routes both search and install to
`FederatedStoreCatalog`. That catalogue trades OpenRegister configuration sets:
registers, schemas, views, flows, sources and mappings.

A LaunchPad dashboard is none of those. The block, its `_note` included, is
decidiq's block with the app name changed. Had the routes existed, the Store page
would have offered configuration sets to somebody looking for a dashboard.

### Finding 3: the engine cannot install a LaunchPad dashboard, and that is fine

The store plane offers two install paths and neither reaches a dashboard.

`GenericStoreInstaller` supports two ops, `writeObject` and `setAppConfig`. The
first writes rows into OpenRegister schemas named by the `installable` allowlist.
`FederatedStoreCatalog` applies configuration bundles through their owning type.

LaunchPad dashboards live in LaunchPad's own tables. `ImportService` takes a
`DashboardMapper`, a `WidgetPlacementMapper` and an `IDBConnection`. No
OpenRegister schema holds a dashboard, so no `writeObject` component can create
one.

The engine anticipates this. `Bootstrap::aliasControllerUnlessLeafDefinesIt()`
is a no-op when the leaf ships its own `Controller\StoreController`, and the
comment beside the store alias names dossiq as an app that keeps winning the
alias until its own class is deleted. A leaf-owned store controller is a
supported state, not a workaround.

### Finding 4: the brief and the spec disagree, and the spec moved most recently

The brief describes ADR-080 Decision 3: discovery is engine-owned, install stays
per app, the leaf builds a `StoreDescriptor` and three routes.

`openspec/specs/apphost-store-plane/spec.md` carries a later requirement, "A leaf
app MUST declare its store rather than implement one", which amends Decision 3.
Under it a leaf declares a `store` block and aliases the engine controller.

We follow the brief's split for LaunchPad, and the reason is the amendment's own
reason. The amendment says install semantics were made data because "the only
thing that actually varied was which schemas an install may write". For LaunchPad
that is not what varies. The install is a ZIP import against LaunchPad's own
tables, expressible as neither `writeObject` nor `setAppConfig`. The amendment
holds for apps whose install is a schema write. This is not one.

### What the plane gives LaunchPad for free

Everything on the discovery half, and it is the half with the security surface:

- SSRF guarding through `SecurityService::assertSafeFetchUrl()`, fail-closed, on
  every built URL before any request is issued.
- `allow_redirects => false`, so a public host cannot bounce the Bearer token to
  a private, link-local or metadata address.
- Bearer-only token transport. The token never enters the URL or query string and
  never returns to a caller.
- Outcome mapping that separates `store_unreachable` from
  `store_invalid_response`, so a misconfigured registry does not read as offline.
- Card normalisation against `cardFields`, which drops every remote property
  outside the map. A registry cannot smuggle a field onto a card.
- Slug verification in `resolve()`, so a registry that ignores an unknown query
  parameter cannot return an arbitrary first row.
- 10 second connect and request timeouts, and a 50 item search cap.

All of that was read from `lib/AppHost/Service/GenericStoreService.php`, not
assumed from the spec.

### What LaunchPad must build

- The two routes, and the controller behind them.
- Install: resolve the item, then hand its payload to `ImportService`.
- The registry configuration surface, because of the finding below.
- A correct `store` block, replacing the configuration-set one.

### Finding 5: the registry config has no admin surface in LaunchPad

`GenericStoreService` reads `registry_url`, `registry_token` and
`registry_register` from `IAppConfig` under the calling app's id. LaunchPad's
admin settings do not go there: `AdminSettingsService` writes through
`AdminSettingMapper` into a LaunchPad table.

So the keys the engine reads are reachable only by `occ config:app:set`. We add a
small admin-gated config endpoint that reads and writes those three keys in
`IAppConfig`. The token is write-only across it: the read returns whether a token
is set, never the token.

## The install mechanism

`ImportService::import()` takes a path to a ZIP. The registry hands back a JSON
object. Bridging them has three candidate shapes and only one is honest.

Writing a payload-shaped importer beside the ZIP-shaped one gives LaunchPad two
import paths that must agree forever. Fetching a ZIP URL named by the remote
payload adds a second outbound fetch that the plane does not guard, which throws
away the SSRF property that is the main reason to adopt the plane at all.

So we materialise. The resolved payload's dashboards are written into a
`launchpad-export-v1` ZIP in a temporary directory, and that path goes to
`ImportService::import()`. The importer is unchanged and remains the only code
that turns a dashboard payload into rows. `ExportService` already writes exactly
this container, so the two stay symmetrical by construction.

`preserveUuids` is false for a store install. A template arriving from a foreign
instance carrying a UUID that collides with a local dashboard must become a new
dashboard, never replace one. This is the same class of defect the plane records
under "An install MUST create a new object, never replace one", reached by a
different route: `saveObject()` resolves its target from the payload, and
`ImportService` with `preserveUuids: true` fails closed on collision rather than
overwriting, so passing false is what makes an install additive.

## Publishing is not solved here, and cannot be

The plane is a read-only client. Nothing in it writes to a registry, and no
requirement in its spec describes a publish. So the answer to "how does a
dashboard get into a registry" is: not through this change.

What publishing would actually take, honestly:

1. A schema on the registry instance holding a dashboard template, with the
   `cardFields` properties and an inline dashboard payload.
2. An authenticated write from the publishing instance to that schema, which is a
   new outbound write path with its own SSRF guarding, its own token scope, and a
   token that is no longer read-only. The current `registry_token` grants read.
3. A moderation posture. A registry that accepts writes from every instance that
   holds a token is a spam target, and the plane's trust boundary for a bundle is
   its publisher.
4. Versioning and withdrawal. A published template that turns out to be wrong
   needs a way to be superseded and a way to be pulled.

Steps 2 and 3 are the expensive ones and neither is a LaunchPad decision alone.
Until they land, a registry is populated out of band: an administrator exports a
dashboard and loads it into the registry instance directly. That is a real
workflow and it is worth saying it is the workflow, rather than implying the
store closes the loop.

## How this relates to the sharing LaunchPad already has

LaunchPad's existing sharing is all in-instance and stays untouched.
`DashboardShareService` shares to users and groups, `PublicShareService` publishes
a read-only link, and templates seed new dashboards. Each of those moves a
dashboard between people who already share a Nextcloud.

Export and import already move a dashboard between instances, by hand, through a
file an administrator carries. The store does not add a capability so much as
remove the carrying: the same ZIP contract, fetched over a guarded HTTP client
instead of downloaded and re-uploaded.

Nothing here changes who may see a dashboard once it exists. An installed
dashboard is owned by the installing administrator and obeys the same permission
model as one made by hand.

## Cost

Roughly 900 lines including tests, in five files plus the manifest and the route
table. The discovery half is a delegation, the install half is the ZIP
materialiser, and the rest is configuration.

The standing cost is the coupling to two contracts we do not own: the engine's
`GenericStoreService` signatures, and the shape of a remote dashboard-template
object. The first is a normal in-fleet dependency and is resolved optionally, so
an absent OpenRegister degrades to `not_configured` rather than fatal. The second
is the real exposure, and it is bounded by the fact that a malformed payload
fails in `ImportService`'s existing validation rather than in new code.
