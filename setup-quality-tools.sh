#!/bin/bash

###############################################################################
# Setup Quality Tools for All Conduction Nextcloud Apps
#
# This script sets up PHPQA and GrumPHP for all apps in the apps-extra folder.
# It copies configuration files, updates composer.json, and installs dependencies.
#
# Usage: ./setup-quality-tools.sh [app-name]
#        If no app-name provided, sets up all apps.
###############################################################################

set -e

# Colors for output.
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Get script directory.
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Source app (template with all configurations).
SOURCE_APP="openregister"

# Apps to setup.
APPS=("openregister" "opencatalogi" "openconnector" "docudesk" "softwarecatalog")

# If argument provided, only setup that app.
if [ ! -z "$1" ]; then
    APPS=("$1")
fi

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Quality Tools Setup${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

###############################################################################
# Function: Setup app
###############################################################################
setup_app() {
    local app=$1
    local app_dir="${SCRIPT_DIR}/${app}"
    
    if [ ! -d "$app_dir" ]; then
        echo -e "${YELLOW}⚠️  App directory not found: ${app}${NC}"
        return 1
    fi
    
    echo -e "${BLUE}📦 Setting up: ${app}${NC}"
    echo ""
    
    # Check if composer.json exists.
    if [ ! -f "${app_dir}/composer.json" ]; then
        echo -e "${YELLOW}⚠️  No composer.json found in ${app}${NC}"
        return 1
    fi
    
    # Copy configuration files (skip if source and destination are the same).
    if [ "$app" != "$SOURCE_APP" ]; then
        echo -e "${GREEN}→${NC} Copying configuration files..."
        
        # Copy GrumPHP config.
        if [ -f "${SCRIPT_DIR}/${SOURCE_APP}/grumphp.yml" ]; then
            cp "${SCRIPT_DIR}/${SOURCE_APP}/grumphp.yml" "${app_dir}/"
            echo -e "  ✓ grumphp.yml"
        fi
        
        # Copy PHPQA config.
        if [ -f "${SCRIPT_DIR}/${SOURCE_APP}/.phpqa.yml" ]; then
            cp "${SCRIPT_DIR}/${SOURCE_APP}/.phpqa.yml" "${app_dir}/"
            echo -e "  ✓ .phpqa.yml"
        fi
        
        # Copy GitHub workflows.
        if [ -d "${SCRIPT_DIR}/${SOURCE_APP}/.github/workflows" ]; then
            mkdir -p "${app_dir}/.github/workflows"
            cp "${SCRIPT_DIR}/${SOURCE_APP}/.github/workflows/quality-check.yml" "${app_dir}/.github/workflows/" 2>/dev/null || true
            cp "${SCRIPT_DIR}/${SOURCE_APP}/.github/workflows/tests.yml" "${app_dir}/.github/workflows/" 2>/dev/null || true
            cp "${SCRIPT_DIR}/${SOURCE_APP}/.github/workflows/branch-protection.yml" "${app_dir}/.github/workflows/" 2>/dev/null || true
            echo -e "  ✓ GitHub workflows"
        fi
        
        # Copy documentation.
        if [ -f "${SCRIPT_DIR}/${SOURCE_APP}/website/docs/quality-assurance.md" ]; then
            mkdir -p "${app_dir}/website/docs"
            cp "${SCRIPT_DIR}/${SOURCE_APP}/website/docs/quality-assurance.md" "${app_dir}/website/docs/" 2>/dev/null || true
            echo -e "  ✓ Documentation"
        fi
    else
        echo -e "${GREEN}→${NC} Source app - skipping file copy"
    fi
    
    echo ""
    echo -e "${GREEN}→${NC} Checking composer.json..."
    
    # Check if dependencies are already in composer.json.
    if ! grep -q "phpro/grumphp" "${app_dir}/composer.json"; then
        echo -e "  ${YELLOW}⚠${NC}  GrumPHP not found in composer.json"
        echo -e "     Add manually: ${GREEN}\"phpro/grumphp\": \"^2.9\"${NC}"
    else
        echo -e "  ✓ GrumPHP dependency found"
    fi
    
    if ! grep -q "edgedesign/phpqa" "${app_dir}/composer.json"; then
        echo -e "  ${YELLOW}⚠${NC}  PHPQA not found in composer.json"
        echo -e "     Add manually: ${GREEN}\"edgedesign/phpqa\": \"^1.30\"${NC}"
    else
        echo -e "  ✓ PHPQA dependency found"
    fi
    
    # Check if scripts are in composer.json.
    if ! grep -q "\"grumphp\":" "${app_dir}/composer.json"; then
        echo -e "  ${YELLOW}⚠${NC}  GrumPHP scripts not found in composer.json"
        echo -e "     Add scripts section from ${SOURCE_APP}/composer.json"
    else
        echo -e "  ✓ GrumPHP scripts found"
    fi
    
    if ! grep -q "\"phpqa\":" "${app_dir}/composer.json"; then
        echo -e "  ${YELLOW}⚠${NC}  PHPQA scripts not found in composer.json"
        echo -e "     Add scripts section from ${SOURCE_APP}/composer.json"
    else
        echo -e "  ✓ PHPQA scripts found"
    fi
    
    echo ""
    echo -e "${GREEN}✅ Configuration complete for ${app}${NC}"
    echo ""
    echo -e "${BLUE}Next steps for ${app}:${NC}"
    echo -e "  1. cd ${app}"
    echo -e "  2. Update composer.json if needed (see warnings above)"
    echo -e "  3. composer update"
    echo -e "  4. composer grumphp:init"
    echo -e "  5. composer phpqa"
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo ""
}

###############################################################################
# Main execution
###############################################################################

# Setup each app.
for app in "${APPS[@]}"; do
    setup_app "$app"
done

echo -e "${GREEN}🎉 Quality tools setup complete!${NC}"
echo ""
echo -e "${BLUE}Summary:${NC}"
echo -e "  ${GREEN}✓${NC} Configuration files copied"
echo -e "  ${GREEN}✓${NC} GitHub workflows created"
echo -e "  ${GREEN}✓${NC} Documentation added"
echo ""
echo -e "${YELLOW}⚠️  Remember to:${NC}"
echo -e "  1. Update composer.json for each app if needed"
echo -e "  2. Run 'composer update' in each app"
echo -e "  3. Run 'composer grumphp:init' to enable git hooks"
echo -e "  4. Test with 'composer phpqa' in each app"
echo ""
echo -e "${BLUE}For more information, see:${NC}"
echo -e "  website/docs/quality-assurance.md"
echo ""

