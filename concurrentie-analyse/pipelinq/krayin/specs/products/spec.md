---
competitor: krayin
analyzed_date: 2026-03-14
feature: products
priority: medium
---

# Product Catalog

## Overview

Krayin maintains a product catalog that can be attached to leads (with quantity/price) and quotes (as line items). Products support custom attributes, inventory tracking across warehouses, tagging, and activity logging.

## Data Model

### Product (`products` table)
| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| name | string | Product name |
| sku | string | Stock Keeping Unit |
| description | text | Description |
| quantity | int | Base quantity |
| price | decimal | Base price |

### Product Inventory (`product_inventories` table)
| Field | Type | Description |
|-------|------|-------------|
| product_id | FK | Product |
| warehouse_id | FK | Warehouse |
| warehouse_location_id | FK | Location within warehouse |
| in_stock | int | Quantity in stock |
| allocated | int | Quantity allocated |

## Key Features

- Products use `CustomAttribute` trait for EAV dynamic fields
- Products use `LogsActivity` trait for activity tracking
- Products link to warehouses/locations for inventory management
- Products attach to leads via `lead_products` table with per-lead quantity/price/amount
- Products attach to quotes as `quote_items` with full line item details

## Routes

```
GET    /products              -- List
GET    /products/create       -- Create form
POST   /products/create       -- Store
GET    /products/view/{id}    -- Detail view
GET    /products/edit/{id}    -- Edit form
PUT    /products/edit/{id}    -- Update
GET    /products/search       -- Autocomplete
GET    /products/{id}/warehouses      -- Warehouse inventory
POST   /products/{id}/inventories/{warehouseId?} -- Update inventory
DELETE /products/{id}         -- Delete
POST   /products/mass-destroy -- Mass delete
```

## Pipelinq Comparison Notes

- Product catalog is simple but functional for CRM use cases
- Inventory/warehouse integration adds depth beyond typical CRM products
- The lead-product relationship (with per-lead pricing) is useful for deal value calculations
- No product categories or hierarchies
- No product images
- SKU-based identification suggests B2B focus
