#!/bin/bash
#
# Full Cycle Test - Agent creates menu, verify in API
#

echo "========================================"
echo "Full Cycle: Agent → Menu → API"
echo "========================================"
echo ""

# Step 1: Check current menus
echo "Step 1: Checking current menus..."
CURRENT_MENUS=$(curl -s http://localhost:3000/api/apps/opencatalogi/api/menus?_source=database 2>&1 | python3 -c "import sys, json; data=json.load(sys.stdin); menus = [m for m in data.get('results', []) if 'title' in m]; print(len(menus)); [print(f'  - {m[\"title\"]}') for m in menus]" 2>/dev/null)
echo "$CURRENT_MENUS"
echo ""

# Step 2: Ask agent to create a menu
echo "Step 2: Asking agent to create 'Full Cycle Test Menu'..."
AGENT_RESPONSE=$(timeout 120 docker exec -u 33 master-nextcloud-1 curl -s -X POST \
  -H 'Content-Type: application/json' \
  -u 'admin:admin' \
  -d '{"agentUuid": "0258cdd2-fe15-4ce6-bbbd-9153142ca5e3", "message": "Create a menu called Full Cycle Test Menu with position 777"}' \
  http://localhost/index.php/apps/openregister/api/chat/send 2>&1)

echo "Agent responded (truncated):"
echo "$AGENT_RESPONSE" | python3 -m json.tool 2>/dev/null | grep -A 5 "content" | head -10
echo ""

# Step 3: Wait and check for new menu
echo "Step 3: Checking if menu was created (waiting 3 seconds)..."
sleep 3

NEW_MENUS=$(curl -s http://localhost:3000/api/apps/opencatalogi/api/menus?_source=database 2>&1 | python3 -c "import sys, json; data=json.load(sys.stdin); menus = [m for m in data.get('results', []) if 'title' in m]; print(len(menus)); [print(f'  - {m[\"title\"]} (position: {m.get(\"position\", \"N/A\")})') for m in menus]" 2>/dev/null)
echo "$NEW_MENUS"
echo ""

# Step 4: Check database directly
echo "Step 4: Checking database for 'Full Cycle' menu..."
DB_CHECK=$(docker exec master-database-mysql-1 mysql -u nextcloud -pnextcloud nextcloud -N -e "SELECT JSON_EXTRACT(object, '$.title') FROM oc_openregister_objects WHERE schema=7 AND JSON_EXTRACT(object, '$.title') LIKE '%Full Cycle%';" 2>/dev/null)

if [ -n "$DB_CHECK" ]; then
    echo "✅ SUCCESS! Menu found in database:"
    echo "$DB_CHECK"
else
    echo "⚠️  Menu not found in database"
    echo ""
    echo "Checking agent logs for tool execution..."
    docker logs master-nextcloud-1 2>&1 | grep -A 5 "cms_create_menu\|Menu created" | tail -20
fi

echo ""
echo "========================================"
echo "Test Complete"
echo "========================================"


