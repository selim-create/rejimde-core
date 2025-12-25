# API Testing Guide

This document provides test scenarios to validate the backend state management improvements.

## Prerequisites
- WordPress installation with rejimde-core plugin active
- REST API client (Postman, Insomnia, or curl)
- At least 1 test user logged in
- Authentication token/cookie

## Test Scenarios

### 1. Progress API - Empty Data Handling

**Test Case**: Initialize progress without data
```bash
POST /wp-json/rejimde/v1/progress/diet/107
Content-Type: application/json

{}
```

**Expected Response**: 200 OK
```json
{
  "status": "success",
  "data": {
    "user_id": 1,
    "content_type": "diet",
    "content_id": 107,
    "progress_data": [],
    "completed_items": [],
    "is_started": false,
    "is_completed": false,
    "progress_percentage": 0
  }
}
```

### 2. Progress API - Completed Items Support

**Test Case**: Update progress with completed items
```bash
POST /wp-json/rejimde/v1/progress/diet/107
Content-Type: application/json

{
  "is_started": true,
  "completed_items": ["day1_meal1", "day1_meal2"]
}
```

**Expected Response**: 200 OK with completed_items merged

**Test Case**: Add more completed items
```bash
POST /wp-json/rejimde/v1/progress/diet/107
Content-Type: application/json

{
  "completed_items": ["day1_meal3"]
}
```

**Expected Response**: Should contain all three items: ["day1_meal1", "day1_meal2", "day1_meal3"]

### 3. Progress API - Get Single Progress

**Test Case**: Retrieve progress with percentage
```bash
GET /wp-json/rejimde/v1/progress/diet/107
```

**Expected Response**: 200 OK
```json
{
  "status": "success",
  "data": {
    "user_id": 1,
    "content_type": "diet",
    "content_id": 107,
    "completed_items": ["day1_meal1", "day1_meal2", "day1_meal3"],
    "progress_percentage": 14.29,
    "is_started": true,
    "is_completed": false
  }
}
```

### 4. Progress API - Complete Item

**Test Case**: Complete a single item
```bash
POST /wp-json/rejimde/v1/progress/diet/107/complete-item
Content-Type: application/json

{
  "item_id": "day2_meal1"
}
```

**Expected Response**: 200 OK
```json
{
  "status": "success",
  "data": {
    "message": "Item completed successfully",
    "completed_items": ["day1_meal1", "day1_meal2", "day1_meal3", "day2_meal1"],
    "progress_percentage": 19.05,
    "is_completed": false
  }
}
```

### 5. Progress API - Get All Progress (Structured)

**Test Case**: Retrieve all user progress
```bash
GET /wp-json/rejimde/v1/progress/my
```

**Expected Response**: 200 OK with structured data
```json
{
  "status": "success",
  "data": {
    "diets": [
      {
        "content_id": 107,
        "is_started": true,
        "completed_items": ["day1_meal1", "day1_meal2"],
        "progress_percentage": 9.52
      }
    ],
    "exercises": [],
    "blogs": [],
    "dictionary": []
  }
}
```

### 6. Gamification API - First Earn

**Test Case**: Earn points for the first time
```bash
POST /wp-json/rejimde/v1/gamification/earn
Content-Type: application/json

{
  "action": "diet_started",
  "entity_type": "diet",
  "entity_id": 107
}
```

**Expected Response**: 200 OK
```json
{
  "status": "success",
  "data": {
    "success": true,
    "event_type": "diet_started",
    "points_earned": 5,
    "total_score": 105,
    "message": "Diyet Başlatma tamamlandı! +5 puan kazandın."
  }
}
```

### 7. Gamification API - Duplicate Earn (Already Earned)

**Test Case**: Try to earn points again for same entity
```bash
POST /wp-json/rejimde/v1/gamification/earn
Content-Type: application/json

{
  "action": "diet_started",
  "entity_type": "diet",
  "entity_id": 107
}
```

**Expected Response**: 200 OK (not 400!)
```json
{
  "status": "success",
  "data": {
    "success": true,
    "already_earned": true,
    "points_earned": 0,
    "total_score": 105,
    "message": "Bu içerik için zaten puan kazandınız."
  }
}
```

### 8. Profile API - Social State

**Test Case**: Get profile with social state
```bash
GET /wp-json/rejimde/v1/profile/testuser
```

**Expected Response**: 200 OK
```json
{
  "id": 2,
  "username": "testuser",
  "is_following": false,
  "has_high_fived": false,
  "has_high_fived_today": false,
  "followers_count": 5,
  "following_count": 3
}
```

## Validation Checklist

- [ ] Empty data to progress API returns 200 (not 400)
- [ ] Completed items are properly merged
- [ ] Progress percentage is calculated correctly
- [ ] Complete-item endpoint returns progress percentage
- [ ] Get my progress returns structured data by type
- [ ] Duplicate gamification earn returns 200 with already_earned flag
- [ ] Profile endpoint includes has_high_fived_today field
- [ ] All responses maintain backward compatibility

## Common Issues

### Issue: 400 Bad Request on empty data
**Solution**: Ensure you're using the updated ProgressController with empty data handling

### Issue: completed_items not merging
**Solution**: Check that the normalize_array helper is working correctly

### Issue: progress_percentage always 0
**Solution**: Check if content has total_items meta or if default values are configured

### Issue: Gamification still returns 400 on duplicate
**Solution**: Ensure EventDispatcher changes are deployed and ScoreService.getDailyScore() is public

## Success Criteria

All test scenarios should pass with:
✅ Correct HTTP status codes (200 for success, not 400 for already-earned)
✅ Expected response structures with new fields
✅ Proper data merging for completed_items
✅ Accurate progress percentage calculations
✅ Idempotent behavior (duplicate requests handled gracefully)
