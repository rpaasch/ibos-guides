=== IBOS Guides ===
Contributors: rpaasch
Tags: pwa, offline, accessibility, guide, app
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
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

== Installation ==

1. Plugins → Tilføj nyt → Upload plugin → vælg `ibos-guides.zip` → Installér → Aktivér.
2. Guides → Importér → importér O&M-teknik og/eller Ledsageteknik. Eller opret en ny guide under Guides → Tilføj ny.
3. Undtag guidernes adresser (fx `/om-teknik/` og `/ledsageteknik/`) fra sidecache i cache-plugins og CDN.

== Frequently Asked Questions ==

= Appen viser 404 =

Gå til Indstillinger → Permalinks og tryk "Gem ændringer". Permalinks må ikke stå på "Simpel".

= Hvordan markerer jeg et billede som dekorativt? =

Giv billedblokken CSS-klassen `dekorativ` (Avanceret → Ekstra CSS-klasse) og lad alt-teksten være tom.

== Changelog ==

= 1.0.0 =
* Første udgave.
