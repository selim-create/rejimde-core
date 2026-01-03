# Service Request API Documentation

## Overview
The Service Request API allows users to request services/packages from experts, and experts to manage these requests through their dashboard.

## Table of Contents
- [Professional Endpoint Enhancements](#professional-endpoint-enhancements)
- [Service Request Endpoints](#service-request-endpoints)
- [Database Schema](#database-schema)
- [Testing](#testing)

---

## Professional Endpoint Enhancements

### GET `/rejimde/v1/professionals/{slug}`

Get detailed information about a professional expert.

**New Fields Added:**
- `followers_count` (int): Total number of followers
- `following_count` (int): Total number of users the expert follows
- `is_following` (boolean): Whether current logged-in user follows this expert
- `client_count` (int): Number of active clients (dynamically calculated from relationships table)

**Example Response:**
```json
{
  "id": 123,
  "user_id": 456,
  "name": "Dr. Ahmet Yılmaz",
  "slug": "dr-ahmet-yilmaz",
  "profession": "dietitian",
  "title": "Uzm. Dyt.",
  "bio": "10 yıllık tecrübe...",
  "followers_count": 1250,
  "following_count": 45,
  "is_following": false,
  "client_count": 32,
  "reji_score": 85,
  "rating": "4.8",
  ...
}
```

---

## Service Request Endpoints

### 1. Create Service Request

**Endpoint:** `POST /rejimde/v1/service-requests`

**Authentication:** Required (logged-in user)

**Description:** Allows any user to request a service/package from an expert.

**Request Body:**
```json
{
  "expert_id": 123,
  "service_id": 456,
  "message": "Paketinizi almak istiyorum. 3 aylık diyet programı için destek istiyorum.",
  "contact_preference": "message"
}
```

**Parameters:**
- `expert_id` (required, int): Expert's user ID
- `service_id` (optional, int): Specific service/package ID
- `message` (optional, string): Message to expert
- `contact_preference` (optional, string): "message" | "video" | "phone" (default: "message")

**Response (Success - 200):**
```json
{
  "status": "success",
  "data": {
    "request_id": 789,
    "status": "pending",
    "message": "Talebiniz uzmana iletildi."
  }
}
```

**Response (Error - 400):**
```json
{
  "code": "request_failed",
  "message": "Bu uzmanla zaten aktif bir ilişkiniz mevcut",
  "data": {
    "status": 400
  }
}
```

**Business Rules:**
- User cannot request if they already have an active relationship with the expert
- User cannot have multiple pending requests to the same expert
- Expert must have `rejimde_pro` role

---

### 2. Get Expert's Service Requests

**Endpoint:** `GET /rejimde/v1/me/service-requests`

**Authentication:** Required (expert with `rejimde_pro` role)

**Description:** Get all service requests received by the current expert.

**Query Parameters:**
- `status` (optional, string): Filter by status - "pending" | "approved" | "rejected"
- `page` (optional, int): Page number for pagination (default: 1)
- `per_page` (optional, int): Results per page (default: 50, max: 100)

**Example Request:**
```
GET /wp-json/rejimde/v1/me/service-requests?status=pending&page=1&per_page=20
```

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 789,
      "user": {
        "id": 100,
        "name": "Ahmet Yılmaz",
        "avatar": "https://example.com/avatar.jpg",
        "email": "ahmet@example.com"
      },
      "service": {
        "id": 456,
        "name": "Online Beslenme Danışmanlığı",
        "price": 2500,
        "type": "session",
        "duration_minutes": 60,
        "session_count": 8
      },
      "message": "Paketinizi almak istiyorum",
      "contact_preference": "message",
      "status": "pending",
      "expert_response": null,
      "created_at": "2026-01-03T10:00:00",
      "updated_at": "2026-01-03T10:00:00"
    }
  ],
  "meta": {
    "pending": 5,
    "approved": 12,
    "rejected": 3,
    "total": 20
  }
}
```

---

### 3. Respond to Service Request

**Endpoint:** `POST /rejimde/v1/service-requests/{id}/respond`

**Authentication:** Required (expert with `rejimde_pro` role)

**Description:** Approve or reject a service request.

**URL Parameters:**
- `id` (required, int): Request ID

**Request Body:**
```json
{
  "action": "approve",
  "response_message": "Hoş geldiniz! Planınızı hazırlıyorum.",
  "assign_package": true
}
```

**Parameters:**
- `action` (required, string): "approve" | "reject"
- `response_message` (optional, string): Response message to user
- `assign_package` (optional, boolean): For "approve" action - automatically assign the requested package (default: false)

**Response (Success - Approve - 200):**
```json
{
  "status": "success",
  "data": {
    "status": "approved",
    "client_id": 200,
    "message": "Danışan eklendi ve paket atandı."
  }
}
```

**Response (Success - Reject - 200):**
```json
{
  "status": "success",
  "data": {
    "status": "rejected",
    "message": "Talep reddedildi"
  }
}
```

**Response (Error - 400):**
```json
{
  "code": "respond_failed",
  "message": "Bu talep zaten yanıtlanmış",
  "data": {
    "status": 400
  }
}
```

**Business Logic:**

**On Approve:**
1. Checks if relationship already exists with expert
2. If exists and archived/paused: Reactivates the relationship
3. If doesn't exist: Creates new relationship in `rejimde_relationships` table
4. If `assign_package` is true: Creates package entry in `rejimde_client_packages` based on service details
5. Updates request status to "approved"
6. Links the created relationship ID to the request
7. TODO: Sends notification to user

**On Reject:**
1. Updates request status to "rejected"
2. Saves expert's response message
3. TODO: Sends notification to user

---

## Database Schema

### `rejimde_service_requests` Table

```sql
CREATE TABLE rejimde_service_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    expert_id BIGINT UNSIGNED NOT NULL COMMENT 'Uzmanın user_id si',
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'Talep eden kullanıcı',
    service_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'İlgili hizmet paketi',
    message TEXT DEFAULT NULL,
    contact_preference VARCHAR(50) DEFAULT 'message' COMMENT 'message, video, phone',
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    expert_response TEXT DEFAULT NULL COMMENT 'Uzmanın yanıtı',
    created_relationship_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'Onaylandığında oluşturulan relationship ID',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_expert_status (expert_id, status),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);
