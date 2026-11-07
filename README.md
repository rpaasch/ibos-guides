# IBOS Guides – WordPress-plugin

Et WordPress-plugin til tilgængelige guide-apps (PWA), fx **O&M-teknik** og **Ledsageteknik**. Indholdet redigeres som almindelige sider i WordPress. Hver guide får sin egen adresse, kan lægges på hjemmeskærmen som en app og virker uden internet.

- Den færdige plugin-fil hedder **`ibos-guides-<version>.zip`**, fx `ibos-guides-1.2.0.zip`, og ligger øverst i repoet.
- Pluginet rører ikke temaet. Appen vises i sit eget, enkle design i IBOS' farver.
- Kræver WordPress 6.4 eller nyere og PHP 7.4 eller nyere. Testet på WordPress 7.1.4 (samme version som ibos.dk) med PHP 8.3. Begrænsningen af overskrifter til H2–H4 kræver WordPress 6.7.

---

## Installation

1. **Plugins → Tilføj nyt plugin → Upload plugin**, vælg `ibos-guides-<version>.zip`, tryk **Installér nu** og derefter **Aktivér**.
2. Gå til **Guides → Importér**. Skriv den ønskede adresse for hver guide (fx `guides/om-teknik` eller `o-og-m`), og tryk **Importér O&M-teknik** og/eller **Importér Ledsageteknik**. Adresser, som sitet allerede bruger, afvises.
   - Billederne hentes fra GitHub (`raw.githubusercontent.com`) og lægges i mediebiblioteket med deres alt-tekster.
   - O&M-teknik har 252 billeder og tager et par minutter. Lad siden være åben imens. Afbrydes importen, fortsætter den, hvor den slap, når du trykker igen.
   - **Når importen er færdig, er guiden udgivet og kan ses af alle.** Importér gerne på et testsite først, eller sæt guiden til Kladde bagefter.
3. Appen ligger nu på den valgte adresse, fx `https://ibos.dk/guides/om-teknik/`.

**Krav:**
- HTTPS
- Permalinks må ikke stå på "Simpel" (der vises en advarsel på listen Guides → Alle guides og sider)
- Serveren skal kunne hente filer fra GitHub under importen

## Prøv det uden at installere

Åbn [WordPress Playground med IBOS Guides](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/rpaasch/ibos-guides/main/playground/blueprint.json). Det er en midlertidig WordPress i browseren med den nyeste udgivne version af pluginet (fra `main`). Den åbner på siden Importér. Intet gemmes, når fanen lukkes.

---

## Hvad borgeren ser

- **Forsiden:** guidens tekst og en menu med kapitlerne (som store felter eller en liste).
- **Kapitler og sider** med brødkrumme ("Du er her"), **Tilbage** og **Forrige/Næste side** nederst.
- **Søg** øverst: søger i titler og tekst, også uden internet, og tåler stavevarianter (fx "dore" finder "Døre").
- **Installér appen** øverst: vejledning til iPhone/iPad, Android og computer, en knap til at installere (hvor telefonen tillader det), **Hent videoer** og en QR-kode til at vise appen til en anden.
- **Ny version:** når guiden er ændret, vises "Der er en ny version af appen" øverst på siden med knappen **Opdatér nu**.
- Første gang appen åbnes med internet, gemmes alle sider, billeder og søgningen på telefonen. Derefter virker den uden net.

## Hvem kan hvad

| Rolle | Kan |
|---|---|
| Administrator og Redaktør | Se Oversigt, oprette, redigere, flytte, kopiere og slette guide-sider, ændre App-indstillinger, importere guides |
| Forfatter, Bidragyder | Kan ikke arbejde med guides (guides er sider, og det kræver rettigheden til at redigere sider) |

---

## Sådan redigerer du

Alt foregår under menupunktet **Guides**.

| Niveau | Hvad det er | Eksempel |
|---|---|---|
| Side uden forælder (med flueben i "Dette er en guide") | **En guide (en app).** Teksten bliver appens forside. | Ledsageteknik |
| Side med guiden som forælder | **Et kapitel** i menuen | Basisteknikker |
| Side under et kapitel | **En side** | Grebet |
| Side under en side | **En underside** | (bruges sjældent) |

