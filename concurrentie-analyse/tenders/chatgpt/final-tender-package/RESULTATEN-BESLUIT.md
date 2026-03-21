# Resultatenbesluit tenderanalyse

## Vastgelegde uitkomst

Voor de verdere verwerking en aggregatie van de tenderanalyses geldt het volgende besluit:

### 1. Leidende brede dataset
De **leidende brede dataset** is:

- `tender-analyses-repair3.zip`

Deze set geldt als de **meest complete en stabiele integrale output** over alle 245 tenders.

### 2. Gerichte aanvulling op probleemgevallen
De **gerichte aanvulling** op de brede dataset is:

- `tender-analyses-repair4-targeted.zip`

Deze set bevat een gerichte herverwerking van 13 probleemdossiers uit repair3 en moet worden gebruikt als **supplement** op repair3, niet als volledige vervanging van repair3.

### 3. Interpretatie van repair4
Repair4 is uitgevoerd met een strakkere, expliciete E/W-parser op de restgroep. Daardoor kunnen in sommige dossiers lagere aantallen eisen of wensen voorkomen dan in repair3. Dat betekent niet automatisch slechtere kwaliteit; het betekent vooral dat repair4 minder fallbacktekst gebruikt en sterker leunt op expliciet herkenbare eis-/wensregels.

## Operationeel gebruik

### Gebruik voor brede analyse / aggregatie
Gebruik als hoofdbron:

- `tender-analyses-repair3.zip`

### Gebruik voor gerichte correcties
Gebruik daarnaast voor de volgende dossiers de README's uit:

- `tender-analyses-repair4-targeted.zip`

voor zover die inhoudelijk rijker of schoner zijn dan de repair3-variant.

## Samenvatting van de beoordeling

### Repair3
- beste brede stabiele set
- duidelijke verbetering ten opzichte van eerdere herstelrondes
- geschikt als hoofdbron voor verdere consolidatie

### Repair4-targeted
- nuttige gerichte verbetering op 13 probleemgevallen
- 11 dossiers met aantoonbare verbetering
- geen volledige vervanging van repair3
- te gebruiken als aanvullende correctielaag

## Nog resterende restgroep

Na repair4 blijft een kleine restgroep over die zich het best leent voor handmatige of semi-handmatige eindcontrole. Deze omvat met name:

- 414697
- 371293
- 365739
- 363605
- 359983

## Advies voor vervolgstap

Voor verdere specificatie- en feature-analyse:

1. neem **repair3** als baselineset;
2. overschrijf of vergelijk waar relevant de 13 gerichte dossiers met **repair4-targeted**;
3. behandel de resterende kleine restgroep alleen nog handmatig indien maximale nauwkeurigheid vereist is.

