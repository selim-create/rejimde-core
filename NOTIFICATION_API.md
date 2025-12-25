# Notification & Activity Log API Endpoints

This document describes the new API endpoints for the Notifications and Activity Log system.

## Authentication

All endpoints require user authentication. Expert-specific endpoints additionally require the user to have the `rejimde_pro` role.

## Notification Endpoints

Base path: `/wp-json/rejimde/v1/notifications`

### GET /notifications

Get user notifications with optional filtering.

**Query Parameters:**
- `category` (optional): Filter by category (social, system, level, circle, points, expert)
- `is_read` (optional): Filter by read status (0 or 1)
- `limit` (optional): Number of results (default: 50, max: 100)
- `offset` (optional): Pagination offset (default: 0)

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "type": "streak_milestone",
      "category": "system",
      "title": "7 günlük seri! +10 bonus puan! 🏆",
      "body": "Muhteşem bir başarı! Serisini sürdürmeye devam et.",
      "icon": "fa-trophy",
      "action_url": null,
      "actor_id": null,
      "entity_type": "streak",
      "entity_id": 7,
      "meta": null,
      "is_read": 0,
      "is_pushed": 0,
      "is_emailed": 0,
      "expires_at": "2024-01-15 00:00:00",
      "created_at": "2024-01-08 10:30:00"
    }
  ]
}
```

### GET /notifications/unread-count

Get count of unread notifications.

**Response:**
```json
{
  "status": "success",
  "data": {
    "unread_count": 5
  }
}
```

### POST /notifications/mark-read

Mark notifications as read.

**Request Body:**
```json
{
  "ids": [1, 2, 3]  // or "all" to mark all as read
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "updated_count": 3,
    "message": "Bildirimler okundu olarak işaretlendi."
  }
}
```

### GET /notifications/preferences

Get notification preferences for all categories.

**Response:**
```json
{
  "status": "success",
  "data": {
    "social": {
      "channel_in_app": 1,
      "channel_push": 1,
      "channel_email": 0,
      "dnd_start": null,
      "dnd_end": null
    },
    "system": {
      "channel_in_app": 1,
      "channel_push": 1,
      "channel_email": 0,
      "dnd_start": "22:00:00",
      "dnd_end": "08:00:00"
    },
    // ... other categories
  }
}
```

### POST /notifications/preferences

Update notification preferences.

**Request Body:**
```json
{
  "preferences": {
    "social": {
      "channel_in_app": 1,
      "channel_push": 0,
      "channel_email": 0,
      "dnd_start": null,
      "dnd_end": null
    },
    "system": {
      "channel_in_app": 1,
      "channel_push": 1,
      "channel_email": 0,
      "dnd_start": "22:00:00",
      "dnd_end": "08:00:00"
    }
  }
}
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "message": "Bildirim tercihleri güncellendi."
  }
}
```

## Activity Log Endpoints

Base path: `/wp-json/rejimde/v1/activity`

### GET /activity

Get user activity log.

**Query Parameters:**
- `event_type` (optional): Filter by event type
- `date_from` (optional): Filter from date (Y-m-d H:i:s)
- `date_to` (optional): Filter to date (Y-m-d H:i:s)
- `limit` (optional): Number of results (default: 50)
- `offset` (optional): Pagination offset (default: 0)

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 456,
      "user_id": 123,
      "event_type": "blog_points_claimed",
      "entity_type": "blog",
      "entity_id": 789,
      "points": 10,
      "context": {
        "is_sticky": false
      },
      "created_at": "2024-01-08 10:30:00",
      "formatted": {
        "label": "Blog Okuma",
        "icon": "fa-book-open",
        "description": "Blog okuma"
      }
    }
  ]
}
```

### GET /activity/points

Get activity with point movements (ledger view).

