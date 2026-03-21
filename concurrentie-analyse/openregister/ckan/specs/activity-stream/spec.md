---
status: draft
source: competitive-analysis
competitor: ckan
analyzed_date: 2026-03-14
---

# Activity Stream

## What It Does

CKAN maintains a comprehensive activity stream that records all changes to datasets, organizations, groups, and users. This provides a full audit trail with who changed what and when, viewable through the web UI and API.

## How It Works

The activity system (in `ckanext/activity/`) records events for:
- Dataset creation, update, deletion
- Resource addition, update, removal
- Organization/group creation, update, deletion
- User creation and profile updates

Each activity record stores:
- `user_id` - Who made the change
- `object_id` - What was changed (dataset/org/group ID)
- `activity_type` - Type of change (e.g., "new package", "changed package", "deleted package")
- `data` - Snapshot of the object before/after the change (as JSON)
- `timestamp` - When the change occurred

**API actions:**
```
package_activity_list        # Activity for a specific dataset
organization_activity_list   # Activity for an organization
user_activity_list           # Activity for a user
dashboard_activity_list      # Activity feed for current user's followed items
recently_changed_packages_activity_list  # Site-wide recent changes
activity_detail_list         # Detailed diffs for an activity
```

**Diff viewing:**
The web UI shows diffs between dataset versions, highlighting which fields changed. The `activity_diff` action returns structured diffs comparing two activity snapshots.

**Following:**
Users can follow datasets, organizations, groups, and other users. The dashboard activity list aggregates activities from all followed items into a personalized feed.

## Key Source Files
- `ckanext/activity/` - Activity stream extension
- `ckanext/activity/logic/action.py` - Activity API actions
- `ckanext/activity/model/activity.py` - Activity model

## Relevance to OpenRegister

OpenRegister has audit trail capabilities but CKAN's activity stream is more user-facing -- with dashboard feeds, following, and visual diffs. The pattern of storing full object snapshots alongside activities (for diff generation) and the social following system are features that could enhance OpenRegister's audit capabilities.
