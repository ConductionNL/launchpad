---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Betrokkenen (Involved Parties) -- Open Klant

## Purpose

Betrokkene links a Partij (customer/organisation) to a specific Klantcontact with a defined role. It captures WHO was involved in a contact interaction, in what capacity, and their contact details at the time of the interaction.

- **Product**: Open Klant
- **Category**: Contact Participation Tracking
- **Relevance to Pipelinq**: Pipelinq links contacts to activities but lacks the structured role/party-in-context model.

## Data Model

### Betrokkene

| Field | Type | Description |
|-------|------|-------------|
| uuid | UUIDField (unique) | Technical ID |
| partij | FK -> Partij (nullable) | The party involved (optional -- supports anonymous contacts) |
| klantcontact | FK -> Klantcontact | The contact this involvement relates to |
| rol | CharField(17, choices) | `klant` or `vertegenwoordiger` |
| initiator | BooleanField | Whether this party initiated the contact |
| organisatienaam | CharField(200) | Organisation the person acted for |
| contactnaam_voornaam | CharField(200) | First name at time of contact |
| contactnaam_voorletters | CharField(10) | Initials |
| contactnaam_voorvoegsel_achternaam | CharField(10) | Name prefix |
| contactnaam_achternaam | CharField(200) | Last name |
| bezoekadres_* | Mixin fields | Visit address at time of contact |
| correspondentieadres_* | Mixin fields | Correspondence address at time of contact |

### Key Design Decisions

1. **Anonymous contacts**: `partij` is nullable, allowing recording of contacts where the customer is unknown
2. **Point-in-time data**: contactnaam and address fields capture the customer's details AT THE TIME of contact, even if they change later
3. **Role distinction**: `klant` vs `vertegenwoordiger` (representative) -- important for legal/governmental context
4. **Initiator flag**: tracks who initiated the contact (citizen calling in vs municipality reaching out)

## API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/betrokkenen/` | List (expand: digitaleAdressen; filter: contactnaam, organisatienaam, hadKlantcontact, wasPartij) |
| POST | `/betrokkenen/` | Create |
| GET/PUT/PATCH/DELETE | `/betrokkenen/{uuid}/` | CRUD (expand: digitaleAdressen) |

## Pipelinq Comparison

| Aspect | Open Klant | Pipelinq |
|--------|-----------|----------|
| Contact-to-party linking | Betrokkene with structured role | Linked contact on activity |
| Role tracking | klant/vertegenwoordiger enum | Not available |
| Initiator tracking | initiator boolean | Not available |
| Anonymous contacts | Supported (partij nullable) | Not modeled |
| Point-in-time capture | Contactnaam + address stored per-contact | Not available (current data only) |
| Representative support | vertegenwoordiger role | Not available |

**Already in Pipelinq**: Basic contact-to-activity linking

**Not yet in Pipelinq**: Structured role model (klant/vertegenwoordiger), initiator tracking, anonymous contact support, point-in-time contact details capture, representative participation tracking