```

**Indexes:**
- `idx_expert_status`: Optimizes queries for expert's dashboard filtering by status
- `idx_user`: Optimizes queries for user's request history
- `idx_status`: General status filtering
- `idx_created_at`: Time-based queries

---

## Integration with Existing Systems

### Professional Follow System

The existing follow system at `/rejimde/v1/profile/{userId}/follow` already works for professionals. It:
- Toggles follow/unfollow status
- Updates `rejimde_followers` and `rejimde_following` user meta
- Dispatches `follow_accepted` event
- Returns updated follower counts

### Client Relationship System

When a service request is approved:
1. Entry created in `rejimde_relationships` table
2. Status set to "active"
3. Source set to "marketplace" (indicating it came from service request)
4. Expert can manage this client in their CRM dashboard

### Package Assignment

If `assign_package` is true on approval:
1. Service details are fetched from `rejimde_services` table
2. Package created in `rejimde_client_packages` table
3. Package linked to the relationship
4. Expert can track package usage in dashboard

---

## Testing

Run the validation script:

```bash
./validate_service_requests.php
```

Expected output: All checks should pass (✓)

### Manual Testing Checklist

1. **Professional Endpoint:**
   - [ ] GET `/wp-json/rejimde/v1/professionals/{slug}` returns all new fields
   - [ ] `followers_count` matches actual follower count
   - [ ] `is_following` is false when not logged in
   - [ ] `is_following` is true after following the expert
   - [ ] `client_count` matches active relationships

2. **Service Request Creation:**
   - [ ] Logged-in user can create request
   - [ ] Cannot create duplicate pending request
   - [ ] Cannot request if already active client
   - [ ] Invalid expert_id returns error

3. **Expert Request Management:**
   - [ ] Expert can view all requests
   - [ ] Filter by status works
   - [ ] Pagination works
   - [ ] Meta counts are accurate

4. **Request Response:**
   - [ ] Expert can approve request
   - [ ] Approval creates client relationship
   - [ ] Package assignment works when enabled
   - [ ] Expert can reject request
   - [ ] Cannot respond to already-responded request

---

## Error Codes

| Code | Message | Status | Description |
|------|---------|--------|-------------|
| `request_failed` | Expert ID gerekli | 400 | Missing expert_id |
| `request_failed` | Geçersiz uzman | 400 | Expert not found or not rejimde_pro |
| `request_failed` | Bu uzmanla zaten aktif bir ilişkiniz mevcut | 400 | Active relationship exists |
| `request_failed` | Bu uzmana zaten bekleyen bir talebiniz var | 400 | Pending request exists |
| `invalid_action` | Geçersiz işlem | 400 | Action not approve or reject |
| `respond_failed` | Talep bulunamadı | 400 | Request not found or not owned by expert |
| `respond_failed` | Bu talep zaten yanıtlanmış | 400 | Request already approved/rejected |

---

## Future Enhancements (Optional)

### Expert Content Endpoint

**Endpoint:** `GET /rejimde/v1/experts/{userId}/content`

Would return all content created by the expert:
- Blog posts
- Diet plans
- Exercise plans
- Approved content
- Circles they mentor

**Example Response:**
```json
{
  "status": "success",
  "data": {
    "blogs": [...],
    "diet_plans": [...],
    "exercises": [...],
    "approved_content": [...],
    "circles": [...]
  },
  "stats": {
    "total_content": 45,
    "answered_questions": 128,
    "approved_count": 23
  }
}
```

This is marked as optional and can be implemented if needed.

---

## Support

For issues or questions about these endpoints, please contact the development team.
