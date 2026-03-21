# Twenty CRM - Product Overview

**Analyzed:** 2026-03-14
**Source:** twenty.com, docs.twenty.com, github.com/twentyhq/twenty

## Identity

- **Tagline:** "The #1 Open-Source CRM"
- **Description:** "Building a modern alternative to Salesforce, powered by the community"
- **License:** AGPL-3.0 (open-source)
- **Founded:** December 2022
- **GitHub Stars:** ~40,400
- **GitHub Forks:** ~5,360
- **Primary Language:** TypeScript
- **Tech Stack:** React, NestJS, GraphQL, PostgreSQL

## Target Market

Twenty positions itself as a modern, developer-friendly alternative to Salesforce. It targets:
- Startups and SMBs looking for affordable CRM
- Developer-led organizations wanting extensibility
- Companies requiring data sovereignty (self-hosting)
- Organizations wanting to avoid vendor lock-in

## Core Philosophy

Twenty emphasizes "universal principles and common patterns over feature lists" and provides building blocks rather than a rigid pre-built solution. It prioritizes:
- Community-driven development
- Full customizability
- Cost-effectiveness through self-hosting
- Developer-first extensibility

## Pricing

| Plan | Price | Key Features |
|------|-------|-------------|
| Free (Self-Hosted) | $0 | All core CRM features, email/calendar sync, workflows, community support |
| Pro (Cloud) | ~$9/user/month | Same as Free + hosted infrastructure, standard support |
| Organization (Cloud) | ~$19/user/month | SSO, row-level permissions, enhanced support |
| Organization (Self-Hosted) | Unknown | SSO, row-level permissions on own infrastructure |

Premium features (SSO, row-level permissions) are only available on Organization plans.

## Deployment Options

- **Cloud:** Hosted by Twenty at api.twenty.com
- **Self-Hosted Docker:** One-line install script or manual docker-compose
- **Cloud Providers:** AWS, GCP, Azure guides available
- **Managed Hosting:** CloudStation (~$18/month), Elestio (usage-based)

### Self-Hosting Requirements
- Minimum 2GB RAM
- Docker & Docker Compose
- PostgreSQL (included in docker-compose)
- SSL recommended for production

## Technology Stack

- **Frontend:** React, TypeScript
- **Backend:** NestJS, TypeScript
- **API:** GraphQL + REST (auto-generated from data model)
- **Database:** PostgreSQL
- **UI Components:** Custom component library (twenty-ui)
- **Documentation:** Mintlify
- **Design:** Figma-based component library with Storybook
