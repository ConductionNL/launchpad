# Research: Legesberekening & Bestuurlijke Besluitvorming (B&W)

Doorzoek van alle 74 ANALYSE.md bestanden in `tenders/docs/*/ANALYSE.md`.
Datum: 2026-03-15.

---

## Deel 1: Legesberekening

### Samenvatting

Legesberekening komt in **minimaal 16 tenders** voor als expliciete eis of wens, vrijwel uitsluitend in **VTH-tenders** (Vergunningen, Toezicht en Handhaving). In generieke zaaksysteem-tenders wordt legesberekening niet gevraagd -- het is een domeinspecifieke VTH-functie.

### Bevindingen per tender

#### 1. 206120 - VTH-SaaS gemeente Baarn & Soest
- **Type**: Wens (W9, 32 punten)
- **Tekst**: > "Leges" -- inclusief export naar financieel pakket (W9 F)
- **Verwachting**: Legesberekening als onderdeel van VTH-applicatie, met export naar financieel pakket via StUF-FIN (optioneel).
- **Koppeling**: I-010: Financieel pakket, out, StUF-FIN (optioneel), "Export leges/berekeningen"

#### 2. 216588 - VTH Applicatie (Zaanstad)
- **Type**: Harde eisen (E-VTH-14, E-VTH-15, E-VTH-17)
- **Teksten**:
  - **E-VTH-14**: > "Kunnen berekenen van leges binnen de applicatie, op basis van de gemeentelijke legesregeling"
  - **E-VTH-15**: > "Financieel-/legesproces zodanig dat een vergunningverlener logische keuzes of voorstellen krijgt voor leges/factuurregels, waardoor het bijna onmogelijk is om fouten te maken of zaken te vergeten."
  - **E-VTH-17**: > "Het automatisch digitaal communiceren van facturatiegegevens naar de applicatie Gouw."
  - **E-RAP-04**: > "Kunnen samenstellen van eigen rapportages [...] zodat informatie uit verschillende systemen gecombineerd kan worden (bijvoorbeeld aansluiting tussen opgelegde en daadwerkelijk ontvangen leges)"
- **Verwachting**: Foutpreventie-gestuurd legesproces, automatische koppeling met GouwBelastingen.
- **Koppeling**: I-007: GouwBelastingen, out, Export/import bestand, "Legesinformatie uitwisselen; wens: geautomatiseerde koppeling"

#### 3. 229146 - VTH-Zaaksysteem (ODRU)
- **Type**: Harde eis (E.B.01)
- **Tekst**: > "De oplossing ondersteunt legesregistratie."
- **Verwachting**: Basisregistratie van leges, rapportages over leges.
- **Beperkt**: Geen gedetailleerde berekeningseisen; meer registratie dan calculatie.

#### 4. 236933 - VTH-applicatie Middelburg
- **Type**: Harde eis (E21) + Wens (W8, 16 punten)
- **Tekst E21**: > "De Oplossing ondersteunt de mogelijkheid om: Leges te berekenen; Meerdere legesverordeningen per jaar (of gedeelte van een jaar) te gebruiken; Verschillende grondslagen toe te passen voor de berekening van leges, waarbij de (meest recente versie van het) VNG model-legesverordening (of een opvolger daarvan) uitgangspunt is; Een gecorrigeerde bouwsom als grondslag te gebruiken; Eerder opgelegde leges in mindering te brengen; In de teruggaaf van leges te voorzien (negatieve bedragen); Leges herleidbaar te corrigeren en in te trekken; Kortings- of toeslagregelingen toe te passen; Een export t.b.v. een financieel pakket aan te leveren (voor het format zie 3.2 integratie)."
- **Tekst W8**: > "Gebruiksvriendelijk leges beheren en toepassen. Subvragen A-G."
- **Verwachting**: Zeer gedetailleerd: VNG modellegesverordening als uitgangspunt, staffels, verrekening, teruggaaf, kortingsregelingen.
- **Koppeling**: I-011: Leges export, out, ASCII exportbestand, "Export naar financieel pakket"
- **E63 koppelingen**: "Leges (ASCII-export)" als een van de verplichte adapters.

#### 5. 256225 - VTH software Waalwijk
- **Type**: Harde eisen (E43-E49) + Wens (W4, 6%)
- **Teksten**:
  - **E43**: > "De Oplossing biedt de functionaliteit om leges te berekenen op basis van verschillende grondslagen, zoals bijvoorbeeld: Vast bedrag per aanvraag; Variabel bedrag (bijvoorbeeld % van de bouwkosten); Staffel (de hoogte van de bouwkosten worden in schijven(staffels) verdeeld, voor elke staffel wordt een bepaald percentage van dat deel van de bouwkosten gerekend als leges); maximum bedrag, dus leges mogen niet hoger zijn dan... De VNG modellegesverordening is daarbij het uitgangspunt."
  - **E44**: > "In De Oplossing is het eenvoudig mogelijk om leges aan te passen. Het moet bijv. mogelijk zijn leges te importeren vanuit Excel of de bestaande legestabel te kopieren."
  - **E45**: > "De Oplossing biedt de functionaliteit om meerdere legesverordeningen per jaar (of een deel van het jaar) te gebruiken."
  - **E46**: > "De Oplossing biedt de mogelijkheid om: Te verrekenen, eerder opgelegde leges in mindering te brengen; Teruggaaf van leges; Navorderen."
  - **E47**: > "De Oplossing biedt de mogelijkheid om legesgegevens te exporteren tbv controle. Hiervoor dient er periodiek een overzicht gegenereerd te worden met onderstaande gegevens: NAW gegevens; BSN/KvK van de debiteur die de leges moet voldoen; Zaaknummer (van de gemeente Waalwijk); Leges nummer volgens de legesverordening; Omschrijving van de leges; Bedrag; Datum dat de vergunningsverleningsbrief is opgesteld met de vermelding van de leges daarin."
  - **E48**: > "De Oplossing heeft een automatisch koppeling met Civision Innen (middels Centraal Facturen van Pink Roccade) voor de periodieke facturatie van de leges waarbij alle relevante gegevens beschikbaar zijn voor op de aanslag."
  - **E49**: > "Mogelijkheid tot het automatisch genereren van legesregels en bedragen op basis van de gevraagde activiteiten en bouwkosten, met daarna de mogelijkheid dat handmatig aan te passen."
  - **W4** (6%): > "De Opdrachtgever wil op een gebruiksvriendelijke manier legesverordeningen beheren en toepassen [...] rechtmatigheid ten alle tijden kan worden aangetoond. Beschrijf kort: A. Hoe de Opdrachtnemer de Opdrachtgever maximaal ontzorgt op het gebied van de leges. B. Hoe de Opdrachtnemer zorgdraagt voor een (bij voorkeur) automatisch koppelvlak tussen de Oplossing en het financiele pakket."
