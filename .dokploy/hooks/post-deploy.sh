#!/usr/bin/env bash
# Dokploy post-deploy hook: call the repository deploy script
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
SCRIPT="$REPO_ROOT/scripts/deploy.sh"

if [ -x "$SCRIPT" ]; then
  echo "Running repository deploy script"
  "$SCRIPT"
else
  bash "$SCRIPT"
fi
