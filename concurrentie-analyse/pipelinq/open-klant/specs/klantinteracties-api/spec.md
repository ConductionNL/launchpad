# Klantinteracties API -- Open Klant Feature Spec

## Overview

The Klantinteracties API (v0.6.0) is the core API of Open Klant. It implements the VNG Klantinteracties standard for storing and exposing customer data and their interactions with the municipality. Based on the VNG specification at https://zaakgerichtwerken.vng.cloud.

## API Endpoints (29 total)

| Resource | Endpoints | Methods |
|---|---|---|
| `/partijen` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/klantcontacten` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/betrokkenen` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/digitaleadressen` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/actoren` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/actorklantcontacten` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/internetaken` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/bijlagen` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/onderwerpobjecten` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/partij-identificatoren` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/categorieen` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/categorie-relaties` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/rekeningnummers` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/vertegenwoordigingen` | list + detail | GET, POST, PUT, PATCH, DELETE |
| `/maak-klantcontact` | composite | POST only |

## Key Features

### 1. Partijen (Parties/Customers)
- Three types: Persoon (person), Organisatie (organization), Contactpersoon (contact person)
- Bezoekadres (visit address) + Correspondentieadres (correspondence address)
- Preferred digital address and bank account number
- Confidentiality indicator (geheimhouding)
- Active/inactive status
- Language preference (ISO 639-2/B)
- Internal notes for staff

### 2. Klantcontacten (Customer Contacts)
- Channel tracking (telefoon, email, balie, etc.)
- Subject and content fields
- Success indicator (indicatie_contact_gelukt)
- Timestamp (plaatsgevonden_op)
- Confidentiality flag
- Metadata (arbitrary JSON key/value pairs)
- Links to betrokkenen, actoren, onderwerpobjecten, bijlagen, interne taken

### 3. Betrokkenen (Stakeholders)
- Links a Partij to a Klantcontact
- Role: klant (customer) or vertegenwoordiger (representative)
- Initiator flag
- Own contact name, addresses (can differ from partij's address)

### 4. Digitale Adressen (Digital Addresses)
- Types: email, telefoonnummer, overig
- Is-standaard-adres flag (default address)
- Verification date
- Can be linked to Partij or Betrokkene

### 5. Actoren (Actors)
- Municipality-side participants in klantcontacten
- Types: medewerker, geautomatiseerde_actor, organisatorische_eenheid
- Actor identification (objecttype, object ID, register code)

### 6. Interne Taken (Internal Tasks)
- Created from klantcontacten
- Status: te_verwerken / verwerkt
- Assigned to actoren
- Deadline tracking (afgehandeld_op)

### 7. Expand Parameter
- `?expand=digitaleAdressen,betrokkenen` inlines related objects in response
- Reduces N+1 API calls

### 8. Filtering
- Partijen: by soortPartij, nummer, indicatieActief
- Klantcontacten: by indicatieContactGelukt, date ranges
- Standard pagination (count, next, previous)

### 9. Composite Endpoint
- `/maak-klantcontact` (POST) -- creates a klantcontact with betrokkene in a single call

## Comparison with Pipelinq

### Already in Pipelinq
- Client/customer management (via OpenRegister objects)
- Contact information storage
- Case linking (via procest/zaakafhandelapp)

### Not yet in Pipelinq
- **Dedicated Klantinteracties API** conforming to VNG standard
- **Betrokkene linking model** (many-to-many between parties and contacts with role)
- **Actor model** (tracking which municipality employee handled a contact)
- **Interne Taken** (task assignment from customer contacts)
- **Onderwerpobjecten** (linking contacts to external objects like zaken)
- **Bijlagen** (attachment references on contacts)
- **Vertegenwoordigingen** (representation relationships between parties)
- **Partij Identificatoren** with BRP/HR register linking (BSN, KVK, RSIN, vestigingsnummer)
- **Rekeningnummers** (bank account numbers on parties)
- **Categorie system** (categorizing parties)
- **Composite maak-klantcontact endpoint**
- **Expand parameter** for eager loading related objects
- **Confidentiality controls** (geheimhouding on party, vertrouwelijk on contact)
- **Language preference** per party
- **Contact channel tracking** with success indicator
