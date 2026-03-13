# Permission Enforcement Flow

## Product API Permission Check

```
API Request to /producten/
        |
        v
  +------------------------------+
  | DjangoModelPermissions       |
  | (producten.add/change/delete)|
  +------------------------------+
        |
        v (if passes)
  +------------------------------+
  | ProductTypeObjectPermission  |
  +------------------------------+
        |
        +-- LIST action:
        |     |
        |     v
        |   is_superuser? --YES--> return ALL products
        |     |
        |     NO
        |     |
        |     v
        |   Filter queryset to only products where
        |   ProductTypePermission exists for (user, producttype)
        |
        +-- CREATE action:
        |     |
        |     v
        |   is_superuser? --YES--> allow
        |     |
        |     NO
        |     |
        |     v
        |   Check ProductTypePermission for
        |   (user, request.producttype_uuid, mode=read_and_write)
        |     |
        |     exists? --YES--> allow
        |     |
        |     NO --> 403 Forbidden
        |
        +-- RETRIEVE action:
        |     |
        |     v
        |   is_superuser? --YES--> allow
        |     |
        |     NO
        |     |
        |     v
        |   ProductTypePermission exists for (user, obj.producttype)?
        |     |
        |     YES (any mode) --> allow
        |     NO --> 403 Forbidden
        |
        +-- UPDATE/DELETE action:
              |
              v
           is_superuser? --YES--> allow
              |
              NO
              |
              v
           Check permission on CURRENT producttype
              |
              v
           mode == read_and_write? --YES--> continue
              |
              NO --> 403 Forbidden
              |
              v (if producttype_uuid changed in request)
           Also check permission on NEW producttype
              |
              v
           mode == read_and_write? --YES--> allow
              |
              NO --> 403 Forbidden
```

## Permission Modes

| Mode           | LIST | RETRIEVE | CREATE | UPDATE | DELETE |
|----------------|------|----------|--------|--------|--------|
| read_only      | Yes  | Yes      | No     | No     | No     |
| read_and_write | Yes  | Yes      | Yes    | Yes    | Yes    |
| no permission  | No   | No       | No     | No     | No     |
| superuser      | All  | All      | All    | All    | All    |