- **Verwachting**: 7 specifieke eisen voor legesberekening. Automatische koppeling met Civision Innen via PinkRoccade. Rechtmatigheid als expliciet doel.
- **Koppeling**: I-003: Civision Innen (PinkRoccade Centraal Facturen), out, Koppelvlak, "Periodieke facturatie leges"

#### 6. 264852 - VTH-software Westerkwartier
- **Type**: Harde eisen (E44-E49) + Wens (W7.2, W7.3)
- **Teksten**: Vrijwel identiek aan Waalwijk (E44-E49), inclusief staffels, verrekening, creditering, navordering, meerdere verordeningen per jaar. Aanvullend:
  - **E47**: Bevat ook "Facturen binnen de Oplossing te genereren via de documentgenerator."
  - **W7.2** (4 punten): "Automatische koppeling met de financiele administratie (Civision Innen)" -- NvI-43: semi-automatische koppeling via datawarehouse volstaat.
  - **W7.3** (4 punten): "Gebruiksvriendelijk beheer en toepassen van legesverordeningen en aantonen van rechtmatigheid"
- **Verwachting**: Idem Waalwijk, met toevoeging factuurgeneratie via documentgenerator.
- **Koppeling**: I-006: Civision Innen, out, XSLT/XML export, "Export leges/facturen"

#### 7. 267875 - VTH systeem (regionaal, vermoedelijk omgevingsdienst)
- **Type**: Harde eis (E32)
- **Tekst**: > "De Oplossing ondersteunt de mogelijkheid om: Leges te berekenen; Meerdere legesverordeningen per jaar te gebruiken; Leges vanuit een aanvraag uit het DSO en/of OLO over te nemen; Verschillende grondslagen toe te passen voor de berekening van leges; Een gecorrigeerde bouwsom als grondslag te gebruiken; Eerder opgelegde leges in mindering te brengen; In de teruggaaf van leges te voorzien (negatieve bedragen); Leges herleidbaar te corrigeren en in te trekken; Kortings- of toeslagregelingen toe te passen; Een export t.b.v. een financieel pakket Key2Financien aan te leveren of een geautomatiseerde koppeling via StUF-FIN."
- **Verwachting**: Standaardformulering, inclusief DSO-integratie (legesgegevens overnemen uit verzoek).
- **Koppeling**: I-016: Key2Financien, out, Export of StUF-FIN, "Legesoverdracht"

#### 8. 282155 - VTH systeem Zoetermeer
- **Type**: Harde eisen (E35, E37, E41, E42, E57, E93, E95)
- **Teksten**:
  - **E35**: > "VTH047 Kunnen laten toetsen van de beschikking inclusief legesberekening c.q. -beschikking"
  - **E37**: > "VTH050 Kunnen opstellen van een legesbeschikking"
  - **E41**: > "VTH057 Kunnen berekenen van leges op basis van gemeentelijke legesregeling"
  - **E42**: > "Aanvullend kan het systeem bij legesberekening staffels en vaste bedragen gebruiken; bij de berekening rekening houden met een ander legesartikel; een legesbedrag berekenen op basis van de bouwkosten; gebruik maken van bedragen tot 100 miljoen, en bij de berekening minimaal 3 variabelen/grondslagen gebruiken."
  - **E93**: > "VTH117 Kunnen registreren van de gemeentelijke legesregeling"
  - **E95**: > "VTH119 Kunnen uitwisselen van gegevens in de financieel component"
- **Verwachting**: GEMMA VTH-gebaseerd (VTH055/057/117/119). Specifiek: tot 100 miljoen, minimaal 3 variabelen, kruisverwijzingen tussen legesartikelen.
- **Koppeling**: I-011: Key2financien, out, StUF-FIN / export, "Registratie legesfacturen"

#### 9. 308208 - VTH systeem SaaS (omgevingsdienst)
- **Type**: Wens (W076, 20 punten)
- **Tekst**: > "De oplossing beschikt over een leges module die op basis van kenmerken in de zaak, het leges bedrag automatisch berekent en toevoegt aan een kenmerk in de zaak. Bijvoorbeeld op de kenmerken Type vergunning en Tijdbesteding."
- **Verwachting**: Automatische berekening op basis van zaakkenmerken. Minder gedetailleerd dan gemeente-tenders.

#### 10. 377711 - VTH applicatie (vermoedelijk Haarlemmermeer/regio)
- **Type**: Harde eis (E24) + Wens (W6, 35 punten = 5%)
- **Tekst E24**: > "De Oplossing ondersteunt de mogelijkheid om: Leges te berekenen; Meerdere legesverordeningen per jaar (of gedeelte van een jaar) te gebruiken; Leges vanuit een aanvraag uit het DSO over te nemen; Verschillende grondslagen toe te passen voor de berekening van leges, waarbij de VNG model-legesverordening (of een opvolger daarvan) uitgangspunt is; Een gecorrigeerde bouwsom als grondslag te gebruiken; Eerder opgelegde leges in mindering te brengen; In de teruggaaf van leges te voorzien (negatieve bedragen); Leges herleidbaar te corrigeren en in te trekken; Kortings- of toeslagregelingen toe te passen; Een export t.b.v. een financieel pakket aan te leveren of een geautomatiseerde koppeling via StUF-FIN."
- **Tekst W6** (35 punten): > "Eenvoudig leges oproepen in de zaak. Alleen relevante leges per zaaktype. Leges aanmaken tijdens het proces. A. Meerdere legesverordeningen gebruiken. B. Berekeningen en correcties. C. Versiehistorie berekeningen. D. Legesberekening vanuit willekeurig zaaktype. E. Controleproces accountantscontrole. F. Export/koppeling financieel systeem. G. Testen en overbrengen legesverordening."
- **Verwachting**: Standaardformulering + opvallend: versiehistorie van berekeningen, controleproces t.b.v. accountantscontrole, testen van legesverordening voor productie.
- **Koppeling**: I-016: Key2Financien, out, Export/import of StUF-FIN, "Leges-export"

