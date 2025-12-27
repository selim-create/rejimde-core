# CRM API Quick Reference

## Base URL
`/wp-json/rejimde/v1`

## Quick Endpoints Reference

### Expert Endpoints (requires `rejimde_pro` role)

```bash
# List all clients (with optional filters)
GET /pro/clients?status=active&search=name&limit=50&offset=0

# Get client details
GET /pro/clients/{id}

# Add new client manually
POST /pro/clients
{
  "client_email": "client@email.com",
  "client_name": "Client Name",
  "package_name": "10 Sessions",
  "package_type": "session",
  "total_sessions": 10,
  "start_date": "2025-01-01",
  "price": 5000
}

# Create invite link
POST /pro/clients/invite
{
  "package_name": "Online Consultation",
  "package_type": "duration",
  "duration_months": 3,
  "price": 6000
}

# Update client status
POST /pro/clients/{id}/status
{
  "status": "active",  // pending|active|paused|archived|blocked
  "reason": "Optional reason"
}

# Update/renew package
POST /pro/clients/{id}/package
{
  "action": "renew",  // renew|extend|cancel
  "package_name": "10 Sessions",
  "total_sessions": 10,
  "start_date": "2025-04-01",
  "price": 5000
}

# Add note
POST /pro/clients/{id}/notes
{
  "type": "health",  // general|health|progress|reminder
  "content": "Note content",
  "is_pinned": false
}

# Delete note
DELETE /pro/clients/{id}/notes/{noteId}

# Get client activity
GET /pro/clients/{id}/activity?limit=50

# Get assigned plans
GET /pro/clients/{id}/plans
```

### Client Endpoints (requires authentication)

```bash
# Get my experts
GET /me/experts
```

## Response Format

### Success Response
```json
{
  "status": "success",
  "data": { ... }
}
```

### Error Response
```json
{
  "status": "error",
  "message": "Error message"
}
```

## Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `403` - Forbidden (no permission)
- `404` - Not Found
- `500` - Server Error

## Risk Status Values

- `normal` - Active client (0-2 days since last activity)
- `warning` - Inactivity warning (3-5 days)
- `danger` - Significant inactivity (5+ days)

## Package Types

- `session` - Session-based (X sessions)
- `duration` - Time-based (X months)
- `unlimited` - Unlimited access

## Relationship Statuses

- `pending` - Awaiting activation (from invite)
- `active` - Active relationship
- `paused` - Temporarily paused
- `archived` - Ended relationship
- `blocked` - Blocked client

## Note Types

- `general` - General notes
- `health` - Health-related notes
- `progress` - Progress notes
- `reminder` - Reminders

## Database Tables

```sql
-- Expert-Client Relationships
wp_rejimde_relationships

-- Client Packages
wp_rejimde_client_packages

-- Expert Notes
wp_rejimde_client_notes
```

## Service Methods

```php
use Rejimde\Services\ClientService;

$service = new ClientService();

// List clients
$result = $service->getClients($expertId, [
    'status' => 'active',
    'search' => 'name',
    'limit' => 50,
    'offset' => 0
]);

// Get client
$client = $service->getClient($expertId, $relationshipId);

// Add client
$relationshipId = $service->addClient($expertId, $data);

// Create invite
$invite = $service->createInvite($expertId, $data);

// Update status
$service->updateStatus($relationshipId, 'active', 'reason');

// Update package
$service->updatePackage($relationshipId, $data);

// Add note
$noteId = $service->addNote($relationshipId, $data);

// Delete note
$service->deleteNote($noteId, $expertId);

// Calculate risk
$risk = $service->calculateRiskStatus($clientId);

// Get activity
$activity = $service->getClientActivity($clientId, 50);

// Get plans
$plans = $service->getAssignedPlans($relationshipId);

// Get client's experts
$experts = $service->getClientExperts($clientId);
```

## Testing with cURL

```bash
# List clients
curl -X GET "http://localhost/wp-json/rejimde/v1/pro/clients" \
  -H "Cookie: wordpress_logged_in_xxx=..."

# Add client
curl -X POST "http://localhost/wp-json/rejimde/v1/pro/clients" \
  -H "Content-Type: application/json" \
  -H "Cookie: wordpress_logged_in_xxx=..." \
  -d '{
    "client_email": "test@email.com",
    "client_name": "Test Client",
    "package_name": "Test Package",
    "package_type": "session",
    "total_sessions": 10,
    "start_date": "2025-01-01",
    "price": 5000
  }'

# Update status
curl -X POST "http://localhost/wp-json/rejimde/v1/pro/clients/1/status" \
  -H "Content-Type: application/json" \
  -H "Cookie: wordpress_logged_in_xxx=..." \
  -d '{
    "status": "active",
    "reason": "Starting consultation"
  }'

# Add note
curl -X POST "http://localhost/wp-json/rejimde/v1/pro/clients/1/notes" \
  -H "Content-Type: application/json" \
  -H "Cookie: wordpress_logged_in_xxx=..." \
  -d '{
    "type": "health",
    "content": "Client has gluten sensitivity",
    "is_pinned": true
  }'
```

## Common Use Cases

### 1. Expert adds new client manually
```
POST /pro/clients → Creates relationship & package
```

### 2. Expert creates invite link
```
POST /pro/clients/invite → Returns invite URL
Client clicks link → Registers → Relationship activated
```

### 3. Expert views client dashboard
```
GET /pro/clients → List all clients with risk status
GET /pro/clients/{id} → Get detailed client info
GET /pro/clients/{id}/activity → View recent activity
```

### 4. Expert manages client
```
POST /pro/clients/{id}/status → Pause/activate
POST /pro/clients/{id}/package → Renew package
POST /pro/clients/{id}/notes → Add notes
```

### 5. Client views their experts
```
GET /me/experts → List all assigned experts
```

## See Also

- `CRM_API_DOCUMENTATION.md` - Full API documentation
- `IMPLEMENTATION_SUMMARY_CRM.md` - Implementation details
