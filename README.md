# AIQ-plugins

Versiebeheer voor de eigen AIQ-plugins van URonline, gebruikt op [aiq.uronline.nl](https://aiq.uronline.nl).

## Plugins

| Plugin | Map | Versie op de site | Status |
|---|---|---|---|
| AIQ Huisstijl Afbeeldingen | `aiq-huisstijl-afbeeldingen/` | **1.1.0** | Actief op aiq.uronline.nl |

## AIQ Huisstijl Afbeeldingen

Bakt automatisch een duotone-tint (iJsblauw #C9EAF1) en het AIQ-logo in een Ice-cirkel in elke nieuw geüploade afbeelding. Volledige voortzetting van de "Huisstijl images"-snippet v2.2 uit AIQ Kernfunctionaliteit, maar met een instellingenpagina in plaats van hardcoded waarden.

- **v1.1.0** (huidig op de site): alles uit 1.0.0 plus WooCommerce-stand voor productafbeeldingen (normaal / geen duotone maar wel logo / helemaal overslaan).
- **v1.0.0**: eerste versie; instellingen voor duotone, logo, maten, skip-lijst en compressie.

Zie `aiq-huisstijl-afbeeldingen/readme.txt` voor de volledige changelog en gebruiksinstructies.

## Werkwijze

- Brontekst van elke plugin staat in een eigen map, gelijk aan de mapnaam in `wp-content/plugins/`.
- Na een wijziging: versienummer in de plugin-header verhogen, changelog in `readme.txt` bijwerken, en de zip voor installatie opnieuw bouwen.