#### 11. 385317 - VTH software Omgevingswet
- **Type**: Harde eisen (E10.1-E10.5)
- **Teksten**:
  - **E10.1**: > "VTH055 Kunnen berekenen van de leges."
  - **E10.2**: > "VTH056 Kunnen opmaken van de factuur."
  - **E10.3**: > "VTH103 Kunnen exporteren van een legesberekening en/of factuur."
  - **E10.4**: > "VTH104 Het legesberekeningssysteem biedt ondersteuning voor een zelf te configureren set legesberekening regels die het volgende omvatten: Onderscheid tussen de legesberekening op basis van de legesverordening Omgevingswet en overige legesverordening(en), Onderscheid op basis van activiteit, Teruggavestaffels voor legesberekening in verband met weigering, intrekking en buiten behandeling laten, Legesberekeningsregels o.b.v. parameters zoals oppervlakte, bouwkosten, vast bedrag of staffels, Samenloop (samenvoeging) van activiteiten."
  - **E10.5**: > "De applicatie dient ondersteuning te bieden voor het accorderen van leges middels een 4-ogen principe, waarbij minimaal twee medewerkers de legesberekening moeten goedkeuren voordat deze definitief wordt."
- **Verwachting**: GEMMA VTH-nummering. Onderscheid Omgevingswet vs. overige legesverordeningen. Samenloop van activiteiten. **4-ogenprincipe voor legesaccordering.**

#### 12. 387927 - VTH-software De Bilt
- **Type**: Harde eisen (E32-E35) + Wens (W5, 6 punten)
- **Teksten**:
  - **E32**: > "De Oplossing biedt de mogelijkheid om leges te berekenen op basis van een legesverordening."
  - **E33**: > "De Oplossing biedt de mogelijkheid om legesgegevens (zoals opgegeven bouwkosten) over te nemen vanuit het DSO."
  - **E34**: > "De Oplossing biedt de mogelijkheid om een legesfactuur op te stellen en door te zetten naar de financiele administratie."
  - **E35**: > "De Oplossing biedt de mogelijkheid om correcties op legesberekeningen door te voeren."
  - **W5** (6 punten): > "Gemeente De Bilt wil leges eenvoudig en zo simpel mogelijk toevoegen aan de zaak. Beschrijf: A) meerdere legesverordeningen; B) berekeningen en correcties; C) foutdetectie; D) controleproces t.b.v. accountantscontrole; E) exportbestand t.b.v. iFinancieen."
- **Verwachting**: Compactere eisen maar zelfde patroon. DSO-integratie, foutdetectie, accountantscontrole.
- **Koppeling**: I-016: iFinancieen, out, Export/API, "Doorzetten legesfacturen"

#### 13. 402863 - SaaS VTH omgevingsdienst (waterschap-gerelateerd)
- **Type**: Harde eisen (E31-E33 heffingenregistratie + E39 leges)
- **Tekst E39**: > "De Oplossing ondersteunt de mogelijkheid om: Leges te berekenen; Leges in delen op te leggen tijdens de behandeling van een zaak; Meerdere legesverordeningen per jaar (of gedeelte van een jaar) te gebruiken; Leges vanuit een aanvraag uit het DSO over te nemen; Verschillende grondslagen toe te passen voor de berekening van leges, waarbij de Unie van Waterschappen model-legesverordening (of een opvolger daarvan) uitgangspunt is; Een gecorrigeerde legessom als grondslag te gebruiken; Eerder opgelegde leges in mindering te brengen; In de teruggaaf van leges te voorzien (negatieve bedragen); Leges herleidbaar te corrigeren en in te trekken; Kortings- of toeslagregelingen toe te passen; Een export t.b.v. een financieel pakket aan te leveren en een geautomatiseerde koppeling te bieden met een financieel pakket."
- **E31-E33 (Heffingenregistratie)**: Specifieke waterschap-heffingen (zuiveringsheffing/verontreinigingsheffing), afgeschermd op grond van art. 67 AwR.
- **Verwachting**: Waterschap-variant: Unie van Waterschappen modelverordening i.p.v. VNG. Leges in delen opleggen. Heffingsadviezen apart.
- **Koppeling**: I-008: Financieel pakket, out, "Export + geautomatiseerde koppeling"

#### 14. 404174 - VTH applicatie Omgevingswet
- **Type**: Wens (B.27, onderdeel gunningscriterium 3)
- **Tekst**: > "De Oplossing beschikt over functionaliteit om de toepasbaarheid en berekening van leges te kunnen koppelen aan het behandelproces van een aanvraag, melding of verzoek."
- **Verwachting**: Leges als onderdeel van het behandelproces, niet als losstaande functie.

#### 15. 414239 - Applicatie VTH
- **Type**: Harde eis (E3-13)
- **Tekst**: > "Berekenen van leges op basis van de grondslag. De financiele afhandeling en facturering vindt plaats in de financiele applicatie. Het is niet nodig om in de ICT-prestatie te zien of er betaald is."
- **Verwachting**: Expliciete scheiding: berekening in VTH, facturering in financieel systeem.
- **Koppeling**: I-009: Financiele applicatie, out, (niet gespecificeerd), "Legesberekening op basis van grondslag, facturatie in financieel systeem"

#### 16. 405475 - CMS-ERP Barneveld (afvalstoffenheffing)
- **Type**: Anders -- niet VTH maar afvalinzameling
- **Tekst E13**: > "Het eenvoudig selecteren en delen van gegevens via een download met Gouw-IT voor de berekening van de afvalstoffenheffing"
- **Tekst E55**: > "Het CMS moet dan via een API-koppeling gegevens t.b.v. de afvalstoffenheffing en facturatie doorgeven aan de nieuwe financiele applicatie."
- **Verwachting**: Heffingsberekening voor afval, niet leges. Export naar Gouw-IT/belastingapplicatie.

