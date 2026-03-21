# CKAN API and Search

## Action API

CKAN exposes all functionality through an Action API. Every operation is a named action callable via HTTP POST or GET.

### Endpoint Pattern
```
POST /api/3/action/{action_name}
GET  /api/3/action/{action_name}?param1=value1&param2=value2
```

### Core Actions

**Read (get.py - 3198 lines, 60+ actions):**
```
package_list              # List all dataset names/IDs
package_search            # Full-text search with Solr
package_show              # Get single dataset with resources
resource_show             # Get single resource
organization_list         # List organizations
organization_show         # Get organization details
group_list / group_show   # List/get groups
user_show                 # Get user profile
tag_list / tag_show       # List/get tags
vocabulary_list           # List controlled vocabularies
```

**Create (create.py - 1477 lines):**
```
package_create            # Create dataset
resource_create           # Add resource to dataset
organization_create       # Create organization
group_create              # Create group
user_create               # Register user
tag_create                # Create tag
vocabulary_create         # Create controlled vocabulary
```

**Update (update.py - 1355 lines):**
```
package_update            # Full dataset update (replaces all fields)
resource_update           # Full resource update
organization_update       # Update organization
user_update               # Update user profile
```

**Patch (patch.py - 180 lines):**
```
package_patch             # Partial dataset update
resource_patch            # Partial resource update
organization_patch        # Partial org update
group_patch               # Partial group update
```

**Delete (delete.py - 826 lines):**
```
package_delete            # Soft-delete dataset (state='deleted')
resource_delete           # Remove resource
organization_delete       # Delete organization
group_delete              # Delete group
member_delete             # Remove member from group/org
```

### Response Format
```json
{
    "success": true,
    "result": { ... },       // action-specific data
    "help": "http://..."     // link to API docs
}
```

On error:
```json
{
    "success": false,
    "error": {
        "__type": "Validation Error",
        "name": ["Missing value"]
    }
}
```

### Authentication
- **API tokens** (recommended): `Authorization: <token>` header
- **Cookie sessions**: For browser-based access
- No OAuth2 built-in (available via extensions)

## Solr Search

CKAN's search is powered by Apache Solr with extensive configuration.

### package_search Action
```
POST /api/3/action/package_search
{
    "q": "health data",                    // Full-text query
    "fq": "organization:who",             // Filter query (Solr syntax)
    "sort": "metadata_modified desc",     // Sort order
    "rows": 20,                           // Results per page
    "start": 0,                           // Offset
    "facet.field": ["organization", "tags", "res_format"],
    "facet.limit": 10,
    "include_private": false
}
```

### Search Query Fields (weighted)
```python
QUERY_FIELDS = "name^4 title^4 tags^2 groups^2 text"
```
Name and title get 4x boost, tags and groups 2x, full text 1x.

### Faceted Search
Solr returns facet counts alongside results:
```json
{
    "result": {
        "count": 1234,
        "results": [...],
        "search_facets": {
            "organization": {
                "items": [
                    {"name": "who", "count": 456},
                    {"name": "unicef", "count": 123}
                ]
            },
            "tags": {
                "items": [
                    {"name": "health", "count": 789},
                    {"name": "education", "count": 234}
                ]
            }
        }
    }
}
```

### Spatial Search (via extension)
With ckanext-spatial installed:
```
package_search?ext_bbox=-180,-90,180,90
```

## DataStore API

The DataStore extension provides structured data storage with a SQL-like query API.

### DataStore Actions
```
datastore_create          # Create table + optionally insert records
datastore_upsert          # Insert or update records
datastore_delete          # Delete records or table
datastore_search          # Search with filters, full-text, SQL
datastore_search_sql      # Raw SQL queries (read-only)
datastore_info            # Table metadata
```

### DataStore Search
```json
POST /api/3/action/datastore_search
{
    "resource_id": "...",
    "filters": {"city": "Amsterdam"},
    "q": "climate",
    "sort": "year desc",
    "limit": 100,
    "offset": 0,
    "fields": ["city", "year", "temperature"]
}
```

### DataStore SQL
```json
POST /api/3/action/datastore_search_sql
{
    "sql": "SELECT city, AVG(temperature) FROM \"resource-id\" GROUP BY city"
}
```

## Relevance to OpenRegister

Key API design lessons:
- **Action-based API** with consistent request/response format across all operations
- **Solr faceted search** with configurable field weights and facet limits
- **DataStore SQL queries** for advanced data analysis
- **Patch operations** for partial updates (CKAN added this later; OpenRegister has it)
- **Filter queries (fq)** separate from full-text queries (q) for better search control
