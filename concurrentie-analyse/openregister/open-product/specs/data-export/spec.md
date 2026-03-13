# Data Export

## Summary

Open Product supports CSV and JSON export of data from the admin interface, both as bulk actions on list views and as individual object exports from detail views.

## Implementation

### Export Actions
- `export_csv` -- admin action for bulk CSV export
- `export_json` -- admin action for bulk JSON export
- Both produce ZIP files containing the exported data

### ExportMixin
Admin model classes using `ExportMixin` get:
- Bulk export via admin list action (select rows -> export)
- Single-object export via a button on the detail form ("_export" POST parameter)

### Export Command
`python manage.py export <app_label> <model_name> --ids <id_list> --format <csv|json>`
- Called internally by admin actions
- Outputs to a response/file
- Supports `export_exclude` list on admin classes to omit sensitive fields

## Already in OpenRegister
- JSON/CSV export via API responses
- Bulk data export not yet implemented

## Not yet in OpenRegister
- **Admin-based bulk export** with format selection (CSV/JSON)
- **ZIP packaging** for export downloads
- **Field exclusion** for sensitive data in exports
- **Single-object export** from admin detail view
