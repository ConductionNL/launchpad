# Procest — Feature Reference

**Application**: Procest
**Description**: Case management (zaakafhandeling), VTH permits and enforcement, digital forms and intake.
**Software categories**: Case management system (`zaaksysteem`), Permits & enforcement system (`vth`), Forms system (`formulieren`)
**Generated**: 2026-03-21

> TEC's BPM template covers process management. VTH and formulieren features come from Dutch tender requirements and GEMMA.

## Case management system — TEC Feature Taxonomy

*88 features from TEC RFP templates, with evidence from 6 source types.*

### 1. Process Modeling — 2 evidence, 2 source types

- **1.1 Graphical Designer**
  - 1.1.1 Workflows can be graphically designed [2 evidence, 2 types: `tec` `g2`]
  - 1.1.2 Top-level status diagram (for business users) [2 evidence, 2 types: `tec` `g2`]
  - 1.1.3 Detailed technical diagram (for implementers) [2 evidence, 2 types: `tec` `g2`]
  - 1.1.4 Drag-and-drop of workflow components [25 evidence, 5 types: `tec` `competitor` `g2` `tender-eis`]
  - 1.1.5 Can be designed by non-technical users [2 evidence, 2 types: `tec` `g2`]
  - 1.1.6 Integrates with third party IDE [2 evidence, 2 types: `tec` `g2`]
  - 1.1.7 Design stored in structured repository
- **1.10 Due Dates**
- **1.11 Process Linkage** [2 evidence, 2 types: `tec` `g2`]
- **1.2 Workflow** [24 evidence, 4 types: `tec` `competitor` `tender-eis` `tender-wens`]
- **1.3 Events**
- **1.4 Task Allocation**
- **1.5 Business Rules**
- **1.6 Business Controls**
- **1.7 Data Modeling**
- **1.8 Process Variable Binding** [2 evidence, 2 types: `tec` `g2`]
- **1.9 Manual or User-Initiated Tasks**

### 2. Security Management — 137 evidence, 5 source types

- **2.1 Roles and Users** [3 evidence, 3 types: `tec` `competitor` `g2`]
- **2.2 Role Management** [135 evidence, 5 types: `tec` `competitor` `g2` `tender-eis`]
- **2.3 User profiles**
- **2.4 User Assignment Algorithms**
- **2.5 Timers**

### 3. Process Collaboration

- **3.1 Check-in/Check-out** [3 evidence, 2 types: `tec` `tender-eis`]
- **3.2 Versioning**
- **3.3 Simulation/Validation**
- **3.4 Export Format**
- **3.5 Import Format**

### 4. Form Management — 134 evidence, 5 source types

- **4.1 Form Types**
- **4.2 Formatting**
- **4.3 Form Elements**
- **4.4 Data Validation**
- **4.5 Dynamic Forms**
- **4.6 Data Bindings**
- **4.7 Form Creation**

### 5. Workflow Portal — 23 evidence, 4 source types

- **5.1 To-do List**
- **5.2 Watch List**
- **5.3 Reports**
- **5.4 Search and Query** [3 evidence, 3 types: `tec` `competitor` `g2`]
- **5.5 Task Information**
- **5.6 Collaboration**
- **5.7 User Time Zones**
- **5.8 User Account Management** [134 evidence, 5 types: `tec` `competitor` `g2` `tender-eis`]

### 6. Monitoring and Management — 134 evidence, 5 source types

- **6.1 Instance Management**
- **6.2 Workflow Initiation** [23 evidence, 4 types: `tec` `competitor` `tender-eis` `tender-wens`]
- **6.3 Workflow Monitoring** [24 evidence, 4 types: `tec` `competitor` `tender-eis` `tender-wens`]
- **6.4 Workflow Statistics**
- **6.5 Audit Trails** [3 evidence, 2 types: `tec` `tender-eis`]
- **6.6 Resource Organization**

### 7. Process Analytics

- **7.1 Performance Data**
- **7.2 Trend Analysis** [12 evidence, 2 types: `tec` `competitor`]
- **7.3 Optimization**

### 8. Product Technology — 2 evidence, 2 source types

