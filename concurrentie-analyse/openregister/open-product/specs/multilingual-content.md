# Spec: Multilingual Content & Translation Support

## Feature Summary

Open Product provides built-in multilingual support for product type names, summaries, and content elements. Translations are managed per-field using Django's translated fields framework, with dedicated API endpoints for managing translations.

## Capabilities

### Translated Fields on ProductType
- `naam` (name) -- translatable
- `samenvatting` (summary) -- translatable
- Current language returned in `taal` response field
- Translation endpoint: `PUT/DELETE /producttypen/{uuid}/vertaling/{taal}`

### Translated Fields on ContentElement
- `content` -- translatable markdown content
- `aanvullende_informatie` -- translatable additional info
- Translation endpoint: `PUT/DELETE /content/{uuid}/vertaling/{taal}`

### How It Works
- Default language is Dutch (nl)
- Additional translations added via dedicated translation endpoints
- API response includes the `taal` field indicating the current language
- Client can request specific language via Accept-Language header or query parameter

## OpenRegister Comparison

OpenRegister does not have built-in multilingual field support. To replicate:
- Option A: Separate schema properties per language (e.g., `naam_nl`, `naam_en`)
- Option B: Nested translation object (`naam: {nl: "...", en: "..."}`)
- Option C: Separate translation objects linked to the main object

**Open Product advantage:** Translation is native to the framework with dedicated endpoints, field-level granularity, and proper HTTP content negotiation.

**OpenRegister advantage:** Schema flexibility means translation patterns can be adapted to any requirement, and translations are searchable/filterable like any other data.
