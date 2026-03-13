# Open Formulieren — Payment Integration

## Overview

Open Formulieren supports online payment collection as part of the form submission flow. When a form requires payment, the citizen is redirected to a payment provider after submitting the form, and registration only completes after successful payment.

## Supported Payment Providers

### 1. Ogone/Ingenico (Legacy)

- **Protocol:** Ogone hosted payment page with PSPID
- **Payment methods:** iDEAL, credit card, Bancontact, and others available through Ogone
- **Configuration:**
  - Create Ogone merchant in admin interface
  - Configure PSPID (Payment Service Provider ID)
  - Set SHA-IN and SHA-OUT passphrases for security
  - Configure transaction feedback URLs
  - Select Ogone endpoint (test/production)
- **Status:** Worldline ended support for Ogone Legacy at end of 2025

### 2. Worldline (Ogone Replacement)

- **Available since:** v3.3.0
- **Protocol:** Worldline's modern payment platform (replacement for Ogone Legacy)
- **Migration:** Automatic migration path from Ogone Legacy configuration
- **Configuration:** New configuration options added in v3.2.x to prepare for migration

## Payment Flow

1. User completes the form and clicks "Submit"
2. If payment is required, user is shown the payment amount
3. User is redirected to the payment provider (hosted payment page)
4. User completes payment (e.g., iDEAL bank selection and confirmation)
5. Payment provider redirects back to Open Formulieren with payment status
6. On successful payment: registration proceeds (ZGW/Objects API/etc.)
7. On failed/cancelled payment: user can retry or cancel submission

## Payment Configuration Per Form

- **Payment required:** Toggle on/off per form
- **Payment amount:** Static amount or calculated from form variables
- **Payment plugin:** Select which payment provider to use
- **Product descriptions:** Configurable line items for the payment

## Comparison with Procest

| Feature | Open Formulieren | Procest |
|---------|-----------------|---------|
| Online payment (citizen-facing) | Yes (Ogone/Worldline) | No |
| iDEAL | Yes (via Ogone/Worldline) | No |
| Payment before registration | Yes | N/A |
| Payment status tracking | Yes | No |
| Leges (government fees) | Supported via payment amount | No |
| Invoice generation | No | No |

### Analysis

Payment integration is a citizen-facing feature tied to the form submission flow. Procest, as an internal case management tool, does not handle payments from citizens. If a municipality needs to collect fees (leges) during a permit application, Open Formulieren handles that during intake. Procest would only see the payment status as a property of the Zaak after it is registered.

Building payment integration into Procest would only be necessary if Procest developed its own citizen-facing intake portal. Otherwise, this remains Open Formulieren's domain.
