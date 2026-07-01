## v1.2.12
- Fixed Symfony 7 command registration by passing the Zender sync command name
  to the parent `Command` constructor. This removes the Mautic 7 warning that
  the command cannot have an empty name during cache clear/plugin reload.

## v1.2.11
- Fixed Mautic 7/Symfony 7 console compatibility for `mautic:zender:sync-messages`
  by adding explicit `configure(): void` and `execute(...): int` method
  signatures. This prevents `cache:clear` from failing while loading plugin
  commands after automatic plugin installation.

## v1.2.10
- Made `https://github.com/AlexanderZlobinM1/MauticZenderPlugin` the public canonical repository.
- Updated plugin metadata, documentation, and release packaging links away from the old private `MauticZenderPlugin-1.2.x` repository name.

## v1.2.9
- Fixed the Mautic plugin install/update path:
  - switched the root bundle class from legacy `PluginBundleBase` to `Mautic\IntegrationsBundle\Bundle\AbstractPluginBundle`.
  - replaced invalid `Version000X` migration files with semantic-versioned `Migrations/Version_1_2_9.php`.
  - kept migration checks idempotent for databases that already have all or part of the Zender schema.
- Added update-event handling so the `id_whatsapp_in_zender` lead field is ensured during plugin updates, not only first install.

## v1.2.8
- Fixed plugin migration layout for Mautic reload flow:
  - moved migrations from `Migrations/Schema/` to flat `Migrations/`.
  - migrated classes to `Mautic\IntegrationsBundle\Migration\AbstractMigration`.
  - implemented `isApplicable(Schema $schema): bool` and `up(): void`.
- Removed legacy migration files that caused plugin reload failures on Mautic 6.

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
