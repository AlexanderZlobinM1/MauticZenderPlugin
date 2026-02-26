## v1.2.7
- Verified Mautic locale list and standardized Serbian locale to `sr_RS`.
- Removed duplicate Serbian translation folder `sr-rs`.

## v1.2.6
- Switched plugin icon to the standard WhatsApp icon (`Assets/img/whatsapp.png`).
- Added Russian translations under locale `ru`.
- Added Latin Serbian translations under locale `sr-rs`.

## v1.2.5
- Replaced old upstream repository references with the new project repository.
- Updated plugin metadata (`homepage`, `support`, `author`) to project-owned values.
- Updated integration help text link to point to the new repository.

## v1.2.4
- Full functional parity with legacy v1.1.14 on top of fixed Mautic 5/6/7-compatible codebase.
- Restored full Features tab settings (`fetch_quantity`, `fetch_unit`, `batch_size`).
- Added and wired `mautic:zender:sync-messages` command (pending/sent/received sync).
- Added webhook receive endpoint support (`/zender/receive/{key}` + legacy route).
- Added robust phone matching (`phone`/`mobile`, normalized variants) for inbound and sync processing.
- Added safe schema migration `Version0002` for sync log table and lead message fields.
- Fixed content-length runtime failures when legacy databases had short `last_*_message_content` columns.

## v1.3.0
- Added inbound WhatsApp receive webhook endpoint.
- Added lead matching by incoming phone (`phone`/`mobile` variants).
- Added automatic lead tagging on inbound messages: `whatsapp_message_answered_zender`.
- Added webhook routes:
  - `GET /zender/receive/{key}/{phone}/{message}/{time}/{datetime}` (legacy-compatible)
  - `GET|POST /zender/receive/{key}` (query/body payloads)

## v1.2.0
- Single package compatibility for Mautic 5, 6 and 7.
- Dynamic integration constructor wiring (`session` for M5, `FieldsWithUniqueIdentifier` for M6/7).
- Relaxed requirements to `mautic >=5.1.0 <8.0.0` and `php >=8.1`.
- Removed duplicate `Config/services.php` registration to avoid container conflicts.

## v1.1.2
- E.164 sin región por defecto
- Envío vía Guzzle (form_params), timeouts
- Detección automática de media y payload "media"
- Reemplazo de /r/... por URL real
- Validación de credenciales y errores claros
- Wiring compatible Mautic 6 (FieldsWithUniqueIdentifier)
