# Expert Services Public API - Test Guide

This document provides test scenarios to validate the new public expert services endpoint and service toggle functionality.

## Prerequisites
- WordPress installation with rejimde-core plugin active
- REST API client (Postman, Insomnia, or curl)
- At least 1 test expert user with rejimde_pro role
- Some test services created in the database
- Authentication token/cookie for protected endpoints

## Base URL
```
http://your-wordpress-site.com/wp-json/rejimde/v1
```

## Test Scenarios

### 1. Get Public Expert Services (No Auth Required)

**Endpoint**: `GET /experts/{expertId}/services`

**Purpose**: Retrieve all active and public services for a specific expert

**Test Case 1**: Get services for existing expert
```bash
curl -X GET "http://your-site.com/wp-json/rejimde/v1/experts/123/services"
```

**Expected Response**: 200 OK
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Beslenme Danışmanlığı",
      "description": "Kişiselleştirilmiş beslenme programı",
      "type": "session",
      "price": 500.00,
      "currency": "TRY",
      "duration_minutes": 60,
      "session_count": null,
      "validity_days": null,
      "color": "#3B82F6",
      "is_featured": true
    },
    {
      "id": 2,
      "name": "10 Seans Paketi",
      "description": "10 seanslık beslenme programı",
      "type": "package",
      "price": 4000.00,
      "currency": "TRY",
      "duration_minutes": 60,
      "session_count": 10,
      "validity_days": 90,
      "color": "#10B981",
      "is_featured": false
    }
  ]
}
```

**Test Case 2**: Get services for non-existent expert
```bash
curl -X GET "http://your-site.com/wp-json/rejimde/v1/experts/99999/services"
```

**Expected Response**: 404 Not Found
```json
{
  "status": "error",
  "message": "Expert not found"
}
```

**Test Case 3**: Verify only active services are returned
- Create a service with `is_active = 0`
- Call the endpoint
- Verify the inactive service is NOT in the response

**Test Case 4**: Verify only public services are returned
- Create a service with `is_public = 0`
- Call the endpoint
- Verify the private service is NOT in the response

**Test Case 5**: Verify sorting order
- Create multiple services with different is_featured and sort_order values
- Call the endpoint
- Verify services are ordered by: is_featured DESC, sort_order ASC, created_at DESC

### 2. Toggle Service Active Status (Auth Required)

**Endpoint**: `PATCH /pro/finance/services/{id}/toggle`

**Purpose**: Toggle the is_active status of a service

**Test Case 1**: Toggle active service to inactive
```bash
curl -X PATCH "http://your-site.com/wp-json/rejimde/v1/pro/finance/services/1/toggle" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Cookie: wordpress_logged_in_xxx=xxx"
```

**Expected Response**: 200 OK
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "is_active": false
  },
  "message": "Service status toggled successfully"
}
```

**Test Case 2**: Toggle inactive service to active
```bash
curl -X PATCH "http://your-site.com/wp-json/rejimde/v1/pro/finance/services/1/toggle" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Expected Response**: 200 OK
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "is_active": true
  },
  "message": "Service status toggled successfully"
}
```

**Test Case 3**: Try to toggle service of another expert
```bash
curl -X PATCH "http://your-site.com/wp-json/rejimde/v1/pro/finance/services/999/toggle" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Expected Response**: 404 Not Found
```json
{
  "status": "error",
  "message": "Service not found or access denied"
}
```

**Test Case 4**: Try to toggle without authentication
```bash
curl -X PATCH "http://your-site.com/wp-json/rejimde/v1/pro/finance/services/1/toggle"
```

**Expected Response**: 401 Unauthorized or 403 Forbidden

### 3. Integration Test - Toggle and Verify Public Visibility

**Test Scenario**: Toggle a service to inactive and verify it disappears from public endpoint

1. Get initial public services list:
```bash
curl -X GET "http://your-site.com/wp-json/rejimde/v1/experts/123/services"
```
Note the number of services returned.

2. Toggle a service to inactive:
```bash
curl -X PATCH "http://your-site.com/wp-json/rejimde/v1/pro/finance/services/1/toggle" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

