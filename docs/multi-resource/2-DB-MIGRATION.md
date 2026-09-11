# DB migration

File: `sql/update/update-1-56-02.txt` (+ `version.txt` bumped to `1.56.02`).

Runs through the normal mechanism: on the first hit of `/install/` after deploy,
`Version::upgradeVersion()` sees `planning_config.CURRENT_VERSION` (`1.56.01`) <
`version.txt` (`1.56.02`), executes the file statement-by-statement
(`explode(";")`), and stamps `CURRENT_VERSION` to `1.56.02`.

## What it does

```sql
CREATE TABLE IF NOT EXISTS `planning_periode_ressource` (
  `periode_id`   int(11)     NOT NULL,
  `ressource_id` varchar(20) NOT NULL DEFAULT '' COLLATE 'latin1_general_ci',
  PRIMARY KEY (`periode_id`, `ressource_id`),
  INDEX `idx_pr_ressource_id` (`ressource_id`),
  CONSTRAINT `fk_pr_periode`   FOREIGN KEY (`periode_id`)   REFERENCES `planning_periode`   (`periode_id`)   ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pr_ressource` FOREIGN KEY (`ressource_id`) REFERENCES `planning_ressource` (`ressource_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = latin1 COLLATE = latin1_general_ci ROW_FORMAT = Dynamic;
```

Then:

1. **Back-fill** from the current single-resource data (INNER JOIN to
   `planning_ressource` so any stale `ressource_id` is skipped rather than
   aborting the FK step):
   ```sql
   INSERT INTO `planning_periode_ressource` (`periode_id`, `ressource_id`)
   SELECT p.`periode_id`, p.`ressource_id`
   FROM `planning_periode` p
   INNER JOIN `planning_ressource` r ON r.`ressource_id` = p.`ressource_id`
   WHERE p.`ressource_id` IS NOT NULL AND p.`ressource_id` <> '';
   ```
2. **New option row**:
   ```sql
   INSERT INTO `planning_config`(`cle`, `valeur`, `commentaire`)
   VALUES ('SOPLANNING_OPTION_RESSOURCES_MULTIPLE', '0',
           'Allow several resources per task, requires SOPLANNING_OPTION_RESSOURCES');
   ```
3. Mandatory final `UPDATE … CURRENT_VERSION = '1.56.02'`.

The file contains no inner `;` (SoPlanning splits on `;`), no `#` / `/* */`
comments (only whole-line `--`), and no transaction wrapper — consistent with the
other `sql/update/*.txt` files.

## Rollback

```sql
DROP TABLE IF EXISTS `planning_periode_ressource`;
DELETE FROM `planning_config` WHERE `cle` = 'SOPLANNING_OPTION_RESSOURCES_MULTIPLE';
UPDATE `planning_config` SET `valeur` = '1.56.01' WHERE `cle` = 'CURRENT_VERSION';
```
and restore `version.txt`. `planning_periode.ressource_id` still holds every
task's primary resource, so the app returns to exact stock behaviour; only the
*extra* resources are lost.

## Notes for a different release train

If this ships as part of a larger `1.57.00` instead, rename the file to
`update-1-57-00.txt` and change both version strings to `1.57.00` — the mechanism
is identical.
