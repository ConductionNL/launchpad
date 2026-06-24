# Smoke runbook — role-based-content (Task 11)

Per ADR-008 smoke discipline. These four steps are the manual smoke
test that a deploy is expected to satisfy on `localhost:8080`
(launchpad bind-mounted as `launchpad` in `apps_paths`). Each step is
copy-pasteable; the expected response is the green-light condition.

## 1. Admin can list role-feature permissions

```bash
curl -s -u admin:admin \
  -H 'OCS-APIRequest: true' \
  http://localhost:8080/index.php/apps/launchpad/api/role-feature-permissions \
  | jq '.'
```

**Expected.** HTTP 200, JSON array (empty `[]` on a fresh install, or
the configured RoleFeaturePermission rows). The shape matches
`RoleFeaturePermission::jsonSerialize()`.

## 2. Non-admin cannot create role-feature permissions

```bash
curl -s -o /dev/null -w '%{http_code}\n' \
  -u user1:user1 \
  -H 'OCS-APIRequest: true' \
  -H 'Content-Type: application/json' \
  -X POST \
  -d '{"groupId":"employees","name":"Employees","allowedWidgets":[],"deniedWidgets":[]}' \
  http://localhost:8080/index.php/apps/launchpad/api/role-feature-permissions
```

**Expected.** HTTP 403 (NC `SecurityMiddleware` rejects the non-admin
caller before the controller body runs — `RoleFeaturePermissionApiController::create`
is admin-gated via `requireAdmin()`).

## 3. Configured-group user sees filtered `/api/widgets`

Pre-condition: as admin, POST one RoleFeaturePermission for
`employees` group with `allowedWidgets: ["launchpad-label"]`.

```bash
curl -s -u alice:alice \
  -H 'OCS-APIRequest: true' \
  http://localhost:8080/index.php/apps/launchpad/api/widgets \
  | jq '.[] | .id'
```

**Expected.** Only `"launchpad-label"` (or the configured slice) appears
— the widget catalogue is filtered by
`RoleFeaturePermissionService::resolveAllowedWidgets()` per the
multi-group deny-wins rule documented in `docs/role-based-content.md`.

## 4. Direct restricted-widget endpoint returns 403 (no stack trace)

For a user without `launchpad-feed` in their allow set:

```bash
curl -s -u alice:alice \
  -H 'OCS-APIRequest: true' \
  http://localhost:8080/index.php/apps/launchpad/api/widgets/launchpad-feed/items \
  | jq '.'
```

**Expected.** HTTP 403, body exactly
`{"message":"Not authorized"}`. **No** stack trace, no internal file
path, no NC framework noise. The controller catches the
`PermissionDeniedException` and returns the sanitised payload.

## Pass criteria

All four steps return the expected status + shape. Run this manually
after every PR that touches the role-based-content surface; the cron
in Task 9 (`composer check:strict` weekly) protects the static-analysis
side, but the four runtime endpoints above are the integration smoke
gate per ADR-008.
