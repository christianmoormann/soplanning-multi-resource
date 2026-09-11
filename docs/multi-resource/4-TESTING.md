# Testing

Environment: full snapshot clone of a production instance — SoPlanning 1.56.01,
MariaDB, Ubuntu 24.04, ~900 `planning_periode` / 25 `planning_ressource` (all
`exclusif=1`) / 53 `planning_user`. All PHP files `php -l`-clean after each
patch.

## Migration

| Check | Result |
|---|---|
| `/install/` upgrade run | "Database upgrade ok", `CURRENT_VERSION` = `1.56.02` |
| `planning_periode_ressource` created with both FKs | yes (`SHOW CREATE TABLE`) |
| Back-fill | 46 rows = 46 tasks that had a `ressource_id` |
| First attempt caught a bug | `;` inside a comment string split a statement — fixed (inlined FKs, no inner `;`) |

## Option OFF (`SOPLANNING_OPTION_RESSOURCES_MULTIPLE = 0`)

| Check | Result |
|---|---|
| `/planning`, `/taches`, `/options`, `/ressources`, by-resource planning | all HTTP 200, no PHP errors in the log |
| Task form | single `<select name="ressource">`, unchanged |
| Toggling the option in the Configuration screen | persists to `planning_config` |

## Option ON — task form

| Check | Result |
|---|---|
| Resource field | `<select name="ressource[]" multiple>`, 25 options |
| Create task, pick 2 resources, save | junction = both; `planning_periode.ressource_id` = first picked |
| Reopen the task | both resources pre-selected in the widget |
| Edit: drop one, add another, save | junction replaced exactly; primary = new first; 0 orphan junction rows |

## Option ON — by-resource planning grid

Test task with resources `{R244, R289}` (primary `R244`):

| Check | Result |
|---|---|
| Task chip in the by-resource grid | appears in **both** `td_R244_<date>` and `td_R289_<date>` |
| grep of the rendered grid | exactly 2 chip occurrences for that `periode_id` |

## Option ON — filter and conflict (junction-aware)

| Check | Result |
|---|---|
| Filter the task list by `R289` (the task's **non-primary** resource) | task is found (stock `ressource_id IN (…)` alone would miss it) |
| Filter by an unrelated resource | task not found |
| `checkConflitRessource('R289')` for an overlapping new task | returns `false` (blocked) — before the fix it returned `true` because `R289` was only in the junction |
| `checkConflitRessources(['R244','R289'], …)` | `false` (first clash) |
| `checkConflitRessources([], …)` | `true` |

## Option ON — drag-drop move / copy

Task with `{R244, R289}` on a given day; both exclusive; another exclusive task
on that day holds `R289`.

| Check | Result |
|---|---|
| Reschedule the task onto a day where `R244` **or** `R289` is already booked | blocked, "move not possible – resource conflict" alert, planning reloads |
| Reschedule a task whose **only** clashing resource is a *secondary* one | blocked (stock would have allowed it — this is the move-path fix) |
| Reschedule onto a genuinely free day | proceeds; junction rows stay attached |
| Ctrl-drag copy of a multi-resource task | the copy gets the full resource set (`copyRessourcesPeriode`) |
| Drag a task onto a different resource row | that resource becomes the task's whole set |

## Option ON — resource deletion

| Check | Result |
|---|---|
| Delete a resource that is a task's non-primary via `Ressource::db_delete()` | its junction row gone, task's other resources intact |
| Delete a resource that is a task's primary | `ressource_id` repointed to a remaining junction resource; junction row gone |
| Orphan junction rows afterwards | 0 |

## Known limitations exercised

- Notification e-mails and CSV/PDF/XLS/iCal/Gantt exports and the REST API were
  confirmed to still show the **primary** resource only — intended, see
  `5-UPSTREAM-NOTES.md`.
- "Hide empty rows" + option on: all resource rows are built and empty ones
  dropped at render — verified no regression; the per-period-scan optimisation is
  simply not used in that mode.
