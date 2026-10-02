# Schemas for the DOI deposit tests (#19)

Copies of the published schemas the CODECHECK links are checked against, so
`DepositSchemaUnitTest` runs without a network. Test fixtures only: `tests/` is
not part of the release package.

| Directory | Source | Fetched |
|---|---|---|
| `crossref/relations.xsd` | <https://www.crossref.org/schemas/relations.xsd>, imported by `crossref5.4.0.xsd`, which OJS 3.5 deposits | 2026-10-02 |
| `datacite-kernel-4/` | <http://schema.datacite.org/meta/kernel-4/metadata.xsd> and its `include/` files, the schema OJS 3.5 deposits against (4.7 at the time) | 2026-10-02 |

Refresh them when Crossref or DataCite publish a new version, or when OJS
deposits against a different one.
