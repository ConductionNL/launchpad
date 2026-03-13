# DigiD Authentication Flow

```
CITIZEN BROWSER              OPEN FORMS                    DigiD IdP (via SAML 2.0)
===============              ==========                    ========================

1. FORM DISPLAY
   Load form             ---> Return form with auth options:
                              [{identifier: "digid",
                                label: "DigiD",
                                url: "/auth/digid/start?form=X",
                                logo: {title: "DigiD", ...}}]

   Display login buttons <---

2. START LOGIN
   Click "Inloggen       ---> DigidAuthentication.start_login():
    met DigiD"                  Build login_url = /digid/login
                                Build return_url = /auth/return/{slug}/digid
                                  ?next={form_url}
                                  [&cosign={submission_uuid} if cosigning]
                                Redirect to login_url?next=return_url

3. SAML AUTHN REQUEST
   Redirect              ---> django-digid-eherkenning library:
                                Generate SAML AuthnRequest
                                Set requested LoA (assurance level):
                                  - Basis (default)
                                  - Midden
                                  - Substantieel
                                  - Hoog
                                Sign request with certificate

   Redirect to DigiD     -----------------------------------------> Receive AuthnRequest
                                                                     Validate signature
                                                                     Show login page

4. CITIZEN AUTHENTICATES AT DigiD
                                                                     Username + password
                                                                     SMS verification
                                                                     (or DigiD app)

                                                                     Generate SAML Response
                                                                     Include BSN + LoA
                                                                     Sign response

5. SAML RESPONSE
   POST to ACS           <----------------------------------------- Redirect with SAMLResponse
   (Assertion Consumer
    Service)              ---> django-digid-eherkenning:
                                Validate SAML signature
                                Extract BSN from assertion
                                Extract authn contexts (LoA)
                                Store in session:
                                  DIGID_AUTH_SESSION_KEY = BSN
                                  DIGID_AUTH_SESSION_AUTHN_CONTEXTS = [LoA]

6. HANDLE RETURN
                              DigidAuthentication.handle_return():
                                Read BSN from session
                                Verify LoA >= required level

                                IF cosign parameter present:
                                  handle_co_sign():
                                    Return CosignSlice{identifier: BSN}
                                ELSE:
                                  Store in form auth session:
                                    FORM_AUTH_SESSION_KEY = {
                                      plugin: "digid",
                                      attribute: "bsn",
                                      value: BSN,
                                      loa: LoA
                                    }

   Redirect to form_url  <--- Redirect to ?next= URL

7. SUBMISSION CREATION
   Resume form filling    ---> Create/update Submission
                                Create AuthInfo:
                                  plugin = "digid"
                                  attribute = "bsn"
                                  value = BSN (encrypted)
                                  loa = assurance level
                                Trigger prefill with BSN


eHERKENNING FLOW (similar):
  - Uses eHerkenning SAML IdP instead of DigiD
  - Provides KvK number + vestigingsnummer
  - Assurance levels: EH2+, EH3, EH4
  - AuthAttribute = "kvk"

OIDC VARIANT (digid_eherkenning_oidc):
  - Uses OpenID Connect instead of SAML
  - Through a broker (e.g., OpenZaak, Signicat)
  - Same auth data, different protocol
  - Simpler certificate management


AUTO-LOGIN:
  If Form.auto_login_authentication_backend = "digid":
    On form load, automatically redirect to DigiD
    Skip the "choose auth method" screen
    User must authenticate before seeing any form fields


LEVEL OF ASSURANCE CHECK:
  Form configured with minimum LoA:
    e.g., DigiDOptions.loa = DigiDAssuranceLevels.substantieel

  On return from DigiD:
    actual_loa = session[DIGID_AUTH_SESSION_AUTHN_CONTEXTS]
    if loa_order(actual_loa) < loa_order(required_loa):
      -> Error: insufficient assurance level
      -> User must re-authenticate at higher level
```
