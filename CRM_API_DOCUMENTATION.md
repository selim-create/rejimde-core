# CRM & Client Management API Endpoints

Base URL: `/wp-json/rejimde/v1`

## Authentication
All `/pro/clients` endpoints require:
- User must be logged in
- User must have `rejimde_pro` role

## Endpoints

### 1. List Clients
**GET** `/pro/clients`

**Query Parameters:**
- `status` (optional): Filter by status (active, pending, archived)
- `search` (optional): Search by client name
- `limit` (optional): Results per page (default: 50)
- `offset` (optional): Pagination offset

**Response:**
```json
{
  "status": "success",
  "data": {
    "data": [
      {
        "id": 1,
        "relationship_id": 123,
        "client": {
          "id": 456,
          "name": "Ahmet Yılmaz",
          "avatar": "https://...",
          "email": "ahmet@email.com"
        },
        "status": "active",
        "source": "marketplace",
        "started_at": "2025-01-01",
        "package": {
          "name": "10 Ders Paketi",
          "type": "session",
          "total": 10,
          "used": 3,
          "remaining": 7,
          "progress_percent": 30
        },
        "last_activity": "2025-12-25",
        "risk_status": "normal",
        "risk_reason": null,
        "score": 850,
        "created_at": "2025-01-01"
      }
    ],
    "meta": {
      "total": 42,
      "active": 35,
      "pending": 5,
      "archived": 2
    }
  }
}
```

### 2. Get Client Details
**GET** `/pro/clients/{id}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "relationship_id": 123,
    "client": {
      "id": 456,
      "name": "Ahmet Yılmaz",
      "avatar": "https://...",
      "email": "ahmet@email.com",
      "phone": "+90...",
      "birth_date": "1990-01-01",
      "gender": "male"
    },
    "status": "active",
    "agreement": {
      "start_date": "2025-01-01",
      "end_date": "2025-04-01",
      "package_name": "3 Aylık Online Danışmanlık",
      "total_sessions": 12,
      "used_sessions": 4,
      "remaining_sessions": 8,
      "price": 6000
    },
    "stats": {
      "score": 850,
      "streak": 7,
      "completed_plans": 0,
      "last_activity": "2025-12-25"
    },
    "notes": [],
    "recent_activity": [],
    "assigned_plans": []
  }
}
```

### 3. Add Client Manually
**POST** `/pro/clients`

**Request Body:**
```json
{
  "client_email": "yeni@email.com",
  "client_name": "Yeni Danışan",
  "package_name": "10 Ders Paketi",
  "package_type": "session",
  "total_sessions": 10,
  "start_date": "2025-01-01",
  "end_date": "2025-04-01",
  "price": 5000,
  "notes": "İlk görüşme notu"
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "relationship_id": 124,
    "message": "Danışan başarıyla eklendi"
  }
}
```

### 4. Create Invite Link
**POST** `/pro/clients/invite`

**Request Body:**
```json
{
  "package_name": "Online Danışmanlık",
  "package_type": "duration",
  "duration_months": 3,
  "price": 6000
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "invite_token": "abc123xyz...",
    "invite_url": "https://rejimde.com/invite/abc123xyz...",
    "expires_at": "2025-01-15"
  }
}
```

### 5. Update Client Status
**POST** `/pro/clients/{id}/status`

**Request Body:**
```json
{
  "status": "paused",
  "reason": "Tatil nedeniyle ara"
}
```

Valid statuses: `pending`, `active`, `paused`, `archived`, `blocked`

**Response:**
```json
{
  "status": "success",
  "data": {
    "message": "Durum güncellendi"
  }
}
```

### 6. Update Package
**POST** `/pro/clients/{id}/package`

**Request Body:**
```json
{
  "action": "renew",
  "package_name": "10 Ders Paketi",
  "total_sessions": 10,
  "start_date": "2025-04-01",
  "price": 5000
}
```

Valid actions: `renew`, `extend`, `cancel`

**Response:**
```json
{
  "status": "success",
  "data": {
    "message": "Paket güncellendi"
  }
}
```

