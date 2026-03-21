# Integration Platform (xxllnc Koppelen)

competitor: xxllnc-zaken
analyzed_date: 2026-03-14
category: integration
maturity: production
source: https://xxllnc.nl/applicaties/koppelen

## Summary

xxllnc Koppelen is a separate integration application that combines three products (Connect, Koppel.app, API-Gateway) into a unified integration platform. It enables data exchange between national provisions, xxllnc applications, and third-party applications using open standards.

## Capabilities

### Open Connectors
- Reusable integration connectors based on open standards
- StUF (XML) support: StUF-BG 2.04/3.10, StUF-ZKN 3.10
- ZGW-API (JSON) support for modern case data exchange
- HaalCentraal for basic registration queries
- DSO STAM and SWF for Omgevingswet compliance

### Flexible Data Mapping
- Modify data structure and transformation per integration
- Add fields or change file formats
- Controlled and flexible data exchange between systems

### Monitoring
- Insight into data flows within the organization
- Alerting when integrations fail
- Centralized integration management view

### Partner Integrations
| Partner | Function |
|---------|----------|
| Xential | Document creation |
| ValidSign | Digital signing |
| Zynyo | Digital signing |
| Datamask | Document anonymization |
| MijnOverheid | Government citizen portal |
| Office365 | Document editing |
| Rx.Mission | VTH (enforcement) application |

### ZGW-API (Built with Conduction)
- Developed summer 2022 with Conduction developers
- Unlocks xxllnc Zaken as ZGW-compliant system
- Enables "connect once, integrate with many" approach
- Active: Rx.Mission integration
- Planned: MijnZaken, KISS

### XxllncZGWBundle (Open Source)
- Symfony bundle on GitHub (CommonGateway/XxllncZGWBundle)
- Translates between xxllnc native API and ZGW standards
- Maintained by CommonGateway/Conduction community

## Strengths
- Comprehensive standard support (StUF + ZGW + HaalCentraal)
- Centralized integration management
- Monitoring and alerting built-in
- Flexible data transformation
- Open connector approach for reuse
- ZGW-API developed with credible open-source partner (Conduction)

## Weaknesses
- Separate product — requires additional license/subscription
- Not included in base xxllnc Zaken offering
- Integration platform is proprietary (despite "open connectors" naming)
- Limited public documentation on connector development
- No evidence of webhook/event-driven integration
- No self-service connector builder for customers

## Relevance to Procest

Procest advantages via OpenConnector:
1. **Built-in integration** — OpenConnector is included, not a separate product
2. **True open source** — connectors are genuinely open source and community-maintained
3. **Self-service** — users can build custom integrations without vendor involvement
4. **Event-driven** — webhook and event-based integration support
5. **n8n integration** — visual workflow builder for complex integrations
6. **ZGW-API native** — built on the same standards without translation layer
