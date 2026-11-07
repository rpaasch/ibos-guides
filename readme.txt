=== IBOS Guides ===
Contributors: rpaasch
Tags: pwa, offline, accessibility, guide, app
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tilgængelige guide-apps (PWA), der redigeres som almindelige sider i WordPress og virker uden internet.

== Description ==

IBOS Guides gør det muligt at lave små, tilgængelige guide-apps direkte i WordPress, fx O&M-teknik og Ledsageteknik.

* Hver guide har sin egen adresse, fx `https://ibos.dk/guides/om-teknik/`.
* Indholdet redigeres i blok-editoren som et sidetræ: guide → kapitler → sider. Guides → Oversigt viser træet med træk og slip, flyt, kopiér og papirkurv.
* Nye sider starter med en skabelon, og mønstrene under "IBOS Guide" giver færdige byggeklodser.
* Appen kan installeres på hjemmeskærmen og virker offline efter første besøg (skjulte sider undtaget). Videoer kan hentes til brug uden internet.
* Bygget til VoiceOver og TalkBack: landmarks, brødkrummer, forrige/næste, søgning med skærmlæser-annoncering og IBOS' orange fokusramme.
* Tilgængelighedstjek live i editoren og før udgivelse: alt-tekster, tomme blokke, undertekster, automatisk afspilning, eksempeltekst, overskriftsniveauer og linktekster.
* Import af O&M-teknik og Ledsageteknik med ét klik (billeder hentes fra GitHub). Den importerede guide udgives med det samme.

Pluginet rører ikke temaet. Appen vises i sit eget, enkle design i IBOS' farver.

== External services ==

Pluginet kontakter kun eksterne tjenester, når en administrator selv starter en import under Guides → Importér:

* **GitHub (raw.githubusercontent.com)** – billederne til de medfølgende guides (O&M-teknik og Ledsageteknik) hentes fra de offentlige repositories rpaasch/O-og-M og rpaasch/ledsageteknik på en fastlåst commit. Hvert billede kontrolleres mod en SHA-256-kontrolsum, før det lægges i mediebiblioteket. Der sendes ingen data om sitet eller brugerne ud over selve forespørgslen. GitHubs vilkår: https://docs.github.com/site-policy/github-terms/github-terms-of-service – privatliv: https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement

Appen selv (sider, søgning, offline-cache) bruger ingen eksterne tjenester, skrifttyper eller sporing.

== Third-party code ==

