# Pro Dashboard API Documentation

## Endpoint: GET /rejimde/v1/pro/dashboard

### Description
Returns aggregated dashboard data from all Pro modules (CRM, Inbox, Calendar, Finance) for the authenticated expert.

### Authentication
Required. User must have one of the following roles:
- `rejimde_pro` (Expert/Professional)
- `administrator`

### Request

#### HTTP Method
```
GET /wp-json/rejimde/v1/pro/dashboard
```

#### Headers
```
Authorization: Bearer {token}
```

#### Query Parameters
None

### Response

#### Success Response (200 OK)

```json
{
  "status": "success",
  "data": {
    "clients": {
      "total": 25,
      "active": 20,
      "pending": 3,
      "at_risk": 2
    },
    "inbox": {
      "unread_count": 5
    },
    "calendar": {
      "today_appointments": 3,
      "pending_requests": 2,
      "this_week_count": 12
    },
    "finance": {
      "month_revenue": 15000.00,
      "pending_payments": 3500.00,
      "overdue_payments": 1000.00
    }
  }
}
```

#### Response Fields

**clients** (object)
- `total` (integer): Total number of clients
- `active` (integer): Number of active client relationships
- `pending` (integer): Number of pending client relationships
- `at_risk` (integer): Number of clients at risk (warning or danger status)

**inbox** (object)
- `unread_count` (integer): Number of unread messages

**calendar** (object)
- `today_appointments` (integer): Number of confirmed appointments today
- `pending_requests` (integer): Number of pending appointment requests
- `this_week_count` (integer): Total appointments this week (next 7 days)

**finance** (object)
- `month_revenue` (float): Total revenue for current month
- `pending_payments` (float): Total pending payment amounts
- `overdue_payments` (float): Total overdue payment amounts

#### Error Response (401 Unauthorized)

```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do that.",
  "data": {
    "status": 401
  }
}
```

### Example Usage

#### cURL
```bash
curl -X GET \
  'https://your-site.com/wp-json/rejimde/v1/pro/dashboard' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE'
```

#### JavaScript (Fetch API)
```javascript
fetch('https://your-site.com/wp-json/rejimde/v1/pro/dashboard', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN_HERE',
    'Content-Type': 'application/json'
  }
})
.then(response => response.json())
.then(data => {
  console.log('Dashboard data:', data.data);
  console.log('Total clients:', data.data.clients.total);
  console.log('Unread messages:', data.data.inbox.unread_count);
})
.catch(error => console.error('Error:', error));
```

#### WordPress JavaScript (with wp.apiFetch)
```javascript
wp.apiFetch({
  path: '/rejimde/v1/pro/dashboard',
  method: 'GET'
})
.then(response => {
  console.log('Dashboard:', response.data);
})
.catch(error => {
  console.error('Error fetching dashboard:', error);
});
```

### Dependencies

This endpoint aggregates data from the following services:
- `ClientService::getClients()`
- `InboxService::getUnreadCount()`
- `CalendarService::getAppointments()`
- `CalendarService::getRequests()`
- `FinanceService::getDashboard()`

### Database Tables Used
- `wp_rejimde_relationships` - For client and at-risk data
- `wp_rejimde_threads` - For inbox/messaging data
- `wp_rejimde_appointments` - For calendar data
- `wp_rejimde_appointment_requests` - For pending requests
- `wp_rejimde_payments` - For finance data

### Performance Considerations
- The endpoint makes multiple database queries
- Consider implementing caching for improved performance
- Response time typically < 500ms for moderate data sets

### Version History
- **v1.0.0** (2025-12-27): Initial implementation
  - Basic dashboard aggregation
  - Support for all Pro modules
  
### Related Endpoints
- `GET /rejimde/v1/pro/clients` - Full client list
- `GET /rejimde/v1/pro/inbox` - Full inbox/messages
- `GET /rejimde/v1/pro/calendar` - Full calendar data
- `GET /rejimde/v1/pro/finance/dashboard` - Detailed finance dashboard

### Notes
- All currency amounts are returned as floats
- Dates are calculated based on server timezone
- At-risk clients are those with `risk_status` of 'warning' or 'danger'
- Week count includes appointments from today through 7 days from now
