# Finance API Documentation

## Overview
The Finance API provides comprehensive income tracking, payment management, and service/package management for expert users in the Rejimde platform.

**Base URL:** `/wp-json/rejimde/v1/pro/finance`

**Authentication:** All endpoints require an authenticated expert user (user with `is_professional` meta set to true).

---

## Endpoints

### Dashboard

#### GET /pro/finance/dashboard
Get financial overview and statistics.

**Query Parameters:**
- `period` (string, optional): Period type - `this_month`, `last_month`, `this_year`, `custom`. Default: `this_month`
- `start_date` (string, optional): Start date for custom period (YYYY-MM-DD)
- `end_date` (string, optional): End date for custom period (YYYY-MM-DD)

**Response:**
```json
{
  "status": "success",
  "data": {
    "summary": {
      "total_revenue": 45000.00,
      "total_pending": 8500.00,
      "total_overdue": 2000.00,
      "paid_count": 18,
      "pending_count": 5,
      "overdue_count": 2
    },
    "monthly_comparison": {
      "current": 15000.00,
      "previous": 12000.00,
      "change_percent": 25.0
    },
    "revenue_by_service": [
      {
        "service_id": 1,
        "service_name": "Online Danışmanlık",
        "total": 25000.00,
        "count": 10
      }
    ],
    "revenue_chart": [
      { "date": "2025-12-01", "amount": 5000.00 }
    ],
    "recent_payments": [
      {
        "id": 1,
        "client": {
          "id": 456,
          "name": "Ahmet Yılmaz",
          "avatar": "https://..."
        },
        "amount": 2500.00,
        "status": "paid",
        "payment_date": "2025-12-27"
      }
    ]
  }
}
```

---

### Payments

#### GET /pro/finance/payments
Get list of payments with filters.

**Query Parameters:**
- `status` (string, optional): Filter by status - `pending`, `paid`, `overdue`, `all`. Default: `all`
- `client_id` (int, optional): Filter by client ID
- `start_date` (string, optional): Filter from date (YYYY-MM-DD)
- `end_date` (string, optional): Filter to date (YYYY-MM-DD)
- `limit` (int, optional): Number of results per page. Default: 30
- `offset` (int, optional): Offset for pagination. Default: 0

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "client": {
        "id": 456,
        "name": "Ahmet Yılmaz",
        "avatar": "https://..."
      },
      "service": {
        "id": 1,
        "name": "Online Danışmanlık"
      },
      "amount": 2500.00,
      "paid_amount": 2500.00,
      "currency": "TRY",
      "payment_method": "bank_transfer",
      "payment_date": "2025-12-27",
      "due_date": "2025-12-25",
      "status": "paid",
      "description": "Aralık ayı danışmanlık ücreti"
    }
  ],
  "meta": {
    "total": 25,
    "total_amount": 55000.00,
    "paid_amount": 45000.00,
    "pending_amount": 10000.00
  }
}
```

#### POST /pro/finance/payments
Create a new payment record.

**Request Body:**
```json
{
  "client_id": 456,
  "service_id": 1,
  "package_id": null,
  "relationship_id": 123,
  "amount": 2500.00,
  "currency": "TRY",
  "payment_method": "bank_transfer",
  "payment_date": "2025-12-27",
  "due_date": "2025-12-25",
  "status": "paid",
  "paid_amount": 2500.00,
  "description": "Aralık ayı danışmanlık ücreti",
  "notes": "Havale ile ödendi"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 123
  },
  "message": "Payment created successfully"
}
```

#### PATCH /pro/finance/payments/{id}
Update an existing payment.

**URL Parameters:**
- `id` (int, required): Payment ID

**Request Body:** (all fields optional)
```json
{
  "amount": 3000.00,
  "status": "paid",
  "payment_date": "2025-12-27",
  "notes": "Updated notes"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Payment updated successfully"
}
```

#### DELETE /pro/finance/payments/{id}
Delete a payment record.

**URL Parameters:**
- `id` (int, required): Payment ID

**Response:**
```json
{
  "status": "success",
  "message": "Payment deleted successfully"
}
```

#### POST /pro/finance/payments/{id}/mark-paid
Mark a payment as fully paid.

**URL Parameters:**
- `id` (int, required): Payment ID

**Request Body:**
```json
{
  "paid_amount": 2500.00,
  "payment_method": "cash",
  "payment_date": "2025-12-27"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Payment marked as paid"
}
```

#### POST /pro/finance/payments/{id}/partial
Record a partial payment.

**URL Parameters:**
- `id` (int, required): Payment ID

**Request Body:**
```json
{
  "amount": 1000.00,
  "payment_method": "cash"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Partial payment recorded"
}
```

---

### Services

#### GET /pro/finance/services
Get list of services/packages.

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Online Danışmanlık",
      "description": "60 dakikalık online görüşme",
      "type": "session",
      "price": 500.00,
      "currency": "TRY",
      "duration_minutes": 60,
      "session_count": null,
      "validity_days": null,
      "is_active": true,
      "is_featured": true,
      "color": "#3B82F6",
      "sort_order": 0,
      "usage_count": 45
    }
  ]
}
```