* `assets/qrcode.js` – QR Code Generator for JavaScript 1.4.4 af Kazuhiko Arase, MIT-licens (https://github.com/kazuhikoarase/qrcode-generator).

== Sikkerhed ==

* Indhold i guiderne vises offentligt. På et site med én installation må redaktører som standard indsætte HTML og JavaScript (WordPress' rettighed `unfiltered_html`). Har flere redaktører adgang, anbefales `define( 'DISALLOW_UNFILTERED_HTML', true );` i wp-config.php.
* "Skjul i menu …" er ikke adgangskontrol – brug Kladde eller Privat.

== Installation ==

1. Plugins → Tilføj nyt plugin → Upload plugin → vælg `ibos-guides-<version>.zip` → Installér → Aktivér.
2. Guides → Importér → importér O&M-teknik og/eller Ledsageteknik. Eller opret en ny guide under Guides → Ny guide.
3. Undtag guidernes adresser (fx `/guides/om-teknik/`) fra sidecache i cache-plugins og CDN.

== Frequently Asked Questions ==

= Appen viser 404 =

Gå til Indstillinger → Permalinks og tryk "Gem ændringer". Permalinks må ikke stå på "Simpel".

= Hvordan markerer jeg et billede som dekorativt? =

Markér det som dekorativt i billedblokkens indstillinger, og lad alt-teksten være tom. (CSS-klassen `dekorativ` virker også.)

= Hvad sker der, hvis pluginet deaktiveres? =

Installerede apps virker videre med det gemte indhold. De fjerner sig først, når en guide gøres privat, slettes eller får ny adresse.

= Kan videoer ses uden internet? =

Ja, hvis borgeren henter dem på siden "Installér appen" (på iPhone og iPad inde i den installerede app). Redaktøren kan slå det fra for den enkelte video.

== Changelog ==

= 1.2.0 =
* Oversigt: træk og slip sider og kapitler – også mellem kapitler. ↑/↓ og "Flyt til kapitlet" virker uden mus.
* Oversigt: Kopiér og Læg i papirkurven (med Fortryd) under "Mere".
* Editoren: Forrige/Næste side, "+ Ny side" og "Tilbage til Oversigt" i boksen Placering.
* Oversigten henter hele træet med én forespørgsel.
* Skabelon til nye sider (indledning, billede, punkter) med grå hjælpetekster; egen skabelon til kapitler og guides.
* Mønstre under "IBOS Guide": side med trin og billede, billede/video med forklaring, mellemoverskrift med punkter, nummererede trin, godt at vide.
* Video: videoblokken kan bruges. Borgeren kan hente videoerne til brug uden internet på "Installér appen" (med fremdrift, plads-tjek og "Fjern"); de afspilles og kan spoles offline (Range fra cachen), også i Safari. Redaktøren ser størrelsen og kan slå "Kan hentes til brug uden internet" fra pr. video. Ikke-hentede videoer viser en besked med link uden net.
* Kun relevante blokke i editoren og overskrifter H2–H4. Tomme blokke vises ikke i appen.
* Tilgængelighedstjek: video uden undertekster, video der afspilles automatisk, tomme billed-/videoblokke og glemt eksempeltekst.
* Tilgængelighed: fokus og besked bevares efter flyt/kopiér/papirkurv i Oversigten; fokus efter hentning af videoer; navngivet fremdriftslinje; reduceret bevægelse respekteres. Testet med axe (WCAG 2.2 AA): ingen fejl.
* Grundig gennemgang (sikkerhed, kode, brugervenlighed, tilgængelighed, offline, dokumentation) med rettelser:
* Sikkerhed: flytning og rækkefølge ændrer kun placeringen (indholdet gemmes ikke igen og kan derfor ikke miste HTML); videolisten bygges af den viste HTML (skjulte blokke kommer ikke med); kopier beholder adgangskoden; beskeder i Oversigten viser kun guide-sider.
* Appen fjerner kun sig selv ved "410 Gone" (guide gjort privat, slettet eller flyttet) – ikke ved midlertidige fejl som et deaktiveret plugin.
* Video: hentning strømmes direkte til lageret, én fejlende video stopper ikke de andre, fejlsider gemmes aldrig som video, iPhone-vejledning, bekræftelse før "Fjern", spoling uden at læse hele filen igen, appen viser altid afspilningsknapper.
* Editor: tilgængelighedstjek live og før udgivelse med "Vis blokken"; live tjek af adresse (med den fulde adresse) og af farvekontrast (for lys farve gemmes ikke); bekræftelse før "Dette er en guide" fjernes (indstillingerne huskes); dekorative billeder accepteres; advarsel om sider med samme titel som kapitlet.
* Oversigt: "N ting at rette" og "Ikke i appen", adresse og "Åbn appen" på guiden, advarsel ved flytning til et kapitel, der er kladde, "Mere" som panel under rækken, "✓"-besked ved rækken.
* App: søgning tåler stavevarianter (æ/ø/å), uddrag ved hele ord, kapitlet i resultatets navn, sidetitel med søgningen; "Ny version" vises øverst på siden med "Opdatér nu" og "Senere"; "Vis appen til en anden" flyttet efter vejledningen; link til tilgængelighedserklæring; brødkrumme-skilletegn læses ikke op.
* Kræver nu WordPress 6.4.
* Forhåndsvisning i appen for redaktører: knappen "Forhåndsvis i appen" gemmer (kladder) eller autogemmer (udgivne sider) og viser siden i appens design – også kladder og ikke-udgivne ændringer. Kun for brugere, der må redigere siden; ingen cache eller service worker.
* Video: opfordring til at hente på wifi, og spørgsmål først, når telefonen er på mobildata. Videoer gemmes i bidder på 2 MB og afspilles bid for bid, så store videoer også virker på ældre iPhones.

= 1.1.2 =
* Zip-filen har versionsnummer i navnet, fx ibos-guides-1.1.2.zip.
* Fjernet opgraderingskode til version 1.0.0, som aldrig var i brug.

= 1.1.1 =
* Selvvalgt adresse ved import (tjekkes mod sitets eksisterende adresser).
* Adresser, som WordPress sender videre fra en sides tidligere adresse, kan ikke bruges.
* Ny menu "Guides" med en Oversigt: hver guide som træ med Redigér, Vis, Flyt op/ned og "+ Ny side i kapitlet".
* Placering bruger WordPress' normale "Overordnet". Boksen "Placering" viser, hvor siden ligger. Flyttes en side til et andet kapitel, lægges den sidst dér.
* App-indstillinger flyttet op i sidepanelet med bedre forklaringer.

= 1.1.0 =
* Sikkerhed: resultatet af en omfattende sikkerhedsgennemgang (se README.md i kildekoden). Bl.a. lukket læk af ikke-offentligt indhold via REST og cache, apps fjernes fra telefoner, når en guide ikke længere er offentlig, fastlåst og kontrolleret import, beskyttelse mod overbelastning.

= 1.0.0 =
* Første udgave.
