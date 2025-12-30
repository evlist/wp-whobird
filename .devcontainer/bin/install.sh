#!/usr/bin/env bash
# Install or update .devcontainer and .vscode from a shared upstream.
# - .devcontainer: force-sync (rsync --delete)
# - .vscode: baseline-aware with side-by-side *.orig / *.dist and apt-style prompts.
#
# First-run helpers (only when NOT in --dry-run):
# - Suggest and optionally append .gitignore lines
# - Optionally insert a Codespaces badge + "Codespace created by" credit in README.md
#
set -euo pipefail

UPSTREAM_REPO="${UPSTREAM_REPO:-https://github.com/evlist/wp-plugin-codespace.git}"
UPSTREAM_REF="${UPSTREAM_REF:-stable}"
ASSUME_YES="${ASSUME_YES:-}"
DRY_RUN="${DRY_RUN:-}"

say() { printf '%s\n' "$*" >&2; }
warn() { printf 'WARN: %s\n' "$*" >&2; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

usage() {
  cat >&2 <<EOF
Usage: bash .devcontainer/bin/install.sh [--repo <url>|-R <url>] [--ref <ref>|-r <ref>] [--yes|-y] [--dry-run|-d]
EOF
}

# Parse flags
while [ $# -gt 0 ]; do
  case "$1" in
    --repo|-R) UPSTREAM_REPO="${2:-}"; shift 2 ;;
    --ref|-r)  UPSTREAM_REF="${2:-}"; shift 2 ;;
    --yes|-y)  ASSUME_YES=1; shift ;;
    --dry-run|-d) DRY_RUN=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) warn "Unknown flag: $1"; usage; exit 2 ;;
  esac
done

# Ensure we are at a git repo root
if ! git rev-parse --show-toplevel >/dev/null 2>&1; then
  die "Not a git repository. Run from your repo root (where .git exists)."
fi
REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

# First-run detection: no provenance file yet
FIRST_RUN=0
[ ! -f ".devcontainer/.codespace-upstream" ] && FIRST_RUN=1

# Prepare prompt FD to read from terminal even inside loops
PROMPT_FD=0
if [ -e /dev/tty ]; then
  exec 3</dev/tty
  PROMPT_FD=3
fi

prompt() {
  # Usage: prompt "Message" varname
  local _msg="$1" _var="$2" _ans
  if [ -n "${ASSUME_YES:-}" ]; then
    _ans="y"
  elif [ "$PROMPT_FD" -ne 0 ]; then
    read -u "$PROMPT_FD" -rp "$_msg" _ans || _ans=""
  else
    _ans="s"
  fi
  printf -v "$_var" "%s" "$_ans"
}

prompt_yesno() {
  # Usage: prompt_yesno "Message" default(var: y|n)
  local _msg="$1" _default="${2:-n}" _ans
  if [ -n "${ASSUME_YES:-}" ]; then
    _ans="y"
  elif [ "$PROMPT_FD" -ne 0 ]; then
    read -u "$PROMPT_FD" -rp "$_msg" _ans || _ans=""
  else
    _ans="s"
  fi
  case "${_ans:-$_default}" in
    y|Y) return 0 ;;
    *)   return 1 ;;
  esac
}

# Idempotently ensure a line exists in .gitignore
ensure_gitignore_line() {
  local line="$1"
  if [ ! -f ".gitignore" ]; then
    printf '%s\n' "$line" >> ".gitignore"
    return 0
  fi
  if ! grep -Fxq "$line" ".gitignore"; then
    printf '%s\n' "$line" >> ".gitignore"
  fi
}

