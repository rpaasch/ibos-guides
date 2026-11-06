# IBOS Guides – WordPress-plugin

Et WordPress-plugin til tilgængelige guide-apps (PWA), fx **O&M-teknik** og **Ledsageteknik**. Indholdet redigeres som almindelige sider i WordPress. Hver guide får sin egen adresse, kan lægges på hjemmeskærmen som en app og virker uden internet.

- Den færdige plugin-fil hedder **`ibos-guides.zip`**.
- Pluginet rører ikke temaet. Appen vises i sit eget, enkle design i IBOS' farver.
- Testet på WordPress 7.1.4 (samme version som ibos.dk) med PHP 8.3.

---

## Installation

1. **Plugins → Tilføj nyt → Upload plugin**, vælg `ibos-guides.zip`, tryk **Installér nu** og derefter **Aktivér**.
2. Gå til **Guides → Importér**. Skriv den ønskede adresse for hver guide (fx `guides/om-teknik` eller `o-og-m`), og tryk **Importér O&M-teknik** og/eller **Importér Ledsageteknik**. Adresser, som sitet allerede bruger, afvises.
   - Billederne hentes fra GitHub (`raw.githubusercontent.com`) og lægges i mediebiblioteket med deres alt-tekster.
   - O&M-teknik har 252 billeder og tager et par minutter. Lad siden være åben imens. Afbrydes importen, fortsætter den, hvor den slap, når du trykker igen.
3. Appen ligger nu på den valgte adresse, fx `https://ibos.dk/guides/om-teknik/`. Adressen kan ændres senere under App-indstillinger (brugere, der har installeret appen, skal så installere den igen).

**Krav:**
- HTTPS
- Permalinks må ikke stå på "Simpel"
- Serveren skal kunne hente filer fra GitHub under importen

---

## Sådan redigerer du

Alt foregår under menupunktet **Guides**.

| Niveau | Hvad det er | Eksempel |
|---|---|---|
| Side uden forælder | **En guide (en app).** Teksten bliver appens forside. | O&M-teknik |
| Side med guiden som forælder | **Et kapitel** i menuen | Ledsageteknik |
| Side under et kapitel | **En indholdsside** | Grebet |

- **Ny side:** Guides → Tilføj ny. Under **Sideattributter** til højre vælger du **Forælder** (kapitlet) og **Rækkefølge**. Lavt tal vises først, så brug spring af 10 (10, 20, 30 …), så der er plads til at indsætte sider imellem.
- **Tekst:** skriv i blok-editoren. Brug **Overskrift H2** og nedefter. Sidens titel er allerede overskrift 1.
- **Billeder:** brug billedblokken, og udfyld **Alternativ tekst**. Er billedet kun pynt, så giv blokken CSS-klassen `dekorativ` (Avanceret → Ekstra CSS-klasse) og lad alt-teksten være tom.
- **Kort beskrivelse i menuen:** udfyld **Uddrag** (excerpt).
- **Skjul en side:** sæt flueben ved "Skjul i menu …" i boksen **Visning**.
- **Udgiv:** tryk **Opdatér**. Næste gang brugerne åbner appen med internet, får de beskeden "Ny version klar – Genindlæs".
- **Tilgængelighedstjek:** boksen af samme navn og kolonnen **Tjek** i listen viser fx manglende alt-tekster og spring i overskriftsniveauer.

### App-indstillinger
Findes på guidens øverste side i boksen **App-indstillinger** under editoren (åbn "Meta Boxes" nederst, hvis boksen er lukket). Guidens **titel** er appens navn, og teksten i editoren er appens forside.

| Indstilling | Forklaring |
|---|---|
| **Dette er en guide** | Sæt flueben, når siden skal være forsiden i en app, som borgere kan åbne og lægge på hjemmeskærmen. Siderne under den bliver appens kapitler og sider. Uden flueben er siden ikke en app og vises ingen steder. |
| **Adresse** | Webadressen, som borgerne åbner – og som QR-koden på "Installér appen" peger på, fx `guides/om-teknik` → `ibos.dk/guides/om-teknik/`. Små bogstaver, tal og bindestreg; skråstreg samler flere guides. Vælg med omhu: ændres adressen senere, forsvinder appen fra borgernes hjemmeskærm, og de skal installere den igen. Adresser, som hjemmesiden allerede bruger, afvises. |
| **Kort navn** | Teksten under app-ikonet på hjemmeskærmen. Telefonen forkorter lange navne, så højst ca. 12 tegn, fx "O&M-teknik". Tomt felt = guidens titel. |
| **Temafarve** | Farven på bjælken øverst i appen og på telefonens statuslinje. Teksten i bjælken er hvid, så farven skal være mørk nok til, at svagsynede kan læse den (kontrast mindst 4,5:1 – vises under feltet). IBOS-farver: `#00656c` (petrol) og `#00628b` (blå). |
| **App-ikon** | Billedet på hjemmeskærmen. Kvadratisk, mindst 512 × 512 pixels, enkelt motiv uden tekst. Hold motivet inde i midten – Android klipper hjørnerne af og kan vise ikonet som en cirkel. Baggrunden får temafarven. Uden valg bruges et standardikon. |
| **Logo** (valgfrit) | Lille logo, fx IBOS-logoet, foran appens navn i bjælken. Brug en hvid/lys udgave. Det er pynt – skærmlæsere læser appens navn. |
| **Udgiver** | Står nederst på alle sider, fx "Udgivet af IBOS – Instituttet for Blinde og Svagsynede". |
| **Kolofon** | Valgfri ekstra linje nederst, fx forfatter eller hvornår indholdet sidst er gennemgået. |
| **Menuvisning** | Hvordan kapitler og sider vises på forsiden og kapitelsiderne: **Felter** (store, lette at ramme på telefon) eller **Liste** (enkel, god ved mange punkter). Skærmlæsere læser begge ens. Den enkelte side kan vælge anderledes under "Visning". |

