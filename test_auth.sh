#!/bin/bash
# Test token extraction properly
JSON_RESPONSE=$(curl -s -X POST http://localhost:8000/api/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"username":"3082563","password":"Password@!"}')
TOKEN=$(echo "$JSON_RESPONSE" | jq -r '.token')

if [ "$TOKEN" != "null" ] && [ -n "$TOKEN" ]; then
    echo "Buyer PBJ1 Items (Default: only_mine=true):"
    curl -s -X GET "http://localhost:8000/api/procurement-items?per_page=100&only_mine=true" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" | jq '.data | map({no_pr, department_name: .department.name}) | unique_by(.department_name)'
    
    echo "Buyer PBJ1 Items (Filter Cleared: only_mine=false):"
    curl -s -X GET "http://localhost:8000/api/procurement-items?per_page=100" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" | jq '.data | map({no_pr, department_name: .department.name}) | unique_by(.department_name)'
fi

JSON_RESPONSE_AVP=$(curl -s -X POST http://localhost:8000/api/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"username":"3942055","password":"Password@!"}')
TOKEN_AVP=$(echo "$JSON_RESPONSE_AVP" | jq -r '.token')

if [ "$TOKEN_AVP" != "null" ] && [ -n "$TOKEN_AVP" ]; then
    echo "AVP PBJ1 Items (Default: no department param but sees all without restriction due to our change, frontend handles dept filter):"
    curl -s -X GET "http://localhost:8000/api/procurement-items?per_page=100" -H "Authorization: Bearer $TOKEN_AVP" -H "Accept: application/json" | jq '.data | map({no_pr, department_name: .department.name}) | unique_by(.department_name)'
fi
