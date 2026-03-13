# Content Management

## Summary

Open Product has an ordered, translatable, label-based content management system. Content elements are rich text blocks (markdown) that can be attached to either a ProductType or a Thema, with ordering and label-based filtering for API consumers.

## Data Model

### ContentElement (TranslatableModel, OrderedModel, BaseModel)
- `producttype` -- FK to ProductType (nullable, CASCADE)
- `thema` -- FK to Thema (nullable, CASCADE)
- `labels` -- M2M to ContentLabel
- `content` -- TranslatedField (the main content, NL required)
- `aanvullende_informatie` -- TranslatedField (additional info, optional)
- `order` -- managed by OrderedModel, ordered within (producttype, thema) scope

### ContentLabel (BaseModel)
- `naam` -- CharField, unique, max 255

### Validation
- Exactly one of `producttype` or `thema` must be set (XOR validation)
- Labels are created in the admin interface, referenced by API

## API Endpoints

### Content CRUD
- `GET/POST /producttypen/api/v1/content` -- list/create
- `GET/PUT/PATCH/DELETE /producttypen/api/v1/content/{uuid}` -- detail CRUD
- `PUT/PATCH /producttypen/api/v1/content/{uuid}/vertaling/{taal}` -- translation management

### ProductType Content
- `GET /producttypen/api/v1/producttypen/{uuid}/content` -- all content for a product type
  - Filter: `?labels=voorwaarden,kosten` -- include only these labels
  - Filter: `?exclude_labels=intern` -- exclude these labels

### ContentLabels
- `GET /producttypen/api/v1/contentlabels` -- list available labels (admin-managed)

## Translation Support
- Content and aanvullende_informatie support NL (required) and EN (optional)
- Language negotiated via Accept-Language header
- Fallback to NL if requested language unavailable
- Response includes `taal` field showing actual language returned

## Already in OpenRegister
- Rich text content on objects
- Object ordering via sort fields

## Not yet in OpenRegister
- **Ordered content blocks** with automatic order management within parent scope
- **Label-based content filtering** (include/exclude labels on API queries)
- **Content shared between product types and themes** (XOR ownership)
- **Per-block translation** with language negotiation
- **Separate "additional information" field** per content block
