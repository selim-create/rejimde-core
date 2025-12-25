# API Validation Checklist

This checklist helps verify that all implemented changes are working correctly.

## Pre-requisites
- WordPress installation with rejimde-core plugin active
- At least 2 test users (1 regular, 1 with rejimde_pro role)
- At least 1 published plan
- At least 1 published circle

## 1. Plan Endpoints Validation

### Test Plan Start Endpoint
- [ ] **Test Case 1.1**: Start a valid, published plan
  - Endpoint: `POST /rejimde/v1/plans/start/{valid_plan_id}`
  - Expected: 200 status, message "Plana başarıyla başladınız.", already_started: false
  - Verify: Event logged in `rejimde_events` table with event_type='diet_started'
  - Verify: User metadata `rejimde_plan_{id}` contains started_at timestamp

- [ ] **Test Case 1.2**: Try to start the same plan again
  - Endpoint: `POST /rejimde/v1/plans/start/{same_plan_id}`
  - Expected: 200 status, already_started: true, message indicates already started
  - Verify: No duplicate event in `rejimde_events` table

- [ ] **Test Case 1.3**: Try to start non-existent plan
  - Endpoint: `POST /rejimde/v1/plans/start/99999`
  - Expected: 404 status, error message "Plan bulunamadı."

- [ ] **Test Case 1.4**: Try to start unpublished plan
  - Create draft plan, get its ID
  - Endpoint: `POST /rejimde/v1/plans/start/{draft_plan_id}`
  - Expected: 400 status, error message about plan not available

### Test Plan Complete Endpoint
- [ ] **Test Case 2.1**: Complete a plan without starting it
  - Endpoint: `POST /rejimde/v1/plans/complete/{unstarted_plan_id}`
  - Expected: 400 status, error message "Bu planı tamamlamadan önce başlatmalısınız."

- [ ] **Test Case 2.2**: Complete a started plan (as regular user)
  - First: `POST /rejimde/v1/plans/start/{plan_id}`
  - Then: `POST /rejimde/v1/plans/complete/{plan_id}`
  - Expected: 200 status, already_completed: false, points awarded
  - Verify: Event logged with event_type='diet_completed'
  - Verify: User's total score increased
  - Verify: Points match plan's score_reward meta

- [ ] **Test Case 2.3**: Complete the same plan again
  - Endpoint: `POST /rejimde/v1/plans/complete/{same_plan_id}`
  - Expected: 200 status, already_completed: true
  - Verify: No additional points awarded (idempotent)

- [ ] **Test Case 2.4**: Complete a plan as Pro user
  - Login as rejimde_pro user
  - Start and complete a plan
  - Expected: Event logged, but points_earned = 0
  - Verify: Pro user's score does NOT increase

## 2. Circle Endpoints Validation

### Test Circle Join Endpoint
- [ ] **Test Case 3.1**: Join a valid, published circle
  - Endpoint: `POST /rejimde/v1/circles/{valid_circle_id}/join`
  - Expected: 200 status, message "Circle'a katıldınız!"
  - Verify: circle_id meta set for user
  - Verify: circle_role meta set to 'member'
  - Verify: Circle member_count increased by 1
  - Verify: Circle total_score increased by user's current score
  - Verify: Event logged with event_type='circle_joined'

- [ ] **Test Case 3.2**: Try to join while already in a circle
  - Endpoint: `POST /rejimde/v1/circles/{another_circle_id}/join`
  - Expected: 400 status, error message about leaving current circle first

- [ ] **Test Case 3.3**: Try to join non-existent circle
  - Endpoint: `POST /rejimde/v1/circles/99999/join`
  - Expected: 404 status, error message "Circle bulunamadı."

### Test Circle Leave Endpoint
- [ ] **Test Case 4.1**: Leave a circle while in one
  - Endpoint: `POST /rejimde/v1/circles/leave`
  - Expected: 200 status, message "Circle'dan ayrıldınız."
  - Verify: circle_id meta removed from user
  - Verify: circle_role meta removed from user
  - Verify: Circle member_count decreased by 1
  - Verify: Circle total_score decreased by user's score

- [ ] **Test Case 4.2**: Try to leave when not in any circle
  - Endpoint: `POST /rejimde/v1/circles/leave`
  - Expected: 400 status, error message "Herhangi bir circle'da değilsiniz."

## 3. Gamification Endpoints Validation

### Test Gamification Earn Endpoint
- [ ] **Test Case 5.1**: Earn points as regular user
  - Endpoint: `POST /rejimde/v1/gamification/earn`
  - Body: `{"action": "login_success"}`
  - Expected: 200 status, success: true, points_earned > 0
  - Verify: Event logged in rejimde_events
  - Verify: User's total_score increased

- [ ] **Test Case 5.2**: Try to earn points as Pro user
  - Login as rejimde_pro user
  - Endpoint: `POST /rejimde/v1/gamification/earn`
  - Body: `{"action": "login_success"}`
  - Expected: 200 status, message about Pro users, points_earned: 0
  - Verify: Event logged but no points awarded

