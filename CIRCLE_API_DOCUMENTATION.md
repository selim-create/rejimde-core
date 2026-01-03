# Circle API Documentation

## Overview
This document describes the Circle management API endpoints, including bug fixes and new task management features.

## Bug Fixes Implemented

### 1. Circle Creation After Deletion Fix
**Problem**: Users who previously created and deleted circles couldn't create new circles due to stale metadata.

**Solution**: The `create_item` endpoint now:
- Checks if the circle referenced in user meta actually exists
- Validates circle post type and publish status
- Cleans up stale metadata automatically
- Allows users to create new circles after cleanup

### 2. Circle Join Validation Fix
**Problem**: Users with stale circle metadata couldn't join new circles.

**Solution**: The `join_circle` endpoint now:
- Validates the existing circle still exists
- Cleans up metadata if circle is deleted
- Allows users to join new circles

### 3. Automatic Cleanup on Circle Deletion
**Problem**: When circles were deleted, user metadata remained orphaned.

**Solution**: Added hooks to automatically clean up:
- `before_delete_post` - Cleans up before permanent deletion
- `wp_trash_post` - Cleans up when circle is trashed
- Removes `circle_id` and `circle_role` from all members

---

## Circle Management Endpoints

### Get All Circles
```
GET /wp-json/rejimde/v1/circles
```
**Authentication**: None (public)

**Response**:
```json
[
  {
    "id": 123,
    "name": "Fitness Warriors",
    "slug": "fitness-warriors",
    "description": "A community dedicated to fitness...",
    "logo": "https://example.com/logo.png",
    "total_score": 15000,
    "member_count": 25,
    "privacy": "public"
  }
]
```

### Create Circle
```
POST /wp-json/rejimde/v1/circles
```
**Authentication**: Required (rejimde_pro users only)

**Request Body**:
```json
{
  "name": "My Circle",
  "description": "Circle description",
  "motto": "Our motto",
  "logo": "https://example.com/logo.png",
  "privacy": "public",
  "chat_status": "open"
}
```

**Success Response** (201):
```json
{
  "id": 124,
  "message": "Circle kuruldu! Circle Mentor sensin.",
  "slug": "my-circle"
}
```

**Error Response** (400):
```json
{
  "code": "already_in_circle",
  "message": "Zaten bir Circle'dasınız.",
  "status": 400
}
```

### Join Circle
```
POST /wp-json/rejimde/v1/circles/{id}/join
```
**Authentication**: Required

**Success Response** (200):
```json
{
  "message": "Circle'a katıldınız!",
  "circle": {
    "id": 123,
    "name": "Fitness Warriors",
    "member_count": 26,
    "total_score": 15100
  }
}
```

### Leave Circle
```
POST /wp-json/rejimde/v1/circles/leave
```
**Authentication**: Required

**Success Response** (200):
```json
{
  "message": "Circle'dan ayrıldınız.",
  "circle": {
    "id": 123,
    "member_count": 25,
    "total_score": 14900
  }
}
```

---

## Task Management Endpoints

### Get Circle Tasks
```
GET /wp-json/rejimde/v1/circles/{id}/tasks
```
**Authentication**: Required

**Response** (200):
```json
[
  {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "title": "Complete 10 Workouts",
    "description": "Finish 10 workout sessions this week",
    "points": 50,
    "deadline": "2026-01-10",
    "assigned_to": [101, 102, 103],
    "completed_by": [101],
    "status": "active",
    "created_at": "2026-01-03 10:30:00",
    "created_by": 100
  }
]
```

### Create Task
```
POST /wp-json/rejimde/v1/circles/{id}/tasks
```
**Authentication**: Required (Mentor only)

**Request Body**:
```json
{
  "title": "Complete 10 Workouts",
  "description": "Finish 10 workout sessions this week",
  "points": 50,
  "deadline": "2026-01-10"
}
```

**Success Response** (201):
```json
{
  "message": "Görev oluşturuldu!",
  "task": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "title": "Complete 10 Workouts",
    "description": "Finish 10 workout sessions this week",
    "points": 50,
    "deadline": "2026-01-10",
    "assigned_to": [],
    "completed_by": [],
    "status": "active",
    "created_at": "2026-01-03 10:30:00",
    "created_by": 100
  }
}
```

**Error Responses**:
- `403`: User is not the circle mentor
- `404`: Circle not found
- `400`: Missing required field (title)

### Update Task
```
PUT /wp-json/rejimde/v1/circles/{id}/tasks/{task_id}
```
**Authentication**: Required (Mentor only)

**Request Body** (all fields optional):
```json
{
  "title": "Updated Title",
  "description": "Updated description",
  "points": 75,
  "deadline": "2026-01-15",
  "status": "completed"
}
```

**Success Response** (200):
```json
{
  "message": "Görev güncellendi!",
  "task": { /* updated task object */ }
}
```

### Delete Task
```
DELETE /wp-json/rejimde/v1/circles/{id}/tasks/{task_id}
```
**Authentication**: Required (Mentor only)

**Success Response** (200):
```json
{
  "message": "Görev silindi!"
}
```

### Assign Task to Members
```
POST /wp-json/rejimde/v1/circles/{id}/tasks/{task_id}/assign
```
**Authentication**: Required (Mentor only)

**Request Body**:
```json
{
  "member_ids": [101, 102, 103]
}
```

**Success Response** (200):
```json
{
  "message": "Görev atandı!",
  "task": { /* updated task with assigned_to array */ }
}
```

