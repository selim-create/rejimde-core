# New Gamification API Endpoints

This document describes the new API endpoints added to support full frontend gamification compatibility.

## Updates (Latest)

### Enhanced Plan Endpoints

The plan start and complete endpoints now include:
- Comprehensive validation (plan exists, published status, user prerequisites)
- Proper error handling with descriptive messages
- Event dispatching for gamification integration
- Enhanced response structures with plan details and statistics
- Idempotent behavior (safe to call multiple times)

**Start Plan Example:**
```bash
curl -X POST https://rejimde.com/wp-json/rejimde/v1/plans/start/123 \
  -H "Authorization: Bearer <token>"
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "message": "Plana başarıyla başladınız.",
    "already_started": false,
    "plan": {
      "id": 123,
      "title": "Ketojenik Diyet",
      "started_count": 42
    }
  }
}
```

**Complete Plan Example:**
```bash
curl -X POST https://rejimde.com/wp-json/rejimde/v1/plans/complete/123 \
  -H "Authorization: Bearer <token>"
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "message": "Plan tamamlandı! Tebrikler!",
    "already_completed": false,
    "plan": {
      "id": 123,
      "title": "Ketojenik Diyet",
      "completed_count": 38,
      "reward_points": 50
    }
  }
}
```

### Enhanced Circle Endpoints

Circle join and leave endpoints now include:
- Validation for circle status and user eligibility
- Automatic circle score updates when members join/leave
- Enhanced response structures
- Better error handling

**Join Circle Example:**
```bash
curl -X POST https://rejimde.com/wp-json/rejimde/v1/circles/42/join \
  -H "Authorization: Bearer <token>"
```

**Response:**
```json
{
  "message": "Circle'a katıldınız!",
  "circle": {
    "id": 42,
    "name": "Sağlıklı Yaşam Topluluğu",
    "member_count": 26,
    "total_score": 16600
  }
}
```

### Circle Milestone Rewards

Circle members now receive bonus points when their circle reaches specific levels:
- Level 2 (Adapt - 200+): +5 points per member
- Level 3 (Commit - 300+): +10 points per member
- Level 4 (Balance - 500+): +20 points per member
- Level 5 (Strengthen - 1000+): +30 points per member
- Level 6 (Sustain - 2000+): +50 points per member
- Level 7 (Mastery - 4000+): +75 points per member
- Level 8 (Transform - 6000+): +100 points per member

These rewards are idempotent - awarded only once per level per user.

## Event Dispatch Endpoint

### POST `/rejimde/v1/events/dispatch`

This endpoint provides an alternative way to dispatch events for the gamification system. It's functionally equivalent to `/rejimde/v1/gamification/earn` but uses a more event-centric naming convention.

#### Request

**Headers:**
- `Authorization: Bearer <token>` or WordPress authentication cookie

**Body (JSON):**
```json
{
  "event_type": "blog_points_claimed",
  "entity_type": "blog",
  "entity_id": 123,
  "context": {
    "is_sticky": true
  }
}
```

**Parameters:**
- `event_type` (required): The type of event to dispatch (e.g., `blog_points_claimed`, `login_success`, etc.)
- `entity_type` (optional): The type of entity related to the event
- `entity_id` (optional): The ID of the entity
- `context` (optional): Additional context data for the event

**Alternative parameter names:**
- `action` can be used instead of `event_type`
- `ref_id` can be used instead of `entity_id`

**Special parameters for specific events:**
- `follower_id`: For follow events
- `followed_id`: For follow events
- `comment_id`: For comment-related events

#### Response

**Success (200):**
```json
{
  "status": "success",
  "data": {
    "success": true,
    "event_type": "blog_points_claimed",
    "points_earned": 50,
    "total_score": 1250,
    "daily_score": 75,
    "message": "Blog Okuma tamamlandı! +50 puan kazandın."
  }
}
```

**Error (400):**
```json
{
  "status": "error",
  "message": "Event type is required"
}
```

#### Example Usage

**Claim blog reading points:**
```bash
curl -X POST https://rejimde.com/wp-json/rejimde/v1/events/dispatch \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{
    "event_type": "blog_points_claimed",
    "entity_type": "blog",
    "entity_id": 456,
    "context": {
      "is_sticky": false
    }
  }'
```

**Record login:**
```bash
curl -X POST https://rejimde.com/wp-json/rejimde/v1/events/dispatch \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{
    "event_type": "login_success"
  }'
```

## Circle Account Endpoint

### GET `/rejimde/v1/gamification/circle-account`

Get the current user's circle membership details, including their contribution to the circle score and information about other members.

#### Request

**Headers:**
- `Authorization: Bearer <token>` or WordPress authentication cookie

#### Response