**Query Parameters:**
- `limit` (optional): Number of results (default: 50)
- `offset` (optional): Pagination offset (default: 0)

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 456,
      "user_id": 123,
      "event_type": "blog_points_claimed",
      "points": 10,
      "balance_before": 100,
      "balance_after": 110,
      "created_at": "2024-01-08 10:30:00"
    }
  ]
}
```

### GET /activity/summary

Get activity summary for a period.

**Query Parameters:**
- `period` (optional): Period (today, week, month) - default: week

**Response:**
```json
{
  "status": "success",
  "data": {
    "period": "week",
    "total_events": 25,
    "total_points": 150,
    "breakdown": [
      {
        "event_type": "login_success",
        "count": 7,
        "total_points": 14
      },
      {
        "event_type": "blog_points_claimed",
        "count": 10,
        "total_points": 100
      }
    ]
  }
}
```

## Expert Activity Endpoints

Base path: `/wp-json/rejimde/v1/expert`

**Note:** These endpoints require `rejimde_pro` role.

### GET /expert/notifications

Get expert-specific notifications.

**Query Parameters:**
- `is_read` (optional): Filter by read status (0 or 1)
- `limit` (optional): Number of results (default: 50)
- `offset` (optional): Pagination offset (default: 0)

**Response:** Same format as `/notifications` but filtered to `expert` category only.

### GET /expert/activity

Get expert activity log.

**Query Parameters:**
- `limit` (optional): Number of results (default: 50)
- `offset` (optional): Pagination offset (default: 0)

**Response:** Same format as `/activity`.

### GET /expert/metrics

Get expert metrics summary.

**Query Parameters:**
- `days` (optional): Number of days to look back (default: 30)

**Response:**
```json
{
  "status": "success",
  "data": {
    "period_days": 30,
    "totals": {
      "profile_views": 250,
      "unique_viewers": 150,
      "rating_count": 12,
      "rating_sum": 58,
      "content_views": 0,
      "client_completions": 5
    },
    "average_rating": 4.83,
    "daily_metrics": [
      {
        "expert_id": 123,
        "metric_date": "2024-01-08",
        "profile_views": 15,
        "unique_viewers": 10,
        "rating_count": 1,
        "rating_sum": 5,
        "content_views": 0,
        "client_completions": 1
      }
    ]
  }
}
```

### GET /expert/profile-viewers

Get recent profile viewers.

**Query Parameters:**
- `limit` (optional): Number of viewers to return (default: 10)

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "viewer_user_id": 456,
      "last_viewed": "2024-01-08 14:30:00",
      "display_name": "Ahmet Yılmaz",
      "avatar_url": "https://example.com/avatar.jpg"
    }
  ]
}
```

### GET /expert/view-stats

Get profile view statistics.

**Query Parameters:**
- `days` (optional): Number of days to look back (default: 30)

**Response:**
```json
{
  "status": "success",
  "data": {
    "period_days": 30,
    "total_views": 250,
    "unique_viewers": 150,
    "daily_views": [
      {
        "date": "2024-01-08",
        "views": 15,
        "unique_viewers": 10
      }
    ]
  }
}
```

## Notification Types

### User Notifications (all users)

**Points Category:**
- `points_earned` - Points earned notification
- `daily_limit_reached` - Daily limit reached notification
- `content_completed` - Content completion notification

**System Category:**
- `streak_continued` - Streak continuation notification
- `streak_milestone` - Streak milestone reached
- `streak_broken` - Streak broken notification
- `grace_used` - Grace period used notification

**Level Category:**
- `level_up` - Level up notification
- `rank_changed` - Rank change notification
- `badge_earned` - Badge earned notification
- `weekly_ranking` - Weekly ranking notification

**Social Category:**
- `new_follower` - New follower notification
- `follow_accepted` - Follow request accepted
- `highfive_received` - High five received
- `comment_reply` - Comment reply notification
- `comment_like_milestone` - Comment like milestone

**Circle Category:**
- `circle_joined` - Joined a circle
- `circle_new_member` - New member joined circle
- `circle_activity` - Circle activity notification

### Expert Notifications (rejimde_pro only)

**Expert Category:**
- `rating_received` - Rating received notification
- `profile_view_milestone` - Profile view milestone
- `client_completed` - Client completed content
- `client_activity` - Client activity notification

## Event to Notification Mapping

The system automatically creates notifications for the following events:

| Event Type | Notification Type(s) | Recipient(s) |
|------------|---------------------|--------------|
| `login_success` (with streak) | `streak_continued`, `streak_milestone` | User |
| `follow_accepted` | `new_follower`, `follow_accepted` | Both follower and followed |
| `highfive_sent` | `highfive_received` | Target user |
| `comment_created` (reply) | `comment_reply` | Parent comment author |
| `comment_liked` (milestone) | `comment_like_milestone` | Comment author |
| `blog_points_claimed` | `content_completed` | User |
| `diet_completed` | `content_completed` | User |
| `exercise_completed` | `content_completed` | User |
| `circle_joined` | `circle_joined` | User |
| `rating_submitted` | `rating_received` | Expert |

## Notification Features

### Idempotency
- Notifications are deduplicated per user/type/entity per day
- Prevents spam from repeated actions

### User Preferences
- Users can control notifications per category
- Channel preferences: in-app, push (future), email (future)
- Do Not Disturb (DND) time periods

### Expiration
- Notifications auto-expire based on type
- Old notifications are cleaned up by cron job (daily)

### Weekly Ranking
- Automatic weekly ranking notifications sent via cron
- Runs every Monday at 9:00 AM
- Based on previous week's snapshot data

## Error Responses

All endpoints return standard error responses:

```json
{
  "status": "error",
  "message": "Error description"
}
```

Common HTTP status codes:
- `400` - Bad request (missing/invalid parameters)
- `401` - Unauthorized (not logged in)
- `403` - Forbidden (insufficient permissions)
- `404` - Not found
- `500` - Server error