#### POST /pro/finance/services
Create a new service or package.

**Request Body:**
```json
{
  "name": "Premium Paket",
  "description": "8 seans + 7/24 destek",
  "type": "package",
  "price": 4000.00,
  "currency": "TRY",
  "duration_minutes": 60,
  "session_count": 8,
  "validity_days": 60,
  "color": "#8B5CF6",
  "is_active": true,
  "is_featured": false,
  "sort_order": 0
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 5
  },
  "message": "Service created successfully"
}
```

#### PATCH /pro/finance/services/{id}
Update a service or package.

**URL Parameters:**
- `id` (int, required): Service ID

**Request Body:** (all fields optional)
```json
{
  "name": "Updated Name",
  "price": 5000.00,
  "is_active": true
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Service updated successfully"
}
```

#### DELETE /pro/finance/services/{id}
Deactivate a service (soft delete).

**URL Parameters:**
- `id` (int, required): Service ID

**Response:**
```json
{
  "status": "success",
  "message": "Service deactivated successfully"
}
```

---

### Reports

#### GET /pro/finance/reports/monthly
Get monthly financial report.

**Query Parameters:**
- `year` (int, optional): Year. Default: current year
- `month` (int, optional): Month (1-12). Default: current month

**Response:**
```json
{
  "status": "success",
  "data": {
    "period": "2025-12",
    "summary": {
      "total_revenue": 15000.00,
      "total_pending": 3000.00,
      "total_overdue": 500.00,
      "paid_count": 12,
      "pending_count": 3,
      "overdue_count": 1,
      "total_sessions": 25,
      "unique_clients": 12,
      "average_per_client": 1250.00
    },
    "by_week": [
      { "week": 1, "revenue": 4000.00, "sessions": 8 },
      { "week": 2, "revenue": 3500.00, "sessions": 6 }
    ],
    "by_service": [
      { "service_id": 1, "service_name": "Online Danışmanlık", "total": 10000.00, "count": 20 }
    ],
    "by_payment_method": [
      { "method": "bank_transfer", "amount": 10000.00, "count": 8 },
      { "method": "cash", "amount": 5000.00, "count": 5 }
    ],
    "top_clients": [
      { "client_id": 456, "name": "Ahmet Yılmaz", "total": 3000.00 }
    ]
  }
}
```

#### GET /pro/finance/reports/yearly
Get yearly financial report.

**Query Parameters:**
- `year` (int, optional): Year. Default: current year

