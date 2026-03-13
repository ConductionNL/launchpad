# Registration Backend Flow

## ZGW API Registration (Primary Flow)

```
OPEN FORMS                          CATALOGI API        ZAKEN API         DOCUMENTEN API
==========                          ============        =========         ==============

PRE-REGISTRATION
1. Resolve ZaakType
   find_catalogus(domain, rsin) --> GET /catalogussen
                               <-- catalogus_url
   find_case_types(catalogus,   --> GET /zaaktypen?catalogus=X
     identification, valid_on)      &identificatie=Y
                               <-- zaaktype_url

2. Create Zaak
   create_zaak(                 -----------------> POST /zaken
     zaaktype=zaaktype_url,                        {zaaktype, bronorganisatie,
     bronorganisatie=RSIN,                          omschrijving, identificatie,
     identificatie=reference,                       zaakgeometrie, ...}
     zaakgeometrie=...)        <----------------- {url, identificatie, ...}

   Store zaak in registration_result
   Set public_registration_reference = zaak.identificatie

MAIN REGISTRATION (after PDF generated)
3. Create Initiator Rol
   Map auth data to betrokkeneIdentificatie:
     BSN -> inpBsn
     KvK -> innNnpId / kvkNummer
     + name, address, gender from prefill

   create_rol(                  -----------------> POST /rollen
     zaak=zaak_url,                                {zaak, betrokkeneType,
     betrokkeneType=NP/NNP/VES,                     roltype, betrokkene-
     betrokkeneIdentificatie=                        Identificatie, ...}
     {inpBsn, voornamen, ...})

4. Upload Confirmation PDF
   resolve_document_type(       --> GET /informatieobjecttypen
     catalogue, description,        ?catalogus=X&omschrijving=Y
     valid_on, within_case_type)
                               <-- informatieobjecttype_url

   create_report_document(      ---------------------------------> POST /enkelvoudig-
     informatieobjecttype_url,                                      informatieobjecten
     submission_report.content,                                     {informatieobjecttype,
     bronorganisatie, ...)                                           bronorganisatie,
                               <---------------------------------- inhoud (base64), ...}

   create_zaak_document(        -----------------> POST /zaak-
     zaak_url, document_url)                        informatieobjecten

5. Upload File Attachments (per file)
   For each SubmissionFileAttachment:
     create_attachment_document( -----------------------------> POST /enkelvoudig-
       informatieobjecttype_url,                                 informatieobjecten
       file_content, ...)
     create_zaak_document(      -----------------> POST /zaak-
       zaak_url, document_url)                      informatieobjecten

6. Set Zaak Eigenschappen
   For each mapped variable:
     resolve eigenschap URL     --> GET /eigenschappen?zaaktype=X
     create_zaakeigenschap(     -----------------> POST /zaak-
       zaak_url, eigenschap_url,                    eigenschappen
       waarde=variable_value)                       {zaak, eigenschap, waarde}

7. Create Status
   resolve statustype           --> GET /statustypen?zaaktype=X
   create_status(               -----------------> POST /statussen
     zaak_url, statustype_url)                     {zaak, statustype,
                                                    datumStatusGezet}

8. Update Payment Status (if applicable, after payment)
   update_zaak_payment(         -----------------> PATCH /zaken/{uuid}
     betalingsindicatie=                           {betalingsindicatie,
     'geheel'/'gedeeltelijk',                       laatsteBetaaldatum}
     laatsteBetaaldatum=...)
```

## Objects API Registration

```
OPEN FORMS                     OBJECTTYPES API       OBJECTS API         DOCUMENTEN API
==========                     ===============       ===========         ==============

1. Get Objecttype
   get_objecttype(uuid)    --> GET /objecttypes/{uuid}
                          <-- {url, name, ...}

2. Prepare Record Data
   V1 (legacy): Render Django template with submission context
     -> JSON object with embedded data

   V2 (mapped): Map form variables to JSON paths
     -> Structured JSON matching objecttype schema

3. Upload Documents (if configured)
   For each file attachment:
     create_document(      ----------------------------------------> POST /enkelvoudig-
       informatieobjecttype,                                          informatieobjecten
       file_content, ...)                                             {inhoud, ...}
                          <----------------------------------------- {url}
     Include document_url in record_data

4. Create or Update Object
   IF update_existing_object AND initial_data_reference:
     update_object(        ----------------------> PUT /objects/{uuid}
       record_data, ...)                           {type, record: {data, ...}}
   ELSE:
     create_object(        ----------------------> POST /objects
       objecttype_url,                             {type, record: {
       record_data, ...)                             typeVersion, data,
                                                     startAt, ...}}
                          <---------------------- {url, uuid, ...}

5. Payment Status Update (if applicable)
   Render payment template
   update_object(          ----------------------> PUT /objects/{uuid}
     payment_data, ...)                            {record: {data: {
                                                     payment: {status, ...}}}}
```
