# BottleCRM — Competitor Analysis

## Overview

- **Website:** https://bottlecrm.io/
- **Open Source:** Yes (MIT)
- **Self-Hosted:** Yes
- **Summary:** Free open-source CRM with visual drag-and-drop pipeline

## Codebase

- **Repository:** https://github.com/MicroPyramid/Django-CRM

## Business Model

Fully open source with no paid tiers or feature gates. The project is maintained by MicroPyramid, a software development company that monetizes through consulting, custom development, and support services rather than through the CRM software itself. No subscription fees, no premium editions.

## Target Market

Startups and small businesses looking for a free, modern CRM. Built with Django REST Framework and SvelteKit, it appeals to Python/Django developers who want a customizable CRM they can extend. Multi-tenant architecture targets SaaS builders who want to offer CRM as a service.

## Pricing

- **Self-Hosted:** Free (MIT license, unlimited users)
- No paid plans, no subscription fees, no feature gates
- Costs are limited to hosting and infrastructure

## Key Features

- 360-degree customer view with smart segmentation and lead scoring
- Visual drag-and-drop pipeline for deal tracking
- Task assignment, reminders, and team synchronization
- Real-time dashboards for performance tracking and ROI measurement
- Multi-tenant architecture with PostgreSQL Row-Level Security (RLS)
- Mobile app built with Flutter
- REST API for integrations
- Built with Django REST Framework + SvelteKit

## Feature Comparison with Pipelinq

| Feature | BottleCRM | Pipelinq |
|---------|-------|----------|
| Client management (persons) | Yes | Yes |
| Organization management | Yes | Yes |
| Contact persons (linked) | Yes | Yes |
| Lead pipeline (kanban) | Yes | Yes |
| Request intake | No | Yes |
| Contact moments logging | Partial (activity history) | Yes |
| My Work queue | Partial (task management) | Yes |
| Nextcloud Contacts sync | No | Native |
| Duplicate detection | No | Yes |
| Import/Export (CSV/vCard) | Partial (CSV only) | Yes |
| Case management integration | No | Yes (Procest) |
| Nextcloud integration | No | Native |
| RBAC | Partial (basic roles) | Yes |
| Audit trail | No | Yes |

## Strengths

- Completely free with no feature restrictions — MIT license allows unrestricted use and modification
- Modern tech stack (Django REST + SvelteKit + Flutter mobile) appeals to developers
- Multi-tenant SaaS-ready architecture with PostgreSQL RLS for data isolation

## Weaknesses

- Smaller community and less mature than established CRMs — limited documentation and ecosystem
- No Nextcloud integration or Dutch government ecosystem support
- Limited enterprise features — no audit trail, no advanced RBAC, no duplicate detection

## Notes

BottleCRM is a developer-oriented open-source CRM with a modern tech stack. Its multi-tenant architecture is interesting for SaaS builders. However, it is less feature-complete than most competitors and has a small community. The project appears to have moderate activity on GitHub. Not suitable for government use cases that require Nextcloud integration and Common Ground compliance.