- **Overblik:** **Guides → Oversigt** viser hver guide som en indholdsfortegnelse med kapitler og sider, i samme rækkefølge som i appen. Alle ændringer gemmes med det samme.
- **Røde mærker** i Oversigt viser, hvad der mangler: fx "2 ting at rette" (alt-tekst, undertekster, eksempeltekst …) eller "Ikke i appen – et niveau over er ikke udgivet".
- **Flyt:** træk i håndtaget **⠿** foran en side, også over i et andet kapitel. Uden mus kan du bruge **↑/↓** eller **Mere → Flyt til kapitlet** (for kapitler: **Mere → Flyt kapitlet til guiden**). Flyttes en side til et kapitel, der er en kladde, får du besked om, at den ikke vises i appen.
- **Ny side:** **+ Ny side i kapitlet** i Oversigt eller i boksen **Placering** i editoren. Siden lægges sidst i kapitlet.
- **Kopiér og slet:** **Mere → Kopiér** laver en kladde lige under originalen (også en eventuel adgangskode kopieres). **Mere → Læg i papirkurven** tager eventuelle undersider med, og det kan fortrydes.
- **I editoren:** boksen **Placering** viser, hvor siden ligger, og har knapperne **‹ Forrige: …** og **Næste: … ›** (i appens rækkefølge), **+ Ny side** og **Tilbage til Oversigt**. Du kan også flytte en side ved at klikke på **Overordnet** øverst i panelet.
- **Kapitler er sider:** et kapitel er selv en side. Den vises som menupunkt med sine undersider og kan have en kort intro-tekst. Giv ikke kapitlet og dets side samme titel – det gør menuen svær at skelne med skærmlæser (tjekket advarer).
- **Skabelon:** en ny side starter med en indledning, et billede og punkter. Hvert felt har en grå hjælpetekst, som forsvinder, når du skriver. Felter, du ikke bruger, vises ikke i appen, men det er pænest at slette dem. Kapitler og guides får deres egen korte skabelon.
- **Mønstre:** tryk **+** (Bloktilføjelse) øverst til venstre → fanen **Blokgrupper** → **IBOS Guide** for færdige byggeklodser: *Side med trin og billede*, *Billede med forklaring*, *Video med forklaring*, *Mellemoverskrift med punkter*, *Nummererede trin* og *Godt at vide*. Eksempelteksten står i `[✎ …]`. Skriv den om – den vises aldrig i appen, og tjekket advarer, hvis den stadig står der.
- **Tekst:** skriv i blok-editoren. Overskrifter kan kun være H2–H4, fordi sidens titel er overskrift 1. Kun blokke, der giver mening i en guide, kan vælges.
- **Billeder:** brug billedblokken, og udfyld **Alternativ tekst**. Er billedet kun pynt, så markér det som dekorativt i billedblokkens indstillinger, og lad alt-teksten være tom. Undgå at starte alt-teksten med "Foto af" – skærmlæseren siger allerede "billede".
- **Kort beskrivelse i menuen:** udfyld **Uddrag** (excerpt).
- **Skjul en side:** sæt flueben ved "Skjul i menu …" i boksen **Visning**. En skjult side kommer heller ikke med i søgningen og virker ikke uden internet.
- **Udgiv:** tryk **Gem** (eller **Udgiv** første gang). Næste gang brugerne åbner appen med internet, får de beskeden "Der er en ny version af appen" med knappen **Opdatér nu**.
- **Tilgængelighedstjek:** panelet af samme navn i fanen **Guide-side** i editoren tjekker siden, mens du skriver, og vises igen lige før du udgiver. **Vis blokken** springer til det sted, der skal rettes. Tjekket finder fx manglende alt-tekst, tomme billed- og videoblokke, video uden undertekster eller med "Afspil automatisk", eksempeltekst fra mønstre, overskrift 1, spring i overskriftsniveauer, links der hedder "klik her" og sider med samme titel som kapitlet. Kolonnen **Tjek** på listen og mærkerne i Oversigt viser det samme.

### Video

Brug videoblokken (eller mønstret *Video med forklaring*), og overfør videoen til **Medier**.

- **Format:** mp4 (H.264). Hold videoen kort – gerne under 2–3 minutter og under 50 MB. Serverens grænse for filstørrelse kan forhindre store videoer.
- **Undertekster:** vælg videoblokken → **Tekstspor** i værktøjslinjen → overfør en `.vtt`-fil, typen **Undertekster** (eller **Billedtekster**) og sproget `da`. En `.vtt`-fil kan laves med fx Subtitle Edit eller eksporteres fra YouTube Studio.
- **Skriv det vigtigste som tekst** på siden, så den kan bruges uden lyd, uden internet og med skærmlæser.
- **Slå ikke "Afspil automatisk" til.** Lyd, der starter af sig selv, overdøver skærmlæseren. Appen viser altid afspilningsknapper.
- **Uden internet:** som standard kan borgeren hente videoerne på siden **Installér appen** → *Hent videoer (ca. X MB)*, fx på wifi. Derefter kan de ses og spoles i uden net. Videoer hentes aldrig automatisk, så borgeren ikke uventet bruger data og plads. De kan fjernes igen med *Fjern hentede videoer*.
  - På **iPhone og iPad** skal videoerne hentes inde i den installerede app (Safari og appen på hjemmeskærmen har hver sin lagerplads). Siden forklarer det.
  - Kun videoer, der ligger i sitets egne Medier, kan hentes. Videoer fra andre servere kræver altid internet.
