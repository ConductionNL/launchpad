# CiviCRM — Competitor Analysis

## Overview

- **Website:** https://civicrm.org/
- **Open Source:** Yes (AGPL-3.0)
- **Self-Hosted:** Yes
- **Summary:** Open-source CRM for nonprofits and civic organizations

## Codebase

- **Repository:** https://github.com/civicrm/civicrm-core
- **Standalone version:** https://github.com/civicrm/civicrm-standalone

## Business Model

Fully open source with no paid editions. CiviCRM is community-funded and community-maintained. There are no licensing fees — the project sustains itself through donations, grants, and a network of paid implementation partners who provide hosting, customization, and support services. The CiviCRM LLC provides coordination but does not sell software.

## Target Market

Nonprofits, civic organizations, advocacy groups, membership organizations, and political campaigns. Specifically designed for organizations that need donor management, event planning, membership tracking, and advocacy tools. Integrates with WordPress, Drupal, and Joomla as a CMS extension. Localized in 20+ languages.

## Pricing

- **Software:** Free (AGPL-3.0, unlimited users and contacts)
- **Hosting:** Through third-party partners, typically $50-200/month
- **Implementation:** Partner services vary, typically $5,000-50,000+ depending on complexity
- No per-user fees, no feature gates

## Key Features

- Contact management with unlimited contacts and custom fields
- Donor lifecycle management and online/offline fundraising
- Membership management with automated renewals
- Event planning and registration
- Email and SMS mass communication campaigns
- Case management (CiviCase) with customizable workflows
- Grant tracking and management
- Advocacy campaigns and petition management
- Reports and analytics
- Integrates with WordPress, Drupal, Joomla
- Localized in 20+ languages

## Feature Comparison with Pipelinq

| Feature | CiviCRM | Pipelinq |
|---------|-------|----------|
| Client management (persons) | Yes | Yes |
| Organization management | Yes | Yes |
| Contact persons (linked) | Yes | Yes |
| Lead pipeline (kanban) | No (donation/membership focused) | Yes |
| Request intake | Partial (forms, petitions) | Yes |
| Contact moments logging | Yes (activities) | Yes |
| My Work queue | Partial (activity dashboard) | Yes |
| Nextcloud Contacts sync | No | Native |
| Duplicate detection | Yes | Yes |
| Import/Export (CSV/vCard) | Yes | Yes |
| Case management integration | Yes (CiviCase built-in) | Yes (Procest) |
| Nextcloud integration | No | Native |
| RBAC | Yes (ACL-based) | Yes |
| Audit trail | Yes (change log) | Yes |

## Strengths

- Purpose-built for nonprofits — donor management, membership, events, and advocacy in one platform
- Built-in case management (CiviCase) with customizable workflows for tracking complex interactions
- Completely free with no feature gates — true open source with no paid enterprise edition

## Weaknesses

- Not designed for sales/pipeline management — lacks kanban pipeline, deal tracking, and sales forecasting
- Steep learning curve and dated interface — frequently criticized for complexity and poor UX
- No Nextcloud integration; requires WordPress/Drupal/Joomla as host CMS

## Notes

CiviCRM is the dominant open-source CRM for the nonprofit sector. Its CiviCase module is the closest equivalent to case management in Pipelinq's ecosystem (Procest). However, CiviCRM is specifically designed for nonprofit workflows (fundraising, memberships, advocacy) and lacks sales pipeline features. The dated interface and steep learning curve are well-documented weaknesses. For Dutch government/municipal use, the lack of Nextcloud integration and Common Ground compliance makes it unsuitable.
