# PHPCF - Resource

The commands on this page maintain the files behind the `resource` table (`CApp_Model_Resource`
and the application's own resource model): regenerating conversions, removing what no longer
belongs to a row, and clearing whole collections. Run them from inside the application folder —
the resource model, its disks and the registered conversions all come from that application.

```
cd application/ohayomart
phpcf resource:clean --dry-run
```

Every command asks for confirmation when the application runs in production; pass `--force` to
skip the question (cron, deploy scripts).

### resource:regenerate

Rebuilds the conversions of the selected resources through `createDerivedFiles()`: non-queued
conversions run in the same process, queued ones are dispatched as usual.

```
phpcf resource:regenerate
phpcf resource:regenerate OHModel_Item
phpcf resource:regenerate --ids=12,15,30
phpcf resource:regenerate --starting-from-id=12000 --only=thumb --only-missing
```

Arguments and options:

- `modelType` — only resources attached to this model type
- `--ids=` — only these resource ids, comma separated or repeated
- `--only=` — only these conversion names (repeatable)
- `--starting-from-id=` — resources with an id equal to or higher than the given one; `-X` /
  `--exclude-starting-id` excludes the id itself
- `--only-missing` — skip conversions whose file already exists
- `--with-responsive-images` — regenerate responsive images too
- `--queue-all` — queue every conversion, the non-queued ones included, so a large regenerate
  leaves the work to the queue worker

Resources whose conversion failed are listed at the end and make the command exit with `1`; the
others are still regenerated.

### resource:clean

Removes what no resource row accounts for any more. Always run it with `--dry-run` first — it
only lists what it would remove — and check the list before running it for real.

```
phpcf resource:clean --dry-run
phpcf resource:clean --dry-run --delete-orphaned
phpcf resource:clean OHModel_Item --skip-directories
phpcf resource:clean OHModel_Item images s3 --dry-run
```

Three independent passes, each of which can be switched off:

1. **Orphaned resources** (`--delete-orphaned`, off by default) — rows whose owning model row is
   gone. Only model types that resolve to a loadable class are checked; the others are listed
   and skipped. A soft-deleted owner still counts as present. Orphans are soft deleted like any
   other resource, which keeps their files; add `--purge` to force delete them so the files go
   too.
2. **Stale conversion files** (skip with `--skip-conversions`) — files in a resource's
   `conversions/` directory that do not belong to any conversion the model still registers for
   that collection, plus responsive images generated for a conversion that no longer asks for
   them. The `generated_conversions` flags of the removed conversions are reset.
3. **Orphaned directories** (skip with `--skip-directories`) — `resources/[<appCode>/]<Ymd>/
   <modelType>/<id>` directories on every disk the table names (or only the `disk` argument)
   that no row, soft-deleted rows included, points at. The sweep is deliberately conservative
   because a docroot or a bucket can be shared: another application's `resources/<appCode>/`
   subtree is left alone, and so is a model type this application cannot load. On S3 this pass
   lists every directory, so it is the slow one — scope it with the `disk` argument or run it
   separately.

Arguments and options:

- `modelType`, `collectionName` — narrow passes 1 and 2; `*` means "any", so
  `resource:clean '*' images` filters by collection only
- `disk` — sweep only this disk in pass 3 (`resource:clean '*' '*' s3`)
- `--dry-run` — list without removing
- `--delete-orphaned`, `--purge`, `--skip-conversions`, `--skip-directories` — see above
- `--rate-limit=` — maximum operations per second, for a remote disk

### resource:clear

Deletes every resource of a model type and/or collection.

```
phpcf resource:clear --dry-run
phpcf resource:clear OHModel_Item
phpcf resource:clear OHModel_Item images --purge
phpcf resource:clear '*' avatars --dry-run
```

Deleting follows the model: a resource model with the soft-delete trait is soft deleted and its
files stay on disk; `--purge` force deletes the rows so the files are removed as well. A model
without soft delete is always purged. `--dry-run` prints the count that would be affected.
