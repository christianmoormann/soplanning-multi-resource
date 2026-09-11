# Changes by file

Cumulative diff vs pristine 1.56.01: **13 code files, ~205 lines** (plus this
`docs/` folder). One patch per bullet group — see `patches/`.

## DB — `patches/0001`

| File | Change |
|---|---|
| `sql/update/update-1-56-02.txt` | **new** — junction table + FKs + back-fill + option row (see `2-DB-MIGRATION.md`) |
| `version.txt` | `1.56.01` -> `1.56.02` |

## Option — `patches/0002`

| File | Change |
|---|---|
| `templates/www_options.tpl` | +10 lines: yes/no `<select name="SOPLANNING_OPTION_RESSOURCES_MULTIPLE">` under the "Resources management" row |
| `www/process/options.php` | +15 lines: persist that POST field to `planning_config` (block copied from the `SOPLANNING_OPTION_RESSOURCES` one) |

## i18n — `patches/0003`

| File | Change |
|---|---|
| `templates/languages/en.txt` `fr.txt` `de.txt` | +3 keys each: `config_options_ressources_multiple`, `config_aide_options_ressources_multiple`, `winPeriode_ressources` |

## Helpers + conflict — `patches/0004`

`includes/lib.inc`, +~70 lines, all new top-level functions inserted just before
`checkConflitRessource()`, plus a 2-line change inside it:

| Symbol | Purpose |
|---|---|
| `soplanningMultiRessourcesActif()` | `true` iff `CONFIG_SOPLANNING_OPTION_RESSOURCES == 1 && CONFIG_SOPLANNING_OPTION_RESSOURCES_MULTIPLE == 1` |
| `getRessourcesPeriode($periode_id)` | resource-id list from the junction; falls back to `planning_periode.ressource_id` |
| `setRessourcesPeriode($periode_id, $ids)` | replace a task's junction rows (array or comma-string or ''); returns the cleaned list |
| `copyRessourcesPeriode($from, $to)` | copy a task's set onto another task |
| `checkConflitRessources($ids, …)` | loop `checkConflitRessource` over a set, fail on first clash |
| `checkConflitRessource()` (edited) | its 2 SQL builders gain `OR EXISTS (SELECT 1 FROM planning_periode_ressource pprc WHERE pprc.periode_id = planning_periode.periode_id AND pprc.ressource_id = …)` so non-primary resources are conflict-protected |

## Write path — `patches/0005`

| File | Change |
|---|---|
| `templates/periode_form.tpl` | +11 lines: when the option is on, render `<select multiple name="ressource[]" id="ressource">` pre-selected from `$ressource_ids` (union with the legacy `$periode.ressource_id` / `$ressource_id_choisi`); the stock single-select block is kept in the `{else}`. `id="ressource"` is unchanged so the existing `$('#ressource').val()` in the submit call now yields an array for a multiple select. |
| `www/process/xajax_server.php` | `modifPeriode()`: +1 line `assign('ressource_ids', getRessourcesPeriode($periode->periode_id))`. `submitFormPeriode()`: `$ressource` normalised to `$ressourceListe` (array), `ressource_id` = first element; conflict call switched to `checkConflitRessources($ressourceListe, …)`; after the save/repetition passes, `setRessourcesPeriode()` for every `planning_periode` row sharing `$periode->link_id`. |
| `includes/class_ressource.inc` | `db_delete()`: for each task pointing at the deleted resource, repoint `ressource_id` to a remaining junction resource (or NULL); then `DELETE FROM planning_periode_ressource WHERE ressource_id = …`. |

## Drag-drop move / copy — `patches/0007`

| File | Change |
|---|---|
| `www/process/xajax_server.php` | `moveCasePeriode()`: both `checkConflitRessource(…primary…)` calls become `checkConflitRessources()` over the task's full set (or the drop-target resource on a drag onto a resource row), so a reschedule is blocked when *any* of the task's resources clashes. A copied task gets its junction copied (`copyRessourcesPeriode`); dropping a task on a resource row sets that resource as its whole set. |

## Read path — `patches/0006`

| File | Change |
|---|---|
| `www/planning.php` | new `planningLignesRessource($infosJour)` (static per-request cache) after the header include; the 8 `if ($base_ligne=='ressources') $planning['taches'][$infosJour['ressource_id']]…` bucket lines become `foreach (planningLignesRessource($infosJour) as $rL) { $planning['taches'][$rL]… }`; `masquerLigneVide` row-scan is skipped when `soplanningMultiRessourcesActif()` (so all resource rows are built; empty ones are still dropped at render); resource filter clause gains `OR EXISTS (…junction…)`. |
| `www/taches.php` | the two resource-filter clauses gain the same `OR EXISTS (…junction…)`. |

## Not touched (deliberate — see `5-UPSTREAM-NOTES.md`)

`www/api/*`, `www/export_*`, `includes/class_periode.inc` notification methods,
`class_vcalendar.inc`, `audit.php`, `stats_*` — all continue to use the primary
`ressource_id`.
