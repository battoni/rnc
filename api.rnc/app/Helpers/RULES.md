---
version: 1.3.2
origin: pendulum
based-on: 1.3.2
---
# RULES — App Helpers (ApiResponse, Pagination, Money, Brazil)

> For AI agents. App-wide helpers every module leans on.

## ApiResponse — the response envelope contract

Every API response in api.rnc goes through `ApiResponse`. The envelope is fixed:

- **success**: `{ "data": mixed, ...extraData }` — `data` is omitted when null.
- **error**: `{ "data": null, "error": string, "status_code": int, ...extraData }`

The frontend reads the sentence from `error`, never from `message` (Laravel's validation
exceptions are the one response that carries `message` + `errors`, and they do not come
through here).

## Methods

- `success(data, extraData, status=200, cookie)` — base success.
- `created(data, extraData)` — success at 201. Use for store actions.
- `error(message, extraData, status=400)` — base error; `badRequest`, `unauthorized`,
  `forbidden`, `notFound`, `conflict`, `internalError`, `serviceUnavailable` name the status.
- `paginated(paginator, resourceClass)` — success carrying a `LengthAwarePaginator`. The
  paginator JSON (`current_page` / `data` / `last_page` / `per_page` / `total`) sits inside
  `data`, so the envelope is unchanged and the frontend `PaginateResult<T>` mirrors it.
- `paginatedCollection(items, page, perPage, resourceClass)` — the same for a collection already
  ordered in PHP. Use it only when the ordering cannot be expressed in SQL.

## Pagination — the paging query params

`Pagination::perPage($request)` and `Pagination::page($request)` are the only way a controller
reads `per_page` / `page`. Both clamp: `perPage` to `[1, MAX_PER_PAGE]`, `page` to `>= 1`.

- **Never read `$request->integer('per_page', …)` directly** — unclamped, it lets any
  authenticated caller pull an entire table in one query.
- `page` only needs reading where a collection is sliced by hand; Eloquent's paginator resolves
  and clamps the page itself.

## Money — integers of cents

Money is an `int` of cents past the edge that receives it. `Money::toCents` converts the one
float that arrives; `Money::percentOf` takes a share in basis points, rounded half up with
integers only. Formatting for a person belongs to a locale (`Brazil\Real::format`).

## Brazil — the country's rules, in one removable place

The products this bootstrap serves are Brazilian, so Brazil's rules ship. They live in
`app/Helpers/Brazil/` and nowhere else, so a product outside Brazil deletes or replaces the
directory and finds every consumer by the build that breaks:

- `Cpf` / `Cnpj` — `normalize` to digits, `isValid` by the check digits (repeated-digit
  sequences refused). Never re-implement the check digits in a module.
- `PhoneNumber::normalize` — digits with the `55` country code, so a masked and an unmasked
  number are one number.
- `Real::format` — cents as `R$ 1.234,56`.
- **The address block** (from chapaja) — one spelling of a Brazilian address for every table:
  - `AddressColumns::register()`, called from `AppServiceProvider::boot()`, declares two Blueprint
    macros: `$table->addressColumns()` (CEP, street, number, complement, district, city, UF,
    lat/lng) and `$table->localityColumns()` (UF, city, CEP, region, lat/lng). They share `cep`,
    `city` and `state`, so a table takes one of them, never both.
  - `AddressData` / `LocalityData` — Spatie `Data` mirroring the macros field for field. A
    FormRequest's validated input maps with `::from()`; a resource spreads
    `...$this->addressData()->toArray()` instead of listing the columns again.
  - **The DTO is for READING. The write path lists its columns** (or drops `lat`/`lng`) until
    something really writes a coordinate. `AddressData::from($validated)->toArray()` carries
    `lat => null, lng => null` whenever the request did not send them, so spreading it into an
    `update()` wipes the geolocation a geocoder stored — silently, on every edit (chapaja).
  - `HasAddress` — the model trait: `addressData()` for an `addressColumns()` table,
    `localityData()` for a `localityColumns()` table.
  - `region` is free text inside a city ("Zona Leste"). A product that needs a list of regions
    owns that list in its own module; it does not grow here.
  - They live here rather than in a module's `DTOs/` or in `app/Support/` because this directory
    is the one a product outside Brazil deletes. The provider line and every migration that calls
    a macro then fail loudly, which is the inventory.

Consumers today: `RegisterRequest` (CPF check digits, phone normalisation). No table in the
bootstrap stores an address — the block is proved in `tests/Unit/Helpers/Brazil/AddressBlockTest.php`
on tables the test creates. A column set goes into a table's `create_*` migration when a module
needs it; never into an existing table through an ALTER (`arcus-api-architecture` §3).

## Known Gaps

- **The coordinate type is only checked on SQLite.** MySQL returns a `decimal` as a numeric
  string; `HasAddress` passes it to a `?float` and relies on the file not being `strict_types`.
  The suite runs on SQLite, which answers a float, so declaring `strict_types` in the trait would
  pass every test and throw a `TypeError` in production. Cast first if that declaration is added.

## Do Not

- Do not build raw `response()->json(...)` in controllers — always use `ApiResponse`.
- Do not change the envelope keys — every client reads them. It is a MAJOR change.
- Do not put a country's rule in `Money` or any other core helper; it goes under that
  country's directory.
- Do not hand-list `cep`/`street`/`number`/… in a migration or a resource when the block fits —
  call the macro and spread the DTO, or the next table spells the address differently.
