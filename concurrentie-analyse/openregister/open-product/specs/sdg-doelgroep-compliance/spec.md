# SDG / Doelgroep Compliance

## Summary

The Single Digital Gateway (SDG) EU regulation requires that government services targeting citizens and businesses are discoverable and documented in a standardized way. Open Product implements this through the `doelgroep` (target audience) field on ProductType, which drives both UPL compliance requirements and publication behavior.

## Doelgroep (Target Audience) Model

### DoelgroepChoices Enum
- `burgers` -- Citizens (natural persons)
- `interne_organisatie` -- Internal organisation
- `samenwerkingspartners` -- Collaboration partners
- `bedrijven_en_instellingen` -- Businesses and institutions

### SDG-Relevant Constraints
1. **UPL Mandatory for Public-Facing Products**: When doelgroep is `burgers` or `bedrijven_en_instellingen`, the product type MUST have a `uniforme_product_naam` (UPL reference). This ensures all citizen/business-facing products are registered with their official government product name.

2. **Publication Model**: ProductTypes use date-range publication (`publicatie_start_datum` / `publicatie_eind_datum`) rather than a simple boolean, enabling scheduled publication for SDG compliance deadlines.

3. **Content Elements**: Rich, ordered, translatable content blocks can be attached to product types. These support markdown and can be labeled (e.g., "voorwaarden", "kosten", "aanvraag") to structure SDG-required information sections.

4. **Multilingual Support**: ProductType naam and samenvatting support NL (required) and EN (optional) translations, aligning with SDG's cross-border accessibility requirements.

5. **Process References**: ProductTypes can link to `Proces` entries (URN/URL), connecting to the government process definitions required by SDG.

## API Support for SDG
- Filter by `doelgroep`: `?doelgroep=burgers` returns only citizen-facing products
- Filter by `gepubliceerd=true` returns only currently published product types
- Content endpoint with label filtering allows SDG portals to fetch specific information sections
- Accept-Language header for multilingual responses

## Already in OpenRegister
- Object properties with enum constraints
- Multilingual content is possible via JSON properties

## Not yet in OpenRegister
- **Doelgroep classification** with automatic UPL requirement enforcement
- **Date-range publication model** for scheduled content visibility
- **Structured content blocks** with labels and ordering (SDG content sections)
- **Built-in NL/EN translation** with language negotiation headers
- **SDG-aligned process linking** (verzoektypen, processen references)