- **8.1 Platforms**
  - 8.1.1 Standards Compliance
  - 8.1.2 Database [2 evidence, 2 types: `tec` `g2`]
  - 8.1.3 Application Architecture [5 evidence, 2 types: `tec` `competitor`]
  - 8.1.4 Designer OS
  - 8.1.5 Web Browser [2 evidence, 2 types: `tec` `tender-eis`]
  - 8.1.6 Portal Support [4 evidence, 3 types: `tec` `g2` `tender-wens`]
  - 8.1.7 Web Service Support [10 evidence, 4 types: `tec` `gemma` `tender-eis` `tender-wens`]
  - 8.1.8 Messaging
  - 8.1.9 LDAP Support [3 evidence, 2 types: `tec` `tender-wens`]
- **8.2 Product Licensing** [2 evidence, 2 types: `tec` `competitor`]
  - 8.2.1 Licensing Components
  - 8.2.2 Licensing Terms
- **8.3 Installation**
  - 8.3.1 Time Frame
  - 8.3.2 Documentation [2 evidence, 2 types: `tec` `competitor`]
  - 8.3.3 Support [4 evidence, 3 types: `tec` `g2` `tender-wens`]
  - 8.3.4 Third Party Products [2 evidence, 2 types: `tec` `g2`]
  - 8.3.5 Acceptance Testing
  - 8.3.6 Tested Environments
- **8.4 Support** [4 evidence, 3 types: `tec` `g2` `tender-wens`]
  - 8.4.1 Trials or Evaluations
  - 8.4.2 Service Level Agreements (SLA) [7 evidence, 3 types: `tec` `gemma` `tender-eis`]
  - 8.4.3 Vendor Certification of Components
  - 8.4.4 Services [2 evidence, 2 types: `tec` `tender-eis`]
  - 8.4.5 Training Courses
  - 8.4.6 Globalization
  - 8.4.7 Languages Supported

## Case management system — Additional Features (not in TEC)

*154 features from sources outside the TEC taxonomy.*

### From competitor
- Competitor: Open Formulieren ([source](https://github.com/open-formulieren))
- Competitor: Open Klant ([source](https://github.com/open-klant))

### From gemma
- Bestuurlijk activiteiten bewakingcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-78153895-50be-4f02-aedb-083406347952))
- Bezwaar- en beroepcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-ec221e15-9b3c-411b-b2f0-c4527d59f25f))
- Generiek zaakafhandelcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-f2dfbd0b-9d36-405c-bdbe-827f3296de29))
- Zaakregistratiecomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-a97b6545-d5a7-485d-9b13-3ce22db5b9cf))
- Zaaktypecataloguscomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-3ef9cdd9-631c-4d3e-88c3-f756423d6314))

