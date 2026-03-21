# PocketBase API and SDK

## REST API Structure

All collection CRUD operations follow a consistent pattern:

```
GET    /api/collections/{collection}/records          # List/Search
GET    /api/collections/{collection}/records/{id}     # View
POST   /api/collections/{collection}/records          # Create
PATCH  /api/collections/{collection}/records/{id}     # Update
DELETE /api/collections/{collection}/records/{id}     # Delete
```

### Query Parameters
- `page`, `perPage` - Pagination (default 30 per page)
- `sort` - Multi-field sorting with `+`/`-` prefix
- `filter` - PocketBase filter syntax (e.g., `name="test" && price > 100`)
- `expand` - Auto-expand relations up to 6 levels deep
- `fields` - Field selection with `:excerpt()` modifier
- `skipTotal` - Skip count query for faster pagination

### Batch API
PocketBase supports transactional batch operations:
```
POST /api/batch
{
  "requests": [
    {"method": "POST", "url": "/api/collections/products/records", "body": {...}},
    {"method": "PATCH", "url": "/api/collections/products/records/abc", "body": {...}}
  ]
}
```

Also supports PUT for upsert operations (create or update based on ID).

### Auth API
```
POST /api/collections/{collection}/auth-with-password
POST /api/collections/{collection}/auth-with-oauth2
POST /api/collections/{collection}/auth-with-otp
POST /api/collections/{collection}/request-otp
POST /api/collections/{collection}/auth-refresh
POST /api/collections/{collection}/request-password-reset
POST /api/collections/{collection}/confirm-password-reset
POST /api/collections/{collection}/request-verification
POST /api/collections/{collection}/confirm-verification
POST /api/collections/{collection}/request-email-change
POST /api/collections/{collection}/confirm-email-change
POST /api/collections/{collection}/impersonate/{id}   # Superuser only
```

### Realtime API
```
GET  /api/realtime    # SSE connection (long-lived)
POST /api/realtime    # Set subscriptions
```

### File API
```
POST /api/files/token                                    # Generate file token
GET  /api/files/{collection}/{recordId}/{filename}       # Download file
GET  /api/files/{collection}/{recordId}/{filename}?thumb=100x100  # Thumbnail
```

## Official SDKs

PocketBase provides official JavaScript and Dart SDKs:

```javascript
import PocketBase from 'pocketbase';
const pb = new PocketBase('http://127.0.0.1:8090');

// List with filtering
const records = await pb.collection('products').getList(1, 50, {
    filter: 'price > 100 && active = true',
    sort: '-created',
    expand: 'category',
});

// Realtime subscription
pb.collection('products').subscribe('*', function (e) {
    console.log(e.action); // create, update, delete
    console.log(e.record);
});
```

## Relevance to OpenRegister

Key takeaways for OpenRegister's API design:
- **Consistent URL patterns** across all collections
- **Inline API documentation** (API Preview) with SDK code generation
- **Filter expressions** as a query language (vs. OData or GraphQL)
- **Batch/upsert** operations reduce roundtrips
- **Field selection** and `expand` for efficient payloads