### 7. Add Note
**POST** `/pro/clients/{id}/notes`

**Request Body:**
```json
{
  "type": "health",
  "content": "Gluten hassasiyeti var",
  "is_pinned": true
}
```

Valid types: `general`, `health`, `progress`, `reminder`

**Response:**
```json
{
  "status": "success",
  "data": {
    "note_id": 45,
    "message": "Not eklendi"
  }
}
```

### 8. Delete Note
**DELETE** `/pro/clients/{id}/notes/{noteId}`

**Response:**
```json
{
  "status": "success",
  "data": {
    "message": "Not silindi"
  }
}
```

### 9. Get Client Activity
**GET** `/pro/clients/{id}/activity`

**Query Parameters:**
- `limit` (optional): Number of activities to return (default: 50)

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "user_id": 456,
      "event_type": "plan_completed",
      "entity_type": "plan",
      "entity_id": 10,
      "points": 100,
      "context": {},
      "created_at": "2025-12-25 10:00:00"
    }
  ]
}
```

### 10. Get Assigned Plans
**GET** `/pro/clients/{id}/plans`

**Response:**
```json
{
  "status": "success",
  "data": []
}
```

### 11. Get My Experts (Client View)
**GET** `/me/experts`

**Authentication:** Any logged-in user

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "expert": {
        "id": 123,
        "name": "Dr. Ayşe Kaya",
        "avatar": "https://...",
        "profession": "dietitian",
        "title": "Diyetisyen"
      },
      "status": "active",
      "started_at": "2025-01-01"
    }
  ]
}
```

## Database Schema

### wp_rejimde_relationships
Stores expert-client relationships.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| expert_id | BIGINT | Expert user ID |
| client_id | BIGINT | Client user ID |
| status | ENUM | pending, active, paused, archived, blocked |
| source | ENUM | marketplace, invite, manual |
| invite_token | VARCHAR(64) | Invite token (for pending invites) |
| started_at | DATETIME | When relationship became active |
| ended_at | DATETIME | When relationship ended |
| notes | TEXT | Relationship notes |
| created_at | DATETIME | Record creation time |
| updated_at | DATETIME | Last update time |

### wp_rejimde_client_packages
Stores client package information.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| relationship_id | BIGINT | FK to relationships |
| package_name | VARCHAR(255) | Package name |
| package_type | ENUM | session, duration, unlimited |
| total_sessions | INT | Total sessions (null for unlimited) |
| used_sessions | INT | Sessions used |
| start_date | DATE | Package start date |
| end_date | DATE | Package end date |
| price | DECIMAL(10,2) | Package price |
| status | ENUM | active, completed, cancelled, expired |
| notes | TEXT | Package notes |
| created_at | DATETIME | Record creation time |
| updated_at | DATETIME | Last update time |

### wp_rejimde_client_notes
Stores client notes by experts.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| relationship_id | BIGINT | FK to relationships |
| expert_id | BIGINT | Expert user ID |
| note_type | ENUM | general, health, progress, reminder |
| content | TEXT | Note content |
| is_pinned | TINYINT(1) | Pin to top |
| created_at | DATETIME | Record creation time |
| updated_at | DATETIME | Last update time |

## Security & Permissions

1. **Expert Authentication**: All `/pro/clients` endpoints require `rejimde_pro` role
2. **Ownership Validation**: Experts can only access/modify their own clients
3. **Client Authentication**: `/me/experts` requires any authenticated user
4. **SQL Injection Prevention**: All database queries use prepared statements
5. **Input Validation**: Required fields are validated before processing

## Risk Status Calculation

Risk status is calculated based on days since last activity:
- **normal** (0-2 days): Client is active
- **warning** (3-5 days): Client hasn't logged activity in a few days
- **danger** (5+ days): Client hasn't logged in for significant time

## TODO Items

1. Implement `getAssignedPlans()` - Query from user_progress table
2. Calculate `completed_plans` stat from progress table
3. Implement invite acceptance flow (when client clicks invite link)
4. Add activity logging when expert performs actions
5. Add email notifications for invite links
6. Implement package expiration checking
