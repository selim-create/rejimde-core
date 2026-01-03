# Level-Based Leaderboard API

## Overview
The Level-Based Leaderboard API provides weekly competitive rankings for users and circles within specific level tiers. Each level has promotion and relegation zones, creating a dynamic competitive environment.

## Endpoint

### Get Level Leaderboard
`GET /wp-json/rejimde/v1/gamification/level-leaderboard`

Retrieves the leaderboard for a specific level, including rankings, zones, and period information.

#### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `level_slug` | string | Yes | - | Level identifier (begin, adapt, commit, balance, strengthen, sustain, mastery, transform) |
| `type` | string | No | `users` | Type of leaderboard (`users` or `circles`) |
| `limit` | integer | No | 50 | Maximum number of results to return |

#### Level Slugs and Score Ranges

Scores are inclusive of the minimum and exclusive of the maximum (min ≤ score < max).

| Level Slug | Name | Score Range | Level Number |
|------------|------|-------------|--------------|
| `begin` | Begin | 0-200 | 1 |
| `adapt` | Adapt | 200-300 | 2 |
| `commit` | Commit | 300-500 | 3 |
| `balance` | Balance | 500-1000 | 4 |
| `strengthen` | Strengthen | 1000-2000 | 5 |
| `sustain` | Sustain | 2000-4000 | 6 |
| `mastery` | Mastery | 4000-6000 | 7 |
| `transform` | Transform | 6000-10000 | 8 |

#### Response Structure

```json
{
  "status": "success",
  "data": {
    "level": {
      "min": 300,
      "max": 500,
      "level": 3,
      "name": "Commit",
      "next": "balance",
      "prev": "adapt"
    },
    "period_ends_at": "2026-01-05 23:59:59",
    "period_ends_timestamp": 1736118000,
    "promotion_count": 5,
    "relegation_count": 5,
    "users": [
      {
        "rank": 1,
        "id": 123,
        "name": "Kullanıcı Adı",
        "slug": "kullanici-adi",
        "avatar": "https://...",
        "score": 450,
        "zone": "promotion",
        "is_current_user": false
      }
    ],
    "circles": [],
    "current_user": {
      "id": 456,
      "rank": 7,
      "score": 380,
      "zone": "safe",
      "points_to_promotion": 71,
      "points_to_relegation": 30,
      "in_this_level": true
    }
  }
}
```

#### Response Fields

##### Level Object
- `min` (integer): Minimum score for this level
- `max` (integer): Maximum score for this level (exclusive)
- `level` (integer): Level number (1-8)
- `name` (string): Level name
- `next` (string|null): Next level slug
- `prev` (string|null): Previous level slug

##### Period Information
- `period_ends_at` (string): ISO datetime when current period ends (Sunday 23:59:59 Istanbul time)
- `period_ends_timestamp` (integer): Unix timestamp of period end
- `promotion_count` (integer): Number of top spots that get promoted (default: 5)
- `relegation_count` (integer): Number of bottom spots that get relegated (default: 5)

##### User/Circle Entry
- `rank` (integer): Current rank in the level
- `id` (integer): User or Circle ID
- `name` (string): Display name
- `slug` (string): Username or circle slug
- `avatar` (string): Avatar URL (users only)
- `logo` (string): Logo URL (circles only)
- `score` (integer): Current total score
- `zone` (string): Current zone (`promotion`, `safe`, or `relegation`)
- `is_current_user` (boolean): Whether this is the current authenticated user (users only)

##### Current User Object
- `id` (integer): User ID
- `rank` (integer|null): Current rank in this level (null if not in this level)
- `score` (integer): Current total score
- `zone` (string|null): Current zone (null if not in this level)
- `points_to_promotion` (integer|null): Points needed to reach promotion zone
- `points_to_relegation` (integer|null): Point buffer before relegation zone
- `in_this_level` (boolean): Whether user is in this level
- `current_level` (string): User's actual level slug (only if `in_this_level` is false)
- `message` (string): Informational message (only if `in_this_level` is false)

#### Zones

1. **Promotion Zone**: Top 5 positions (configurable via `promotion_count`)
   - Users/circles in this zone will be promoted to the next level at period end
   
2. **Safe Zone**: Middle positions
   - Users/circles remain in the same level
   
3. **Relegation Zone**: Bottom 5 positions (configurable via `relegation_count`)
   - Users/circles in this zone will be relegated to the previous level at period end

#### Examples

##### Get User Leaderboard for Commit Level
```bash
curl -X GET "https://yoursite.com/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=commit"
```

##### Get Circle Leaderboard for Balance Level
```bash
curl -X GET "https://yoursite.com/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=balance&type=circles&limit=20"
```

##### Get Transform Level with Authentication
```bash
curl -X GET \
  "https://yoursite.com/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=transform" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Error Responses

##### Missing level_slug
```json
{
  "status": "error",
  "message": "level_slug parameter is required"
}
```
HTTP Status: 400

##### Invalid level_slug
```json
{
  "status": "error",
  "message": "Invalid level_slug"
}
```
HTTP Status: 400

## Notes

### Period Calculation
- Periods run from Monday 00:00:00 to Sunday 23:59:59 (Istanbul timezone)
- The `period_ends_at` field always shows the upcoming Sunday at 23:59:59

### Exclusions
- Users with `administrator` or `rejimde_pro` roles are excluded from level-based leaderboards
- Deleted circles are automatically excluded

### Current User
- If not authenticated, `current_user` will be `null`
- If authenticated but not in the requested level, `in_this_level` will be `false` and additional info about the user's actual level will be provided
- Points calculations help users understand what they need to do to improve their position

### Performance Considerations
- The endpoint queries all users/circles in the level to calculate accurate zones and positions
- Results are limited by the `limit` parameter (default: 50, showing top positions)
- Use appropriate caching strategies for production environments

## Integration Tips

1. **Display Promotion/Relegation Indicators**: Use different colors or icons based on the `zone` field
2. **Show Period Countdown**: Use `period_ends_timestamp` to display a countdown timer
3. **Highlight Current User**: Use `is_current_user` to highlight the authenticated user's position
4. **Progressive Loading**: For large leaderboards, implement pagination or infinite scroll
5. **Real-time Updates**: Consider implementing WebSocket or polling for live ranking updates

## Testing

Use the provided validation script:
```bash
php validate_level_leaderboard.php
```

For API testing examples, refer to the main API_TESTING_GUIDE.md.