### Synthese: Wat willen gemeenten bij legesberekening?

**Kernfunctionaliteit (in vrijwel elke VTH-tender):**
1. Berekening op basis van de gemeentelijke legesverordening (VNG model als uitgangspunt)
2. Meerdere grondslagen: vast bedrag, percentage van bouwkosten, staffels, maximumbedrag
3. Meerdere legesverordeningen per jaar (wetswijziging halverwege het jaar)
4. Verrekening: eerder opgelegde leges in mindering brengen
5. Teruggaaf (negatieve bedragen) bij weigering/intrekking
6. Correctie en intrekking (herleidbaar)
7. Kortings- en toeslagregelingen

**Export/integratie (altijd aanwezig):**
- Export naar financieel pakket is **altijd** een eis
- Formaten: ASCII, XSLT/XML, StUF-FIN, of generieke API
- Doelsystemen: Key2Financien (Centric), Civision Innen (PinkRoccade), iFinancieen, Gouw-IT
- **Geen enkele tender vraagt om betaling/incasso in de VTH-applicatie zelf** -- altijd via extern financieel systeem
- **iDEAL komt nergens voor** in VTH-legestenders

**Geavanceerde eisen (in 30-50% van tenders):**
- DSO-integratie: legesgegevens (bouwkosten) overnemen vanuit DSO-verzoek
- Automatisch genereren van legesregels op basis van activiteiten
- Foutpreventie: logische keuzes, waarschuwingen
- 4-ogenprincipe / accordering van legesberekening
- Versiehistorie van berekeningen
- Accountantscontrole-ondersteuning / rechtmatigheidsverantwoording
- Onderscheid Omgevingswet-leges vs. overige legesverordeningen
- Samenloop (samenvoeging) van activiteiten

**Conclusie: Standalone module of integratie?**
- Legesberekening is een **VTH-domein-specifieke module** die altijd gekoppeld is aan de zaak
- Het is **geen standalone financieel systeem** -- het berekent, de financiele applicatie int
- De berekening is **configureerbaar** (verordeningen wijzigen jaarlijks) maar op basis van een **vast patroon** (VNG modellegesverordening)
- Export naar het financieel systeem is het koppelpunt -- via StUF-FIN, ASCII, XML of API
- Voor ons (Procest/OpenRegister): dit is een **regelengine** op zaakkenmerken die een bedrag oplevert + een exportfunctie. n8n/Windmill zou de berekeningslogica kunnen draaien als een workflow die getriggerd wordt bij statuswijziging van de zaak

---

## Deel 2: Bestuurlijke Besluitvorming (B&W)

### Samenvatting

Bestuurlijke besluitvorming (BBV) komt in **minimaal 20 tenders** voor, en is een **standaard verwachting** bij generieke zaaksysteem-tenders. Het wordt zowel als harde eis als als wens uitgevraagd. Er is een duidelijk **standaard workflow-patroon** dat steeds terugkeert.

### Het standaard BBV-patroon (gereconstrueerd uit alle tenders)

Op basis van alle tenders is het volgende standaardproces te destilleren:

1. **Steller** maakt een advies/voorstel op vanuit een zaak
2. **Adviseurs** worden om advies gevraagd (intern, soms extern)
3. **Parafeerders** paraferen het voorstel (sequentieel of parallel)
4. **Manager/afdelingshoofd** accordeert
5. **Portefeuillehouder** (wethouder) accordeert
6. **Secretariaat/agendacommissie** plaatst op agenda + toetst (BMO/kwaliteit)
7. **College B&W** behandelt in vergadering (hamer- of bespreekstuk)
8. **Besluitenlijst** wordt opgesteld en gepubliceerd
9. **Besluiten** worden teruggekoppeld naar de zaak
10. **Archivering** van het besluit in het zaak-/DMS

Bij sommige gemeenten gaat het ook door naar de **gemeenteraad** (raadsvoorstel na collegebesluit), met moties, amendementen en stemregistratie.

### Bevindingen per tender

#### 1. 162869 - Zaaksysteem Berkelland
- **Type**: Harde eisen (O1-O7)
- **Teksten**:
  - **O1**: "Het BBV-proces van de gemeente Berkelland is als workflow ingericht tot het College."
  - **O2**: "Per processtap wordt gelogd wie welke stap heeft uitgevoerd, wanneer en hoe, om te kunnen reconstrueren hoe de besluitvormingsroute tot het College is doorlopen en wie op welk moment akkoord heeft gegeven op een voorstel."
  - **O3**: "Het is niet mogelijk een wijziging in een lopend BBV-proces door te voeren zonder deze wijziging vast te leggen."
  - **O4**: "BBV-zaken kunnen worden geagendeerd voor diverse gremia."
  - **O5**: "Meerdere mensen kunnen akkoord geven op een voorstel, waarbij wel de mogelijkheid bestaat hier in bijzondere gevallen van af te wijken."
  - **O6**: "Het is mogelijk op alle gangbare apparaten (laptops, tablets, smartphones, etc.) het proces te doorlopen en te kunnen accorderen/paraferen."
  - **O7**: "Het BBV-proces binnen de Oplossing sluit aan op het raadsinformatiesysteem (RIS) van iBabs. Voor agendering voor het College kunnen agendastukken beschikbaar gesteld worden binnen het RIS d.m.v. een koppeling. Er bestaat aantoonbaar een naadloze koppeling tussen het zaaksysteem en iBabs."
- **Parafering**: Ja (O6)
- **RIS-koppeling**: iBabs (harde eis)
- **Standaard workflow**: Ja, expliciet als workflow ingericht

#### 2. 177754 / 204076 - Zaaksysteem Hilversum/Gooise Meren
- **Type**: Wens (O4, 64 punten)
- **Tekst**: > "D. De wijze waarop het (volledige) bestuurlijke besluitvormingsproces inclusief het agendabeheer, behandelen tijdens vergaderingen en het publiceren van documenten."
- **RIS-koppeling**: iBabs (E73: verplichte koppeling voor Hilversum)
- **Parafering**: Niet expliciet

