# Internationalization & Translations

## Summary

Open Product supports multilingual content using django-parler. ProductType (naam, samenvatting) and ContentElement (content, aanvullende_informatie) are translatable. Dutch is required; English is optional with Dutch fallback.

## Implementation

### Translatable Models
1. **ProductType**: `naam` and `samenvatting` via ProductTypeTranslation
2. **ContentElement**: `content` and `aanvullende_informatie` via ContentElementTranslation

### Language Configuration
- Supported: NL (required), EN (optional)
- Fallback: always NL
- `PARLER_LANGUAGES` configuration with `hide_untranslated: False`

### API Behavior

#### Reading (GET)
- `Accept-Language` header controls response language
- Response includes `taal` field indicating actual language returned
- If requested language unavailable, falls back to NL

#### Writing (POST/PUT/PATCH)
- Create/update always writes NL translation (forced via `activate("nl")` in viewset)
- English translations managed via dedicated endpoints:
  - `PUT/PATCH /producttypen/{uuid}/vertaling/en`
  - `DELETE /producttypen/{uuid}/vertaling/en` (NL cannot be deleted)
  - `PUT/PATCH /content/{uuid}/vertaling/en`

### TranslatableViewSetMixin
Provides `update_vertaling` and `delete_vertaling` methods used by both ProductType and ContentElement viewsets.

## Already in OpenRegister
- Multilingual content possible via JSON properties with language keys
- Nextcloud l10n framework for UI translations

## Not yet in OpenRegister
- **django-parler style translation management** with separate translation tables
- **Language negotiation** via Accept-Language header
- **Guaranteed NL fallback** when requested language unavailable
- **Dedicated translation endpoints** (separate from main CRUD)
- **Language indicator** in API responses (`taal` field)
