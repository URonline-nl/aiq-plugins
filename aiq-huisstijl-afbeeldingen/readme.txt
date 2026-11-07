=== AIQ Huisstijl Afbeeldingen ===
Contributors: mivanthi
Tags: images, branding, watermark, duotone, house style
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bakt automatisch een duotone-tint en je merklogo in elke nieuw geüploade afbeelding, volledig via instellingen te regelen.

== Description ==

Deze plugin past bij elke nieuwe afbeeldingsupload automatisch jouw huisstijl toe:

1. **Duotone-tint** — histogram-auto-normalisatie (vlakke bronnen krijgen eerst contrast), grijstinten, schaduw-lift en een multiply-kleur (standaard iJsblauw #C9EAF1). De tint is niet zomaar een doorzichtig vlak, maar een echte duotone-curve.
2. **Logo-watermerk** — je logo gecentreerd in een ijsblauwe cirkel met zachte uitwaaiering, standaard links-onder. Volledig instelbaar: maten, marges, dekking en cirkelgrootte.
3. **Optioneel verkleinen** — uploads groter dan de ingestelde maximummaten worden proportioneel verkleind.
4. **Skip-lijst** — bestandsnamen met een opgegeven fragment (standaard grafiek, chart, kaart, diagram, social) krijgen géén duotone, zodat datagrafieken leesbaar blijven.

Alles loopt server-side op `wp_generate_attachment_metadata`: ook uploads via REST, de blokkeditor of externe tools worden gegrepen. Bestaande afbeeldingen worden nooit achteraf gewijzigd.

== Installation ==

1. Upload de map `aiq-huisstijl-afbeeldingen` naar `wp-content/plugins/` (of installeer de zip via Plugins -> Nieuwe plugin -> Uploaden).
2. Activeer de plugin.
3. Ga naar Instellingen -> AIQ Huisstijl en stel je logo (attachment-ID of fallback-pad), kleuren en maten in.

**Belangrijk bij migratie van de AIQ Kern-functionaliteit:** deactiveer eerst de oude "Huisstijl images"-snippet (AIQ Kern -> PHP-snippets). De plugin respecteert en zet zowel `_aiq_hs_done` als `_aiq_house_style_done`, zodat dubbele verwerking toch wordt voorkomen.

== Frequently Asked Questions ==

= Mijn afbeeldingen krijgen geen tint én geen logo. Wat is er aan de hand? =
Controleer of het logo geladen kan worden: het attachment-ID moet bestaan en het fallback-pad moet kloppen. Bestaat het logo niet, dan slaat de plugin de verwerking stilletjes over (net als bij het originele snippet).

= Kan ik een al geüploade afbeelding alsnog van huisstijl voorzien? =
Verwijder de meta-velden `_aiq_hs_done` en `_aiq_house_style_done` van de attachment en genereer de thumbnails opnieuw (bijv. met Regenerate Thumbnails), of herupload het bestand.

= Waarom krijgt mijn datagrafiek wel een logo maar geen tint? =
De bestandsnaam bevat een fragment uit de skip-lijst. Wil je ook geen logo, zet dan "Logo toepassen" uit voor dat bestandstype of gebruik een eigen werkwijze.

== Changelog ==

= 1.1.0 =
* Nieuw: WooCommerce-stand voor productafbeeldingen - normaal stylen, geen duotone maar wel logo, of helemaal overslaan (handig voor webshops: productfoto's houden hun echte kleuren).

= 1.0.0 =
* Eerste versie. Volledige instellingenpagina (duotone, logo, maten, skip-lijst, compressie).
* Directe voortzetting van de "Huisstijl images"-snippet v2.2 (26-10-2026) uit AIQ Kernfunctionaliteit, met dezelfde standaardwaarden.
* Nieuw t.o.v. het snippet: optioneel automatisch verkleinen naar maximummaten, instelbare logo-positie en maten, skip-lijst instelbaar (incl. "social"), en instelbare JPEG/PNG-compressie.
