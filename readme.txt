=== IBOS Guides ===
Contributors: rpaasch
Tags: pwa, offline, accessibility, guide, app
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tilgængelige guide-apps (PWA), der redigeres som almindelige sider i WordPress og virker uden internet.

== Description ==

IBOS Guides gør det muligt at lave små, tilgængelige guide-apps direkte i WordPress, fx O&M-teknik og Ledsageteknik.

* Hver guide har sin egen adresse, fx `https://ibos.dk/om-teknik/`.
* Indholdet redigeres i blok-editoren som et sidetræ: guide → kapitler → sider.
* Appen kan installeres på hjemmeskærmen og virker offline efter første besøg.
* Bygget til VoiceOver og TalkBack: landmarks, brødkrummer, forrige/næste, søgning med skærmlæser-annoncering og IBOS' orange fokusramme.
* Indbygget tilgængelighedstjek (alt-tekster, overskriftsniveauer, linktekster).
* Import af O&M-teknik og Ledsageteknik med ét klik (billeder hentes fra GitHub).

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

1. Plugins → Tilføj nyt → Upload plugin → vælg `ibos-guides-<version>.zip` → Installér → Aktivér.
2. Guides → Importér → importér O&M-teknik og/eller Ledsageteknik. Eller opret en ny guide under Guides → Tilføj ny.
3. Undtag guidernes adresser (fx `/om-teknik/` og `/ledsageteknik/`) fra sidecache i cache-plugins og CDN.

== Frequently Asked Questions ==

= Appen viser 404 =

Gå til Indstillinger → Permalinks og tryk "Gem ændringer". Permalinks må ikke stå på "Simpel".

= Hvordan markerer jeg et billede som dekorativt? =

Giv billedblokken CSS-klassen `dekorativ` (Avanceret → Ekstra CSS-klasse) og lad alt-teksten være tom.

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
* Tilgængelighedstjek: video uden undertekster, tomme billed-/videoblokke og glemt eksempeltekst.

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
* Sikkerhed: resultatet af en omfattende sikkerhedsgennemgang (se README). Bl.a. lukket læk af ikke-offentligt indhold via REST og cache, apps fjernes fra telefoner, når en guide ikke længere er offentlig, fastlåst og kontrolleret import, beskyttelse mod overbelastning.

= 1.0.0 =
* Første udgave.
