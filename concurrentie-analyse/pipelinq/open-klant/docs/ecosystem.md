# Open Klant -- Ecosystem & Integrations

## Municipalities Using Open Klant

Open Klant was developed in collaboration with and is deployed at:
- **Gemeente Amsterdam**
- **Gemeente Den Haag**
- **Gemeente Utrecht**
- **Gemeente Rotterdam** (via Dimpact)

Additionally, the broader Dimpact cooperative (40+ municipalities) uses Open Klant as part of the PodiumD stack.

Live instances:
- Dimpact development: https://ontw-openklant.dimpact.info.nl/
- Dimpact acceptance: https://acc-contact.dimpact.info.nl/

## Integration Architecture

### KISS (Klantinteractie Servicesysteem)

KISS is the primary user-facing application that uses Open Klant as its backend.

- **Purpose**: Customer Contact Center (KCC) work environment
- **Developed by**: ICATT for Dimpact (municipality of Utrecht initiative)
- **Architecture**: Vue.js frontend + .NET Backend for Frontend + Kubernetes
- **Open Klant integration**: KISS writes to Open Klant for:
  - Customer registration (Partij/Klant data)
  - Contact moment logging (Klantcontact records)
  - Internal task routing (InterneTaak)
- **Also integrates with**: Open Zaak (ZGW APIs), Objects API, KvK API, Haal Centraal BRP
- **Status**: v1 delivered September 2023, actively maintained

### Open Zaak

Open Zaak is the case management component in the Common Ground stack.

- Open Klant links to zaak (case) records via Onderwerpobject
- Cloud Events (zaak-gekoppeld/zaak-ontkoppeld) notify Open Zaak of linking/unlinking
- Both developed by Maykin Media

### Open Inwoner (Citizen Portal)

Open Inwoner is the citizen-facing portal that reads from Open Klant.

- Citizens can view their contact history
- Portal reads Partij preferences (voorkeurstaal, voorkeurs_digitaal_adres)
- Managed by Maykin Media

### Objects API

KISS uses the Objects API for data that does not fit the Klantinteracties standard:
- Employee profiles
- Knowledge articles (PDC products)
- Question-Answer Combinations (VACs)
- Department and group data

### Notificaties API

Open Klant sends notifications for Partij and InterneTaak create/update/delete events to a central notification service. Other applications can subscribe to these channels.

### Referentielijsten API

Since v2.14.0, Open Klant validates the `kanaal` field of Klantcontact against a reference list from the Referentielijsten API.

## Common Ground Marketplace

- **OpenGem listing**: https://www.opengem.nl/producten/open-klant/
- Part of the Common Ground component ecosystem
- Follows the 5-layer model: Open Klant sits in the "Services" layer
- KISS sits in the "Interaction" layer

## Business Model

- **Software**: Free under EUPL license (no license costs ever)
- **SaaS (via OpenGem)**: Pay only for support and infrastructure, monthly cancellable
- **Self-hosted**: Free, with optional paid support
- **Development**: Co-funded by participating municipalities via VNG Realisatie
- **Community support**: Available for everyone
- **Priority support**: Via service level agreements with Maykin Media

## Competitive Landscape

Open Klant's position in the Dutch government CRM/contact management space:

1. **Open Klant + KISS**: The standard Common Ground stack for customer interaction
2. **e-Suite (Atos)**: Proprietary alternative used by many municipalities
3. **Pega**: Enterprise CRM used by some larger municipalities
4. **Salesforce**: Used by some government organisations
5. **Pipelinq**: Nextcloud-based CRM with broader functionality but no VNG standard compliance (yet)

Open Klant's competitive advantage is its position as the VNG-backed reference implementation. Its weakness is being API-only with no user interface.
