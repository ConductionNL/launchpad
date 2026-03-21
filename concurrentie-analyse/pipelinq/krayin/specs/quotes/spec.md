---
competitor: krayin
analyzed_date: 2026-03-14
feature: quotes
priority: medium
---

# Quoting System

## Overview

Krayin supports creating quotes (proposals) with line items, discounts, tax, and shipping. Quotes link to leads and persons, and can be printed as formatted documents. Quotes use custom attributes for extensibility.

## Data Model

### Quote (`quotes` table)
| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| subject | string | Quote title/subject |
| description | text | Description |
| billing_address | JSON | Billing address object |
| shipping_address | JSON | Shipping address object |
| discount_percent | decimal | Discount percentage |
| discount_amount | decimal | Discount amount |
| tax_amount | decimal | Tax total |
| adjustment_amount | decimal | Manual adjustment |
| sub_total | decimal | Subtotal before adjustments |
| grand_total | decimal | Final total |
| expired_at | datetime | Quote expiration date |
| user_id | FK | Quote owner |
| person_id | FK | Customer contact |

### Quote Item (`quote_items` table)
| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| sku | string | Product SKU |
| name | string | Product/service name |
| quantity | int | Quantity |
| price | decimal | Unit price |
| coupon_code | string | Applied coupon |
| discount_percent | decimal | Line discount % |
| discount_amount | decimal | Line discount amount |
| tax_percent | decimal | Line tax % |
| tax_amount | decimal | Line tax amount |
| total | decimal | Line total |
| product_id | FK | Product from catalog |
| quote_id | FK | Parent quote |

## Key Features

- **Printable output**: `GET /quotes/print/{id}` generates formatted quote document
- **Lead linking**: Quotes linked to leads via `lead_quotes` pivot table
- **Custom attributes**: Quote uses `CustomAttribute` trait for extensibility
- **Expiration tracking**: `expired_at` field for quote validity
- **Full pricing model**: subtotal, discounts (% and fixed), tax, adjustments, grand total
- **Per-line pricing**: Each item has its own discount, tax, and total calculations

## Routes

```
GET    /quotes                 -- List
GET    /quotes/create/{lead_id?} -- Create (optionally pre-linked to lead)
POST   /quotes/create          -- Store
GET    /quotes/edit/{id}       -- Edit form
PUT    /quotes/edit/{id}       -- Update
GET    /quotes/print/{id}      -- Print view
DELETE /quotes/{id}            -- Delete
GET    /quotes/search          -- Autocomplete
POST   /quotes/mass-destroy    -- Mass delete
```

## Pipelinq Comparison Notes

- Comprehensive quoting with per-line discounts and tax is enterprise-grade
- Print capability is essential for sales teams
- No quote status workflow (draft -> sent -> accepted -> declined)
- No e-signature integration
- No quote versioning
- Coupon code support on line items is unusual for CRM -- e-commerce heritage
- Quote-to-lead linking via M:N allows one quote across multiple deals