- [ ] **Test Case 5.3**: Test multiple parameter formats
  - Test with `{"action": "blog_points_claimed", "ref_id": 123}`
  - Test with `{"event_type": "blog_points_claimed", "entity_id": 123}`
  - Both should work identically

- [ ] **Test Case 5.4**: Test idempotency
  - Earn points for same entity twice
  - Endpoint: `POST /rejimde/v1/gamification/earn`
  - Body: `{"action": "blog_points_claimed", "entity_id": 123, "entity_type": "blog"}`
  - Expected: Second call should not award points (per_entity_limit)

### Test Gamification Me Endpoint
- [ ] **Test Case 6.1**: Get stats as regular user in a circle
  - Endpoint: `GET /rejimde/v1/gamification/me`
  - Expected: Returns daily_score, total_score, rank, level, earned_badges
  - Verify: is_pro: false
  - Verify: circle object contains {id, name, role}

- [ ] **Test Case 6.2**: Get stats as Pro user
  - Login as rejimde_pro user
  - Endpoint: `GET /rejimde/v1/gamification/me`
  - Expected: is_pro: true

- [ ] **Test Case 6.3**: Get stats as user not in circle
  - Use user not in any circle
  - Endpoint: `GET /rejimde/v1/gamification/me`
  - Expected: circle: null

## 4. Score Service Validation

### Test Dynamic Point Calculation
- [ ] **Test Case 7.1**: Create plan with score_reward meta
  - Set score_reward to 100 on a plan
  - Complete the plan
  - Expected: User receives 100 points

- [ ] **Test Case 7.2**: Create plan without score_reward meta
  - Complete the plan
  - Expected: User receives default 10 points

## 5. Milestone Service Validation

### Test Circle Level Milestones
- [ ] **Test Case 8.1**: Award circle level milestone
  - Use MilestoneService->checkAndAward()
  - milestone_type: 'circle_level'
  - currentValue: 2 (for level 2)
  - Expected: Returns 5 points
  - Verify: Milestone record created in rejimde_milestones table

- [ ] **Test Case 8.2**: Try to award same milestone again
  - Call checkAndAward with same parameters
  - Expected: Returns null (already awarded)

## 6. Backward Compatibility Validation

### Test Old Parameter Formats
- [ ] **Test Case 9.1**: Use old 'action' parameter
  - Endpoint: `POST /rejimde/v1/gamification/earn`
  - Body: `{"action": "login_success"}`
  - Expected: Works correctly

- [ ] **Test Case 9.2**: Use old 'ref_id' parameter
  - Body: `{"action": "blog_points_claimed", "ref_id": 123}`
  - Expected: Works correctly

## 7. Error Handling Validation

### Test Various Error Scenarios
- [ ] **Test Case 10.1**: Missing required parameters
  - Endpoint: `POST /rejimde/v1/gamification/earn`
  - Body: `{}` (empty)
  - Expected: 400 status, error about missing event type

- [ ] **Test Case 10.2**: Invalid IDs
  - Try operations with ID: 0, -1, "abc"
  - Expected: Appropriate error messages

## 8. Event Logging Validation

### Verify Event Table
- [ ] **Test Case 11.1**: Check event log entries
  - Perform various actions
  - Query: `SELECT * FROM wp_rejimde_events ORDER BY created_at DESC LIMIT 10`
  - Verify: Events logged with correct user_id, event_type, entity_type, entity_id
  - Verify: Context JSON properly formatted

- [ ] **Test Case 11.2**: Verify Pro user events
  - Check Pro user's events
  - Verify: Events exist but points = 0

## Database Verification Queries

```sql
-- Check events table
SELECT event_type, points, entity_type, entity_id 
FROM wp_rejimde_events 
WHERE user_id = {test_user_id} 
ORDER BY created_at DESC 
LIMIT 10;

-- Check milestones
SELECT * FROM wp_rejimde_milestones 
WHERE user_id = {test_user_id};

-- Check daily scores
SELECT * FROM wp_rejimde_daily_logs 
WHERE user_id = {test_user_id} 
ORDER BY log_date DESC 
LIMIT 7;

-- Check circle scores
SELECT 
  p.ID, 
  p.post_title, 
  pm1.meta_value as total_score,
  pm2.meta_value as member_count
FROM wp_posts p
LEFT JOIN wp_postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'total_score'
LEFT JOIN wp_postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'member_count'
WHERE p.post_type = 'rejimde_circle' 
AND p.post_status = 'publish';
```

## Success Criteria

All test cases should pass with:
- ✅ Correct HTTP status codes
- ✅ Expected response structures
- ✅ Proper database updates
- ✅ Event logging
- ✅ Idempotent behavior
- ✅ Pro user exclusion from points
- ✅ Backward compatibility maintained

## Notes

- Use a REST client like Postman or Insomnia for testing
- Use proper authentication (WordPress cookies or JWT tokens)
- Check WordPress debug.log for any PHP errors
- Monitor database for unexpected changes
- Test with both fresh users and users with existing data