### From standard
- GEMIP ([source](https://github.com/regione-piemonte/gemip.git))
- Regione Umbria ([source](https://github.com/RegioneUmbria-RCB/VBG.git))
- SERSE ([source](https://github.com/regione-piemonte/serse.git))

### From tender-eis
- Tender 206120: VTH-SaaS applicatie gemeente Baarn & Soest ([source](https://www.tenderned.nl/aankondigingen/overzicht/206120))
- Tender 206120: VTH-SaaS applicatie gemeente Baarn & Soest ([source](https://www.tenderned.nl/aankondigingen/overzicht/206120))
- Tender 256225: VTH software gemeente Waalwijk ([source](https://www.tenderned.nl/aankondigingen/overzicht/256225))
- Tender 256225: VTH software gemeente Waalwijk ([source](https://www.tenderned.nl/aankondigingen/overzicht/256225))
- Tender 256225: VTH software gemeente Waalwijk ([source](https://www.tenderned.nl/aankondigingen/overzicht/256225))
- Tender 256225: VTH software gemeente Waalwijk ([source](https://www.tenderned.nl/aankondigingen/overzicht/256225))
- Tender 256225: VTH software gemeente Waalwijk ([source](https://www.tenderned.nl/aankondigingen/overzicht/256225))
- Tender 256225: VTH software gemeente Waalwijk ([source](https://www.tenderned.nl/aankondigingen/overzicht/256225))
- Tender 306597: Leveren van een document generator inclusief dienstverlening ([source](https://www.tenderned.nl/aankondigingen/overzicht/306597))
- Tender 306597: Leveren van een document generator inclusief dienstverlening ([source](https://www.tenderned.nl/aankondigingen/overzicht/306597))
- Tender 384261: ICC-Mutatiesignalering, -kartering en -verwerking 2025-2027 ([source](https://www.tenderned.nl/aankondigingen/overzicht/384261))
- Tender 384261: ICC-Mutatiesignalering, -kartering en -verwerking 2025-2027 ([source](https://www.tenderned.nl/aankondigingen/overzicht/384261))
- Tender 386683: Raadsinformatiesysteem Stede Broec-Enkhuizen-Drechterland ([source](https://www.tenderned.nl/aankondigingen/overzicht/386683))
- Tender 386683: Raadsinformatiesysteem Stede Broec-Enkhuizen-Drechterland ([source](https://www.tenderned.nl/aankondigingen/overzicht/386683))
- Tender 386683: Raadsinformatiesysteem Stede Broec-Enkhuizen-Drechterland ([source](https://www.tenderned.nl/aankondigingen/overzicht/386683))
- Tender 386683: Raadsinformatiesysteem Stede Broec-Enkhuizen-Drechterland ([source](https://www.tenderned.nl/aankondigingen/overzicht/386683))
- Tender 387927: VTH-software ([source](https://www.tenderned.nl/aankondigingen/overzicht/387927))
- Tender 387927: VTH-software ([source](https://www.tenderned.nl/aankondigingen/overzicht/387927))
- Tender 387927: VTH-software ([source](https://www.tenderned.nl/aankondigingen/overzicht/387927))
- Tender 387927: VTH-software ([source](https://www.tenderned.nl/aankondigingen/overzicht/387927))
- *...and 3 more*

### From tender-wens
- Tender 177754: Aanbesteding Zaaksysteem gemeente Hilversum ([source](https://www.tenderned.nl/aankondigingen/overzicht/177754))
- Tender 177754: Aanbesteding Zaaksysteem gemeente Hilversum ([source](https://www.tenderned.nl/aankondigingen/overzicht/177754))
- Tender 177754: Aanbesteding Zaaksysteem gemeente Hilversum ([source](https://www.tenderned.nl/aankondigingen/overzicht/177754))
- Tender 177754: Aanbesteding Zaaksysteem gemeente Hilversum ([source](https://www.tenderned.nl/aankondigingen/overzicht/177754))
- Tender 177754: Aanbesteding Zaaksysteem gemeente Hilversum ([source](https://www.tenderned.nl/aankondigingen/overzicht/177754))
- Tender 177754: Aanbesteding Zaaksysteem gemeente Hilversum ([source](https://www.tenderned.nl/aankondigingen/overzicht/177754))
- Tender 177754: Aanbesteding Zaaksysteem gemeente Hilversum ([source](https://www.tenderned.nl/aankondigingen/overzicht/177754))
- Tender 177754: Aanbesteding Zaaksysteem gemeente Hilversum ([source](https://www.tenderned.nl/aankondigingen/overzicht/177754))
- Tender 212765: Leveren en Implementeren van een Zaaksysteem - Openbare Euro ([source](https://www.tenderned.nl/aankondigingen/overzicht/212765))
- Tender 212765: Leveren en Implementeren van een Zaaksysteem - Openbare Euro ([source](https://www.tenderned.nl/aankondigingen/overzicht/212765))
- Tender 212765: Leveren en Implementeren van een Zaaksysteem - Openbare Euro ([source](https://www.tenderned.nl/aankondigingen/overzicht/212765))
- Tender 212765: Leveren en Implementeren van een Zaaksysteem - Openbare Euro ([source](https://www.tenderned.nl/aankondigingen/overzicht/212765))
- Tender 212765: Leveren en Implementeren van een Zaaksysteem - Openbare Euro ([source](https://www.tenderned.nl/aankondigingen/overzicht/212765))
- Tender 212765: Leveren en Implementeren van een Zaaksysteem - Openbare Euro ([source](https://www.tenderned.nl/aankondigingen/overzicht/212765))
- Tender 212765: Leveren en Implementeren van een Zaaksysteem - Openbare Euro ([source](https://www.tenderned.nl/aankondigingen/overzicht/212765))
- Tender 212765: Leveren en Implementeren van een Zaaksysteem - Openbare Euro ([source](https://www.tenderned.nl/aankondigingen/overzicht/212765))
- Tender 212765: Leveren en Implementeren van een Zaaksysteem - Openbare Euro ([source](https://www.tenderned.nl/aankondigingen/overzicht/212765))
- Tender 224235: Zaaksysteem, DMS en KCS ([source](https://www.tenderned.nl/aankondigingen/overzicht/224235))
- Tender 224235: Zaaksysteem, DMS en KCS ([source](https://www.tenderned.nl/aankondigingen/overzicht/224235))
- Tender 235619: Zaakgericht registreren ([source](https://www.tenderned.nl/aankondigingen/overzicht/235619))
- *...and 101 more*

## Permits & enforcement system — Additional Features (not in TEC)

*51 features from sources outside the TEC taxonomy.*

### From gemma
- Inspectiecomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-53d5f60e4c594a3aae70cf244797216f))
- Inspectiecomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-2c47c0aa-c9ad-4644-bfd8-9929ddafc6c0))
- Keuringcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-e46dd3d653904490af85779211e682df))
- Keuringcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-8bddf6560da14b6c9fe483d93f420ec0))
- Mobiel-toezicht-en-handhavingcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-5db42fbea5f741d1af61f2be2627ec57))
- Mobiel-toezicht-en-handhavingcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-fcf9e7bec99d4b8eb2dcc6469ebd1fa4))
- Mobiel-toezicht-en-handhavingcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-f6140c23-112b-4859-a6da-ca96c89898a2))
- Toezicht- en handhavingcomponent sociaal domein ([source](https://gemmaonline.nl/index.php/GEMMA/id-5db42fbea5f741d1af61f2be2627ec57))
- Toezicht- en handhavingcomponent sociaal domein ([source](https://gemmaonline.nl/index.php/GEMMA/id-fcf9e7bec99d4b8eb2dcc6469ebd1fa4))
- Toezicht- en handhavingcomponent sociaal domein ([source](https://gemmaonline.nl/index.php/GEMMA/id-01c26b42-e047-4322-95ba-46d53a1696c0))
- Vergunning- Toezicht- Handhavingcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-8de4d01b39624269b52f96102f643e72))
- Vergunning- Toezicht- Handhavingcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-ea8fbe41a5344c84a414de606824b40f))
- Vergunning- Toezicht- Handhavingcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-426d0f7cc8bb4012bfa79e9a71322ab2))
- Vergunning- Toezicht- Handhavingcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-59e3c3ddad2e4ad4a793ffcc0f2ff4e1))
- Vergunning- Toezicht- Handhavingcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-7ff525cbd3b34e6d98367e768f3eceb4))
- Vergunning- Toezicht- Handhavingcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-a16facc13efd493d9fe3d8af65ef82fa))
- Vergunning- Toezicht- Handhavingcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-5db42fbea5f741d1af61f2be2627ec57))
- Vergunning- Toezicht- Handhavingcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-fcf9e7bec99d4b8eb2dcc6469ebd1fa4))
- Vergunning- Toezicht- Handhavingcomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-ca98dd6d-1c0b-43dc-a26e-61ebd1cd810d))
- Vergunning- Toezicht- en Handhavingcomponent fysieke leefomgeving ([source](https://gemmaonline.nl/index.php/GEMMA/id-8de4d01b39624269b52f96102f643e72))
- *...and 4 more*

### From tender-eis
- Tender 206120: VTH-SaaS applicatie gemeente Baarn & Soest ([source](https://www.tenderned.nl/aankondigingen/overzicht/206120))
- Tender 206120: VTH-SaaS applicatie gemeente Baarn & Soest ([source](https://www.tenderned.nl/aankondigingen/overzicht/206120))
- Tender 256225: VTH software gemeente Waalwijk ([source](https://www.tenderned.nl/aankondigingen/overzicht/256225))
- Tender 256225: VTH software gemeente Waalwijk ([source](https://www.tenderned.nl/aankondigingen/overzicht/256225))
- Tender 384261: ICC-Mutatiesignalering, -kartering en -verwerking 2025-2027 ([source](https://www.tenderned.nl/aankondigingen/overzicht/384261))
- Tender 387927: VTH-software ([source](https://www.tenderned.nl/aankondigingen/overzicht/387927))
- Tender 387927: VTH-software ([source](https://www.tenderned.nl/aankondigingen/overzicht/387927))

### From tender-wens
- Tender 224235: Zaaksysteem, DMS en KCS ([source](https://www.tenderned.nl/aankondigingen/overzicht/224235))
- Tender 236933: Europese aanbesteding “aanschaf VTH-applicatie gemeente Midd ([source](https://www.tenderned.nl/aankondigingen/overzicht/236933))
- Tender 236933: Europese aanbesteding “aanschaf VTH-applicatie gemeente Midd ([source](https://www.tenderned.nl/aankondigingen/overzicht/236933))
- Tender 236933: Europese aanbesteding “aanschaf VTH-applicatie gemeente Midd ([source](https://www.tenderned.nl/aankondigingen/overzicht/236933))
- Tender 255697: VTH (Vergunning, Toezicht en Handhaving) - applicatie met ge ([source](https://www.tenderned.nl/aankondigingen/overzicht/255697))
- Tender 255697: VTH (Vergunning, Toezicht en Handhaving) - applicatie met ge ([source](https://www.tenderned.nl/aankondigingen/overzicht/255697))
- Tender 255697: VTH (Vergunning, Toezicht en Handhaving) - applicatie met ge ([source](https://www.tenderned.nl/aankondigingen/overzicht/255697))
- Tender 352726: Marktconsultatie Objectregistratie- en zaaksysteem VRZHZ ([source](https://www.tenderned.nl/aankondigingen/overzicht/352726))
- Tender 361871: VTH zaak- en registratiesysteem omgevingsdienst ([source](https://www.tenderned.nl/aankondigingen/overzicht/361871))
- Tender 377711: Vergunningverlening, Toezicht en Handhaving (VTH) applicatie ([source](https://www.tenderned.nl/aankondigingen/overzicht/377711))
- Tender 377711: Vergunningverlening, Toezicht en Handhaving (VTH) applicatie ([source](https://www.tenderned.nl/aankondigingen/overzicht/377711))
- Tender 377711: Vergunningverlening, Toezicht en Handhaving (VTH) applicatie ([source](https://www.tenderned.nl/aankondigingen/overzicht/377711))
- Tender 377711: Vergunningverlening, Toezicht en Handhaving (VTH) applicatie ([source](https://www.tenderned.nl/aankondigingen/overzicht/377711))
- Tender 385317: Vergunning-, Toezicht- en Handhaving  software Omgevingswet ([source](https://www.tenderned.nl/aankondigingen/overzicht/385317))
- Tender 387927: VTH-software ([source](https://www.tenderned.nl/aankondigingen/overzicht/387927))
- Tender 387927: VTH-software ([source](https://www.tenderned.nl/aankondigingen/overzicht/387927))
- Tender 387927: VTH-software ([source](https://www.tenderned.nl/aankondigingen/overzicht/387927))
- Tender 402863: Levering en implementatie van een SaaS-oplossing ter onderst ([source](https://www.tenderned.nl/aankondigingen/overzicht/402863))
- Tender 402863: Levering en implementatie van een SaaS-oplossing ter onderst ([source](https://www.tenderned.nl/aankondigingen/overzicht/402863))
- Tender 402863: Levering en implementatie van een SaaS-oplossing ter onderst ([source](https://www.tenderned.nl/aankondigingen/overzicht/402863))

## Forms system — Additional Features (not in TEC)

*214 features from sources outside the TEC taxonomy.*

### From g2
- AI Content Creation Platforms ([source](https://www.g2.com/categories/ai-content-creation-platforms))
- AI Medical Diagnostic Platforms ([source](https://www.g2.com/categories/ai-medical-diagnostic-platforms))
- AI Search  and Discovery Platforms ([source](https://www.g2.com/categories/ai-search-and-discovery-platforms))
- AI Search & Retrieval Infrastructure Platforms ([source](https://www.g2.com/categories/ai-search-retrieval-infrastructure-platforms))
- AIOps Platforms ([source](https://www.g2.com/categories/aiops-platforms))
- API Platforms ([source](https://www.g2.com/categories/api-platforms))
- Account-Based Orchestration Platforms ([source](https://www.g2.com/categories/account-based-orchestration-platforms))
- Analytics Platforms ([source](https://www.g2.com/categories/analytics-platforms))
- App Monetization Platforms ([source](https://www.g2.com/categories/app-monetization-platforms))
- Application Development Platforms ([source](https://www.g2.com/categories/application-development-platforms))
- Application Performance Monitoring (APM) ([source](https://www.g2.com/categories/application-performance-monitoring-apm))
- Asset Performance Management ([source](https://www.g2.com/categories/asset-performance-management))
- Asset Tokenization Platforms ([source](https://www.g2.com/categories/asset-tokenization-platforms))
- Audience Intelligence Platforms ([source](https://www.g2.com/categories/audience-intelligence-platforms))
- B2B Services Review Platforms ([source](https://www.g2.com/categories/b2b-services-review-platforms))
- Big Data Integration Platforms ([source](https://www.g2.com/categories/big-data-integration-platforms))
- Blockchain Platforms ([source](https://www.g2.com/categories/blockchain-platforms))
- Bot Platforms ([source](https://www.g2.com/categories/bot-platforms))
- Brokerage Trading Platforms ([source](https://www.g2.com/categories/brokerage-trading-platforms))
- Building Design and Building Information Modeling (BIM) ([source](https://www.g2.com/categories/building-design-and-building-information-modeling-bim))
- *...and 107 more*

### From gemma
- E-formulieren publicatie-en-beheercomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-8f4aeac59f9c4d658cfa3d7c18b2bbb7))
- E-formulieren publicatie-en-beheercomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-74c62622dd3c4540b0e5e1af44fa065e))
- E-formulieren publicatie-en-beheercomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-5c9f683b-4454-4e3e-b93c-da50dfd6934a))
- Toepasbare regelscomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-8f11297d7e264d54a6a7e96dafb70e4d))
- Toepasbare regelscomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-7f053bcc-9558-41ce-8a17-d3fa81fb7c17))
- Zelfdiagnosecomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-fbc7461e4aec4238854e56ddd9537aa8))
- Zelfdiagnosecomponent ([source](https://gemmaonline.nl/index.php/GEMMA/id-9ecb8b9c-30d2-455e-b2ad-ffbd33eb62ea))

### From standard
- AGLS Metadata Terms ([source](https://interoperable-europe.ec.europa.eu))
- Azienda Sanitaria Provinciale Siracusa ([source](https://github.com/aspsr/tracking_ps.git))
- Azienda Sanitaria Provinciale Siracusa ([source](https://github.com/aspsr/sara.git))
- CAMSS Assessment of Digikopeling ([source](https://interoperable-europe.ec.europa.eu))
- CAMSS Assessment of IPv6 - Scenario 2 ([source](https://interoperable-europe.ec.europa.eu))
- CAMSS Assessment of MQTT - Scenario 2 ([source](https://interoperable-europe.ec.europa.eu))
- CAMSS Assessment of NTA 9040 - Scenario 2 ([source](https://interoperable-europe.ec.europa.eu))
- CAMSS Assessment of PDF 1.7 - Scenario 2 ([source](https://interoperable-europe.ec.europa.eu))
- CAMSS Assessment of PDF A2 - Scenario 2 ([source](https://interoperable-europe.ec.europa.eu))
- CAMSS Assessment of STIX - Scenario 2 ([source](https://interoperable-europe.ec.europa.eu))
- CAMSS Assessment of TAXII - Scenario 2 ([source](https://interoperable-europe.ec.europa.eu))
- CAMSS Assessment of URL - Scenario 2 ([source](https://interoperable-europe.ec.europa.eu))
- CAMSS Assessment of UTF-8 - Scenario 2 ([source](https://interoperable-europe.ec.europa.eu))
- Comparison of survey software ([source](https://en.wikipedia.org/wiki/Comparison_of_survey_software))
- Comparison of survey software ([source](https://en.wikipedia.org/wiki/Comparison_of_survey_software))
- Comparison of survey software ([source](https://en.wikipedia.org/wiki/Comparison_of_survey_software))
- Comparison of survey software ([source](https://en.wikipedia.org/wiki/Comparison_of_survey_software))
- Comparison of survey software ([source](https://en.wikipedia.org/wiki/Comparison_of_survey_software))
- Comparison of survey software ([source](https://en.wikipedia.org/wiki/Comparison_of_survey_software))
- Comparison of survey software ([source](https://en.wikipedia.org/wiki/Comparison_of_survey_software))
- *...and 60 more*

---

**Summary**: 88 TEC features, 935 evidence links, 419 additional (non-TEC) features

*Generated from `concurrentie-analyse/intelligence.db` by `scripts/generate_app_features.py`*