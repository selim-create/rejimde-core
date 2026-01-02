# Expert Evaluation System Testing Guide

This guide provides quick reference examples for testing the new expert evaluation system endpoints.

## Prerequisites
- WordPress site running with Rejimde Core plugin
- Expert profile post ID (e.g., 123)
- Authentication token for protected endpoints

## Quick Test Commands

### 1. Create Enhanced Review

```bash
# Create a complete review with all new fields
curl -X POST https://rejimde.com/wp-json/rejimde/v1/comments \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "post": 123,
    "content": "Harika bir deneyimdi, çok memnunum!",
    "context": "expert",
    "rating": 5,
    "is_anonymous": false,
    "goal_tag": "weight_loss",
    "program_type": "online",
    "process_weeks": 12,
    "success_story": "12 haftada 10 kilo verdim ve hedefime ulaştım.",
    "would_recommend": true
  }'
```

### 2. Create Anonymous Review

```bash
# Create anonymous review (name will show as initials)
curl -X POST https://rejimde.com/wp-json/rejimde/v1/comments \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "post": 123,
    "content": "Gizli kalarak paylaşmak istedim.",
    "context": "expert",
    "rating": 5,
    "is_anonymous": true,
    "goal_tag": "muscle_gain",
    "program_type": "face_to_face",
    "process_weeks": 8
  }'
```

### 3. Get All Reviews (No Filters)

```bash
# Get all reviews for an expert
curl -X GET "https://rejimde.com/wp-json/rejimde/v1/comments?post=123&context=expert"
```

### 4. Get Filtered Reviews

```bash
# Get only verified client reviews with 4+ stars
curl -X GET "https://rejimde.com/wp-json/rejimde/v1/comments?post=123&context=expert&verified_only=true&rating_min=4"

# Get reviews with success stories
curl -X GET "https://rejimde.com/wp-json/rejimde/v1/comments?post=123&context=expert&with_stories=true"

# Get featured reviews only
curl -X GET "https://rejimde.com/wp-json/rejimde/v1/comments?post=123&context=expert&featured_only=true"

# Filter by goal tag
curl -X GET "https://rejimde.com/wp-json/rejimde/v1/comments?post=123&context=expert&goal_tag=weight_loss"

# Filter by program type
curl -X GET "https://rejimde.com/wp-json/rejimde/v1/comments?post=123&context=expert&program_type=online"

# Combine multiple filters
curl -X GET "https://rejimde.com/wp-json/rejimde/v1/comments?post=123&context=expert&verified_only=true&goal_tag=weight_loss&rating_min=4"
```

### 5. Feature/Unfeature Comment

```bash
# Toggle featured status (expert owner only)
curl -X POST https://rejimde.com/wp-json/rejimde/v1/comments/456/feature \
  -H "Authorization: Bearer EXPERT_TOKEN"
```

### 6. Get Expert Impact Statistics

```bash
# Get community impact stats
curl -X GET "https://rejimde.com/wp-json/rejimde/v1/experts/123/impact"
```

### 7. Get Success Stories

```bash
# Get latest 5 success stories
curl -X GET "https://rejimde.com/wp-json/rejimde/v1/experts/123/success-stories?limit=5"

# Get latest 10 success stories (default)
curl -X GET "https://rejimde.com/wp-json/rejimde/v1/experts/123/success-stories"
```

## Testing Scenarios

### Scenario 1: Verified Client Badge
1. Create a user account
2. Create a completed appointment for that user with the expert
3. Post a review as that user
4. Review should show `verified_client: true`

### Scenario 2: Anonymous Review
1. Post a review with `is_anonymous: true`
2. Check response - author name should be initials (e.g., "A.K.")
3. Author ID should be 0
4. Avatar should use initials seed

### Scenario 3: Featured Reviews Limit
1. Feature 3 different reviews (should succeed)
2. Try to feature a 4th review (should fail with limit error)
3. Unfeature one review
4. Now feature the 4th review (should succeed)

### Scenario 4: Advanced Filtering
1. Create multiple reviews with different attributes:
   - Review A: verified, weight_loss, 5 stars
   - Review B: not verified, muscle_gain, 3 stars
   - Review C: verified, weight_loss, 4 stars, has success story
2. Test filters:
   - `verified_only=true` should return A and C
   - `goal_tag=weight_loss` should return A and C
   - `rating_min=4` should return A and C
   - `with_stories=true` should return C only

### Scenario 5: Expert Impact Stats
1. Ensure expert has:
   - Completed appointments
   - Reviews with various metadata
2. Call impact endpoint
3. Verify statistics are accurate:
   - Total clients count
   - Recommendation rate calculation
   - Goal distribution

## Response Structure Examples

### Review Response (with new fields)
```json
{
  "id": 456,
  "content": "Great experience!",
  "rating": 5,
  "is_anonymous": false,
  "goal_tag": "weight_loss",
  "program_type": "online",
  "process_weeks": 12,
  "success_story": "Lost 10kg in 12 weeks!",
  "would_recommend": true,
  "verified_client": true,
  "is_featured": false,
  "author": { ... },
  "date": "2025-11-15 10:30:00",
  "timeAgo": "2 months ago"
}
```

### Stats Response (enhanced)
```json
{
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
}
```

### Impact Response
```json
{
  "total_clients_supported": 127,
  "programs_completed": 89,
  "average_journey_weeks": 8,
  "recommend_rate": 92,
  "verified_client_count": 98,
  "goal_distribution": [...],
  "context": {
    "message": "Supported 45 clients in last 6 months",
    "highlight": "4 out of 5 clients recommend"
  }
}
```

## Common Issues & Solutions

### Issue: Verified client badge not showing
- **Check**: Ensure `rejimde_appointments` table exists
- **Check**: User has completed appointment with expert
- **Check**: Expert user_id mapping is correct

### Issue: Anonymous name not showing as initials
- **Check**: `is_anonymous` meta is set to true
- **Check**: User has valid display_name
- **Check**: Using latest CommentMeta.php

### Issue: Featured comment limit not enforced
- **Check**: Using POST to feature endpoint
- **Check**: Request is authenticated as expert owner
- **Check**: Count query is working correctly

### Issue: Filters not working
- **Check**: Parameter names match exactly (case sensitive)
- **Check**: Boolean values use string "true" not boolean true
- **Check**: Meta values are stored correctly in database

## Database Verification

### Check meta fields
```sql
-- Check comment meta
SELECT * FROM wp_commentmeta WHERE comment_id = 456;

-- Check featured comments count
SELECT COUNT(*) FROM wp_commentmeta 
WHERE meta_key = 'is_featured' AND meta_value = '1' 
AND comment_id IN (SELECT comment_ID FROM wp_comments WHERE comment_post_ID = 123);

-- Check verified clients
SELECT * FROM wp_commentmeta 
WHERE meta_key = 'verified_client' AND meta_value = '1';
```

### Check appointments table
```sql
-- Check completed appointments
SELECT * FROM wp_rejimde_appointments 
WHERE expert_id = USER_ID AND status = 'completed';
```

## Notes
- All new fields are optional when creating reviews
- Filtering parameters are also optional
- Multiple filters can be combined with AND logic
- Anonymous reviews preserve rank but hide other user data
- Verified client status is calculated automatically
