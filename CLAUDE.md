# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

"SC Online" for Heaven Scent: a Laravel 13 / PHP 8.3 supply-chain app for perfume production (purchasing, warehouses, batch production + BOM, request/transfer, stock analysis, fulfillment). Server-rendered Blade UI; no SPA. UI text, docs, and many identifiers (`produk`, `gudang`, `stok`, `analisa`, `varian`) are Indonesian. Match that naming in new code.

The README is the stock Laravel one and mostly useless. The real domain docs are [DOKUMENTASI_FLOW_BISNIS.md](DOKUMENTASI_FLOW_BISNIS.md) (business flows, all formulas, role matrix) and [PRD_SC_Online_v3.md](PRD_SC_Online_v3.md). Per-feature specs and plans live in `docs/superpowers/{specs,plans}/` and `.agent/*.md`. Check them before changing business rules.

## Commands

```bash
composer setup                 # install, .env, key, migrate, npm install, build
composer dev                   # serve + queue:listen + pail + vite (concurrently)
npm run dev | npm run build    # both first run scripts/copy-vendor.mjs, then vite
composer test                  # config:clear + php artisan test
php artisan test --filter=PurchasingFlowTest
php artisan test --filter=test_best_case_full_purchasing_lifecycle_from_draft_to_received_and_paid
php artisan test tests/Feature/PurchasingFlowTest.php
vendor/bin/pint                # code style (laravel/pint)
php artisan purchasing:check-overdue   # also scheduled daily (routes/console.php)
php artisan db:seed            # PermissionSeeder, Divisi, User, Uom, KlasifikasiAbc, MasterData (+ ProductVarianBomSeeder outside testing)
```

- Dev/prod DB is PostgreSQL (`.env.example`); **tests run on in-memory SQLite** (`phpunit.xml`). Keep migrations and queries portable across both.
- Feature tests call `$this->seed()` in `setUp` and log in as seeded users (`manager@`, `purchasing@`, `gudang@heavenscent.id`, etc.). Seeders therefore define the test fixtures. `ProductVarianBomSeeder` is skipped when `APP_ENV=testing`.
- There is no JS test suite or linter.

## Architecture

**Layering.** Routes ([routes/web.php](routes/web.php), all behind `auth`, grouped by `can:<permission>` middleware) → thin-to-fat controllers → `app/Services/*` for business rules → Eloquent models. The heavy logic lives in services: `AnalisaService` (~2k lines, stock-analysis formulas), `TransferService`, `RequestService`, `StokService`, `StampsBomImporter`, and `Services/Purchasing/*` (stage machine, cost calculator, payments, revisions, supplier lead time). `PurchasingController` and `AnalisaController` are also large, so read the matching service first.

**Authorization is the main cross-cutting concern.** Spatie laravel-permission with *dynamic* roles (UI-managed via `RoleController`/`RoleService`), not just 5 fixed ones. Five system roles are seeded: manager, purchasing, gudang, operasional, fulfillment.
- Permission names are catalogued in `Services/Authorization/PermissionCatalogService` (source for the role matrix UI) and seeded by `PermissionSeeder`. A new permission has to go in both.
- `AppServiceProvider::boot` registers `Gate::before`: `manager` bypasses everything, and an alias table maps legacy `rt.*` and module-level `request.*`/`transfer.*` permissions to their granular `…bahan.*` / `…produk_jadi.*` variants. Add new aliases there, not in controllers.
- Row-level scope is separate from permissions: `LocationScopeService::getAccessibleWarehouseIds()` returns the warehouse IDs a user may touch (`null` = global), driven by `users.warehouse_access_type`, the `gudang_user` pivot, and a role-based fallback. `InventoryTransferAuthorizationService` layers on top for request/transfer actions.

**Stock ledger.** All stock movement goes through `StokService` (`masuk`/`keluar`/`pindah`/`setAbsolut`), which writes `KartuStok` entries inside DB transactions. Don't update `stok` rows directly. HPP (cost) resolution (`resolveHppMap`) falls back across latest PO, analisa lokal, and analisa impor prices.