#### 3. 212765 - Zaaksysteem Zeist
- **Type**: Wens (W15, 24 punten = 3%) + Harde eis koppeling (E89)
- **Tekst W15**: > "De Gemeente Zeist wil het bestuurlijke besluitvormingsproces onderbrengen en ondersteunen binnen de Oplossing."
- **Tekst E89**: > "Functionaliteit voor het ondersteunen van collegevergaderingen is onderdeel van de Oplossing of de Oplossing koppelt met iBabs."
- **RIS-koppeling**: iBabs (harde eis)
- **Parafering**: Ja (E26: "De Oplossing ondersteunt digitaal paraferen en accorderen.")

#### 4. 21556 - Zaaksuite DOWR gemeenten
- **Type**: Wens (W103, gewicht W3)
- **Tekst**: > "Bestuurlijke besluitvormingsprocessen (agenda, vergaderen, parafencircuit)."
- **Parafering**: Ja (expliciet "parafencircuit")

#### 5. 223537 - Zaaksysteem (DMS en RMA)
- **Type**: Harde eisen (E51-E53) + Optie (E198)
- **Teksten**:
  - **E51**: > "De oplossing ondersteunt ambtelijke besluitvormingsprocessen. Dit betreft ten minste: het opstellen van een besluitvoorstel, het routeren van een voorstel ter advisering en ter besluitvorming, het vastleggen van besluiten en het publiceren of verzenden van besluiten."
  - **E52**: > "De oplossing ondersteunt het parafenproces: meerdere personen kunnen sequentieel of parallel een document of voorstel paraferen."
  - **E53**: > "Een besluit kan worden gerelateerd aan een vergadering of agendapunt."
  - **E148**: > "[...] i-Babs (oplossing voor bestuurlijke besluitvorming en vergadercycli)."
  - **E198 (Optie)**: > "Module bestuurlijke besluitvorming (BBV): vergaderingen ondersteunen in voorbereiding, uitvoering en afhandeling. Functionaliteiten: vastleggen vergadercycli, inplannen zaken op vergadering, accepteren vergaderstukken, beoordelingen portefeuillehouders/commissies, agenderen, verspreiden vergaderstukken, voeren vergadering, verslaglegging/notulering, publicatie/archivering. Retourprocessen bij niet-geschikte documenten."
  - **Wens B.c** (10 punten demonstratie): "Ambtelijke besluitvorming en aansluiting i-Babs"
- **Parafering**: Ja (E52: sequentieel of parallel)
- **RIS-koppeling**: i-Babs (harde eis)
- **Standaard workflow**: Ja -- ambtelijke besluitvorming = advies + paraferen + vastleggen + publiceren

#### 6. 224235 - Zaaksysteem, DMS en KCS
- **Type**: Koppeling
- **Koppeling**: I-005: Notubiz (NotuBiz), bi, StUF-ZKN / ZDS, "Raadsinformatiesysteem, bestuurlijke besluitvorming"

#### 7. 235619 - Zaakgericht registreren
- **Type**: Koppeling
- **Tekst E85**: Koppellijst bevat "Bestuurlijke besluitvorming/RiS (nu Ibabs)"
- **Koppeling**: I-015: BBV/RiS (nu iBabs), bi, ZGW-API/ZDS 1.1

#### 8. 252425 - Zaak- en archiefsysteem
- **Type**: Wens (W15, 64 punten -- zwaarste wens)
- **Tekst**: > "BBV-proces met MT, college, raad. 18 processtappen. Notubox-integratie. A-F: Voorstellen vanuit zaak, memo's, procesondersteuning, route monitoren, Notubox koppeling, standaard of apart component."
- **Parafering**: Ja (W4, 48 punten: "Statussen/checklists/paraferen")
- **RIS-koppeling**: Notubiz (bi, API/webservices)
- **Standaard workflow**: Ja, 18 processtappen

#### 9. 257916 - Zaaksysteem (DMS en RMA)
- **Type**: Harde eisen (E94-E97) + Optie (E197) + Demonstratie-wens
- **Teksten**:
  - **E94**: > "De gemeente wil het ambtelijk besluitvormingsproces onderbrengen en ondersteunen binnen de Oplossing. De Oplossing ondersteunt dit aan de hand van nader te configureren besluitvormingszaken en -processen, waarin met name de documentflow een centrale plaats inneemt."
  - **E95**: > "In de ambtelijke besluitvormingsprocessen is het ten minste mogelijk om advies aan te vragen en uit te (laten) voeren op processen en documenten, het (laten) accorderen van processen en documenten en het (laten) paraferen van processen en documenten. Deze taken kunnen ook door externen worden uitgevoerd."
  - **E96**: > "De ambtelijke besluitvormingsprocessen kunnen worden gekoppeld aan de bestuurlijke besluitvormingsprocessen in NotuBiz. Er wordt een koppeling vereist die het mogelijk maakt dat documenten vanuit de zaakcontext in het ZSDMS in een besluitvormings- en vergadercontext van NotuBiz kunnen worden gebracht."
  - **E197 (Optie)**: "Module bestuurlijke besluitvorming (BBV)"
  - **Demonstratie**: "Ambtelijke besluitvorming en aansluiting NotuBiz (max. 30 min, 10 punten)"
- **Parafering**: Ja (E95: expliciet, inclusief door externen)
- **RIS-koppeling**: NotuBiz (harde eis E96)
- **Standaard workflow**: Ja -- ambtelijk (in zaaksysteem) gekoppeld aan bestuurlijk (in NotuBiz)
- **Opvallend**: Expliciete scheiding ambtelijk (= zaaksysteem) vs. bestuurlijk (= NotuBiz/iBabs)