- **Redaktøren** ser videoens størrelse ved blokken og i panelet **Video i appen** (fanen Blok). Her kan *Kan hentes til brug uden internet* slås fra ved meget lange videoer.
- **Uden net, og videoen ikke hentet:** borgeren ser beskeden "Videoen kan kun ses med internet …" med et link til at hente videoerne. Når nettet er tilbage, vises videoen igen.
- Fjernes en video fra appen, slettes den også fra telefonerne ved næste opdatering.

### App-indstillinger
Findes på guidens øverste side i boksen **App-indstillinger** i panelet til højre i editoren (under Placering). Guidens **titel** er appens navn, og teksten i editoren er appens forside.

| Indstilling | Forklaring |
|---|---|
| **Dette er en guide** | Sæt flueben, når siden skal være forsiden i en app, som borgere kan åbne og lægge på hjemmeskærmen. Siderne under den bliver appens kapitler og sider. Uden flueben er siden ikke en app og vises ingen steder. Fjernes fluebenet, spørges der først, og adresse og farve huskes, hvis det sættes igen. |
| **Adresse** | Webadressen, som borgerne åbner – og som QR-koden på "Installér appen" peger på, fx `guides/om-teknik` → `ibos.dk/guides/om-teknik/`. Den fulde adresse vises under feltet, mens du skriver, og adresser, som hjemmesiden allerede bruger, afvises med det samme. Brug små bogstaver, tal og bindestreg (æ, ø og å bliver til ae, oe og aa); skråstreg samler flere guides. Se også "Skift adresse" herunder. |
| **Kort navn** | Teksten under app-ikonet på hjemmeskærmen. Telefonen forkorter lange navne, så højst ca. 12 tegn, fx "O&M-teknik". Tomt felt = guidens titel. |
| **Temafarve** | Farven på bjælken øverst i appen. Teksten i bjælken er hvid, så farven skal være mørk nok (kontrast mindst 4,5:1). Kontrasten vises, mens du vælger, og en for lys farve gemmes ikke. Skriv en farvekode eller brug farvevælgeren. IBOS-farver: `#00656c` (petrol) og `#00628b` (blå). |
| **App-ikon** | Billedet på hjemmeskærmen. Kvadratisk, mindst 512 × 512 pixels, enkelt motiv uden tekst. Hold motivet inde i midten – Android klipper hjørnerne af og kan vise ikonet som en cirkel. Baggrunden får temafarven. Uden valg bruges et standardikon. |
| **Logo** (valgfrit) | Lille logo, fx IBOS-logoet, foran appens navn i bjælken. Brug en hvid/lys udgave. Det er pynt – skærmlæsere læser appens navn. |
| **Udgiver** | Står nederst på alle sider, fx "Udgivet af IBOS – Instituttet for Blinde og Svagsynede". |
| **Kolofon** | Valgfri ekstra linje nederst, fx forfatter eller hvornår indholdet sidst er gennemgået. |
| **Tilgængelighedserklæring** | Link til IBOS' tilgængelighedserklæring (fx på was.digst.dk). Vises nederst på alle sider. Offentlige myndigheder skal have en. |
| **Menuvisning** | Hvordan kapitler og sider vises på forsiden og kapitelsiderne: **Felter** (store, lette at ramme på telefon) eller **Liste** (enkel, god ved mange punkter). Skærmlæsere læser begge ens. Den enkelte side kan vælge anderledes under "Visning". |

**Uddrag** (panelet til højre) er guidens korte beskrivelse; den bruges, når appen installeres, og i søgemaskiner.

### En ny guide
Vælg **Guides → Ny guide**, giv siden en titel, udfyld app-indstillingerne, og udgiv. Opret derefter kapitler og sider fra **Guides → Oversigt**.

