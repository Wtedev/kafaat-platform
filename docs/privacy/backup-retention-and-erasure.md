# Backup Retention and Erasure

## Application scope

**This application does not delete data from backup archives.** Backups may retain personal data until their operational retention period expires independently.

## Operational requirements

1. Backups must be encrypted at rest where infrastructure supports it
2. Access restricted to authorized operators
3. Backup retention period is an **organizational decision**, not enforced by this codebase
4. Erasure requests satisfied in the live system do not imply immediate erasure from historical backups

## Relationship to retention engine

When the engine deletes or anonymizes live records:

- Completed runs are logged in `retention_runs`
- File deletions occur on configured private disks only
- Backup tapes/snapshots are out of scope

## Documentation owner

Infrastructure / DPO must define backup TTL and access controls outside this repository.
