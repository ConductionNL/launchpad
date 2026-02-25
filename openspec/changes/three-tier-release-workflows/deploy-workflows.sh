#!/bin/bash
# Deploy three-tier release workflows to a GitHub repo
# Usage: ./deploy-workflows.sh <org> <repo> [--no-frontend] [--skip-dev-release] [--skip-beta-release]

set -e

ORG="$1"
REPO="$2"
shift 2

NO_FRONTEND=false
SKIP_DEV_RELEASE=false
SKIP_BETA_RELEASE=false

for arg in "$@"; do
  case $arg in
    --no-frontend) NO_FRONTEND=true ;;
    --skip-dev-release) SKIP_DEV_RELEASE=true ;;
    --skip-beta-release) SKIP_BETA_RELEASE=true ;;
  esac
done

TEMPLATES_DIR="$(dirname "$0")/templates"
echo "=== Deploying workflows to $ORG/$REPO ==="

# Step 1: Create missing branches
MAIN_SHA=$(gh api repos/$ORG/$REPO/git/ref/heads/main --jq '.object.sha')
echo "Main SHA: $MAIN_SHA"

if [ "$SKIP_DEV_RELEASE" = false ]; then
  echo "Creating development-release branch..."
  gh api repos/$ORG/$REPO/git/refs -f "ref=refs/heads/development-release" -f "sha=$MAIN_SHA" 2>/dev/null && echo "  Created" || echo "  Already exists"
fi

if [ "$SKIP_BETA_RELEASE" = false ]; then
  echo "Creating beta-release branch..."
  gh api repos/$ORG/$REPO/git/refs -f "ref=refs/heads/beta-release" -f "sha=$MAIN_SHA" 2>/dev/null && echo "  Created" || echo "  Already exists"
fi

# Step 2: Push workflow files to development branch
echo "Pushing workflow files to development branch..."
DEV_SHA=$(gh api repos/$ORG/$REPO/git/ref/heads/development --jq '.object.sha')
TREE_SHA=$(gh api repos/$ORG/$REPO/git/commits/$DEV_SHA --jq '.tree.sha')

# Create blobs and build tree entries
TREE_ENTRIES="["
for file in sync-dev.yaml sync-beta.yaml release-unstable.yaml release-beta.yaml release-stable.yaml pr-check.yaml; do
  SRC_FILE="$TEMPLATES_DIR/$file"

  # For no-frontend apps, remove npm steps from release workflows
  if [ "$NO_FRONTEND" = true ] && [[ "$file" == release-* ]]; then
    CONTENT=$(sed '/- run: npm ci/d; /- run: npm run build/d; /Install npm dependencies/,/node-version/d' "$SRC_FILE" | base64 -w 0)
  else
    CONTENT=$(cat "$SRC_FILE" | base64 -w 0)
  fi

  BLOB_SHA=$(gh api repos/$ORG/$REPO/git/blobs -f "content=$CONTENT" -f "encoding=base64" --jq '.sha')
  TREE_ENTRIES="$TREE_ENTRIES{\"path\":\".github/workflows/$file\",\"mode\":\"100644\",\"type\":\"blob\",\"sha\":\"$BLOB_SHA\"},"
done
TREE_ENTRIES="${TREE_ENTRIES%,}]"

# Create tree
NEW_TREE_SHA=$(echo "{\"base_tree\":\"$TREE_SHA\",\"tree\":$TREE_ENTRIES}" | gh api repos/$ORG/$REPO/git/trees --input - --jq '.sha')

# Create commit
COMMIT_SHA=$(gh api repos/$ORG/$REPO/git/commits \
  -f "message=Add three-tier release workflow files [skip ci]" \
  -f "tree=$NEW_TREE_SHA" \
  -f "parents[]=$DEV_SHA" \
  --jq '.sha')

# Update development ref
gh api repos/$ORG/$REPO/git/refs/heads/development -X PATCH -f "sha=$COMMIT_SHA" --jq '.ref'

echo "=== $REPO complete ==="
echo ""
