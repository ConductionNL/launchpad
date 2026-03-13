# Product Create Flow

## Sequence Diagram

```
Client                  ProductViewSet            ProductSerializer              Database
  |                           |                          |                          |
  |-- POST /producten ------->|                          |                          |
  |                           |                          |                          |
  |                           |-- check permissions ---->|                          |
  |                           |   ProductTypeObjectPerm  |                          |
  |                           |   (user needs r/w perm   |                          |
  |                           |    on producttype)       |                          |
  |                           |                          |                          |
  |                           |-- serializer.is_valid()-->|                         |
  |                           |                          |-- StatusValidator        |
  |                           |                          |   (status in             |
  |                           |                          |    toegestane_statussen) |
  |                           |                          |-- DateValidator          |
  |                           |                          |   (start/eind dates vs   |
  |                           |                          |    allowed statuses)     |
  |                           |                          |-- VerbruiksObjectValidator|
  |                           |                          |   (validate against      |
  |                           |                          |    type schema)          |
  |                           |                          |-- DataObjectValidator    |
  |                           |                          |   (validate against      |
  |                           |                          |    type schema)          |
  |                           |                          |-- validate eigenaren     |
  |                           |                          |   (min 1 required)       |
  |                           |                          |-- NestedObjectsValidator |
  |                           |                          |   (eigenaar: BSN/KVK)    |
  |                           |                          |-- UrnMappingMixin        |
  |                           |                          |   (resolve aanvraag_zaak |
  |                           |                          |    URN <-> URL)          |
  |                           |                          |                          |
  |                           |                          |-- @transaction.atomic    |
  |                           |                          |-- Product.save() ------->|
  |                           |                          |   (checks start/eind     |
  |                           |                          |    dates for auto status  |
  |                           |                          |    transitions)          |
  |                           |                          |                          |
  |                           |                          |-- create eigenaren ----->|
  |                           |                          |-- create documenten ---->|
  |                           |                          |-- create zaken --------->|
  |                           |                          |-- create taken ---------->|
  |                           |                          |   (all via URN mapping   |
  |                           |                          |    resolution)           |
  |                           |                          |                          |
  |                           |<-- product instance -----|                          |
  |                           |                          |                          |
  |                           |-- audit log (create) --------------------------->  |
  |                           |-- metrics counter +1                               |
  |                           |-- notification (producten channel) ---> Notificaties API
  |                           |                          |                          |
  |<-- 201 Created -----------|                          |                          |
```

## Auto Status Transition on Create
If `start_datum <= today` and status is INITIEEL, status is automatically set to ACTIEF during `save()`.
If `eind_datum <= today` and status is not already terminal, status is set to VERLOPEN.

## Permission Check Detail
```
ProductTypeObjectPermission.has_permission():
  if user.is_superuser: allow
  else: check ProductTypePermission exists for (user, producttype_uuid, mode=read_and_write)
```
