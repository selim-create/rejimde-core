# Level-Based Leaderboard Test Guide

## Manual Testing Guide

This guide helps you manually test the new level-based leaderboard endpoint.

### Prerequisites
- WordPress installation with rejimde-core plugin active
- REST API client (Postman, Insomnia, curl, or browser)
- Test users with different score levels

### Test Scenarios

#### 1. Basic User Leaderboard Test

**Request:**
```bash
curl -X GET "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=commit"
```

**Expected Response:**
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
    "users": [...],
    "circles": [],
    "current_user": null
  }
}
```

**Verification:**
- ✅ Status is "success"
- ✅ Level info matches commit level (300-500)
- ✅ Period ends on next Sunday at 23:59:59
- ✅ Users array contains entries with scores between 300 and 499
- ✅ Each user has rank, zone, score, etc.

#### 2. Circle Leaderboard Test

**Request:**
```bash
curl -X GET "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=balance&type=circles"
```

**Expected Response:**
```json
{
  "status": "success",
  "data": {
    "level": {
      "min": 500,
      "max": 1000,
      "level": 4,
      "name": "Balance",
      "next": "strengthen",
      "prev": "commit"
    },
    "users": [],
    "circles": [
      {
        "rank": 1,
        "id": 123,
        "name": "Circle Name",
        "slug": "circle-slug",
        "logo": "https://...",
        "score": 750,
        "zone": "promotion"
      }
    ]
  }
}
```

**Verification:**
- ✅ Circles array populated, users array empty
- ✅ Circles have scores between 500 and 999
- ✅ Each circle has logo field instead of avatar

#### 3. Authenticated User Test

**Request:**
```bash
curl -X GET \
  "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=commit" \
  -H "Cookie: wordpress_logged_in_xxx=..."
```

**Expected Response (User in This Level):**
```json
{
  "data": {
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

**Expected Response (User NOT in This Level):**
```json
{
  "data": {
    "current_user": {
      "id": 456,
      "rank": null,
      "score": 150,
      "zone": null,
      "points_to_promotion": null,
      "points_to_relegation": null,
      "in_this_level": false,
      "current_level": "begin",
      "message": "You are currently in Begin level"
    }
  }
}
```

**Verification:**
- ✅ current_user object present when authenticated
- ✅ is_current_user flag true for logged-in user in users array
- ✅ Points calculations present when in_this_level is true

#### 4. Zone Distribution Test

**Request:**
```bash
curl -X GET "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=adapt&limit=50"
```

**Verification:**
- ✅ Top 5 users have zone: "promotion"
- ✅ Bottom 5 users have zone: "relegation"
- ✅ Middle users have zone: "safe"
- ✅ Zones correctly calculated based on total users

#### 5. All Level Slugs Test

Test each level slug:

```bash
# Begin (0-200)
curl "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=begin"

# Adapt (200-300)
curl "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=adapt"

# Commit (300-500)
curl "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=commit"

# Balance (500-1000)
curl "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=balance"

# Strengthen (1000-2000)
curl "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=strengthen"

# Sustain (2000-4000)
curl "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=sustain"

# Mastery (4000-6000)
curl "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=mastery"

# Transform (6000-10000)
curl "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=transform"
```

**Verification:**
- ✅ Each level returns correct min/max values
- ✅ prev/next links are correct
- ✅ Users filtered to correct score range

#### 6. Error Handling Test

**Missing level_slug:**
```bash
curl "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard"
```

**Expected:**
```json
{
  "status": "error",
  "message": "level_slug parameter is required"
}
```

**Invalid level_slug:**
```bash
curl "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=invalid"
```

**Expected:**
```json
{
  "status": "error",
  "message": "Invalid level_slug"
}
```

**Verification:**
- ✅ Returns 400 status code
- ✅ Error message is clear

#### 7. Period End Date Test

Test on different days of the week to verify period_ends_at:

**Monday:** Should show next Sunday
**Sunday:** Should show current Sunday (today at 23:59:59)

**Verification:**
- ✅ period_ends_at always shows Sunday 23:59:59
- ✅ Timezone is Europe/Istanbul
- ✅ period_ends_timestamp matches the datetime

#### 8. Limit Parameter Test

```bash
curl "http://yoursite.local/wp-json/rejimde/v1/gamification/level-leaderboard?level_slug=commit&limit=10"
```

**Verification:**
- ✅ Returns maximum 10 users
- ✅ Still shows correct zones for all users (not just returned ones)
- ✅ current_user data complete even if outside limit

#### 9. Administrator/Pro Exclusion Test

Create test users with administrator and rejimde_pro roles, give them scores in the level range.

**Verification:**
- ✅ Administrator users NOT in leaderboard
- ✅ rejimde_pro users NOT in leaderboard
- ✅ Regular users with same scores ARE in leaderboard

### Performance Tests

#### Load Test
Test with large datasets:
- 100+ users in a single level
- Multiple concurrent requests
- Different level slugs simultaneously

**Verification:**
- ✅ Response time < 1 second
- ✅ No memory issues
- ✅ Correct ranking maintained

### Integration Tests

#### Frontend Integration
If you have a frontend:
- Display promotion zone users in green
- Display relegation zone users in red
- Show countdown to period end
- Highlight current user
- Show points needed to promotion

### Common Issues & Solutions

**Issue:** Empty users/circles array
- **Solution:** Check if any users/circles have scores in the level range

**Issue:** Wrong period_ends_at
- **Solution:** Verify server timezone and Istanbul timezone configuration

**Issue:** Duplicate users in multiple levels
- **Solution:** Verify the >= and < meta_query comparisons

**Issue:** current_user always null
- **Solution:** Ensure WordPress authentication cookie is sent

**Issue:** Zones incorrect
- **Solution:** Verify total count calculation and zone formula

### Success Criteria

All tests should pass with:
- ✅ Correct HTTP status codes
- ✅ Expected response structures
- ✅ Proper data filtering by level
- ✅ Accurate zone calculations
- ✅ Correct period dates
- ✅ Proper role exclusions
- ✅ Current user data accuracy

### Automated Testing Script

For quick validation, use:
```bash
php validate_level_leaderboard.php
```

This validates:
- File existence
- PHP syntax
- Method implementation
- Route registration
- Level definitions
- Response structure
- Zone logic
- Period calculation
- User/circle support
- Role exclusions
