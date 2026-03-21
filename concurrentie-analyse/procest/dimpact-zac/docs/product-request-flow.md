# ZAC Product Request (Productaanvraag) Flow

Source: https://github.com/infonl/dimpact-zaakafhandelcomponent/blob/main/docs/solution-architecture/productRequestSupport.md

## Overview

Standardised cross-component zaakgericht-werken flow allowing external applications (e.g., Open Formulieren) to create and start handling a zaak in ZAC.

## Flow (from Open Formulieren)

### Step 1: Citizen Submits Form
1. Citizen submits a "zaakstartformulier" in Open Formulieren
2. Open Formulieren:
   - Saves completed form as PDF in Open Zaak
   - Saves uploaded attachments as documents in Open Zaak
   - Saves form content as JSON **Product Request** in Objecten API
   - BSN (via DigiD) or KVK data stored in the product request
3. Creating the Product Request triggers a notification to Open Notificaties

### Step 2: ZAC Receives Notification
ZAC has a subscription to Product Request notifications. On receiving one:
1. Retrieves Product Request from Objecten
2. Determines zaaktype based on Product Request type
3. Creates zaak in Open Zaak
4. Links Product Request to zaak
5. Links PDF document of form to zaak
6. Links any uploaded attachments to zaak
7. Links BSN/KVK data as Role (Applicant) to zaak
8. Starts process:
   - If CMMN mapping exists -> starts CMMN Case
   - If only BPMN mapping exists -> starts BPMN process
   - If both exist -> CMMN takes precedence (BPMN ignored, warning logged)
   - If no mapping -> registers as inbox product request (no case started)

## Key Design Decisions

- ZAC does NOT integrate directly with Open Formulieren
- Integration is event-driven via Open Notificaties
- Objecten API is the intermediary for product request data
- Open Zaak is used for document storage throughout