### Skift adresse
Skift kun adressen, hvis det er nødvendigt. Den gamle adresse holder op med at virke – der sendes ikke videre:
- Borgere, der har appen installeret, mister den og skal installere den igen fra den nye adresse.
- Trykte QR-koder, links og bogmærker til den gamle adresse virker ikke længere.

Opret evt. selv en omdirigering fra den gamle adresse (fx med pluginet *Redirection*).

### Tag en guide ud af brug
Åbn guiden, og sæt status til **Kladde** eller **Privat** (eller fjern fluebenet "Dette er en guide"), og gem. Telefoner med appen fjerner den næste gang, den åbnes med internet. Vil du slette den helt: **Guides → Alle guides og sider → Papirkurv** (kapitler og sider følger med). Gendannes guiden fra papirkurven, kommer kapitler og sider med tilbage.

### Sikkerhed og synlighed
- Kun **udgivne** sider vises i appen. Kladder, private, planlagte og **adgangskodebeskyttede** sider – og alt under dem – vises hverken i appen, søgningen, offline-cachen, videolisten eller REST-API'et. Skjulte blokke ("Skjul blok") kommer heller ikke med.
- **"Skjul i menu …"** er *ikke* adgangskontrol: en skjult side kan åbnes, hvis man kender adressen. Brug Kladde eller Privat til indhold, der ikke må være offentligt.
- Lægges en guide eller et kapitel i papirkurven, følger undersiderne med (og gendannes sammen med den). Gendannes en enkelt side, mens dens kapitel stadig ligger i papirkurven, bliver den en kladde.
- Flytning og ændring af rækkefølge ændrer kun sidernes placering – indholdet gemmes ikke igen.
- Billeder, der hører til guide-sider, vises ikke via WordPress' medie-API eller "vedhæftningssider" for andre end redaktører.
- Søgeindeks, offline-liste og videoliste bygges altid som en anonym besøgende og genopbygges i baggrunden ca. 30 sekunder efter ændringer.
- Importen henter billeder fra en fastlåst commit på GitHub og afviser billeder, hvis kontrolsum ikke passer.
- På et site med én installation må redaktører som standard indsætte JavaScript. Har flere redaktører adgang, så sæt `define( 'DISALLOW_UNFILTERED_HTML', true );` i wp-config.php.

### Privatliv
Appen bruger ingen cookies, sporing eller eksterne tjenester. Gemte sider og hentede videoer ligger kun på borgerens egen telefon.

---

## Drift

> **Vigtigt:** Afinstallér eller slet ikke pluginet på ibos.dk, mens guiderne er i brug. Test opdateringer på en staging-kopi først.
>
> En kortvarig deaktivering (fx under fejlsøgning) sletter ikke appen fra borgernes telefoner – den virker videre med det gemte indhold, til pluginet er aktivt igen. Kun når en guide gøres privat, slettes eller flyttes, fjerner telefonerne appen.

### Opdatering
Upload den nye zip (**Plugins → Tilføj nyt plugin → Upload plugin**), og vælg **"Erstat nuværende med uploadet"**. Eksisterende guides, indstillinger og adresser bevares. Borgerne får den nye version, næste gang de åbner appen med internet.

### Cache-plugins og CDN
Undtag guidernes adresser, fx `/guides/om-teknik/`, fra sidecache i cache-plugins (WP Rocket, LiteSpeed, W3 Total Cache …) og CDN. Gælder især `…/sw.js`, `…/manifest.webmanifest` og `…/search-index.json`. Ellers får brugerne ikke nye versioner. Pluginet sætter selv `DONOTCACHEPAGE` og `Cache-Control: no-cache`, men ikke alle cache-lag respekterer det.

### Fejlfinding
- **Appen opdaterer ikke:** tjek undtagelserne for cache/CDN ovenfor. Luk appen helt, og åbn den igen – så kommer "Der er en ny version af appen". Søgning og offline-liste genopbygges ca. 30 sekunder efter, at der er gemt.
- **En side vises ikke i appen:** er den – og kapitlet og guiden over den – udgivet? Er den skjult i menuen? Oversigten viser "Ikke i appen", hvis et niveau over ikke er udgivet.
- **Video virker ikke uden net:** er videoen hentet på "Installér appen" (på iPhone inde i appen)? Er "Kan hentes til brug uden internet" slået fra? Har telefonen slettet data for at frigøre plads?
- **iPhone:** Safari kan slette gemte data for websteder, der ikke er lagt på hjemmeskærmen og ikke har været brugt i et stykke tid. Råd borgerne til at installere appen.
- **Fejlen "404"** på appens adresse: gem permalinks igen under **Indstillinger → Permanente links**.

