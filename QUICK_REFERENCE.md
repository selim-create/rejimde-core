# Quick Reference: Backend API Updates

This is a quick reference guide for the key changes made to the rejimde-core backend. For comprehensive details, see IMPLEMENTATION_SUMMARY.md.

## What Changed?

### 1. Plan Endpoints - Now More Robust ✨

**Before:**
```php
// Simple tracking, no validation
start_plan() // Just adds user to array
complete_plan() // Just adds user to array
```

**After:**
```php
// Comprehensive validation and event integration
start_plan()
  ✓ Validates plan exists and is published
  ✓ Checks if already started (idempotent)
  ✓ Logs start timestamp
  ✓ Dispatches diet_started event (awards 5 points)
  ✓ Returns detailed response

complete_plan()
  ✓ Validates plan exists and is published
  ✓ Requires plan to be started first
  ✓ Checks if already completed (idempotent)
  ✓ Logs completion timestamp
  ✓ Dispatches diet_completed event (awards dynamic points)
  ✓ Returns detailed response with reward info
```

### 2. Circle Endpoints - Score Management 🏆

**Before:**
```php
// Basic join/leave, no score updates
join_circle() // Just sets user meta
leave_circle() // Just removes user meta
```

**After:**
```php
join_circle()
  ✓ Validates circle exists and is published
  ✓ Checks user eligibility
  ✓ Updates circle total score (+user's score)
  ✓ Dispatches circle_joined event
  ✓ Returns circle details

leave_circle()
  ✓ Updates circle total score (-user's score)
  ✓ Prevents negative scores
  ✓ Cleans up user metadata
  ✓ Returns updated circle info
```

### 3. Gamification Endpoints - Enhanced Info 📊

**Before:**
```php
/gamification/me returns:
{
  "daily_score": 75,
  "total_score": 1250,
  "rank": 3,
  "level": {...},
  "earned_badges": [...]
}
```

**After:**
```php
/gamification/me returns:
{
  "daily_score": 75,
  "total_score": 1250,
  "rank": 3,
  "level": {...},
  "earned_badges": [...],
  "is_pro": false,           // NEW! Identify pro users
  "circle": {                 // NEW! Circle membership
    "id": 42,
    "name": "Healthy Living",
    "role": "member"
  }
}
```

### 4. Pro User Handling - Verified ✅

**How it works:**
```php
EventDispatcher->dispatch()
  → Check if user has 'rejimde_pro' role
  → If YES: Log event with 0 points, return success
  → If NO: Calculate and award points normally
```

**Result:**
- Pro users can still use the app
- Their actions are logged
- They don't earn points
- Frontend can identify them via `is_pro` flag

### 5. Circle Milestones - New Feature 🎖️

**What:**
When a circle reaches a new level, all members get bonus points!

**Levels & Rewards:**
```
Level 2 (200+ points)   → +5 points per member
Level 3 (300+ points)   → +10 points per member
Level 4 (500+ points)   → +20 points per member
Level 5 (1000+ points)  → +30 points per member
Level 6 (2000+ points)  → +50 points per member
Level 7 (4000+ points)  → +75 points per member
Level 8 (6000+ points)  → +100 points per member
```

**Idempotent:** Each member gets the reward only once per level.

### 6. Parameter Flexibility - Frontend Compatible 🔄

**earn endpoint now accepts multiple formats:**
```javascript
// Format 1 (old)
{"action": "login_success"}

// Format 2 (new)
{"event_type": "login_success"}

// Format 3 (old)
{"action": "blog_points_claimed", "ref_id": 123}

// Format 4 (new)
{"event_type": "blog_points_claimed", "entity_id": 123}

// All work the same!
```

## Key Improvements at a Glance

| Feature | Before | After |
|---------|--------|-------|
| Plan Validation | ❌ None | ✅ Comprehensive |
| Error Messages | ❌ Generic | ✅ Descriptive |
| Idempotency | ⚠️ Partial | ✅ Full |
| Event Logging | ⚠️ Some | ✅ All actions |
| Circle Scores | ❌ Manual | ✅ Automatic |
| Pro User Info | ❌ Hidden | ✅ Exposed |
| Circle Milestones | ❌ None | ✅ 8 levels |
| Response Details | ⚠️ Basic | ✅ Enhanced |

