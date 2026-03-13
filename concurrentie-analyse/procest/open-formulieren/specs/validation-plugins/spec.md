# Validation Plugins

## What Open Forms Does

### Plugin Architecture
- `BasePlugin[T]` generic over validated value type
- `value_serializer` -- DRF serializer for the value
- `for_components` -- tuple of component types this validator applies to
- `__call__(value, submission)` -- raises ValidationError for invalid values

### Built-in Validators
- BSN (11-proof check for Dutch citizen service numbers)
- KvK number validation
- Postcode format validation
- IBAN format and checksum validation
- License plate format validation
- Custom regex-based validators (configurable per component)

### Server-Side Validation
- All validation runs server-side (not just client-side)
- FormIO components have their own client-side validation
- Backend re-validates all data on submission step save
- Prevents tampering with client-side validation bypass

### Validation Flow
1. Client-side Form.io validation (immediate feedback)
2. On step submit, server validates all component values
3. Plugin validators called for applicable component types
4. Validation errors returned per component key

## Already in Procest

- OpenRegister schema validation (JSON Schema based)
- Basic field type validation

## Not Yet in Procest

- **BSN 11-proof validation** -- No citizen ID validation
- **KvK number validation** -- No business ID validation
- **Plugin-based validation system** -- No pluggable validators per component type
- **Server-side re-validation** -- No dual client+server validation pattern
- **IBAN/postcode/license plate validators** -- No Dutch-specific format validators