**Uddrag** (panelet til højre) er guidens korte beskrivelse; den bruges, når appen installeres, og i søgemaskiner.

### En ny guide
Opret en ny side **uden forælder**, sæt flueben ved **Dette er en guide**, udfyld app-indstillingerne, og udgiv. Opret derefter kapitler og sider under den.

Adressen afvises, hvis den allerede bruges af en side, et indlæg, en anden guide eller WordPress selv (fx `/admin/`, `/category/`). Så vises en fejl i App-indstillinger, og den gamle adresse bevares.

### Sikkerhed og synlighed
- Kun **udgivne** sider vises i appen. Kladder, private, planlagte og **adgangskodebeskyttede** sider – og alt under dem – vises hverken i appen, søgningen, offline-cachen eller REST-API'et.
- **"Skjul i menu …"** er *ikke* adgangskontrol: en skjult side kan åbnes, hvis man kender adressen. Brug Kladde eller Privat til indhold, der ikke må være offentligt.
- Lægges en guide i papirkurven, følger kapitler og sider med (og gendannes sammen med den).
- Gøres en guide privat, slettes eller får den ny adresse – eller deaktiveres pluginet – fjerner telefoner, der har appen installeret, selv appen og det gemte indhold, næste gang den åbnes med internet.
- Billeder, der hører til guide-sider, vises ikke via WordPress' medie-API eller "vedhæftningssider" for andre end redaktører.
- Søgeindeks og offline-liste bygges altid som en anonym besøgende og genopbygges i baggrunden efter ændringer – også når indhold, der vises via blokke (fx "Seneste indlæg"), ændres.
- Importen henter billeder fra en fastlåst commit på GitHub og afviser billeder, hvis kontrolsum ikke passer.
- På et site med én installation må redaktører som standard indsætte JavaScript. Har flere redaktører adgang, så sæt `define( 'DISALLOW_UNFILTERED_HTML', true );` i wp-config.php.
- Permalinks må ikke stå på "Simpel" (der vises en advarsel under Guides).

### Opdatering fra 1.0.0
Upload den nye zip og vælg "Erstat nuværende med uploadet". Eksisterende guides og deres adresser bevares automatisk.

---

## Cache-plugins og CDN

Undtag guidernes adresser, fx `/om-teknik/` og `/ledsageteknik/`, fra sidecache i cache-plugins (WP Rocket, LiteSpeed, W3 Total Cache …) og CDN. Gælder især `…/sw.js`. Ellers får brugerne ikke nye versioner. Pluginet sætter selv `DONOTCACHEPAGE` og `Cache-Control: no-cache`, men ikke alle cache-lag respekterer det.

---

## Teknik

| Fil | Indhold |
|---|---|
| `includes/post-type.php` | Post typen `ibos_guide_page` (hierarkisk), metabokse, gem, listevisning |
| `includes/guide.php` | Indstillinger, sidetræ, adresser, version, tilgængelighedstjek |
| `includes/router.php` | Rewrite-regel pr. guide; serverer sider, `sw.js`, manifest, søgeindeks og ikoner |
| `includes/render.php` | HTML-skabeloner (brødkrummer, menu, forrige/næste, søgning, installation), manifest, service worker |
| `includes/icons.php` | App-ikoner i alle størrelser fra det valgte billede (GD) |
| `includes/importer.php` | Trinvis import af `import/*.json` med billeder fra GitHub |
| `assets/` | CSS i IBOS-stil, JavaScript, service worker-skabelon, QR-kode-bibliotek, standardikoner |

**Offline:** service workeren gemmer alle sider, søgeindekset og billederne i den størrelse, der bruges i indholdet ("large"). Hver guide har sit eget versionsnummer, der hæves ved hver redigering.

### Udvikling

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

`export-guide.mjs` omsætter en Markdown-guide (`content/` + `site.config.json`) til WordPress-blokke i `import/<nøgle>.json`, fastlåser billedernes kilde til den angivne commit (som skal være pushet til GitHub) og gemmer en SHA-256 pr. billede. `npm run zip` pakker `ibos-guides.zip` reproducerbart ud fra den seneste commit (commit først).

---

## Tjekliste før brug på ibos.dk

- [ ] Installér først på et testsite eller en staging-kopi.
- [ ] Importér begge guides, og åbn dem på en iPhone og en Android-telefon.
- [ ] VoiceOver og TalkBack: overskrifter, landmarks, menu, forrige/næste og søgning.
- [ ] Installér appen på hjemmeskærmen, og test i flytilstand.
- [ ] Ret en side, og tjek at "Ny version klar" vises.
- [ ] Undtag adresserne fra cache.