### Afinstallation og backup
**Slet plugin** (efter deaktivering) bevarer guidernes sider og billeder, men fjerner indstillinger, cache og genererede ikoner. Guiderne er almindelige WordPress-indlæg (post type `ibos_guide_page`) og billeder i mediebiblioteket – de er med i sitets normale backup af database og uploads.

---

## Teknik

| Fil | Indhold |
|---|---|
| `ibos-guides.php` | Plugin-header, indlæsning, aktivering/deaktivering |
| `includes/post-type.php` | Post typen `ibos_guide_page` (hierarkisk), App-indstillinger, gem, adresse- og kontrasttjek, papirkurv, listevisning |
| `includes/admin-tree.php` | Guides → Oversigt (træ, træk og slip, flyt, kopiér, papirkurv) og boksen Placering |
| `includes/editor.php` | Skabeloner, mønstre, tilladte blokke, video i appen (offline-markering, videoliste) |
| `includes/guide.php` | Indstillinger, sidetræ, adresser, version, tilgængelighedstjek |
| `includes/router.php` | Rewrite-regel pr. guide; serverer sider, `sw.js`, manifest, søgeindeks og ikoner; "410 Gone" og afmeldings-service worker på tidligere adresser |
| `includes/render.php` | HTML-skabeloner (brødkrummer, menu, forrige/næste, søgning, installation), manifest, offline-liste, service worker |
| `includes/rest.php` | REST kun for redaktører |
| `includes/icons.php` | App-ikoner i alle størrelser fra det valgte billede (GD) |
| `includes/importer.php` | Trinvis import af `import/*.json` med billeder fra GitHub |
| `assets/` | CSS i IBOS-stil, app-JavaScript, service worker-skabelon, editor- og admin-scripts, QR-kode-bibliotek, standardikoner |
| `import/` | Guiderne, der kan importeres (genereres af `tools/export-guide.mjs`) |
| `tools/` | `export-guide.mjs` (Markdown → import-JSON) og `zip.mjs` (pakker pluginet) |
| `playground/` | Opsætning til WordPress Playground |

**Offline:** service workeren gemmer alle synlige sider, søgeindekset og billederne. Appen får kun en ny version, når det, borgeren ser, faktisk ændrer sig (versionen er en kontrolsum af indholdet). Hentede videoer ligger i en særskilt cache, som overlever nye versioner og afspilles med spoling (HTTP Range). Appen fjerner kun sig selv, når manifestet svarer "410 Gone".

### Udvikling

Kræver Node 20+ og git. Guidernes Markdown-repoer skal ligge ved siden af dette repo (fx `../O-og-M`).

```bash
npm install
```
```bash
node tools/export-guide.mjs ../O-og-M om-teknik rpaasch/O-og-M origin/main '#00628b'
```
```bash
node tools/export-guide.mjs ../ledsageteknik ledsageteknik rpaasch/ledsageteknik origin/main '#00656c'
```
```bash
npm run zip
```

`export-guide.mjs` omsætter en Markdown-guide (`content/` + `site.config.json`) til WordPress-blokke i `import/<nøgle>.json`, fastlåser billedernes kilde til den angivne commit (som skal være pushet til GitHub) og gemmer en SHA-256 pr. billede. `npm run zip` pakker `ibos-guides-<version>.zip` reproducerbart ud fra den seneste commit (commit først). Versionen læses fra `ibos-guides.php`, gamle zip-filer slettes, og `playground/blueprint.json` peger automatisk på den nye fil.

### Ny version

1. Hæv `Version:` og `IBOS_GUIDES_VERSION` i `ibos-guides.php`, `Stable tag` i `readme.txt` og `version` i `package.json`, og skriv ændringerne i changelog i `readme.txt`.
2. Commit.
3. `npm run zip`, og commit zip-filen og `playground/blueprint.json`.
4. Merge til `main`. Playground-linket bruger derefter den nye version.

---

## Tjekliste før brug på ibos.dk

- [ ] Installér først på et testsite eller en staging-kopi.
- [ ] Importér begge guides, og åbn dem på en iPhone og en Android-telefon.
- [ ] VoiceOver og TalkBack: overskrifter, landmarks, menu, forrige/næste, søgning og "Ny version".
- [ ] Installér appen på hjemmeskærmen, og test i flytilstand – også en hentet video.
- [ ] Ret en side, og tjek at "Der er en ny version af appen" vises.
- [ ] Udfyld Tilgængelighedserklæring i App-indstillinger.
- [ ] Undtag adresserne fra cache.