# Insert the Codespaces badge and credit at top of README.md (create if missing), idempotent
insert_badge_and_credit_in_readme() {
  local origin owner_repo branch badge_line credit_line tmp has_badge has_credit
  origin="$(git config --get remote.origin.url || true)"
  case "$origin" in
    https://github.com/*) owner_repo="${origin#https://github.com/}"; owner_repo="${owner_repo%.git}" ;;
    git@github.com:*)     owner_repo="${origin#git@github.com:}";     owner_repo="${owner_repo%.git}" ;;
    *)                    owner_repo="${GITHUB_REPOSITORY:-}" ;;
  esac
  branch="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo main)"
  badge_line="[![Open in GitHub Codespaces](https://github.com/codespaces/badge.svg)](https://github.com/codespaces/new?hide_repo_select=true&ref=${branch}&repo=${owner_repo})"
  credit_line="Codespace created by [@evlist/wp-plugin-codespace](https://github.com/evlist/wp-plugin-codespace)."

  has_badge=0; has_credit=0
  if [ -f "README.md" ]; then
    grep -Fq "github.com/codespaces/badge.svg" "README.md" && has_badge=1 || true
    grep -Fq "evlist/wp-plugin-codespace" "README.md" && has_credit=1 || true
  fi

  tmp="$(mktemp)"
  if [ "$has_badge" -eq 0 ] && [ "$has_credit" -eq 0 ]; then
    { printf "%s\n%s\n\n" "$badge_line" "$credit_line"; [ -f "README.md" ] && cat "README.md"; } > "$tmp"
    mv "$tmp" "README.md"
    say "Inserted Codespaces badge and credit into README.md"
  elif [ "$has_badge" -eq 0 ] && [ "$has_credit" -eq 1 ]; then
    { printf "%s\n\n" "$badge_line"; cat "README.md"; } > "$tmp"
    mv "$tmp" "README.md"
    say "Inserted Codespaces badge into README.md"
  elif [ "$has_badge" -eq 1 ] && [ "$has_credit" -eq 0 ]; then
    { printf "%s\n\n" "$credit_line"; cat "README.md"; } > "$tmp"
    mv "$tmp" "README.md"
    say "Inserted credit into README.md"
  else
    say "README.md already contains badge and credit; skipping insert."
  fi
}

TMPDIR="$(mktemp -d)"
cleanup() { rm -rf "$TMPDIR"; }
trap cleanup EXIT

say "Fetching upstream $UPSTREAM_REPO@$UPSTREAM_REF ..."
git clone --depth=1 --branch "$UPSTREAM_REF" "$UPSTREAM_REPO" "$TMPDIR/src" >/dev/null 2>&1 || die "Unable to clone upstream."
UPSTREAM_COMMIT="$(git -C "$TMPDIR/src" rev-parse --short HEAD)"
say "Upstream commit: $UPSTREAM_COMMIT"

# 1) Update .devcontainer (force-sync)
if [ -d "$TMPDIR/src/.devcontainer" ]; then
  say "Updating .devcontainer (force-sync; local changes may be overwritten)"
  if ! command -v rsync >/dev/null 2>&1; then
    die "rsync is required. Install rsync (apt-get install rsync, brew install rsync, or your package manager)."
  fi
  RSYNC_OPTS=(-a --delete)
  [ -n "$DRY_RUN" ] && RSYNC_OPTS+=(--dry-run)
  EXCLUDES=(--exclude "tmp/")
  rsync "${RSYNC_OPTS[@]}" "${EXCLUDES[@]}" "$TMPDIR/src/.devcontainer/" ".devcontainer/"
else
  warn "Upstream has no .devcontainer; skipping."
fi

# Helper: unified diff
udiff() {
  local a="$1" b="$2"
  if command -v git >/dev/null 2>&1; then
    git diff --no-index -- "$a" "$b" || true
  else
    diff -u "$a" "$b" || true
  fi
}