#### 10. 258217 - Applicatie bezwaar en beroep (Amsterdam)
- **Type**: Harde eis (E1)
- **Tekst**: > "De applicatie facilteert het uitwisselen van informatie in de gehele keten, zowel met interne (de besluitvormingsafdelingen) als exerne ketenpartners (de rechtbank) en de klanten"
- **RIS-koppeling**: IBabs als optionele koppeling (NvI: "Geen prijsopgave voor BRP/IBabs nodig")
- **Beperkt**: Alleen informatieuitwisseling met besluitvormingsafdelingen, geen volledig BBV-proces

#### 11. 263227 - Zaaksysteem (middelgrote gemeente)
- **Type**: Wens (W13, 7% = 56 punten)
- **Tekst**: > "Beschrijf ondersteuning voor het volledige BBV-proces (10 stappen) inclusief agendabeheer, vergaderbehandeling, publicatie. Geef aan of dit standaard is of aanvullende componenten/koppelingen vereist."
- **RIS-koppeling**: iBabs, out, API, "Bestuurlijke besluitvorming, vergaderagenda"
- **Standaard workflow**: Ja, 10 stappen

#### 12. 263644 - Zaaksysteem Terneuzen
- **Type**: Wens (Wens 14, 3% = 24 punten)
- **Tekst**: > "Bestuurlijke besluitvorming (college, raad, management, commissie bezwaarschriften, mandaatbesluiten), agendabeheer, vergaderondersteuning"
- **RIS-koppeling**: eBesluitvorming (Visma Roxit), bi, StUF-ZKN/ZKN-DMS
- **Parafering**: Niet expliciet
- **Opvallend**: Benoemt ook mandaatbesluiten en commissie bezwaarschriften

#### 13. 265849 - Generiek zaaksysteem (DMS en RMA)
- **Type**: Onderdeel van scope
- **Tekst SCOPE-ZS-01**: > "Ondersteuning van de (bestuurlijke) besluitvorming, denk hierbij aan de mogelijkheden voor agendering, verslaglegging en besluitvorming"
- **RIS-koppeling**: iBabs (B&W) + NotuBiz (Raad), beide 2-weg koppelvlak

#### 14. 267874 - Zaaksysteem Den Helder
- **Type**: Wens (deel van grotere functionaliteitsbeschrijving)
- **RIS-koppeling**: GemeenteOplossingen (Visma Roxit Besluitstraat), iBabs
- **Scope**: Volledig BBV-proces gevraagd als onderdeel van zaaksysteem

#### 15. 305956 - Vervanging Zaaksysteem Lochem
- **Type**: Harde eis (E37)
- **Tekst**: > "Bestuurlijke besluitvorming wordt ondersteund. Hierbij is aanwezig: Een proces voor het opstellen van adviezen; Een proces voor vergaderingen (elke vergadering apart, inclusief bijbehorende besluitenlijsten)."
- **NvI verduidelijking**: "Adviesproces met parafering en aanbieden aan raadsvergadering; vergaderingszaak met besluitenlijsten."
- **Parafering**: Ja (NvI: expliciet "parafering")
- **RIS-koppeling**: Niet expliciet (PolitiekPortaal als optie)

#### 16. 348539 - DMS met Zaaksysteemfunctionaliteit
- **Type**: Harde eisen (E85-E86, E94-E97) + Koppeling
- **Teksten**:
  - **E85**: > "De Oplossing biedt ondersteuning voor zogenaamde documentflows: documenten die zich door de organisatie bewegen voordat deze een definitieve status verkrijgen."
  - **E86**: > "Documentflows kunnen bestaan uit de volgende documentaire handelingen: documenten aanmaken, documenten reviewen, nieuwe versies maken, adviseren op documenten, accorderen van documenten, paraferen en/of ondertekenen van documenten, status aanpassen."
  - **E94**: > "De gemeente wil het ambtelijk besluitvormingsproces onderbrengen en ondersteunen binnen de Oplossing."
  - **E95**: > "In de ambtelijke besluitvormingsprocessen is het ten minste mogelijk om advies aan te vragen [...], het (laten) accorderen [...] en het (laten) paraferen van processen en documenten. Deze taken kunnen ook door externen worden uitgevoerd."
  - **E96**: > "De ambtelijke besluitvormingsprocessen kunnen worden gekoppeld aan de bestuurlijke besluitvormingsprocessen in NotuBiz."
- **Parafering**: Ja (E86, E95)
- **RIS-koppeling**: NotuBiz (harde eis E96)

#### 17. 362829 - Zaak-/archiefsysteem
- **Type**: Harde eisen (E93-E99) + Wens (W3, 40 punten)
- **Teksten**:
  - **E93**: > "De oplossing dient het bestuurlijke besluitvormingsproces te ondersteunen."
  - **E94**: > "Vanuit de oplossing maakt de behandelaar (steller) een advies (DT-advies, collegeadvies raadsadvies) op met eventuele bijlagen."
  - **E96**: > "De oplossing biedt mogelijkheden om een parafering in te stellen afhankelijk van het soort voorstel."
  - **E97**: > "De oplossing geeft een agendaoverzicht met daarin per onderwerp het voorstel inclusief eventuele bijlagen."
  - **E99**: > "De oplossing biedt een integratie met iBabs en Notubiz. Hierbij is het tenminste mogelijk om vanuit een zaak documenten over te zetten naar iBabs/Notubiz ter besluitvorming. Na besluitvorming is het mogelijk om het besluit terug in te lezen in de zaak."
  - **W3** (40 punten): > "1. Beschrijf hoe de oplossing het proces zaakgericht ondersteunt voor steller, parafeerder, portefeuillehouder, secretariaat. 2. Beschrijf mogelijkheden om advies op te vragen bij collega's. 3. Beschrijf mogelijkheden om een voorstel als hamer- of bespreekstuk aan te merken. 4. Beschrijf mogelijkheden om te paraferen vanaf mobiele apparaten."
- **Parafering**: Ja (E96: afhankelijk van soort voorstel; W3: mobiel paraferen)
- **RIS-koppeling**: iBabs OF Notubiz (na NvI: keuze bij implementatie)
- **Standaard workflow**: Ja -- steller > parafeerder > portefeuillehouder > secretariaat > college

