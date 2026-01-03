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

---

## Expert Reviews (Enhanced)

### GET `/rejimde/v1/comments`
Get comments with advanced filtering support for expert evaluations.

**Query Parameters:**
- `post` (required): Expert post ID
- `context`: Filter by context (expert, diet, exercise)
- `goal_tag`: Filter by goal (weight_loss, muscle_gain, healthy_eating, etc.)
- `program_type`: Filter by program type (online, face_to_face, package, group)
- `verified_only`: Only verified clients (true/false)
- `featured_only`: Only featured reviews (true/false)
- `with_stories`: Only reviews with success stories (true/false)
- `rating_min`: Minimum rating (1-5)

**Example Request:**
```bash
curl -X GET "https://rejimde.com/wp-json/rejimde/v1/comments?post=123&context=expert&verified_only=true&rating_min=4"
```

**Response:**
```json
{
  "comments": [
    {
      "id": 456,
      "content": "Harika bir deneyimdi...",
      "rating": 5,
      "is_anonymous": false,
      "goal_tag": "weight_loss",
      "program_type": "online",
      "process_weeks": 12,
      "success_story": "12 haftada 10 kilo verdim...",
      "would_recommend": true,
      "verified_client": true,
      "is_featured": false,
      "author": { ... },
      "date": "2025-11-15 10:30:00",
      "timeAgo": "2 ay önce"
    }
  ],
  "stats": {
    "average": 4.8,
    "total": 127,
    "distribution": { ... },
    "verified_client_count": 98,
    "average_process_weeks": 8,
    "recommend_rate": 92,
    "goal_distribution": [
      {"goal": "weight_loss", "count": 45},
      {"goal": "muscle_gain", "count": 32}
    ]
  },
  "filters_applied": {
    "goal_tag": null,
    "program_type": null,
    "verified_only": true,
    "featured_only": false,
    "with_stories": false,
    "rating_min": 4
  }
}
```

### POST `/rejimde/v1/comments`
Create a new expert review with enhanced metadata.

**Authentication:** Required

**Request Body:**
```json
{
  "post": 123,
  "content": "Harika bir deneyimdi, çok memnunum!",
  "context": "expert",
  "rating": 5,
  "is_anonymous": false,
  "goal_tag": "weight_loss",
  "program_type": "online",
  "process_weeks": 12,
  "success_story": "12 haftada 10 kilo verdim ve hedefime ulaştım. Süreç boyunca uzmanım bana çok destek oldu.",
  "would_recommend": true
}
```

**Parameters:**
- `post` (required): Expert post ID
- `content` (required): Review content
- `context`: Context type (default: "general")
- `rating`: Rating 1-5
- `parent`: Parent comment ID for replies
- `is_anonymous`: Anonymous review (default: false)
- `goal_tag`: Goal tag (weight_loss, muscle_gain, healthy_eating, etc.)
- `program_type`: Program type (online, face_to_face, package, group)
- `process_weeks`: Journey duration in weeks
- `success_story`: Success story text
- `would_recommend`: Would recommend (default: true)

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 456,
    "content": "Harika bir deneyimdi...",
    "rating": 5,
    "verified_client": true,
    ...
  },
  "earned_points": 10,
  "message": "Değerlendirmeniz alındı, uzman onayından sonra yayınlanacaktır.",
  "status": "pending"
}
```

### POST `/rejimde/v1/comments/{id}/feature`
Toggle featured status for a comment. Only the expert who owns the profile can feature comments. Maximum 3 featured comments allowed.

**Authentication:** Required (Expert owner only)

**Example Request:**
```bash
curl -X POST https://rejimde.com/wp-json/rejimde/v1/comments/456/feature \
  -H "Authorization: Bearer <token>"
