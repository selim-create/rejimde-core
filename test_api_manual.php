#!/usr/bin/env php
<?php
/**
 * Manual API Testing Guide
 * 
 * This script can be run as a standalone PHP script to generate testing documentation.
 * Usage: php test_api_manual.php
 * 
 * Note: Actual API testing requires a live WordPress installation with the plugin active.
 */

echo "=== API Manual Testing Guide ===\n\n";

echo "1. EXPERTS API - Pagination Tests:\n";
echo "   Base URL: /wp-json/rejimde/v1/professionals\n\n";

echo "   Test Case 1: Default pagination (24 per page, page 1)\n";
echo "   Command: GET /wp-json/rejimde/v1/professionals\n";
echo "   Expected: Response with 'data' array and 'pagination' object\n";
echo "   Expected pagination: {per_page: 24, current_page: 1, total: X, total_pages: Y}\n\n";

echo "   Test Case 2: Custom per_page parameter\n";
echo "   Command: GET /wp-json/rejimde/v1/professionals?per_page=10\n";
echo "   Expected: Response with max 10 experts in 'data' array\n";
echo "   Expected pagination: {per_page: 10, current_page: 1, ...}\n\n";

echo "   Test Case 3: Custom page parameter\n";
echo "   Command: GET /wp-json/rejimde/v1/professionals?page=2\n";
echo "   Expected: Second page of results (items 25-48)\n";
echo "   Expected pagination: {per_page: 24, current_page: 2, ...}\n\n";

echo "   Test Case 4: Both parameters together\n";
echo "   Command: GET /wp-json/rejimde/v1/professionals?per_page=5&page=3\n";
echo "   Expected: 5 experts from page 3 (items 11-15)\n";
echo "   Expected pagination: {per_page: 5, current_page: 3, ...}\n\n";

echo "   Test Case 5: Maximum limit enforcement\n";
echo "   Command: GET /wp-json/rejimde/v1/professionals?per_page=200\n";
echo "   Expected: Max 100 experts (enforced limit)\n";
echo "   Expected pagination: {per_page: 100, ...}\n\n";

echo "   Test Case 6: With type filter\n";
echo "   Command: GET /wp-json/rejimde/v1/professionals?type=dietitian&per_page=10\n";
echo "   Expected: Max 10 dietitian experts\n\n";

echo "   Test Case 7: Verify sorting is preserved\n";
echo "   Command: GET /wp-json/rejimde/v1/professionals?per_page=50\n";
echo "   Expected: Results sorted by: 1) is_claimed, 2) is_featured, 3) is_verified, 4) reji_score\n\n";

echo "2. DICTIONARY API - Update Tests:\n";
echo "   Base URL: /wp-json/rejimde/v1/dictionary/{id}\n\n";

echo "   Test Case 1: Update with POST method (existing)\n";
echo "   Command: POST /wp-json/rejimde/v1/dictionary/123\n";
echo "   Body: {\"title\": \"Updated Title\", \"content\": \"Updated content\"}\n";
echo "   Headers: Authorization: Bearer <token>\n";
echo "   Expected: 200 OK with success message\n\n";

echo "   Test Case 2: Update with PUT method (new)\n";
echo "   Command: PUT /wp-json/rejimde/v1/dictionary/123\n";
echo "   Body: {\"title\": \"Updated Title\", \"content\": \"Updated content\"}\n";
echo "   Headers: Authorization: Bearer <token>\n";
echo "   Expected: 200 OK with success message\n\n";

echo "   Test Case 3: Update with PATCH method (new)\n";
echo "   Command: PATCH /wp-json/rejimde/v1/dictionary/123\n";
echo "   Body: {\"title\": \"Updated Title\"}\n";
echo "   Headers: Authorization: Bearer <token>\n";
echo "   Expected: 200 OK with success message\n\n";

echo "   Test Case 4: Permission check - author can update\n";
echo "   Command: POST /wp-json/rejimde/v1/dictionary/123\n";
echo "   Headers: Authorization: Bearer <author_token>\n";
echo "   Expected: 200 OK if user is author\n\n";

echo "   Test Case 5: Permission check - non-author cannot update\n";
echo "   Command: POST /wp-json/rejimde/v1/dictionary/123\n";
echo "   Headers: Authorization: Bearer <different_user_token>\n";
echo "   Expected: 403 Forbidden\n\n";

echo "   Test Case 6: Permission check - admin can update\n";
echo "   Command: POST /wp-json/rejimde/v1/dictionary/123\n";
echo "   Headers: Authorization: Bearer <admin_token>\n";
echo "   Expected: 200 OK (admin override)\n\n";

echo "3. CURL EXAMPLES:\n\n";

echo "   # Test Experts API pagination:\n";
echo "   curl -X GET 'http://localhost/wp-json/rejimde/v1/professionals?per_page=10&page=1'\n\n";

echo "   # Test Dictionary PUT:\n";
echo "   curl -X PUT 'http://localhost/wp-json/rejimde/v1/dictionary/123' \\\n";
echo "     -H 'Content-Type: application/json' \\\n";
echo "     -H 'Authorization: Bearer YOUR_TOKEN' \\\n";
echo "     -d '{\"title\": \"Updated Title\"}'\n\n";

echo "   # Test Dictionary PATCH:\n";
echo "   curl -X PATCH 'http://localhost/wp-json/rejimde/v1/dictionary/123' \\\n";
echo "     -H 'Content-Type: application/json' \\\n";
echo "     -H 'Authorization: Bearer YOUR_TOKEN' \\\n";
echo "     -d '{\"excerpt\": \"Updated excerpt\"}'\n\n";

echo "4. EXPECTED RESPONSE STRUCTURE:\n\n";

echo "   Experts API Response:\n";
echo "   {\n";
echo "     \"data\": [\n";
echo "       {\n";
echo "         \"id\": 123,\n";
echo "         \"name\": \"Expert Name\",\n";
echo "         \"profession\": \"dietitian\",\n";
echo "         \"is_claimed\": true,\n";
echo "         \"is_featured\": false,\n";
echo "         \"reji_score\": 85,\n";
echo "         ...\n";
echo "       }\n";
echo "     ],\n";
echo "     \"pagination\": {\n";
echo "       \"total\": 1472,\n";
echo "       \"per_page\": 24,\n";
echo "       \"current_page\": 1,\n";
echo "       \"total_pages\": 62\n";
echo "     }\n";
echo "   }\n\n";

echo "   Dictionary Update Response:\n";
echo "   {\n";
echo "     \"success\": true,\n";
echo "     \"id\": 123,\n";
echo "     \"message\": \"Terim güncellendi.\"\n";
echo "   }\n\n";

echo "✅ Testing guide generated successfully!\n";
echo "Note: Actual API testing requires a WordPress installation with the plugin active.\n";
