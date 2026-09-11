# Design: multiple resources per task

## 1. Problem

In stock SoPlanning a task (`planning_periode`) carries **exactly one** resource,
in the scalar column `planning_periode.ressource_id`. The task form renders it as
a single-value `<select>`, gated by the existing option
`CONFIG_SOPLANNING_OPTION_RESSOURCES`.

The *user* assignment is already multi-valued (`user_id2` is a
`<select multiple>`, stored as one linked `planning_periode` row per user sharing
a `link_id`). Resources have no equivalent.

Teams that schedule equipment-heavy jobs need several resources on one task
(e.g. a clean-gas measurement = 2 FID analysers + 1 NO/CO analyser + gas
cylinders). The two stock workarounds are both poor: one task per device
clutters the planning and lets dates/status drift; modelling devices as *users*
breaks the "by resource" planning view and pollutes the staff list.

Forum request: <https://forum.soplanning.org/viewtopic.php?f=9&t=3538>

## 2. Goals / non-goals

**Goals**

1. 0..N resources on a task.
2. The "by resource" planning view keeps working: a task with N resources shows
   on each of the N resource rows.
3. Booking-conflict detection (`checkConflitRessource`) covers every resource on
   the task, primary or not.
4. **Zero behavioural change when the option is off** — existing installs upgrade
   silently and stay identical until an admin opts in.
5. Existing data stays readable by untouched code (exports, API v1, mail
   templates) during and after the change.
6. Match existing project conventions (schema style, `sql/update` file format,
   the `Gobject` field pattern, Smarty/xajax idioms).

**Non-goals**

- Quantities / stock levels (a resource stays one named entity; "3 cylinders" =
  3 resources).
- Turning the soft conflict into a hard block (unchanged: surfaced, not
  prevented).
- Per-resource role/notes within a task.
- Any change to user assignment / `link_id`.
- Multi-resource in the REST API, CSV/iCal/Gantt/PDF/XLS exports and
  notification e-mails — these keep showing the **primary** resource; see
  `5-UPSTREAM-NOTES.md` for the suggested follow-ups.

## 3. Approach

### 3.1 Storage — junction table + kept denormalised pointer

New table `planning_periode_ressource (periode_id, ressource_id)`, composite
primary key, secondary index on `ressource_id`, FKs `ON DELETE CASCADE ON UPDATE
CASCADE` to `planning_periode` and `planning_ressource` (both InnoDB, both
`latin1_general_ci`).

`planning_periode.ressource_id` is **kept** and always holds the task's *first /
primary* resource (or `NULL`). Every write keeps the two consistent. This lets
all the read paths that were left out of scope keep working unchanged, keeps the
diff small, and makes a downgrade lossless apart from the extra resources.

Rejected alternative — reuse the `link_id` trick (one `planning_periode` row per
resource): explodes row counts (2 users x 3 resources = 6 rows), entangles the
user- and resource-multiplicity mechanisms, and complicates every query that
counts tasks.

### 3.2 Opt-in option

New key `SOPLANNING_OPTION_RESSOURCES_MULTIPLE` in `planning_config`
(`0` = default). Surfaced in Configuration under "Resources management".

| `…_RESSOURCES` | `…_RESSOURCES_MULTIPLE` | behaviour |
|---|---|---|
| 0 | – | resources feature off (unchanged) |
| 1 | 0 | **stock**: single `<select>`, single `ressource_id`; junction just mirrors it |
| 1 | 1 | `<select multiple>`; 0..N resources; junction is source of truth; `ressource_id` = first |

### 3.3 Write path (`submitFormPeriode`)

`$ressource` is accepted as a scalar *or* an array. After the `planning_periode`
row(s) are saved, for **every** row sharing the task's `link_id` (all assigned
users, all repeated occurrences) the junction is rewritten to the selected set
and `ressource_id` is set to the first element. Implemented as
`setRessourcesPeriode()` in `lib.inc`.

### 3.4 Read paths

- **Task form** – pre-selected from `getRessourcesPeriode()` (falls back to the
  scalar).
- **"By resource" planning grid** (`www/planning.php`) – a small helper
  `planningLignesRessource($infosJour)` returns the task's resource-id list (or
  the single primary when the option is off); the ~8 by-resource bucket
  assignments iterate it, so the task lands on every one of its resource rows.
  `masquerLigneVide` builds the full resource-row set when the option is on
  (empty rows are still hidden later at render time).
- **Resource filter** (`www/planning.php`, `www/taches.php`) – the
  `ressource_id IN (…)` clause gains `OR EXISTS (SELECT 1 FROM
  planning_periode_ressource … )`.
- **Conflict check** (`checkConflitRessource` in `lib.inc`) – its SQL gains the
  same `OR EXISTS (…)`; `checkConflitRessources()` loops a set.
- **Resource delete** (`Ressource::db_delete`) – repoints `ressource_id` of
  affected tasks to a remaining resource (or NULL) and clears junction rows
  (redundant with the FK cascade, safe without it).

## 4. Backward compatibility

| Consumer | After this change |
|---|---|
| Option off | Identical to stock. Junction mirrors the single value; nothing reads it. |
| REST API v1 | Unchanged: returns/accepts the single `resource_id` (= primary). |
| CSV / iCal / Gantt / PDF / XLS exports | Unchanged: emit the primary via `ressource_id`. |
| Notification e-mails | Unchanged: show the primary. |
| Existing rows | Back-filled into the junction by the migration; `ressource_id` untouched. |
| Downgrade | Drop the table + the option row; `ressource_id` still holds the primary. |

## 5. i18n

New keys in `templates/languages/{en,fr,de}.txt`
(`config_options_ressources_multiple`, `config_aide_options_ressources_multiple`,
`winPeriode_ressources`). English filled; fr/de placeholder text is ASCII —
these files are ISO-8859-1, so accented wording is left for the translators.
