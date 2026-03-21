---
status: draft
source: competitive-analysis
competitor: ckan
analyzed_date: 2026-03-14
---

# Organizations and Groups

## What It Does

CKAN provides two types of dataset collections: Organizations (ownership + RBAC) and Groups (thematic collections). Organizations own datasets and control who can create/edit them. Groups are cross-organizational collections for topic-based browsing.

## How It Works

Both Organizations and Groups share the same `group` table in PostgreSQL, differentiated by the `is_organization` boolean column. Fields include: `id`, `name` (unique slug), `title`, `type`, `description`, `image_url`, `is_organization`, `approval_status`, `state`, `extras` (JSONB).

Membership is managed through the polymorphic `member` table which links entities to groups:
- `table_name` = "user" | "package" | "group" (what type of member)
- `table_id` = ID of the member entity
- `capacity` = role/permission level
- `group_id` = which group/org they belong to

For user membership, capacity values are: `admin` (full control), `editor` (create/edit datasets), `member` (read private datasets). For package membership, capacity is `public`, `private`, or `organization`.

Organizations enforce ownership: every dataset must belong to exactly one organization (via `owner_org`). Private datasets are only visible to organization members.

Groups support hierarchies: a group can be a parent of other groups via the member table (capacity = "parent").

## Key Source Files
- `ckan/model/group.py` - Group/Organization model with Member class
- `ckan/logic/action/create.py` - `organization_create`, `group_create`
- `ckan/logic/action/get.py` - `organization_list`, `organization_show`, member listing

## Relevance to OpenRegister

OpenRegister uses Nextcloud's user/group system for RBAC rather than building its own. CKAN's organization model provides tighter dataset-level ownership and visibility controls. The three-tier role system (admin/editor/member) per organization is a pattern OpenRegister could adopt for register-level permissions.
