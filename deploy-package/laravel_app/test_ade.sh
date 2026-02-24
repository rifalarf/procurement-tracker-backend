#!/bin/bash
JSON_ADE=$(curl -s -X POST http://localhost:8000/api/auth/login -H "Content-Type: application/json" -d '{"username":"3042327","password":"Password@!"}')
TOKEN_ADE=$(echo "$JSON_ADE" | jq -r '.token')

if [ "$TOKEN_ADE" != "null" ] && [ -n "$TOKEN_ADE" ]; then
    echo "1. ADE (only_mine=true)"
    curl -s -X GET "http://localhost:8000/api/procurement-items?per_page=100&only_mine=true" -H "Authorization: Bearer $TOKEN_ADE" | jq '.data | map({no_pr, nama_barang, department: .department.name, buyer: (if .buyer then .buyer.name else "UNASSIGNED" end)})'
    
    echo "2. ADE (tanpa only_mine)"
    curl -s -X GET "http://localhost:8000/api/procurement-items?per_page=100" -H "Authorization: Bearer $TOKEN_ADE" | jq '.data | map({no_pr, department: .department.name, buyer: (if .buyer then .buyer.name else "UNASSIGNED" end)})'
else
    echo "Gagal login Ade"
fi
