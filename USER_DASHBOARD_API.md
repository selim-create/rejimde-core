# User Dashboard API Endpoints

## Base URL
All endpoints are under `/wp-json/rejimde/v1/me`

## Authentication
All endpoints require user authentication (`is_user_logged_in()`)

## Endpoints

### Experts

#### GET `/me/experts`
List user's connected experts

**Query Parameters:**
- `status` (optional): Filter by status (all, active, pending, etc.)
- `limit` (optional): Number of results (default: 50)
- `offset` (optional): Offset for pagination (default: 0)

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "relationship_id": 1,
      "expert": {
        "id": 5,
        "name": "Dr. Ahmet Yılmaz",
        "title": "Beslenme Uzmanı",
        "avatar": "https://...",
        "profession": "dietitian"
      },
      "status": "active",
      "package": {
        "name": "3 Aylık Paket",
        "type": "session",
        "total": 12,
        "used": 4,
        "remaining": 8,
        "progress_percent": 33,
        "expiry_date": "2025-03-31"
      },
      "next_appointment": {
        "date": "2025-01-15",
        "time": "14:00",
        "title": "Online Seans"
      },
      "unread_messages": 2,
      "upcoming_appointments": 3,
      "started_at": "2024-12-01"
    }
  ]
}
```

#### GET `/me/experts/{id}`
Get detailed information about a specific expert

**Response:** Same as single item in experts list

---

### Packages

#### GET `/me/packages`
List user's active packages

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "relationship_id": 1,
      "expert": {
        "id": 5,
        "name": "Dr. Ahmet Yılmaz",
        "avatar": "https://..."
      },
      "name": "3 Aylık Paket",
      "type": "session",
      "total": 12,
      "used": 4,
      "remaining": 8,
      "progress_percent": 33,
      "start_date": "2024-12-01",
      "expiry_date": "2025-03-31",
      "status": "active",
      "price": 1500.00
    }
  ]
}
```

#### GET `/me/packages/{id}`
Get package details

**Response:** Same as single item in packages list

---

### Transactions

#### GET `/me/transactions`
List user's payment history

**Query Parameters:**
- `limit` (optional): Number of results (default: 50)
- `offset` (optional): Offset for pagination (default: 0)

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "date": "2024-12-01",
      "expert": {
        "id": 5,
        "name": "Dr. Ahmet Yılmaz"
      },
      "description": "3 Aylık Paket",
      "amount": 1500.00,
      "paid_amount": 1500.00,
      "currency": "TRY",
      "payment_method": "credit_card",
      "status": "paid"
    }
  ]
}
```

---

### Private Plans

#### GET `/me/private-plans`
List assigned private plans

**Query Parameters:**
- `type` (optional): Filter by type (diet, workout, flow, rehab, habit)
- `status` (optional): Filter by status (assigned, in_progress, completed)
- `limit` (optional): Number of results (default: 50)
- `offset` (optional): Offset for pagination (default: 0)

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "expert": {
        "id": 5,
        "name": "Dr. Ahmet Yılmaz",
        "avatar": "https://..."
      },
      "title": "Kilo Verme Diyeti",
      "type": "diet",
      "status": "in_progress",
      "plan_data": { /* JSON plan structure */ },
      "notes": "Özel notlar",
      "progress_percent": 45,
      "completed_items": ["day1_breakfast", "day1_lunch"],
      "assigned_at": "2024-12-01",
      "completed_at": null
    }
  ]
}
```

#### GET `/me/private-plans/{id}`
Get plan detail

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "expert": {
      "id": 5,
      "name": "Dr. Ahmet Yılmaz",
      "avatar": "https://...",
      "title": "Beslenme Uzmanı"
    },
    "title": "Kilo Verme Diyeti",
    "type": "diet",
    "status": "in_progress",
    "plan_data": { /* Detailed JSON plan structure */ },
    "notes": "Özel notlar",
    "progress_percent": 45,
    "completed_items": ["day1_breakfast", "day1_lunch"],
    "assigned_at": "2024-12-01",
    "completed_at": null,
    "created_at": "2024-11-28"
  }
}
```

#### POST `/me/private-plans/{id}/progress`
Update plan progress

**Request Body:**
```json
{
  "completed_items": ["day1_breakfast", "day1_lunch", "day1_dinner"],
  "progress_percent": 60
}
```

**Response:**
```json
{
  "status": "success",
  "message": "İlerleme güncellendi"
}
```

---

### Inbox

#### POST `/me/inbox/new`
Create new thread with expert

**Request Body:**
```json
{
  "expert_id": 5,
  "subject": "Soru hakkında",
  "content": "Merhaba, diyet hakkında bir sorum var..."
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "thread_id": 123
  }
}
```

---

### Dashboard Summary

#### GET `/me/dashboard-summary`
Get dashboard summary for widgets

**Response:**
```json
{
  "status": "success",
  "data": {
    "active_experts": 2,
    "upcoming_appointments": 3,
    "unread_messages": 5,
    "active_plans": 2,
    "next_appointment": {
      "id": 45,
      "title": "Online Seans",
      "expert_name": "Dr. Ahmet Yılmaz",
      "date": "2025-01-15",
      "time": "14:00",
      "type": "online",
      "meeting_link": "https://..."
    }
  }
}
```

---

### Invites

#### POST `/me/accept-invite`
Accept expert invite

**Request Body:**
```json
{
  "token": "abc123def456..."
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "success": true,
    "relationship_id": 10,
    "expert": {
      "id": 5,
      "name": "Dr. Ahmet Yılmaz",
      "avatar": "https://..."
    }
  }
}
```

**Error Response:**
```json
{
  "status": "error",
  "message": "Geçersiz veya süresi dolmuş davet linki"
}
```

---

## Error Responses

All endpoints use standard error format:

```json
{
  "status": "error",
  "message": "Hata açıklaması"
}
```

Common HTTP status codes:
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `403` - Forbidden
- `404` - Not Found
- `500` - Internal Server Error