**User in a circle (200):**
```json
{
  "status": "success",
  "data": {
    "in_circle": true,
    "circle": {
      "id": 42,
      "name": "Sağlıklı Yaşam Topluluğu",
      "slug": "saglikli-yasam-toplulugu",
      "logo": "https://rejimde.com/uploads/circle-logo.jpg",
      "total_score": 15750,
      "member_count": 25,
      "motto": "Birlikte daha güçlüyüz",
      "privacy": "public"
    },
    "user_role": "member",
    "user_contribution": 850,
    "user_contribution_percentage": 5.4,
    "members": [
      {
        "id": 123,
        "name": "Ahmet Yılmaz",
        "avatar": "https://rejimde.com/uploads/avatar-123.jpg",
        "score": 2500,
        "role": "mentor",
        "is_current_user": false
      },
      {
        "id": 456,
        "name": "Ayşe Demir",
        "avatar": "https://rejimde.com/uploads/avatar-456.jpg",
        "score": 850,
        "role": "member",
        "is_current_user": true
      }
    ]
  }
}
```

**User not in a circle (200):**
```json
{
  "status": "success",
  "data": {
    "in_circle": false,
    "circle": null,
    "user_contribution": 0,
    "user_role": null
  }
}
```

#### Response Fields

- `in_circle`: Boolean indicating if user is in a circle
- `circle`: Circle details object (null if not in a circle)
  - `id`: Circle ID
  - `name`: Circle name
  - `slug`: URL-friendly circle identifier
  - `logo`: Circle logo URL
  - `total_score`: Combined score of all circle members
  - `member_count`: Number of members in the circle
  - `motto`: Circle motto/slogan
  - `privacy`: Circle privacy setting (`public` or `private`)
- `user_role`: User's role in the circle (`mentor` or `member`)
- `user_contribution`: User's total score (contribution to circle)
- `user_contribution_percentage`: Percentage of total circle score from this user
- `members`: Array of circle members sorted by score (highest first)

#### Example Usage

```bash
curl -X GET https://rejimde.com/wp-json/rejimde/v1/gamification/circle-account \
  -H "Authorization: Bearer <token>"
```

## Milestone Logging Endpoint

### POST `/rejimde/v1/gamification/milestones/log`

Manually log a milestone achievement. This endpoint allows tracking custom milestones that may not be automatically detected by the event system.

#### Request

**Headers:**
- `Authorization: Bearer <token>` or WordPress authentication cookie

**Body (JSON):**
```json
{
  "milestone_type": "comment_likes",
  "entity_id": 789,
  "current_value": 50
}
```

**Parameters:**
- `milestone_type` (required): Type of milestone (e.g., `comment_likes`)
- `entity_id` (required): ID of the entity the milestone is related to
- `current_value` (required): Current value that triggered the milestone check

#### Response

**Milestone awarded (200):**
```json
{
  "status": "success",
  "data": {
    "milestone_awarded": true,
    "milestone_type": "comment_likes",
    "milestone_value": 50,
    "points_earned": 5,
    "total_score": 1305,
    "daily_score": 80
  }
}
```

**No milestone reached (200):**
```json
{
  "status": "success",
  "data": {
    "milestone_awarded": false,
    "message": "No milestone reached or already awarded"
  }
}
```

**Error (400):**
```json
{
  "status": "error",
  "message": "Milestone type is required"
}
```

#### Milestone Types and Thresholds

**Comment Likes Milestones:**
- 3 likes: +1 point
- 7 likes: +1 point
- 10 likes: +2 points
- 25 likes: +2 points
- 50 likes: +5 points
- 100 likes: +5 points
- 150 likes: +5 points
- Every 50 likes after 150: +5 points

#### Example Usage

```bash
curl -X POST https://rejimde.com/wp-json/rejimde/v1/gamification/milestones/log \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{
    "milestone_type": "comment_likes",
    "entity_id": 789,
    "current_value": 50
  }'
```

## Notes

### Idempotency
All endpoints are idempotent. Dispatching the same event multiple times will not award points more than once (subject to the event's rules in `ScoringRules.php`).

### Pro Users
Users with the `rejimde_pro` role will have events logged but will not earn points.

### Circle Score Updates
When a user earns points and is a member of a circle, the circle's total score is automatically updated.

### Event Types
Available event types are defined in `includes/Config/ScoringRules.php`. Common event types include:
- `login_success`
- `blog_points_claimed`
- `diet_started`, `diet_completed`
- `exercise_started`, `exercise_completed`
- `calculator_saved`
- `rating_submitted`
- `comment_created`, `comment_liked`
- `follow_accepted`
- `highfive_sent`
- `circle_created`, `circle_joined`

### Migration from Old Endpoints
The new `/rejimde/v1/events/dispatch` endpoint is fully compatible with the existing `/rejimde/v1/gamification/earn` endpoint. Both use the same underlying `EventDispatcher` system.
