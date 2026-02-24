#!/bin/bash
# Script untuk memverifikasi logika akses

echo "====================================="
echo "MEMVERIFIKASI LOGIKA AKSES PROCUREMENT"
echo "====================================="

# Login sebagai Buyer PBJ1
JSON_BUYER=$(curl -s -X POST http://localhost:8000/api/auth/login -H "Content-Type: application/json" -d '{"username":"3082563","password":"Password@!"}')
TOKEN_BUYER=$(echo "$JSON_BUYER" | jq -r '.token')

if [ "$TOKEN_BUYER" != "null" ] && [ -n "$TOKEN_BUYER" ]; then
    echo -e "\n1. BUYER PBJ1 (only_mine=true)"
    echo "Expected: Hanya melihat item dari departemennya yang di-assign/unassigned"
    curl -s -X GET "http://localhost:8000/api/procurement-items?per_page=100&only_mine=true" -H "Authorization: Bearer $TOKEN_BUYER" | jq '.data | map({no_pr, nama_barang, department: .department.name, buyer: (if .buyer then .buyer.name else "UNASSIGNED" end)})'
    
    echo -e "\n2. BUYER PBJ1 (tanpa only_mine)"
    echo "Expected: Melihat semua item lintas departemen"
    curl -s -X GET "http://localhost:8000/api/procurement-items?per_page=100" -H "Authorization: Bearer $TOKEN_BUYER" | jq '.data | map({no_pr, department: .department.name}) | unique_by(.department)'
else
    echo "Gagal login Buyer"
fi

# Login sebagai AVP PBJ1
JSON_AVP=$(curl -s -X POST http://localhost:8000/api/auth/login -H "Content-Type: application/json" -d '{"username":"3942055","password":"Password@!"}')
TOKEN_AVP=$(echo "$JSON_AVP" | jq -r '.token')

if [ "$TOKEN_AVP" != "null" ] && [ -n "$TOKEN_AVP" ]; then
    echo -e "\n3. AVP PBJ1 (tanpa filter)"
    echo "Expected: Melihat semua item lintas departemen (karena filter default departemen diurus frontend)"
    curl -s -X GET "http://localhost:8000/api/procurement-items?per_page=100" -H "Authorization: Bearer $TOKEN_AVP" | jq '.data | map({no_pr, department: .department.name}) | unique_by(.department)'
fi

# Login sebagai Admin
JSON_ADMIN=$(curl -s -X POST http://localhost:8000/api/auth/login -H "Content-Type: application/json" -d '{"username":"admin","password":"Password@!"}')
TOKEN_ADMIN=$(echo "$JSON_ADMIN" | jq -r '.token')

if [ "$TOKEN_ADMIN" != "null" ] && [ -n "$TOKEN_ADMIN" ]; then
    echo -e "\n4. ADMIN"
    echo "Expected: Melihat semua item"
    curl -s -X GET "http://localhost:8000/api/procurement-items?per_page=100" -H "Authorization: Bearer $TOKEN_ADMIN" | jq '.data | map({no_pr, department: .department.name}) | unique_by(.department)'
fi
echo "====================================="