**Error Responses**:
- `400`: No members provided or invalid format
- `403`: User is not the circle mentor
- `404`: Circle or task not found

---

## Member Management Endpoints

### Get Circle Members
```
GET /wp-json/rejimde/v1/circles/{id}/members
```
**Authentication**: Required

**Response** (200):
```json
[
  {
    "id": 100,
    "name": "John Doe",
    "email": "john@example.com",
    "avatar": "https://example.com/avatar.jpg",
    "role": "mentor",
    "score": 1500,
    "joined_at": "2025-12-01 10:00:00"
  },
  {
    "id": 101,
    "name": "Jane Smith",
    "email": "jane@example.com",
    "avatar": "https://example.com/avatar2.jpg",
    "role": "member",
    "score": 1200,
    "joined_at": "2025-12-05 14:30:00"
  }
]
```

**Note**: Members are automatically sorted by score (highest first).

### Remove Member from Circle
```
POST /wp-json/rejimde/v1/circles/{id}/members/{member_id}/remove
```
**Authentication**: Required (Mentor only)

**Success Response** (200):
```json
{
  "message": "Üye circle'dan çıkarıldı."
}
```

**Error Responses**:
- `400`: Trying to remove the mentor (not allowed)
- `400`: User is not a member of this circle
- `403`: User is not the circle mentor
- `404`: Circle not found

**Side Effects**:
- Member's score is subtracted from circle total score
- Circle member count is decremented
- Member's `circle_id` and `circle_role` metadata are removed

---

## Authorization

### Permission Levels

1. **Public Endpoints** (No authentication required):
   - `GET /circles` - List all circles
   - `GET /circles/{slug}` - Get circle details

2. **Authenticated Endpoints** (Logged-in users):
   - `POST /circles/{id}/join` - Join a circle
   - `POST /circles/leave` - Leave current circle
   - `GET /circles/{id}/tasks` - View circle tasks
   - `GET /circles/{id}/members` - View circle members

3. **Pro Users Only**:
   - `POST /circles` - Create a new circle

4. **Mentor Only** (Circle creator/mentor):
   - `PUT /circles/{id}` - Update circle details
   - `PUT /circles/{id}/settings` - Update circle settings
   - `POST /circles/{id}/tasks` - Create task
   - `PUT /circles/{id}/tasks/{task_id}` - Update task
   - `DELETE /circles/{id}/tasks/{task_id}` - Delete task
   - `POST /circles/{id}/tasks/{task_id}/assign` - Assign task
   - `POST /circles/{id}/members/{member_id}/remove` - Remove member

5. **Administrator**:
   - All endpoints (bypass permission checks)

---

## Data Validation & Sanitization

All inputs are properly sanitized:
- `sanitize_text_field()` - For single-line text (titles, names, etc.)
- `sanitize_textarea_field()` - For multi-line text (descriptions)
- `esc_url_raw()` - For URLs (logos, avatars)
- `intval()` - For numeric IDs and scores

---

## Test Scenarios

### Scenario 1: Circle Deletion and Recreation
```bash
# User creates a circle
curl -X POST https://rejimde.com/wp-json/rejimde/v1/circles \
  -H "Authorization: Bearer <token>" \
  -d '{"name": "Test Circle"}'

# Admin deletes the circle (via WordPress admin)

# User creates a new circle (should work now)
curl -X POST https://rejimde.com/wp-json/rejimde/v1/circles \
  -H "Authorization: Bearer <token>" \
  -d '{"name": "New Circle"}'
```

### Scenario 2: Task Management
```bash
# Create a task
curl -X POST https://rejimde.com/wp-json/rejimde/v1/circles/123/tasks \
  -H "Authorization: Bearer <mentor-token>" \
  -d '{"title": "Workout Challenge", "points": 50, "deadline": "2026-01-15"}'

# Assign task to members
curl -X POST https://rejimde.com/wp-json/rejimde/v1/circles/123/tasks/{task_id}/assign \
  -H "Authorization: Bearer <mentor-token>" \
  -d '{"member_ids": [101, 102, 103]}'

# Update task
curl -X PUT https://rejimde.com/wp-json/rejimde/v1/circles/123/tasks/{task_id} \
  -H "Authorization: Bearer <mentor-token>" \
  -d '{"status": "completed"}'
```

### Scenario 3: Member Management
```bash
# Get all members
curl -X GET https://rejimde.com/wp-json/rejimde/v1/circles/123/members \
  -H "Authorization: Bearer <token>"

# Remove a member (mentor only)
curl -X POST https://rejimde.com/wp-json/rejimde/v1/circles/123/members/101/remove \
  -H "Authorization: Bearer <mentor-token>"
```

---

## Implementation Notes

### Task Storage
- Tasks are stored as serialized arrays in post meta (`circle_tasks`)
- Each task has a unique UUID v4 identifier
- Tasks are stored as an array to allow simple CRUD operations

### Performance Considerations
- Circle member queries use `meta_key` and `meta_value` lookups
- For large member lists, consider adding database indexes on `wp_usermeta(meta_key, meta_value)`
- Member sorting by score is done in PHP (acceptable for typical circle sizes)

### Security
- All mentor-only actions verify the user is the circle mentor
- Input validation prevents XSS and SQL injection
- WordPress nonce validation is handled by REST API framework
- Mentors cannot remove themselves from circles

---

## Migration Notes

The system includes automatic migration from the old `rejimde_clan` post type to `rejimde_circle`. This migration:
- Runs once automatically
- Updates post types, user meta, and post meta
- Is marked complete with an option flag
- Is safe to run multiple times (idempotent)
