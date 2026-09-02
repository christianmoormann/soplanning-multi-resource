# SoPlanning — multi-resource maintenance fork

Working fork of SoPlanning, kept only to carry local changes that are not
(yet) in upstream. SoPlanning itself: https://www.soplanning.org  (GPLv3)

## Branch layout

| Ref | What |
|---|---|
| `main` / tag `baseline-1.56.01` | SoPlanning 1.56.01 exactly as shipped (minus instance secrets — see `.gitignore`). This branch only ever gets pristine upstream releases. |
| `feature/multi-resource-per-task` | "several resources per one task", opt-in. 7 code commits + a docs commit. See `docs/multi-resource/`. Prototyped and tested on a production snapshot; not run against production. Upstream discussion: https://forum.soplanning.org/viewtopic.php?f=9&t=3538 |

## Updating to a new SoPlanning release

1. On `main`: drop in the new pristine release, commit, tag `baseline-<version>`.
2. `git rebase --onto baseline-<version> baseline-1.56.01 feature/multi-resource-per-task`
   (or cherry-pick the 7 code commits).
3. Redeploy to a throwaway VM and re-run the checks in
   `docs/multi-resource/4-TESTING.md`, esp. "option off = unchanged".
4. Update `sql/update/update-1-56-02.txt` → the new next version number and the
   `CURRENT_VERSION` string, and `version.txt`.

## Status

The SoPlanning maintainers indicated a large version bump is coming that would
likely conflict with this patch, and that they plan to build the feature
themselves rather than merge this one — see the forum thread above. This fork
is kept public as a working, tested stop-gap for anyone who wants it before an
official version lands.
