# Circle API Quick Reference

## Endpoints Summary

### Circle Management
| Method | Endpoint | Auth | Role | Description |
|--------|----------|------|------|-------------|
| GET | `/circles` | No | - | List all circles |
| POST | `/circles` | Yes | Pro | Create circle |
| GET | `/circles/{slug}` | No | - | Get circle details |
| POST | `/circles/{id}` | Yes | Mentor | Update circle |
| POST | `/circles/{id}/join` | Yes | Member | Join circle |
| POST | `/circles/leave` | Yes | Member | Leave circle |
| GET | `/circles/{id}/settings` | Yes | Mentor | Get settings |
| PUT | `/circles/{id}/settings` | Yes | Mentor | Update settings |

### Task Management (New)
| Method | Endpoint | Auth | Role | Description |
|--------|----------|------|------|-------------|
| GET | `/circles/{id}/tasks` | Yes | Member | List tasks |
| POST | `/circles/{id}/tasks` | Yes | Mentor | Create task |
| PUT | `/circles/{id}/tasks/{task_id}` | Yes | Mentor | Update task |
| DELETE | `/circles/{id}/tasks/{task_id}` | Yes | Mentor | Delete task |
| POST | `/circles/{id}/tasks/{task_id}/assign` | Yes | Mentor | Assign task |

### Member Management (New)
| Method | Endpoint | Auth | Role | Description |
|--------|----------|------|------|-------------|
| GET | `/circles/{id}/members` | Yes | Member | List members |
| POST | `/circles/{id}/members/{member_id}/remove` | Yes | Mentor | Remove member |

## Bug Fixes

### ✅ Circle Creation After Deletion
- **Before**: Couldn't create new circle after deleting old one
- **After**: Automatically cleans up stale metadata and allows creation

### ✅ Circle Join After Deletion
- **Before**: Couldn't join new circle if old circle was deleted
- **After**: Automatically cleans up stale metadata and allows joining

### ✅ Automatic Cleanup
- **Before**: User metadata remained after circle deletion
- **After**: Hooks automatically clean up all member metadata

## Quick Examples

### Create Task
```bash
POST /wp-json/rejimde/v1/circles/123/tasks
{
  "title": "Workout Challenge",
  "description": "Complete 10 workouts",
  "points": 50,
  "deadline": "2026-01-15"
}
```

### Assign Task
```bash
POST /wp-json/rejimde/v1/circles/123/tasks/{task_id}/assign
{
  "member_ids": [101, 102, 103]
}
```

### Get Members
```bash
GET /wp-json/rejimde/v1/circles/123/members
```
Returns members sorted by score (highest first).

### Remove Member
```bash
POST /wp-json/rejimde/v1/circles/123/members/101/remove
```

## Error Codes

| Code | Status | Description |
|------|--------|-------------|
| `already_in_circle` | 400 | User already in a circle |
| `not_found` | 404 | Circle/task not found |
| `forbidden` | 403 | Insufficient permissions |
| `missing_title` | 400 | Task title required |
| `invalid_members` | 400 | Invalid member IDs |
| `not_member` | 400 | User not a circle member |

## Notes

- All mentor actions require `circle_mentor_id` match
- Admins can bypass permission checks
- Tasks use UUID v4 for unique IDs
- Members sorted by score automatically
- Mentors cannot remove themselves
- Circle deletion triggers automatic cleanup
