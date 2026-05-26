---
description: Bump module version across all files and prepend a CHANGELOG entry generated from commits since the previous version.
---

Bump the Conekta Magento plugin version to: `$ARGUMENTS`

`$ARGUMENTS` must be a semver string like `5.6.0`, `5.5.1`, or `6.0.0`. If empty or invalid, stop and ask the user for the target version.

## Steps

### 1. Resolve previous version

Read the current version from `composer.json` (`"version": "X.Y.Z"`). Call this `OLD_VERSION`. The new version is `NEW_VERSION = $ARGUMENTS`.

If `NEW_VERSION` is not a valid `MAJOR.MINOR.PATCH` (digits only), stop and ask the user to correct it. Reject `v`-prefixed inputs (`v5.6.0`).

If `NEW_VERSION <= OLD_VERSION` (semver-aware compare), stop and confirm with the user before continuing.

### 2. Collect commit log since previous version

Get the commits since the last version bump using:

```bash
git log --pretty=format:'- %s' "$(git log --grep='Bump version' -n 1 --format=%H)..HEAD" -- . ':!CHANGELOG.md' ':!README.md' ':!composer.json' ':!etc/module.xml' ':!etc/adminhtml/system.xml' ':!etc/config.xml'
```

If that returns nothing or fails (no previous "Bump version" commit), fall back to:

```bash
git log --pretty=format:'- %s' -20 -- . ':!CHANGELOG.md' ':!README.md' ':!composer.json' ':!etc/module.xml' ':!etc/adminhtml/system.xml' ':!etc/config.xml'
```

Filter the resulting lines:
- Drop merge commits (`Merge pull request`, `Merge branch`).
- Drop trivial subjects (`wip`, `fix typo`, `lint`, `format`).
- Drop subjects that are just version bumps.
- Trim trailing `(#NN)` PR suffixes only if there are duplicates after stripping.
- Keep the original wording — do not paraphrase.

Show the filtered list to the user and ask: "¿Apruebas estas entradas para el CHANGELOG, o quieres editarlas / agregar manuales?" Wait for confirmation before continuing.

### 3. Update files (use Edit, not sed)

Today's date in `YYYY/MM/DD` is taken from the conversation's `currentDate` context.

Apply these exact replacements via the Edit tool:

| File | Old → New |
|---|---|
| `composer.json` | `"version": "OLD_VERSION"` → `"version": "NEW_VERSION"` |
| `README.md` | `Magento 2 Plugin v.OLD_VERSION` → `Magento 2 Plugin v.NEW_VERSION` |
| `README.md` | `composer require conekta/conekta_payments OLD_VERSION` → `composer require conekta/conekta_payments NEW_VERSION` |
| `etc/module.xml` | `setup_version="OLD_VERSION"` → `setup_version="NEW_VERSION"` |
| `etc/adminhtml/system.xml` | `Conekta Configuration. (vOLD_VERSION) ` → `Conekta Configuration. (vNEW_VERSION) ` |
| `etc/config.xml` | `<plugin_version><![CDATA[OLD_VERSION]]></plugin_version>` → `<plugin_version><![CDATA[NEW_VERSION]]></plugin_version>` |

Prepend a new entry to the very top of `CHANGELOG.md`:

```
### NEW_VERSION - YYYY/MM/DD
<approved bullet list from step 2>

```

(Leave the rest of `CHANGELOG.md` untouched.)

### 4. Verify

Run, in parallel:

```bash
grep -n "NEW_VERSION" composer.json README.md etc/module.xml etc/adminhtml/system.xml etc/config.xml
grep -n "OLD_VERSION" composer.json README.md etc/module.xml etc/adminhtml/system.xml etc/config.xml
head -10 CHANGELOG.md
```

Confirm: every target file has `NEW_VERSION`, none still has `OLD_VERSION` (outside of CHANGELOG.md history), and the new CHANGELOG entry is at the top.

### 5. Report

Print a one-line summary: `Bumped OLD_VERSION → NEW_VERSION. Updated 6 files + CHANGELOG.` Do **not** commit or push — leave that to the user.

## Notes

- Never edit `phpstan-baseline.neon`, `composer.lock`, or anything under `vendor/`.
- Never call this tool to bump to a pre-release suffix (`-rc1`, `-beta`) — those need a different release flow; stop and ask the user.
- If any Edit fails because `OLD_VERSION` is not found verbatim in a file, stop and report which file is out of sync instead of guessing.
