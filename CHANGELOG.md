# Changelog

All notable changes to `dashed-marketing`.

## v4.15.5 - 2026-04-24

### Fixed

- Kritieke fix: blokken worden nu opgeslagen met `data.content` als TipTap-JSON
  (`{type: 'doc', content: [...]}`) in plaats van als raw HTML-string. Dat is
  het formaat dat Filament's Builder + RichEditor verwacht. Eerder crashte
  Filament met "Argument #1 (\$itemData) must be of type array, null given"
  bij het openen van de page-editor na een apply.
- FAQ-apply gebruikt ook TipTap-JSON voor `description` + `content` binnen
  `questions`, consistent met hoe Filament FAQ-items opslaat.
- `loadBlocks()` filtert niet-numerieke keys (`savefirst`, `top-content`) en
  null-entries uit bij het inlezen, zodat corrupte state de apply niet
  blokkeert.
- `writeBlocks()` preservert niet-numerieke keys in `CustomBlock.blocks` (bv.
  `top-content` bij ProductCategory) en verwijdert alleen legacy null-junk.
  Subject.content krijgt een pure numerieke array (dat is wat Filament Builder
  verwacht).
- Applier wipete bestaande legacy blokken op `Page.content` en
  vergelijkbare subjects.
- FAQ-blokken worden na elke apply automatisch naar het einde van de blokken-
  lijst verplaatst. Nieuw toegepaste content-blokken komen daarmee altijd vóór
  een bestaand FAQ-blok op de pagina.
- `GenerateOutlineContentJob` slaat FAQ-achtige headings ("Veelgestelde vragen",
  "FAQ", "Frequently Asked Questions", etc.) over bij het genereren van
  content-blokken. Die secties worden al afgedekt door het aparte FAQ-blok —
  voorheen werden ze zowel als content-blok (met de FAQ-lijst in HTML) als
  apart FAQ-blok toegevoegd aan de pagina.

### BREAKING

- Apply-gedrag is nu **wipe-first** voor block/FAQ suggesties: zodra er ook maar
  één `is_new_block=true` block-suggestie of FAQ-suggestie in de apply-batch zit,
  worden alle bestaande blokken op het subject eerst gewist. Daarna worden
  alleen de geselecteerde suggesties geplaatst. Pure meta-only applies (zonder
  blokken/FAQ) laten de blokken ongemoeid. Een `ContentApplyLog` met
  `field_key='blocks.wipe'` en de oude blokken-array wordt aangemaakt zodat
  `rollbackAudit` de pre-apply state kan herstellen. De applier laadde blokken uit `CustomBlock.blocks`
  (vaak leeg voor legacy pagina's) en schreef die lege set vervolgens terug
  naar `$subject->content`, waardoor alle bestaande blokken verdwenen. Applier
  gebruikt nu een nieuwe `loadBlocks()` helper die eerst `$subject->content`
  als source of truth pakt (matcht wat de frontend en de legacy Filament
  Builder tonen) en alleen bij echt lege content terugvalt op `CustomBlock`.
  `writeBlocks()` mirrort dezelfde array naar beide opslagplekken.
- Outline-blok-data bevat nu `full-width: false` zodat de structuur exact
  matcht met hoe Filament's Builder handmatig aangemaakte content-blokken
  opslaat. Dit voorkomt "Argument #1 (\$itemData) must be of type array, null
  given"-errors wanneer Filament de Builder-items ophaalt.
- `detectExistingFaqBlock()` leest ook uit `$subject->content` eerst zodat
  FAQ-target-detectie consistent is met wat er daadwerkelijk op de pagina
  staat.

## v4.15.4 - 2026-04-24

### Fixed

- SeoAuditApplier mirrort toegepaste blokken nu óók naar de legacy `content`
  translatable-kolom op het subject. De frontend leest blokken uit
  `$subject->content` (via `<x-blocks :content="$subject->content">`) maar de
  applier schreef alleen naar `CustomBlock.blocks`. Daardoor verschenen
  outline- en FAQ-blokken wel in de Filament "Maatwerk blokken"-tab maar niet
  op de live pagina. Sync gebeurt automatisch voor subjects die `content` in
  hun `$translatable` hebben staan (Page, Article, Product, ProductGroup,
  ProductCategory).
- SEO Audit review: per-item "Toepassen"/"Afwijzen" knoppen op meta- en
  blok-suggesties zijn nu ook zichtbaar wanneer status `applied` is, zodat
  re-apply mogelijk is via de per-item knoppen (niet alleen via "Alles
  selecteren" + "Geselecteerde toepassen").
- SEO Audit review: FAQ-apply-target (`new` vs `existing`) wordt nu
  live opnieuw gedetecteerd in `pollAudit()` en na `applySelected()`. Eerder
  bleef de waarde staan vanaf `mount()` wat ertoe leidde dat na een eerste
  FAQ-apply of na handmatige verwijdering van het FAQ-blok de UI de verkeerde
  optie koos.

### Changed

- `SeoAudit::metaSuggestions()` sorteert nu `meta_title` eerst, daarna
  `meta_description`, overige velden op `id`-volgorde. De review-UI toont de
  meta-suggesties nu in de verwachte volgorde.

## v4.15.3 - 2026-04-24

### Fixed

- SEO Audit review: "Alles selecteren" selecteert nu ook al-toegepaste suggesties
  zodat re-apply na een eerste toepassing mogelijk is.
- `SeoAuditApplier` staat re-apply toe: `applied` suggesties kunnen opnieuw
  toegepast worden. Alleen `rejected` en `failed` worden nog geskipt. Audits met
  status `fully_applied` zijn ook re-applyable.

### Added

- `applied_block_index` kolom op `dashed__seo_audit_block_suggestions` —
  re-apply van `is_new_block=true` suggesties overschrijft nu het eerder
  aangemaakte blok op dezelfde positie in plaats van een duplicaat toe te
  voegen. Valt terug op append als het blok tussentijds is verwijderd of als
  het type op die index niet meer matcht.

## v4.15.2 - 2026-04-24

### Fixed

- Content-generatie op basis van outline draait nu op de achtergrond via
  `GenerateOutlineContentJob`. Eerder liep de AI-loop synchroon in de Livewire
  request, waardoor pagina's met veel outline-headings konden timeouten. De knop
  keert nu direct terug, de Livewire poller ververst de UI zodra de job klaar is,
  en dubbel-klikken wordt geblokkeerd via een nieuwe `content_generating_at`
  kolom op `dashed__seo_audit_outlines`.

## v4.15.1 - 2026-04-23

### Added

- SEO Audit review: "Alles selecteren" en "Alles deselecteren" knoppen in de header
  om in een klik alle pending/edited suggesties over meta, blokken, FAQ's en
  structured data te (de)selecteren.
- Alle pending/edited suggesties zijn standaard aangevinkt bij openen van de
  review-pagina, na een status-update via polling, en na het toepassen.

### Changed

- Outline content generatie maakt nu een blokvoorstel voor elke heading, ook als
  de AI geen body teruggeeft (valt dan terug op de heading-HTML alleen).
- Outline blokvoorstellen tonen in de review-UI alleen de content-HTML, niet meer
  de volledige block-JSON. De container/margin-instellingen worden bij toepassen
  opgebouwd door de applier.
- Outline blokken krijgen top_margin alleen op het eerste blok; volgende outline
  blokken krijgen top_margin false (bottom_margin blijft true).

### Fixed

- `content_draft_link_candidates` migratie: expliciete index-naam om MySQL
  64-karakter limiet te omzeilen (voorkomt migratie-fouten op live DB's).

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
