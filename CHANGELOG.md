# Changelog

All notable changes to `dashed-marketing`.

## v4.12.0 - 2026-04-22

### BREAKING

- `SeoImprovement` replaced by `SeoAudit` with a new schema and Filament resource.
  Consumers must run `php artisan dashed-marketing:migrate-seo-improvements` after
  upgrade to convert legacy records. The old `dashed__seo_improvements` table is kept
  for one release for rollback safety, then dropped in the next minor release.
- `GenerateContentDraftJob` no longer creates `SeoImprovement` records when a keyword
  matches an existing entity. Instead it always creates a `ContentDraft`. Use the new
  "Genereer SEO audit" action on the entity's edit page for suggestion-based updates.

### Added

- `SeoAudit` model with seven analysis domains: page-analysis, keywords, meta, blocks,
  FAQ, structured data, internal links.
- `GenerateSeoAuditJob` runs seven sequential AI steps with progress reporting.
- `SeoAuditApplier` service: per-item apply, full-audit rollback, per-log revert.
- `RequestSeoAuditAction` header action, auto-injected via `HasEditableCMSActions`
  on every CMS editpage.
- Filament `SeoAuditResource` with a list page and a custom Review page that has
  seven tabs (overview, keywords, meta, blocks, FAQ, structured data, internal links).
- Config keys `seo_block_whitelist` and `seo_faq_block_types`.
- Artisan command `dashed-marketing:migrate-seo-improvements` (idempotent).
- `ContentDraftLinkCandidate` subject morph for model-linked candidates (from prior
  release bundled in the same cycle).

### Changed

- `ContentApplyLog` gains an `audit_id` foreign key pointing to `seo_audits`.
  The existing `seo_improvement_id` column stays nullable for legacy rows.

### Removed

- `SeoImprovement` model and Eloquent class
- `SeoImprovementResource` plus all its Pages (list/create/review)
- `RequestSeoImprovementAction`
- `AnalyzeSubjectSeoJob`
- `ApplyContentImprovementJob`
- `ContentApplyLog::seoImprovement()` relation

### Migration path

1. Upgrade dashed-core to v4.0.137 in the same release cycle.
2. Deploy the new dashed-marketing v4.12.0.
3. Run `php artisan dashed-marketing:migrate-seo-improvements` once to convert
   legacy records. Safe to rerun.
4. After the next minor release, the `dashed__seo_improvements` table is dropped.