# 2) Update .vscode with .orig baselines
if [ -d "$TMPDIR/src/.vscode" ]; then
  say "Updating .vscode (baseline: .orig; interactive prompts)"
  # Do not create directories in dry-run
  [ -n "$DRY_RUN" ] || mkdir -p ".vscode"
  umask 022

  while IFS= read -r -d '' uf; do
    rel="${uf#"$TMPDIR/src/"}"          # ".vscode/filename"
    dest="$REPO_ROOT/$rel"
    base="${dest}.orig"                 # baseline side-by-side
    dist="${dest}.dist"                 # upstream sample when keeping local

    # Ensure parent dirs only when not dry-run
    [ -n "$DRY_RUN" ] || mkdir -p "$(dirname "$dest")"

    # New file case
    if [ ! -f "$dest" ]; then
      if [ -n "$DRY_RUN" ]; then
        say "[DRY] add $rel"
      else
        say "add $rel"
        install -m 0644 "$uf" "$dest"
        install -m 0644 "$uf" "$base"
      fi
      continue
    fi

    # Initialize baseline if missing
    if [ ! -f "$base" ]; then
      if [ -n "$DRY_RUN" ]; then
        say "[DRY] init baseline (.orig) for $rel"
      else
        install -m 0644 "$dest" "$base"
      fi
    fi

    same_local_upstream=0
    same_local_baseline=0
    same_baseline_upstream=0
    cmp -s "$dest" "$uf" && same_local_upstream=1 || true
    cmp -s "$dest" "$base" && same_local_baseline=1 || true
    cmp -s "$base" "$uf" && same_baseline_upstream=1 || true

    if [ "$same_local_upstream" -eq 1 ]; then
      say "keep (identical to upstream) $rel"
      if [ "$same_baseline_upstream" -eq 0 ]; then
        if [ -n "$DRY_RUN" ]; then
          say "[DRY] update baseline (.orig) for $rel"
        else
          install -m 0644 "$uf" "$base"
        fi
      fi
      continue
    fi

    if [ "$same_local_baseline" -eq 1 ] && [ "$same_baseline_upstream" -eq 0 ]; then
      if [ -n "$DRY_RUN" ]; then
        say "[DRY] replace (unmodified locally; upstream changed) $rel"
      else
        say "replace (unmodified locally; upstream changed) $rel"
        install -m 0644 "$uf" "$dest"
        install -m 0644 "$uf" "$base"
      fi
      continue
    fi

    # Differing states: interactive loop (view multiple diffs before choosing an action)
    if [ -n "$ASSUME_YES" ]; then
      choice="y"
    else
      say ""
      say "Config file '$rel' differs."
      while :; do
        say "  d: local vs upstream"
        say "  2: local vs baseline (.orig)"
        say "  3: baseline (.orig) vs upstream"
        say "Actions: y=replace, n=keep, b=backup+replace, u=save upstream -> .dist, m=merge, r=revert to .orig, s=skip"
        prompt "Choose [d/2/3/y/n/b/u/m/r/s]: " choice

        case "${choice:-s}" in
          d|D) say "---- local vs upstream ($rel) ----"; udiff "$dest" "$uf"; continue ;;
          2)   say "---- local vs baseline (.orig) ($rel) ----"; udiff "$dest" "$base"; continue ;;
          3)   say "---- baseline (.orig) vs upstream ($rel) ----"; udiff "$base" "$uf"; continue ;;
          y|Y)
            if [ -n "$DRY_RUN" ]; then
              say "[DRY] replace $rel"
            else
              say "replace $rel"; install -m 0644 "$uf" "$dest"; install -m 0644 "$uf" "$base"
            fi; break ;;
          n|N) say "keep local $rel"; break ;;
          b|B)
            if [ -n "$DRY_RUN" ]; then
              say "[DRY] backup+replace $rel"
            else
              bak="${dest}.bak.$(date +%Y%m%d%H%M%S)"; say "backup local to $(basename "$bak") and replace $rel"
              cp -p "$dest" "$bak"; install -m 0644 "$uf" "$dest"; install -m 0644 "$uf" "$base"
            fi; break ;;
          u|U)
            if [ -n "$DRY_RUN" ]; then
              say "[DRY] save upstream sample -> $dist"
            else
              say "save upstream sample to $dist; keeping local $rel"; install -m 0644 "$uf" "$dist"
            fi; break ;;
          m|M)
            if [ -n "$DRY_RUN" ]; then
              say "[DRY] attempt merge $rel"
            else
              say "attempt 3-way merge $rel (baseline, local, upstream)"
              merged="$(mktemp)"
              if command -v git >/dev/null 2>&1; then git merge-file -p "$dest" "$base" "$uf" > "$merged" || true
              else diff3 -m "$dest" "$base" "$uf" > "$merged" || true
              fi
              say "---- merged preview (first 60 lines) ----"; head -n 60 "$merged" || true
              prompt "Apply merged result to $rel? [y/N]: " apply
              if [ "${apply:-N}" = "y" ] || [ "${apply:-N}" = "Y" ]; then
                install -m 0644 "$merged" "$dest"; install -m 0644 "$uf" "$base"; say "merged applied"; rm -f "$merged"; break
              else say "merge discarded; leaving local unchanged"; rm -f "$merged"; continue; fi
            fi ;;
          r|R)
            if [ -n "$DRY_RUN" ]; then
              say "[DRY] revert $rel <- .orig"
            else
              bak="${dest}.bak.$(date +%Y%m%d%H%M%S)"; say "revert local to baseline; backup to $(basename "$bak")"
              cp -p "$dest" "$bak"; install -m 0644 "$base" "$dest"
            fi; break ;;
          s|S|*) say "skip $rel"; break ;;
        esac
      done
    fi
  done < <(find "$TMPDIR/src/.vscode" -type f -print0)

