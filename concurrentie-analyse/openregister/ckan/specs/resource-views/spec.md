---
status: draft
source: competitive-analysis
competitor: ckan
analyzed_date: 2026-03-14
---

# Resource Views

## What It Does

CKAN provides configurable data preview and visualization for resources. Resource Views render data inline on the dataset page -- showing tables for CSVs, maps for geographic data, images for image files, and formatted text for documents. Plugins can add custom view types.

## How It Works

Resource views are configured per-resource and rendered via the `IResourceView` plugin interface. Built-in view types:

- **DataTables View** (`ckanext/datatables_view/`) - Interactive table with sorting, filtering, pagination using DataTables.js. Works with DataStore resources.
- **Recline View** (`ckanext/recline_view/`) - Data explorer with grid, graph, and map views using Recline.js.
- **Text View** (`ckanext/text_view/`) - Renders plain text, HTML, JSON, XML with syntax highlighting.
- **Image View** (`ckanext/image_view/`) - Displays image files inline.
- **Webpage View** - Embeds external URLs in an iframe.

**Plugin interface (`IResourceView`):**
```python
class IResourceView(Interface):
    def info(self):
        # Return dict with name, title, icon, default_title, etc.
    def can_view(self, data_dict):
        # Return True if this view can handle the resource format
    def setup_template_variables(self, context, data_dict):
        # Prepare template variables for rendering
    def view_template(self, context, data_dict):
        # Return Jinja2 template path
    def form_template(self, context, data_dict):
        # Return template for view configuration form
```

Multiple views can be configured per resource, with users able to switch between them. Default views are automatically created based on resource format.

## Key Source Files
- `ckanext/datatables_view/` - DataTables interactive grid
- `ckanext/recline_view/` - Recline.js data explorer
- `ckanext/text_view/` - Text/code preview
- `ckanext/image_view/` - Image display
- `ckan/plugins/interfaces.py` - `IResourceView` interface

## Relevance to OpenRegister

OpenRegister renders object data in its Nextcloud Vue UI but doesn't have a pluggable view system. CKAN's resource view pattern -- where different view types are registered via plugins and automatically selected based on data format -- could inspire configurable object rendering in OpenRegister.
