---
version: 1.0.1
origin: pendulum
based-on: 1.0.1
---

# RULES — MAddressFields

> For AI agents. Last updated: 2026-09-17.

## Purpose

The field block for a full Brazilian street address: CEP, street and number, complement, district,
city and UF. Typing a complete CEP fills street, district, city and UF from ViaCEP. It is the
front half of the API's `addressColumns()` block (`api.rnc/app/Helpers/RULES.md`).

## The contract

|                  |                                                                                |
| ---------------- | ------------------------------------------------------------------------------ |
| `v-model`        | `AddressFields` (`@Interfaces`), required — one object, mutated field by field |
| Emits            | nothing beyond `v-model`                                                       |
| Owns             | the fields, the CEP lookup and its two status lines                            |
| Does **not** own | validation, error messages, the submit, the payload, the coordinates           |

## Intentional Decisions

- **One object, not seven models.** The parent owns a `ref<AddressFields>` and reads it when it
  builds the payload. Mapping to the API's snake_case keys, if any, is the parent's job — this is
  a form shape, not a resource or a payload (`celer-05`).
- **The lookup is the reason the block exists**, so it calls `useCepLookup` (`@Composables`),
  which calls `GetAddressByCepService`. This is the documented exception in
  `components/RULES.md`: a public utility lookup, not our API.
- **ViaCEP only fills what it knows.** An empty `logradouro` or `bairro` (a city with a single CEP
  answers that way) keeps what the person typed. `number` and `complement` are never touched.
- **A failed lookup says so once and gets out of the way.** An unknown CEP and an unreachable
  ViaCEP read the same — "type it below" — because the person's next step is the same. The fields
  stay editable the whole time; the lookup never blocks typing.
- **A slower answer for an earlier CEP never fills the form** after a newer CEP was typed
  (`useCepLookup` drops it).
- **Ids come from `useId()`.** Chapaja fixed them (`cep`, `street`, …) and forbade two instances in
  one form; here two instances — the Showcase renders one beside `MLocalityFields` — keep every
  `<label for>` pointing at its own input. Specs address fields by `name`, which is stable.
- **Real `autocomplete` tokens** (`postal-code`, `address-line1`, `address-line2`,
  `address-level3`, `address-level2`) so a browser can fill the block.
- **The UF `Select` takes `inputId`**, not `id` (`celer-04`, "Which prop reaches the real input").
- **Two-thirds / one-third rows** (street + number, city + UF) on a `grid-cols-3 gap-3`, at every
  width: the short field never needs more than a third, and `gap-3` keeps the controls apart on a
  phone.

## Do Not

- **Do not add validation or a submit** — create and edit flows ask different things of an address.
- **Do not route the lookup through `API`** — it is a third party, with no session and no app-busy
  signal (`services/RULES.md`).
- **Do not overwrite `number` or `complement` from ViaCEP** — it does not know them.
- **Do not go back to fixed ids.**

## Known Gaps

- **The coordinates are not edited here.** `AddressFields` has no `lat`/`lng`; a product that
  geocodes does it on the API or adds its own field.
