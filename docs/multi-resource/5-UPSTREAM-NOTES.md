# Notes for the SoPlanning maintainers

Thanks for a tool that is genuinely pleasant to extend. This branch is a working
prototype, tested on a production snapshot (see `4-TESTING.md`), offered for you
to take, reshape, or reject. Nothing here is load-bearing for us yet.

## Choices you may want to revisit

1. **Opt-in via a second option.** We added
   `SOPLANNING_OPTION_RESSOURCES_MULTIPLE` so existing installs see no change.
   If you would rather have multi-resource always-on when
   `SOPLANNING_OPTION_RESSOURCES = 1`, drop the option, the extra
   `www/process/options.php` block, the 2 template `{if}` branches and
   `soplanningMultiRessourcesActif()` (make it `return true`), and delete the
   `INSERT INTO planning_config` line from the migration.

2. **Kept `planning_periode.ressource_id` as a denormalised "primary".** This is
   what let the patch stay ~180 lines: exports, the REST API, iCal, audit and
   the notification e-mails all keep working untouched by reading that column.
   The alternative (drop the column, teach every reader the junction) is a much
   bigger change and makes downgrade lossy. If you prefer that, the junction
   table and `getRessourcesPeriode()` are the foundation to build on.

3. **Junction, not `link_id`.** We did not want to multiply `planning_periode`
   rows per resource on top of the existing per-user multiplication.

4. **FKs in the migration.** `planning_periode` is InnoDB with FKs already, so
   `ON DELETE CASCADE` there is safe and removes a lot of cleanup code. If you
   support non-InnoDB or exotic collations in the wild, move the two
   `CONSTRAINT` lines out and rely on `Ressource::db_delete()` +
   `supprimerPeriode()` for cleanup (the code already deletes junction rows
   explicitly, so it is safe without the FKs).

5. **`checkConflitRessource` SQL.** We appended `OR EXISTS (… junction …)` to the
   two builders rather than rewriting the function. It works but the query is
   uglier; you may prefer a `UNION` or a small rewrite.

## Deliberately left for a follow-up

These keep showing only the **primary** resource today. Each is a small,
self-contained addition once you are happy with the data model:

- **REST API** (`www/api/endpoint.php`, `endpoint_mobile.php`,
  `Periode::getAPIData` / `putAPI`) — add a `resource_ids` array field alongside
  the existing single `resource_id`.
- **Exports** (`www/export_{csv,csv_raw,gantt,pdf,pdf_calendrier,xls}.php`) — they
  all do `LEFT JOIN planning_ressource ON planning_periode.ressource_id`; add a
  `GROUP_CONCAT` over the junction for a "resources" column.
- **iCal** (`www/export_ical.php`, `class_vcalendar.inc`).
- **Notification e-mails** (`class_periode.inc` `notif_avant` / `notif_apres` /
  `envoiNotification`, `templates/mail_*_tache.tpl`) — list the set instead of
  the one `ressource`.
- **`planning_recap.php`** — shows one `ressource_nom` per task.
- **Drag-drop onto a resource row** in `moveCasePeriode()` — currently
  reassigns the primary; multi-mode semantics ("add" vs "replace") are a
  product decision.
- A real **stats-by-resource** page (there is none today).

## i18n

New keys are in `en/fr/de` only; fr/de values are ASCII placeholders because the
`.txt` files are ISO-8859-1 and we did not want to risk the encoding. Please
have your translators set the accented wording and add the other languages.

## Status

The maintainers replied on the forum thread that a large upcoming version bump
would likely conflict with this patch, and that they plan to build the feature
themselves rather than merge this one. Left here as-is in case any of the
approach or code is still useful reference material.

## Housekeeping

- Branch is 7 commits on top of the `1.56.01` tag; `patches/` holds the same as
  `git format-patch`.
- No new runtime dependencies. No changes to `composer.json`.
