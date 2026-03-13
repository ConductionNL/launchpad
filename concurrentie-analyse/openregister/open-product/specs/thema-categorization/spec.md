# Thema Categorization

## Summary

Themas (themes) provide a hierarchical categorization system for product types. They form a tree structure (parent-child), have publication state management with cascade rules, and support content elements. Every ProductType must be linked to at least one Thema.

## Data Model

### Thema (BasePublishableModel)
- `naam` -- CharField, max 255
- `hoofd_thema` -- self-referential FK ("parent theme", nullable, PROTECT)
- `beschrijving` -- TextField (markdown supported)
- `gepubliceerd` -- BooleanField (unlike ProductType which uses date ranges)

### Relations
- `sub_themas` -- reverse relation (children)
- `producttypen` -- M2M to ProductType
- `content_elementen` -- FK from ContentElement (shared content system)

## Business Rules

### Hierarchy
1. Circular references are prevented (validated by walking the parent chain)
2. A thema cannot be its own parent

### Publication Cascade
3. A sub-thema can only be published if its parent thema is published
4. A thema cannot be unpublished if it has published sub-themas
5. A thema cannot be deleted if it has sub-themas (PROTECT on FK)

### ProductType Constraint
6. Every ProductType must have at least one thema (validated at serializer level)
7. A thema cannot be deleted if ProductTypes are linked only to this thema

## API Endpoints
- `GET/POST /producttypen/api/v1/themas` -- list/create
- `GET/PUT/PATCH/DELETE /producttypen/api/v1/themas/{uuid}` -- detail CRUD

### Filters
- `naam` -- exact match
- `hoofd_thema` -- filter by parent UUID
- `gepubliceerd` -- boolean filter

## Content Elements on Themas
Content elements can belong to either a ProductType OR a Thema (mutually exclusive, validated). This allows shared content across all products in a theme.

## Already in OpenRegister
- Hierarchical object relationships via JSON references
- Tagging / categorization via schema properties

## Not yet in OpenRegister
- **Dedicated hierarchical theme system** with tree-structure validation
- **Publication state cascade** (parent must be published before children)
- **Circular reference prevention** in self-referential hierarchies
- **Content sharing** between themes and individual product types
- **Mandatory categorization** (at least one theme per product type)
