# Spec: Payment Integration — Open Formulieren vs Procest

## Feature: Online Payment Collection During Form Submission

### Open Formulieren Payment

**Supported providers:**
- Ogone/Ingenico (legacy, support ended 2025)
- Worldline (replacement, available since v3.3.0)
- Plugin architecture supports additional providers

**Payment flow:**
1. Form is configured with payment requirement and amount
2. Citizen completes form and clicks submit
3. Redirected to hosted payment page (Ogone/Worldline)
4. Payment methods: iDEAL, credit card, Bancontact, etc.
5. After payment: redirect back to Open Formulieren
6. Successful payment → registration proceeds
7. Failed/cancelled → retry or cancel option

**Configuration per form:**
- Payment required: on/off toggle
- Payment amount: static or calculated from form variables
- Payment plugin selection
- Product descriptions / line items

**Technical details:**
- PSPID configuration for Ogone
- SHA-IN/SHA-OUT passphrases for security
- Transaction feedback URLs
- Endpoint selection (test/production)
- Automatic migration path from Ogone Legacy to Worldline (v3.3.0)

### Procest Payment

**No payment functionality.** Procest is an internal case management tool that does not interact with citizens or collect payments.

### Gap Analysis

| Capability | Open Formulieren | Procest |
|-----------|-----------------|---------|
| Online payment (iDEAL) | Yes | No |
| Payment before case creation | Yes | No |
| Payment status tracking | Yes | No |
| Leges/fees collection | Yes | No |
| Payment provider plugins | Yes | No |

### Strategic Assessment

Payment collection is exclusively a citizen-facing intake feature. Procest has no need for it unless:
1. Procest adds a citizen-facing portal (not recommended)
2. Procest needs to track payment status of cases received from Open Formulieren — this would be visible as a Zaakeigenschap

**Recommendation:** Leave payment to Open Formulieren. If payment status tracking is needed in Procest, read it from the Zaakeigenschappen set by Open Formulieren's registration.