**Purchasing.** A PO moves through an ordered stage machine in `PurchasingStageService` + `PoStage` (`perencanaan → konfirmasi_supplier → approval → pembayaran → po_terbit → pengiriman → unloading → input_sistem`). Stages cannot be skipped. The `PoStage::STAGE_n` constant names are historical keys, not execution order (`STAGE_2` = approval runs 3rd, `STAGE_3` = konfirmasi_supplier runs 2nd); use `PoStage::STAGES` for order. A Manager rejection at approval returns the PO to konfirmasi_supplier and opens a new round. The system uses **8 stages**; some test names (e.g. `PurchasingNineStageAndLeadTimeTest`) still say "9", and `pengemasan` survives only as a legacy constant (`LEGACY_STAGE_PENGEMASAN`, archived POs). Stage timings feed `SupplierLeadTimeService` summaries. PO data is editable only in perencanaan and konfirmasi_supplier; from approval on, `PurchasingController::update` rejects edits and a mistake means cancel + new PO (only `quickDates` for tanggal/ETA and payment recording still work until final). `PurchaseOrderRevisionService` / `PoRevisi` are no longer wired to any route or controller (only tests and legacy view display still reference them). Payments are termin-based (`PurchasingPaymentService`, `PurchasingCostCalculator`, UOM conversion). QA time tools: `config/qa.php`, `App\Services\Qa\*`, hanya non-production; lihat docs/qa/QA_TIME_TOOLS.md.

**Analisa (stock analysis).** Three tabs — lokal, impor, fulfillment — plus riwayat snapshots, backed by `Analisa*` models and `AnalisaService`. ABC classification (`KlasifikasiAbc`), per-supplier lead times, and ADU are inputs. Formulas are specified in section 5 of the business doc.

**Production.** `BatchProduksi` with multiple outputs (`BatchProduksiOutput`), BOM-driven material allocation (`AlokasiBahan`), and defect/yield tracking. Request → Transfer are deliberately separate modules, with Request for demand and Transfer for physical shipment/QC.

**Errors and audit.**
- Throw subclasses of `App\Exceptions\DomainException` (e.g. `InsufficientStockException`) for business-rule failures. `bootstrap/app.php` converts them to `back()->with(flashKey, message)` or a 422 JSON, and also customizes 401/404/419 handling. Don't catch-and-redirect manually in controllers.
- `Traits/AuditableOperation` (documents → `OperationalAudit`) and `AuditableMasterData` (→ `MasterDataAudit`) auto-log created/updated events through model hooks. Controllers sometimes call `recordAudit()` explicitly, with a flag to suppress the duplicate auto-entry.

**Eloquent strictness.** `Model::preventLazyLoading` is on in every non-production environment, **including tests**. Missing `with()`/`load()` throws. Eager-load relations you touch in views and services.

**Frontend.** Blade views under `resources/views/<module>/` with shared `components/` and `partials/`. Alpine.js + Tailwind 4 via Vite, SweetAlert2 and Chart.js as bundled chunks. **jQuery and DataTables are not bundled.** `scripts/copy-vendor.mjs` copies them to `public/vendor` (gitignored) because page scripts in `@push('scripts')` call `$(...)` directly. List pages use yajra DataTables with a `<module>/data` JSON route next to each resource route.

## Deploy

Push to `main` triggers `.github/workflows/deploy.yml`: build Docker image → GHCR → SSH to the VPS → `docker compose up -d`, migrate, `storage:link`, `optimize`. It shares `nginx-proxy` with another stack on the same VPS. Cache, session, and queue use the `database` driver, with no Redis. See [DEPLOY.md](DEPLOY.md).

## Repo quirks

- `production_lab` (repo root) is a tracked SQLite file, not source. `.worktrees/` is gitignored and holds local git worktrees. `.superpowers/sdd/` progress notes are gitignored.
- Large root-level docs (PRD v2.2/v2.4/v3, `implementation_plan.md`, `prd.md`, the QA `.xlsx`, `list product varian dan bom.csv`) are reference material. PRD v3 is the current one.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- When graphify-out/graph.json exists, run `graphify query "<question>"` before your first grep, search or source-file read for a codebase question, and put this rule in every subagent prompt that explores code. Read raw files only for lines the graph or an earlier read already located. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. When the answer is under a Markdown heading, `graphify section "<heading>"` prints that section's text, so the .md need not be opened. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
- A committed graphify-out/graph.json is rewritten whole on every build, so a large diff to it in a pull request is expected. Do not flag it as churn or suggest skipping the graph rebuild or its hooks.
