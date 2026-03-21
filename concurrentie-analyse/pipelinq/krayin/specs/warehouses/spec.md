---
competitor: krayin
analyzed_date: 2026-03-14
feature: warehouses
priority: low
---

# Warehouse & Inventory Management

## Overview

Krayin includes warehouse management with locations and product inventory tracking. This reflects Krayin's Webkul (e-commerce) heritage and is unusual for a CRM.

## Data Model

### Warehouse
name, description, contact_name, contact_emails (JSON), contact_numbers (JSON), contact_address (JSON)

### Location
name, warehouse_id (FK)

## Features

- Custom attributes (EAV), tags, and activity logging on warehouses
- Product inventory: in_stock and allocated quantities per warehouse/location
- Sub-warehouse granularity via locations

## Pipelinq Comparison Notes

- Not relevant to Pipelinq's pipeline management scope
- Demonstrates Krayin's broader ambition beyond pure CRM
