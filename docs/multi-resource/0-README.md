# Multiple resources per task — contribution package

This branch adds the ability to assign **several resources to a single task**
("période"), instead of exactly one, without losing the "by resource" planning
view or reporting.

It is written as an **opt-in feature**: with the new option turned off (the
default), behaviour is byte-for-byte identical to stock SoPlanning 1.56.01.

## Origin

Feature request on the official forum:
<https://forum.soplanning.org/viewtopic.php?f=9&t=3538>

Real-world need: field gas-measurement jobs where one task ("Reingasmessung")
requires e.g. 2 FID analysers + 1 NO/CO analyser + gas cylinders at the same
time. Modelling each device as a separate task clutters the planning; modelling
devices as users breaks the "by resource" view.

## How to read this package

| File | Purpose |
|------|---------|
| `1-DESIGN.md` | Problem, goals/non-goals, chosen approach, alternatives rejected, data model, backward compatibility, the new config option |
| `2-DB-MIGRATION.md` | New table, migration file, data backfill, rollback |
| `3-CHANGES-BY-FILE.md` | Every file touched and why (map to the commits) |
| `4-TESTING.md` | Manual test plan and recorded results |
| `5-UPSTREAM-NOTES.md` | Notes for the SoPlanning developers: assumptions, i18n strings added, open questions, points where you may prefer a different choice |
| `patches/` | `git format-patch` output — one patch per logical step, in order |

## How to apply

```
git checkout -b feature/multi-resource-per-task v1.56.01
git am docs/multi-resource/patches/*.patch
# then run the DB migration described in 2-DB-MIGRATION.md
```

Or cherry-pick / squash as preferred — the commits are grouped so each is
individually reviewable and reversible.

## Status

Prototype developed and tested on a throwaway VM that is a full snapshot clone of
a production instance (SoPlanning 1.56.01, MariaDB, ~900 tasks / 25 resources /
53 users). Not yet run against production.