**Response:**
```json
{
  "status": "success",
  "data": {
    "year": 2025,
    "summary": {
      "total_revenue": 120000.00,
      "total_pending": 15000.00,
      "total_overdue": 2000.00,
      "paid_count": 150,
      "pending_count": 20,
      "overdue_count": 5
    },
    "by_month": [
      { "month": 1, "revenue": 10000.00, "count": 12 },
      { "month": 2, "revenue": 9500.00, "count": 11 }
    ],
    "by_service": [
      { "service_id": 1, "service_name": "Online Danışmanlık", "total": 80000.00, "count": 100 }
    ]
  }
}
```

#### GET /pro/finance/export
Export financial data.

**Query Parameters:**
- `format` (string, optional): Export format - `csv`, `excel`. Default: `csv`
- `type` (string, optional): Data type - `payments`, `services`, `summary`. Default: `payments`
- `start_date` (string, optional): Start date (YYYY-MM-DD)
- `end_date` (string, optional): End date (YYYY-MM-DD)

**Response:**
```json
{
  "status": "success",
  "data": [...],
  "format": "csv",
  "type": "payments"
}
```

---

## Database Tables

### wp_rejimde_services
Stores service and package definitions.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| expert_id | BIGINT | Expert user ID |
| name | VARCHAR(255) | Service/package name |
| description | TEXT | Service description |
| type | ENUM | Type: session, package, subscription, one_time |
| price | DECIMAL(10,2) | Price |
| currency | VARCHAR(3) | Currency code (e.g., TRY) |
| duration_minutes | INT | Session duration in minutes |
| session_count | INT | Number of sessions in package |
| validity_days | INT | Package validity period |
| is_active | TINYINT(1) | Active status |
| is_featured | TINYINT(1) | Featured status |
| color | VARCHAR(7) | UI color code |
| sort_order | INT | Display order |
| created_at | DATETIME | Created timestamp |
| updated_at | DATETIME | Updated timestamp |

### wp_rejimde_payments
Stores payment records.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| expert_id | BIGINT | Expert user ID |
| client_id | BIGINT | Client user ID |
| relationship_id | BIGINT | Relationship ID |
| package_id | BIGINT | Client package ID |
| service_id | BIGINT | Service ID |
| amount | DECIMAL(10,2) | Total amount |
| currency | VARCHAR(3) | Currency code |
| payment_method | ENUM | Method: cash, bank_transfer, credit_card, online, other |
| payment_date | DATE | Payment date |
| due_date | DATE | Due date |
| status | ENUM | Status: pending, paid, partial, overdue, cancelled, refunded |
| paid_amount | DECIMAL(10,2) | Amount paid |
| description | VARCHAR(500) | Payment description |
| receipt_url | VARCHAR(500) | Receipt URL |
| notes | TEXT | Additional notes |
| created_at | DATETIME | Created timestamp |
| updated_at | DATETIME | Updated timestamp |

### wp_rejimde_payment_reminders
Stores payment reminder records.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| payment_id | BIGINT | Payment ID |
| reminder_date | DATE | Reminder date |
| reminder_type | ENUM | Type: upcoming, due, overdue |
| is_sent | TINYINT(1) | Sent status |
| sent_at | DATETIME | Sent timestamp |
| created_at | DATETIME | Created timestamp |

---

## Error Responses

All endpoints return standard error responses:

```json
{
  "status": "error",
  "message": "Error description"
}
```

Common HTTP status codes:
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `404` - Not Found
- `500` - Server Error

---

## Implementation Notes

1. **Authentication**: All endpoints check for authenticated expert users using `check_expert_auth()` permission callback
2. **Ownership**: Update and delete operations verify that the expert owns the resource
3. **Soft Delete**: Services are deactivated rather than deleted to preserve historical data
4. **Automatic Status Updates**: Payments with overdue due_dates are automatically marked as overdue
5. **Partial Payments**: The system tracks both total amount and paid amount, automatically updating status when fully paid

---

## Future Enhancements

- Actual CSV/Excel export implementation
- Integration with notification system for payment reminders
- Automatic package-payment linking
- Payment gateway integration
- Receipt generation
- Multi-currency support
- Recurring payment handling
