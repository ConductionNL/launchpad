# ArkCase Security and Compliance

**Source:** arkcase.com, release notes, search results

## Security Certifications

### FedRAMP Authorization
ArkCase has achieved **FedRAMP Authorization** -- the US Federal Risk and Authorization Management Program that provides a standardized approach to security authorization for cloud products and services. This is a significant differentiator and barrier to entry for competitors.

FedRAMP requires continuous monitoring, regular security improvements, and recertification. Release 25.09.00 included "Security Improvements for FedRAMP Recertification."

### StateRAMP
Also authorized under StateRAMP, the state-level equivalent of FedRAMP.

### HIPAA / HITECH
Deployed to comply with healthcare data protection requirements.

### DoD 5015.2
Records management through Alfresco Governance Services meets Department of Defense records management standards.

## Security Architecture

### Network Security
- Database, Solr server, and ECM repository isolated from user-accessible network
- Users cannot directly access backend systems
- Access controls enforced at the application layer (both UI and service)
- External portal has restricted access path through REST gateway to main webapp

### Encryption
- At rest and in transit
- TLS 1.2 for all connections
- Self-signed certificates for development; proper CA certs for production
- SSL/TLS keystores and truststores in configuration

### Authentication
- **LDAP** direct binds
- **Active Directory** via LDAP
- **ADFS** via SAML or Kerberos
- Session management with configurable timeout

### Authorization (RBAC)
- Role-based access control throughout
- Navigation and viewing based on RBAC
- User groups determine content access and available actions
- Data-level access control embedded in Solr search results
- Facet counts respect access controls
- Participant-based access on case files, complaints, and consultations

### Audit
- Full audit trail/log for all actions
- Audit reports generation (with performance improvements in v25.09.01)
- History tracking for all objects
- Per-user activity monitoring

### Document Security
- Field-level redaction
- PII auto-redaction
- Version control and document locking
- Access control on documents inherits from parent objects
- "No Access" and "Group No Access" participant types

## Accessibility Compliance

### Section 508
Built-in Section 508 compliance for US government accessibility requirements.

### VPAT
Voluntary Product Accessibility Template available for procurement evaluation.

## Compliance-Specific Features

### FOIA Compliance
- 32 DOJ-required annual reports
- NIEM-XML output
- Exemption code tracking (including Exemption 3 and 5 subtypes)
- Throughput time and business days calculation
- Hold management with holiday awareness
- Fiscal year configuration

### Records Management
- Alfresco Governance Services integration
- DoD 5015.2 compliant retention and disposition
- Automated retention policies
- Records lifecycle management

### Data Protection
- PII redaction (manual and automated)
- Confidential business information workflows
- Role-based document access
- Restricted object search filtering

## Password Security
- Release 24.09.02 included "Remediate password disclosures" fix
- Ongoing security improvements each release