#### 18. 386683 - Raadsinformatiesysteem SED
- **Type**: Dit IS een RIS-tender (geen zaaksysteem)
- **Context**: SED zoekt vervanger voor Notubiz. RIS wordt gebruikt door raads- en commissieleden, colleges van B&W, griffiemedewerkers en inwoners. 60 gebruikers per gemeente.
- **Functionaliteit**: Verzamelplaats vergaderstukken, besluitvormingsproces, live streaming, webcasting.
- **Koppeling**: Met zaaksystemen (Djuma), AV-systemen, digitale ondertekening (ValidSign).
- **Opvallend**: Optionele uitbreiding voor Bestuurlijk Informatiesysteem (BIS).

#### 19. 397891 - Zaaksysteem Leusden
- **Type**: Wens (W10, 8%)
- **Tekst**: > "De Gemeente Leusden wil het bestuurlijke besluitvormingsproces onderbrengen en ondersteunen binnen de Oplossing. [...] Hieronder valt het opstellen van stukken waar besluitvorming op dient plaats te vinden. Voor het creeren van deze stukken dienen adviezen te worden ingewonnen welke geregistreerd worden in de Oplossing. Vervolgens moeten deze stukken kunnen worden aangeboden middels een koppeling met de applicatie voor bestuurlijke besluitvorming."
- **RIS-koppeling**: GemeenteOplossingen (huidig RIS)
- **Standaard workflow**: Ja -- stukken opstellen > advies > koppeling met RIS > archivering na besluit

#### 20. 400508 - Zaaksysteem (onbekende gemeente)
- **Type**: Wens (W11, 48 punten)
- **Tekst**: > "Bestuurlijk besluitvormingsproces in Oplossing. College en gemeenteraad. Paraferen. iBabs voor agendabeheer. Subvragen: A. Ondersteuning. B. Standaard of aparte componenten. C. Moties, amendementen, raadsvragen. D. Documenten beveiligen. E. Overzicht en statusvolging."
- **Parafering**: Ja
- **RIS-koppeling**: iBabs (API)
- **Opvallend**: Ook moties, amendementen, raadsvragen -- raadsproces naast collegeproces

#### 21. 402469 - Document Management Systeem (waterschap)
- **Type**: Open vraag (Bijlage 9, 5% gewicht)
- **Tekst Bijl9-A**: > "Geef aan op welke manier de Oplossing dit ondersteunt ga hierbij specifiek in op: De ondersteuning van de opsteller van voorstellen; De ondersteuning van de manager die stukken accordeert; De ondersteuning van besluitvormers en de secretaris-directeur; De manier waarop besluitenlijst en verslag worden gegenereerd en overgezet naar het zaaksysteem; De manier waarop dit geheel te configureren is; De manier waarop digitaal ondertekend kan worden; De integratiemogelijkheden van het zaaksysteem met Ibabs, waaronder het up- en downloaden van documenten van en naar het zaak-DMS systeem."
- **Parafering**: Niet expliciet (wel "accorderen")
- **RIS-koppeling**: IBabs (harde verwachting)
- **Standaard workflow**: Ja -- opsteller > manager > besluitvormers > secretaris-directeur

#### 22. 411386 - Geintegreerd Zaaksysteem met KCS
- **Type**: Harde eisen (2.12.1-2.12.10+)
- **Teksten**:
  - **2.12.1**: "Bestuurlijke besluitvorming wordt ondersteund. Hierbij is aanwezig: Een proces voor het opstellen van adviezen; Een proces voor vergaderingen (elke vergadering apart, inclusief bijbehorende besluitenlijsten)."
  - **2.12.3**: "De Oplossing ondersteunt het opstellen, beheren en publiceren van agenda's voor alle gremia (PHO, college, raad, commissies)."
  - **2.12.8**: "Besluitvormingsstukken zijn voorzien van metadata (zaaktype, onderwerp, portefeuillehouder, status)."
  - **2.12.9**: "Het proces van totstandkoming is traceerbaar, inclusief wijzigingen, annotaties en opmerkingen."
  - **2.12.10**: "Alle versies en instanties van besluitstukken zijn vindbaar en corrigeerbaar gedurende de volledige levenscyclus."
  - **NvI**: Vergadertool (iBabs) hoeft niet als onderdeel geleverd; koppeling volstaat.
- **RIS-koppeling**: iBabs / Notubiz (koppeling volstaat)
- **Standaard workflow**: Ja -- 24 eisen rondom BBV, inclusief stemregistratie per fractie/raadslid, videoverslagen met metadata

#### 23. 414248 - Zaaksysteem (Zaanstad)
- **Type**: Harde eisen (E52-E54) + Wens (W9, 68 punten)
- **Teksten**:
  - **E52**: > "In de Oplossing is het mogelijk om een BBV ter parafering aan te bieden aan de opdrachtgever en portefeuillehouder(s), inclusief de mogelijkheid voor het secretariaat om namens de wethouder te paraferen."
  - **E53**: > "In de Oplossing is het mogelijk om technische en inhoudelijke opmerkingen te plaatsen bij een BBV door de agendacommissie en BMO, en de voortgang van de parafering en toetsing te monitoren."
  - **E54**: > "De Oplossing ondersteunt het faciliteren van het opstellen en publiceren van agenda's en het digitaal vergaderen, inclusief het toewijzen van BBV's aan vergaderingen."
  - **W9** (68 punten): > "Beschrijf hoe de Oplossing het proces van bestuurlijke besluitvorming ondersteunt, zoals beschreven in Bijlage 14. Ga daarbij in op: A. Het aanmaken en routeren van BBV's. B. Parafering en toetsing. C. Vergaderbeheer. D. Geheimhouding. E. Indien het BBV-proces niet standaard onderdeel is van de Oplossing, beschrijf dan welke additionele component(en) en/of koppeling(en) nodig zijn en wat de prijs hiervan is."
- **Parafering**: Ja (E52: inclusief namens wethouder; W9: expliciet)
- **RIS-koppeling**: iBabs (vereist)
- **Opvallend**: Geheimhouding als apart onderdeel. Secretariaat mag namens wethouder paraferen.

### Synthese: Wat willen gemeenten bij B&W-besluitvorming?

