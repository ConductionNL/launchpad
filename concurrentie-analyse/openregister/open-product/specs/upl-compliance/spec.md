# UPL (Uniforme Productnamenlijst) Compliance

## Summary

The Uniforme Productnamenlijst (UPL) is a standardized list of product names maintained by the Dutch government. Open Product has first-class UPL support: product types must reference a UPL entry when targeting citizens or businesses. The UPL list can be imported from CSV (local file or URL) and tracks which entries have been removed from the official list.

## Data Model

### UniformeProductNaam (BaseModel)
- `naam` -- CharField, unique, max 255 (the standard product name)
- `uri` -- URLField, unique (link to the official UPL definition)
- `is_verwijderd` -- BooleanField, default False (marks names removed from the official list)

### Natural Key
Uses `naam` as natural key for serialization/fixtures.

## UPL Import Management Command

`python manage.py load_upl --file <path.csv>` or `--url <url.csv>`

### Import Logic
1. Reads CSV with columns `URI` and `UniformeProductnaam`
2. For each row: `update_or_create` by naam, setting uri and `is_verwijderd=False`
3. After import: all UPN entries NOT in the CSV are marked `is_verwijderd=True` (soft-delete)
4. Runs in a single transaction (atomic)
5. Reports: created count, updated count, removed count

### CSV Format
Required columns: `URI`, `UniformeProductnaam`
Encoding: UTF-8 BOM (`utf-8-sig`)

## UPL Enforcement Rules

### Doelgroep Constraint
When a ProductType has `doelgroep` set to:
- **Burgers** (citizens) -- `uniforme_product_naam` is REQUIRED
- **Bedrijven en instellingen** (businesses) -- `uniforme_product_naam` is REQUIRED
- **Interne organisatie** or **Samenwerkingspartners** -- `uniforme_product_naam` is optional

This is enforced at both model level (`clean()`) and serializer level (`DoelgroepUplValidator`).

### API Usage
- ProductType serializer: `uniforme_product_naam` is a SlugRelatedField on `naam` (you send the name string, not an ID)
- Product serializer: includes nested `uniforme_product_naam` in the producttype response
- Filter: `?uniforme_product_naam=<naam>` on both producttypen and producten endpoints
- Actuele prijs endpoint includes `upl_naam` and `upl_uri` fields

### Notification Kenmerken
Product notifications include `producttype.uniforme_product_naam` as a message attribute, enabling subscribers to filter on UPL name.

## Already in OpenRegister
- Schemas can reference external definitions
- Object properties can be validated

## Not yet in OpenRegister
- **Dedicated UPL entity** with name + URI + soft-delete tracking
- **CSV import command** for bulk UPL loading from government source
- **Conditional mandatory validation** (UPL required based on target audience)
- **Doelgroep-to-UPL constraint enforcement**
- **UPL-based notification filtering**
- **Soft-delete marking** for removed UPL entries (not hard-deleted, since existing products may reference them)