## Common Use Cases

### Starting a Plan
```bash
POST /rejimde/v1/plans/start/123
Authorization: Bearer <token>

Response (Success):
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

Response (Already Started):
{
  "status": "success",
  "data": {
    "message": "Bu plana zaten başlamıştınız.",
    "already_started": true,
    "plan": {...}
  }
}

Response (Error):
{
  "status": "error",
  "message": "Plan bulunamadı.",
  "code": "plan_not_found"
}
```

### Joining a Circle
```bash
POST /rejimde/v1/circles/42/join
Authorization: Bearer <token>

Response (Success):
{
  "message": "Circle'a katıldınız!",
  "circle": {
    "id": 42,
    "name": "Sağlıklı Yaşam",
    "member_count": 26,
    "total_score": 16600  // Includes your score now!
  }
}
```

### Checking User Stats
```bash
GET /rejimde/v1/gamification/me
Authorization: Bearer <token>

Response:
{
  "status": "success",
  "data": {
    "daily_score": 75,
    "total_score": 1250,
    "rank": 3,
    "level": {
      "id": "level-5",
      "name": "Strengthen",
      "level": 5
    },
    "earned_badges": [1, 5, 12],
    "is_pro": false,      // Check if pro user
    "circle": {           // Circle membership
      "id": 42,
      "name": "Sağlıklı Yaşam",
      "role": "member"
    }
  }
}
```

## Error Handling Examples

All endpoints now return clear, actionable error messages:

```javascript
// Plan not found
{
  "status": "error",
  "message": "Plan bulunamadı.",
  "code": "plan_not_found"
}

// Plan not started yet
{
  "status": "error",
  "message": "Bu planı tamamlamadan önce başlatmalısınız.",
  "code": "plan_not_started"
}

// Already in a circle
{
  "status": "error",
  "message": "Önce mevcut circle'dan ayrılmalısınız.",
  "code": "already_in_circle"
}

// Not in any circle
{
  "status": "error",
  "message": "Herhangi bir circle'da değilsiniz.",
  "code": "no_circle"
}
```

## Backward Compatibility

✅ **100% Compatible** - All existing frontend code works unchanged!

- Old parameter names still supported (`action`, `ref_id`)
- New parameter names added (`event_type`, `entity_id`)
- Response structures only enhanced, not changed
- All existing endpoints work as before

## Testing Checklist

Quick tests to verify everything works:

- [ ] Start a plan → Should get success + event logged
- [ ] Start same plan again → Should indicate already started
- [ ] Complete unstarted plan → Should get error
- [ ] Complete started plan → Should get points
- [ ] Join a circle → Circle score should increase
- [ ] Leave circle → Circle score should decrease
- [ ] Check stats as regular user → Should have is_pro: false
- [ ] Check stats as pro user → Should have is_pro: true
- [ ] Earn points as pro user → Should log event but no points

## Database Tables Used

```
wp_rejimde_events         → All user actions logged here
wp_rejimde_milestones     → Circle level achievements
wp_rejimde_daily_logs     → Daily score tracking
wp_postmeta               → Circle scores (total_score, member_count)
wp_usermeta               → User scores, circle membership
```

## Performance Notes

For large databases, consider adding these indexes:

```sql
-- Circle membership queries
ALTER TABLE wp_usermeta 
ADD INDEX idx_circle_id (meta_key(20), meta_value(20));

-- Event queries
ALTER TABLE wp_rejimde_events 
ADD INDEX idx_user_event (user_id, event_type, created_at);
```

## Need More Info?

- **Full Details:** See `IMPLEMENTATION_SUMMARY.md`
- **Testing Guide:** See `VALIDATION_CHECKLIST.md`
- **API Docs:** See `API_ENDPOINTS.md`
- **Event System:** See `EVENT_SYSTEM_GUIDE.md`

## Questions?

Contact the development team or check the comprehensive documentation files.

---

**Version:** 1.0.2  
**Updated:** December 2024  
**Status:** ✅ Ready for Production
