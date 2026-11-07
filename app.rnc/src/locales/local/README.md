# Project-owned translations (LOCAL)

The cortex sync NEVER touches files in this folder. Put your feature-module
translations here, keyed exactly like the core locale files (`../en.json`,
`../pt-BR.json`). They are deep-merged OVER the synced core at i18n setup
(`src/libraries/i18n/index.ts`), so a rnc update can never revert them.

Example `local/pt-BR.json`:

```json
{
  "catalog": { "title": "Catálogo" },
  "navigation": { "catalog": "Catálogo" }
}
```
