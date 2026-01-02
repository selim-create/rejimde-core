# RejiScore API - Testing Guide

## Overview
This guide provides instructions for testing the RejiScore backend implementation.

## API Endpoints

### 1. Get RejiScore by Expert Post ID
**Endpoint:** `GET /wp-json/rejimde/v1/experts/{post_id}/reji-score`

**Description:** Retrieves the RejiScore for an expert using their professional profile post ID.

**Example Request:**
```bash
curl -X GET "https://your-domain.com/wp-json/rejimde/v1/experts/123/reji-score"
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "reji_score": 82,
    "trust_score": 85,
    "contribution_score": 70,
    "freshness_score": 80,
    "verification_bonus": 100,
    "is_verified": true,
    "trend_percentage": 18,
    "trend_direction": "up",
    "user_rating": 4.7,
    "review_count": 128,
    "content_count": 42,
    "level": 4,
    "level_label": "Yüksek Güven"
  }
}
```

### 2. Get RejiScore by User ID
**Endpoint:** `GET /wp-json/rejimde/v1/users/{user_id}/reji-score`

**Description:** Retrieves the RejiScore for an expert using their WordPress user ID.

**Example Request:**
```bash
curl -X GET "https://your-domain.com/wp-json/rejimde/v1/users/456/reji-score"
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "reji_score": 75,
    "trust_score": 70,
    "contribution_score": 80,
    "freshness_score": 65,
    "verification_bonus": 50,
    "is_verified": false,
    "trend_percentage": -5,
    "trend_direction": "down",
    "user_rating": 4.2,
    "review_count": 45,
    "content_count": 18,
    "level": 3,
    "level_label": "İyi"
  }
}
```

### 3. Expert Detail with RejiScore
**Endpoint:** `GET /wp-json/rejimde/v1/professionals/{slug}`

**Description:** The existing expert detail endpoint now includes RejiScore data.

**Example Request:**
```bash
curl -X GET "https://your-domain.com/wp-json/rejimde/v1/professionals/ahmet-yilmaz-dyt"
```

**Additional Fields in Response:**
```json
{
  "id": 123,
  "name": "Ahmet Yılmaz",
  "slug": "ahmet-yilmaz-dyt",
  "...": "...",
  "reji_score": 82,
  "trust_score": 85,
  "contribution_score": 70,
  "freshness_score": 80,
  "trend_percentage": 18,
  "trend_direction": "up",
  "score_level": 4,
  "score_level_label": "Yüksek Güven"
}
```

## RejiScore Components

### Score Breakdown (0-100 scale)

| Component | Weight | Description |
|-----------|--------|-------------|
| **Trust Score** | 30% | Based on weighted user reviews (verified clients = 3x weight) |
| **Contribution Score** | 25% | Diet plans, exercise plans, articles, client completions |
| **Freshness Score** | 25% | Last 30 days activity and growth trends |
| **Verification Bonus** | 20% | Verified expert = 100, claimed = 50, other = 0 |

### Score Levels

| Score Range | Level | Label |
|-------------|-------|-------|
| 90-100 | 5 | Efsane |
| 80-89 | 4 | Yüksek Güven |
| 70-79 | 3 | İyi |
| 50-69 | 2 | Gelişiyor |
| 0-49 | 1 | Yeni |

### Trend Direction
- **up**: Increasing activity (positive trend percentage)
- **down**: Decreasing activity (negative trend percentage)
- **stable**: No significant change (0% trend)

## Testing Scenarios

### Scenario 1: New Expert (No Activity)
- **Expected:** reji_score ≈ 50, level = 1-2, label = "Yeni" or "Gelişiyor"
- **Components:** Default values with minimal or no reviews/content

### Scenario 2: Active Expert with Reviews
- **Expected:** reji_score ≈ 70-85, level = 3-4
- **Components:** Good trust score from reviews, some content

### Scenario 3: Verified Expert with High Activity
- **Expected:** reji_score ≈ 85-95, level = 4-5, label = "Yüksek Güven" or "Efsane"
- **Components:** verification_bonus = 100, high trust, contribution, and freshness

### Scenario 4: Expert with Declining Activity
- **Expected:** trend_percentage < 0, trend_direction = "down"
- **Components:** Lower freshness score, negative trend

## Error Responses

### Expert Not Found (Post ID)
```json
{
  "code": "expert_not_found",
  "message": "Expert user ID not found for this profile.",
  "data": {
    "status": 404
  }
}
```

### User Not Found
```json
{
  "code": "user_not_found",
  "message": "User not found.",
  "data": {
    "status": 404
  }
}
```

## Implementation Details

### Database Dependencies
- **wp_comments**: For user reviews and ratings
- **wp_commentmeta**: For rating values and verified_client status
- **wp_posts**: For content counting (diets, exercises, articles)
- **wp_rejimde_expert_metrics**: For activity tracking (optional, defaults to 50 if missing)

### User Meta Keys
- `professional_profile_id`: Links user to their expert post
- `is_verified_expert`: Verification status (1 = verified)

### Post Meta Keys
- `related_user_id` or `user_id`: Links expert post to user
- `is_claimed`: Claimed status (1 = claimed)
- `puan`: Average rating
- `onayli`: Verified status

## Performance Notes
- Scores are calculated on-demand (no caching in this version)
- Database queries are optimized with proper indexes
- Default values prevent errors when data is missing
- Safe to call even for experts without metrics data

## Future Enhancements
- Add caching layer for frequently accessed scores
- Implement scheduled recalculation for all experts
- Add score history tracking
- Provide score improvement suggestions