```

**Response:**
```json
{
  "success": true,
  "is_featured": true,
  "message": "Yorum öne çıkarıldı."
}
```

**Error Response (Limit Reached):**
```json
{
  "code": "limit_reached",
  "message": "Maksimum 3 yorum öne çıkarılabilir.",
  "data": {
    "status": 400
  }
}
```

### GET `/rejimde/v1/experts/{id}/impact`
Get expert's community impact statistics.

**Example Request:**
```bash
curl -X GET https://rejimde.com/wp-json/rejimde/v1/experts/123/impact
```

**Response:**
```json
{
  "total_clients_supported": 127,
  "programs_completed": 89,
  "average_journey_weeks": 8,
  "recommend_rate": 92,
  "verified_client_count": 98,
  "goal_distribution": [
    {"goal_tag": "weight_loss", "count": 45},
    {"goal_tag": "muscle_gain", "count": 32},
    {"goal_tag": "healthy_eating", "count": 25}
  ],
  "context": {
    "message": "Son 6 ayda 45 danışana destek oldu",
    "highlight": "Her 5 danışandan 4'i tavsiye ediyor"
  }
}
```

**Fields:**
- `total_clients_supported`: Total unique clients from appointments
- `programs_completed`: Number of completed programs
- `average_journey_weeks`: Average duration of client journeys
- `recommend_rate`: Percentage of clients who would recommend (0-100)
- `verified_client_count`: Number of verified clients who left reviews
- `goal_distribution`: Distribution of client goals
- `context.message`: Contextual message about recent activity
- `context.highlight`: Key highlight metric

### GET `/rejimde/v1/experts/{id}/success-stories`
Get expert's success stories from client reviews.

**Query Parameters:**
- `limit`: Number of stories to return (default: 10)

**Example Request:**
```bash
curl -X GET "https://rejimde.com/wp-json/rejimde/v1/experts/123/success-stories?limit=5"
```

**Response:**
```json
{
  "stories": [
    {
      "id": 456,
      "author_initials": "A.K.",
      "author_name": null,
      "is_anonymous": true,
      "goal_tag": "weight_loss",
      "program_type": "online",
      "process_weeks": 12,
      "story": "12 haftada hedefime ulaştım ve hayatım değişti...",
      "rating": 5,
      "verified_client": true,
      "created_at": "2025-11-15 10:30:00",
      "time_ago": "2 ay önce"
    }
  ],
  "total": 5
}
```

**Fields:**
- `author_initials`: Initials of the author (e.g., "A.K.")
- `author_name`: Full name (null if anonymous)
- `is_anonymous`: Whether the review is anonymous
- `goal_tag`: Client's goal
- `program_type`: Type of program
- `process_weeks`: Duration in weeks
- `story`: Success story text
- `rating`: Rating given by client
- `verified_client`: Whether client is verified
- `created_at`: ISO timestamp
- `time_ago`: Human-readable time ago

---

## Profile Following Activity

### GET `/rejimde/v1/profile/following`

Get the activity feed of users that the current user is following. This endpoint returns a list of followed users along with their most recent activities from the events system.

**Authentication:** Required (logged-in users only)

**Example Request:**
```bash
curl -X GET https://rejimde.com/wp-json/rejimde/v1/profile/following \
  -H "Authorization: Bearer <token>"
```

**Response (with following):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 123,
      "name": "Ahmet Yılmaz",
      "slug": "ahmet-yilmaz",
      "avatar_url": "https://rejimde.com/uploads/avatar-123.jpg",
      "last_activity": {
        "type": "exercise_completed",
        "label": "Egzersiz tamamladı",
        "icon": "💪",
        "time_ago": "2 saat önce"
      }
    },
    {
      "id": 456,
      "name": "Ayşe Demir",
      "slug": "ayse-demir",
      "avatar_url": "https://api.dicebear.com/9.x/personas/svg?seed=ayse-demir",
      "last_activity": {
        "type": "water_added",
        "label": "Su hedefini tamamladı",
        "icon": "💧",
        "time_ago": "5 dakika önce"
      }
    }
  ],
  "total_following": 15
}
```

**Response (no following):**
```json
{
  "status": "success",
  "data": [],
  "total_following": 0,
  "message": "Henüz kimseyi takip etmiyorsun."
}
```

**Response Fields:**
- `status`: Request status ("success")
- `data`: Array of followed users with their last activities
  - `id`: User ID
  - `name`: User's display name
  - `slug`: URL-friendly username (nicename)
  - `avatar_url`: User's avatar URL (custom or generated)
  - `last_activity`: Object containing the user's most recent activity
    - `type`: Event type from rejimde_events table
    - `label`: Human-readable activity description
    - `icon`: Emoji icon representing the activity
    - `time_ago`: Human-readable time since the activity
- `total_following`: Total number of users being followed
- `message`: Informational message (only when no following)

**Supported Activity Types:**
- `water_added`: "Su hedefini tamamladı" 💧
- `steps_logged`: "Adım hedefini tamamladı" 👟
- `meal_photo_uploaded`: "Öğün fotoğrafı yükledi" 📸
- `diet_completed`: "Diyet tamamladı" 🥗
- `exercise_completed`: "Egzersiz tamamladı" 💪
- `login_success`: "Giriş yaptı" ✅
- `blog_points_claimed`: "Blog okudu" 📚
- `comment_created`: "Yorum yaptı" 💬
- `highfive_sent`: "Beşlik çaktı" ✋
- `follow_accepted`: "Birini takip etti" 👥
- `calculator_saved`: "Hesaplayıcı kullandı" 🧮
- `circle_joined`: "Circle'a katıldı" 🎯
- `diet_started`: "Diyet başlattı" 🍽️
- `exercise_started`: "Egzersiz başlattı" 🏃
- `rating_submitted`: "Uzman değerlendirdi" ⭐
- `milestone_*`: "Bir başarı kazandı" 🏆
- Default: "Aktivite gerçekleştirdi" 📌

**Notes:**
- Only returns users that are currently in the database (skips deleted users)
- Activities are fetched from the `wp_rejimde_events` table
- Returns the most recent activity for each followed user
- Uses optimized SQL query for performance
- Avatar URLs fall back to DiceBear API if no custom avatar is set