3. Get public services list again:
```bash
curl -X GET "http://your-site.com/wp-json/rejimde/v1/experts/123/services"
```
Verify the count is reduced by 1 and the toggled service is not in the list.

4. Toggle the service back to active:
```bash
curl -X PATCH "http://your-site.com/wp-json/rejimde/v1/pro/finance/services/1/toggle" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

5. Verify the service appears again in the public list.

## Validation Checklist

### Public Endpoint (/experts/{expertId}/services)
- [ ] Returns 200 OK for valid expert ID
- [ ] Returns 404 for non-existent expert ID
- [ ] Works without authentication (permission_callback: __return_true)
- [ ] Only returns services where is_active = 1
- [ ] Only returns services where is_public = 1 OR is_public IS NULL
- [ ] Services are ordered by is_featured DESC, sort_order ASC, created_at DESC
- [ ] Featured services appear first in the list
- [ ] All numeric fields are properly typed (int/float, not strings)
- [ ] session_count and validity_days are null when not applicable

### Toggle Endpoint (/pro/finance/services/{id}/toggle)
- [ ] Returns 200 OK on successful toggle
- [ ] Returns correct is_active status in response
- [ ] Requires authentication (check_expert_auth)
- [ ] Returns 404 when service not found
- [ ] Returns 404 when trying to toggle another expert's service
- [ ] Successfully toggles from active to inactive
- [ ] Successfully toggles from inactive to active
- [ ] Changes persist in database
- [ ] Integration: toggled service appears/disappears from public endpoint

### Data Integrity
- [ ] Type casting is correct (id: int, price: float, etc.)
- [ ] Null values are handled properly
- [ ] No SQL injection vulnerabilities
- [ ] Expert ownership is verified for protected operations
- [ ] Response format matches the specification

## Common Issues and Solutions

### Issue: 404 on all requests
**Solution**: Ensure the plugin is activated and routes are registered. Check WordPress permalink settings.

### Issue: Inactive services still appearing in public endpoint
**Solution**: Verify the WHERE clause in getPublicServices() includes `is_active = 1`

### Issue: Services not sorted correctly
**Solution**: Check the ORDER BY clause: `is_featured DESC, sort_order ASC, created_at DESC`

### Issue: Toggle endpoint returns 401/403
**Solution**: Ensure user is logged in with rejimde_pro role and has valid authentication cookie/token

### Issue: Can toggle other experts' services
**Solution**: Verify toggleServiceActive() method includes ownership check

## Success Criteria

All test scenarios should pass with:
- ✅ Correct HTTP status codes
- ✅ Expected response structures
- ✅ Proper authentication checks
- ✅ Security: ownership validation for protected operations
- ✅ Correct data filtering (active and public only)
- ✅ Proper sorting order
- ✅ Type-safe responses (proper int/float/bool types)

## Example Test Data Setup

### SQL to create test services:
```sql
-- Insert test services for expert with ID 123
INSERT INTO wp_rejimde_services (expert_id, name, description, type, price, currency, duration_minutes, is_active, is_featured, is_public, sort_order) VALUES
(123, 'Featured Active Service', 'This should appear first', 'session', 500.00, 'TRY', 60, 1, 1, 1, 0),
(123, 'Normal Active Service', 'This should appear second', 'session', 300.00, 'TRY', 45, 1, 0, 1, 1),
(123, 'Inactive Service', 'This should NOT appear', 'session', 400.00, 'TRY', 60, 0, 0, 1, 2),
(123, 'Private Service', 'This should NOT appear', 'session', 350.00, 'TRY', 30, 1, 0, 0, 3);
```

This will create:
- 1 featured active public service (should appear first)
- 1 normal active public service (should appear second)
- 1 inactive service (should NOT appear in public endpoint)
- 1 active but private service (should NOT appear in public endpoint)