**Standaard workflow-patroon (in >80% van de tenders die BBV noemen):**
1. Steller maakt voorstel/advies
2. Intern advies inwinnen
3. Parafering (configureerbaar: sequentieel/parallel, afhankelijk van type voorstel)
4. Accordering door management/portefeuillehouder
5. Agendering op vergadering (college/raad)
6. Behandeling in vergadering
7. Besluitenlijst genereren
8. Besluit terugkoppelen naar zaak
9. Publicatie en archivering

**Scheiding ambtelijk vs. bestuurlijk (expliciet in 5+ tenders):**
- **Ambtelijk** (stap 1-4): In het zaaksysteem -- parafering, advisering, accordering
- **Bestuurlijk** (stap 5-9): In het RIS (iBabs/NotuBiz) -- vergadering, besluitenlijst
- De koppeling ertussen is het **kritieke punt**: documenten heen, besluiten terug

**RIS-koppelingen (welke systemen):**
| RIS-systeem | Aantal tenders | Type koppeling |
|---|---|---|
| iBabs | 12+ | API, ZDS, ZGW-API, of bestandsuitwisseling |
| NotuBiz/Notubiz | 8+ | API, ZDS, StUF-ZKN, of ZIP(XML+PDF) |
| GemeenteOplossingen (Visma Roxit) | 3 | Diverse |
| eBesluitvorming (Visma Roxit) | 1 | StUF-ZKN/ZKN-DMS |
| PolitiekPortaal | 1 | Onbekend |

**Parafering/mandatering:**
- Parafering is **standaard** in BBV-processen (gevonden in 12+ tenders)
- Sequentieel of parallel paraferen wordt expliciet gevraagd
- Mobiel paraferen (tablets/smartphones) wordt in meerdere tenders gevraagd
- Parafering is **afhankelijk van het type voorstel** (configureerbaar)
- Externen kunnen ook paraferen (E95 in meerdere tenders)
- Mandatering komt beperkt voor (1x als reden voor vervallen eis, 1x als onderdeel van scope)
- 4-ogenprincipe wordt soms gevraagd (vooral bij leges, soms bij besluiten)

**Wat is standaard, wat is specifiek?**
- **Standaard**: Advies-parafeer-accordeer workflow, koppeling met RIS, besluitenlijst
- **Specifiek per gemeente**: Welk RIS (iBabs vs. NotuBiz), aantal parafeerstappen, gremia (alleen college of ook raad), metadata-eisen
- **Geavanceerd**: Stemregistratie per raadslid, videoverslagen, moties/amendementen

### Conclusie: n8n/Windmill vs. custom?

**Het BBV-proces is zeer geschikt voor workflow-automatisering via n8n/Windmill:**

1. **Het is een documentflow** -- documenten bewegen door de organisatie met statuswijzigingen, accordering en parafering. Dit is een sequentiele workflow met conditionele routing.

2. **De stappen zijn configureerbaar maar voorspelbaar** -- het verschilt per zaaktype welke parafeerroute gevolgd wordt, maar het patroon is altijd: opstellen > adviseren > paraferen > accorderen > agenderen.

3. **De koppeling met iBabs/NotuBiz is het complexe deel** -- maar dit is een API-integratie, geen workflow-vraagstuk. iBabs heeft een API; NotuBiz accepteert ook bestandsuitwisseling.

4. **De audit trail is cruciaal** -- elke stap moet gelogd worden (wie, wanneer, welk besluit). Dit is standaard n8n/workflow-functionaliteit.

**Wat n8n/Windmill WEL kan:**
- Workflow-routing (sequentieel/parallel parafering)
- Taaktoewijzing aan gebruikers
- Statusbewaking en notificaties (termijnen)
- API-integratie met iBabs/NotuBiz
- Audit trail / logging

**Wat AANVULLEND nodig is (niet standaard in n8n):**
- **UI voor parafering** -- een gebruiker moet een voorstel kunnen inzien, opmerkingen plaatsen, en paraferen/accorderen. Dit vereist een frontend-component (niet alleen een workflow-engine).
- **Agendabeheer** -- vergaderingen plannen, stukken toewijzen aan agendapunten. Sommige gemeenten doen dit in het RIS, maar anderen willen het in het zaaksysteem.
- **Mobiel paraferen** -- responsive UI of app voor wethouders/bestuurders.
- **Digitale ondertekening** -- integratie met ValidSign of vergelijkbaar (niet parafering maar formele ondertekening van besluiten).

**Aanbeveling:**
- Het BBV-proces als **configureerbare workflow in Procest** implementeren, met n8n als workflow-engine
- De **parafering-UI** als Nextcloud-app-component (taaklijst met accordeer-/parafeerknoppen)
- De **RIS-koppeling** als aparte connector (iBabs-API, NotuBiz-API) die vanuit de workflow wordt aangestuurd
- **Niet** proberen om het volledige vergaderbeheer (agenda, vergaderondersteuning, streaming) in het zaaksysteem te bouwen -- dat is het domein van iBabs/NotuBiz/GemeenteOplossingen

---

## Appendix: Financiele systemen die voorkomen als koppelingsdoel

### Bij leges (VTH):
| Systeem | Leverancier | Protocol | Frequentie |
|---|---|---|---|
| Key2Financien | Centric | StUF-FIN of export | 4+ tenders |
| Civision Innen | PinkRoccade | Koppelvlak / Centraal Facturen | 3+ tenders |
| iFinancieen | Centric | Export/API | 2+ tenders |
| GouwBelastingen | Gouw-IT | Export/import bestand | 1 tender |
| Unit4Financials | Unit4 | ZGW-API | 1 tender |

### Bij BBV (besluitvorming):
| Systeem | Leverancier | Rol | Frequentie |
|---|---|---|---|
| iBabs | iBabs B.V. | RIS voor college/raad | 12+ tenders |
| NotuBiz/Notubiz | NotuBiz B.V. | RIS voor raad | 8+ tenders |
| GemeenteOplossingen | GemeenteOplossingen B.V. | RIS/BIS | 3 tenders |
| eBesluitvorming | Visma Roxit | Besluitstraat | 1-2 tenders |
| PolitiekPortaal | Diversen | RIS | 1 tender |
