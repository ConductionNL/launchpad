# Prefill Flow

```
CITIZEN BROWSER              OPEN FORMS                    EXTERNAL REGISTRIES
===============              ==========                    ===================

1. AUTHENTICATION
   Authenticate via      ---> Store auth data in session:
   DigiD/eHerkenning          BSN = "123456789" or
                              KvK = "12345678"
                              Create AuthInfo record

2. FORM LOAD
   GET /api/v2/          ---> Identify form variables needing prefill:
   submissions/{uuid}/         For each FormVariable where
   steps/{step_uuid}            prefill_plugin != "" AND
                                prefill_attribute != "":
                                Group by plugin

3. PLUGIN DISPATCH (per plugin)

   [Haal Centraal BRP]
   Grouped attributes:        GET /brp/personen         --> Haal Centraal BRP API
     - voornamen                ?burgerservicenummer=BSN     (Kadaster)
     - geslachtsnaam            &fields=naam,verblijfplaats,...
     - geboortedatum       <-- {naam: {voornamen: "Jan",
     - verblijfplaats           geslachtsnaam: "Jansen"},
                                geboorte: {datum: "1990-01-15"},
                                verblijfplaats: {straat: "...",
                                  huisnummer: 42, ...}}

   [KvK]
   Grouped attributes:        GET /api/v1/basisprofielen --> KvK API
     - handelsnaam              /{kvkNummer}
     - adres                <-- {naam: "Bedrijf BV",
     - rechtsvorm               adres: {straat: "...", ...}}

   [StUF-BG]
   Grouped attributes:        SOAP npsLv01               --> StUF-BG endpoint
     - same as BRP              <bsn>123456789</bsn>
                           <-- <antwoord>
                                 <voornamen>Jan</voornamen>
                                 ...
                               </antwoord>

   [Objects API]
   Via initial_data_reference: GET /objects/{uuid}        --> Objects API
                           <-- {record: {data: {...}}}
                                Map JSON paths to variables

   [Suwinet]
   BSN-based:                  SOAP request               --> Suwinet
                           <-- Income/benefits data

   [Customer Interactions]
   BSN/KvK-based:              GET /klantinteracties      --> Klantinteracties API
                                /digitaleadressen
                           <-- {results: [{adres: "email@...",
                                  soortDigitaalAdres: "email"}]}

4. VALUE MAPPING
                              For each (plugin, attribute) -> variable:
                                Map returned value to FormVariable.key
                                Create/update SubmissionValueVariable:
                                  value = mapped_value
                                  source = "prefill"
                                  is_initially_prefilled = True

5. RETURN TO FRONTEND
   Receive prefilled     <--- Return step data with prefilled values
   form data                  Frontend populates form fields

6. USER MODIFICATION
   User can modify            Prefilled values are editable
   prefilled values           (unless component is read-only)

   On step submit        ---> If value changed from prefill:
                                source updated to "user_input"
                              If value unchanged:
                                source remains "prefill"


IDENTIFIER ROLES:

  "main" (default):
    - The authenticated person themselves
    - BSN/KvK from their own DigiD/eHerkenning session

  "authorizee":
    - Person being acted upon (e.g., employee filling form for citizen)
    - Requires RegistratorInfo with separate BSN/KvK
    - Separate prefill call with authorizee's identifier
```

## Plugin Requirements Matrix

```
Plugin                  | Auth Required | Auth Plugin Required | Data Source
------------------------|---------------|---------------------|------------------
haalcentraal_brp        | BSN           | -                   | Haal Centraal BRP
stufbg                  | BSN           | -                   | StUF-BG SOAP
kvk                     | KvK           | -                   | KvK API
objects_api             | varies        | -                   | Objects API
suwinet                 | BSN           | -                   | Suwinet SOAP
family_members          | BSN           | -                   | Haal Centraal BRP
customer_interactions   | BSN or KvK    | -                   | Klantinteracties API
eidas                   | pseudo        | eidas               | eIDAS attributes
yivi                    | -             | yivi_oidc           | Yivi attributes
demo                    | -             | -                   | Static test data
```
