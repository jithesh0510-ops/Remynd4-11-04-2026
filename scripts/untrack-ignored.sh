#!/usr/bin/env bash
# Remove from Git tracking any files that are now in .gitignore.
# Files stay on disk; they are only removed from the index.
# Run from project root:  ./scripts/untrack-ignored.sh

set -e
cd "$(dirname "$0")/.."

TRACKED_IGNORED=$(git ls-files -ci --exclude-standard 2>/dev/null || true)
if [ -z "$TRACKED_IGNORED" ]; then
  echo "Nothing to do: no tracked files are currently ignored."
  exit 0
fi

echo "Removing from Git index (files stay on disk):"
echo "$TRACKED_IGNORED" | sed 's/.*/  &/'
echo "$TRACKED_IGNORED" | xargs git rm --cached

echo ""
echo "Done. Run:  git status"
git status --short