else
  warn "Upstream has no .vscode; skipping."
fi

# Provenance (skip in dry-run)
PROVENANCE_FILE=".devcontainer/.codespace-upstream"
if [ -n "$DRY_RUN" ]; then
  say "[DRY] would record provenance in $PROVENANCE_FILE"
else
  mkdir -p "$(dirname "$PROVENANCE_FILE")"
  {
    echo "repo=$UPSTREAM_REPO"
    echo "ref=$UPSTREAM_REF"
    echo "commit=$UPSTREAM_COMMIT"
    echo "date=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  } > "$PROVENANCE_FILE"
fi

# First-run guidance: suggest/apply .gitignore entries and offer README badge+credit insert
if [ "$FIRST_RUN" -eq 1 ]; then
  say ""
  say "First-run detected."
  say "Suggested .gitignore entries:"
  say "  .vscode/*.dist"
  say "  .vscode/*.bak.*"
  say "  # Decide whether to ignore baselines; keep them tracked if you want persistent, shared baselines"
  say "  # .vscode/*.orig"
  say "  # Devcontainer temp and var dirs"
  say "  .devcontainer/tmp/"
  say "  .devcontainer/var/"

  if [ -n "$DRY_RUN" ]; then
    say "[DRY] README badge/credit and .gitignore prompts suppressed; no changes will be made."
  else
    if prompt_yesno "Append .gitignore entries now? [Y/n]: " y; then
      ensure_gitignore_line ".vscode/*.dist"
      ensure_gitignore_line ".vscode/*.bak.*"
      ensure_gitignore_line ".devcontainer/tmp/"
      ensure_gitignore_line ".devcontainer/var/"
      say ".gitignore updated with recommended entries."
      if prompt_yesno "Also ignore baselines (*.orig)? [y/N]: " n; then
        ensure_gitignore_line ".vscode/*.orig"
        say "Added .vscode/*.orig to .gitignore."
      fi
      if prompt_yesno "Do you want to keep *.dist untracked? [Y/n]: " y; then
        say "*.dist will be ignored by git."
      else
        if [ -f ".gitignore" ]; then
          sed -i.bak '/^\.vscode\/\*\.dist$/d' ".gitignore"; rm -f ".gitignore.bak"
          say "Removed .vscode/*.dist from .gitignore (dist files will be tracked)."
        fi
      fi
    fi
    if prompt_yesno "Insert Codespaces badge and wp-plugin-codespace credit into README.md now? [Y/n]: " y; then
      insert_badge_and_credit_in_readme
    fi
  fi
fi

say ""
if [ -n "$DRY_RUN" ]; then
  say "DRY RUN complete. No files or directories were created or modified."
else
  say "Done."
  say "Provenance recorded in $PROVENANCE_FILE"
fi
say "Baselines stored as .orig next to each .vscode file; new upstream samples as .dist"
say "Tip: --yes for non-interactive replace; --dry-run to preview; --ref=stable or a tag for released versions."