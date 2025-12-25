# Rejimde Core

WordPress core plugin for Rejimde.com - A comprehensive health and fitness platform.

## Overview

Rejimde Core is a WordPress plugin that provides the foundational API and gamification system for the Rejimde platform. It includes user management, content tracking, social features, and an advanced event-driven scoring system.

## Features

### 🎮 Event-Driven Gamification System
- **20+ Event Types**: Login, blog reading, diet/exercise completion, comments, follows, etc.
- **Idempotent Points**: Ensures points are awarded only once per action
- **Daily & Entity Limits**: Prevents point farming and abuse
- **Streak System**: Rewards consecutive daily logins with grace period support
- **Milestone Achievements**: Automatic bonus points for reaching milestones
- **Pro User Support**: Special handling for expert users

### 📊 Progress Tracking
- User progress for diets, exercises, blogs, and dictionary items
- Content completion tracking
- Reward claiming system
- Started/completed timestamps

### 👥 Social Features
- Follow/unfollow system
- High-five interactions
- Circle (group) creation and management
- Comment system with likes and ratings
- Expert reviews and ratings

### 🏆 Scoring & Rankings
- Real-time score updates
- Daily, weekly, and monthly leaderboards
- Level system based on total score
- Circle (team) scoring
- Automated score snapshots

### 🔄 Automated Tasks (Cron)
- Daily score snapshots
- Weekly rankings and grace period resets
- Monthly summaries
- Automatic cleanup of old event logs (90+ days)

## Installation

1. Upload the plugin to `/wp-content/plugins/rejimde-core/`
2. Activate the plugin through WordPress admin
3. Database tables will be created automatically

## Database Schema

### Core Tables
- `rejimde_measurements` - User measurements (weight, waist, etc.)
- `rejimde_daily_logs` - Daily activity logs
- `rejimde_user_progress` - Content progress tracking

### Event System Tables
- `rejimde_events` - Detailed event logs
- `rejimde_streaks` - Streak tracking with grace periods
- `rejimde_milestones` - Achievement rewards (idempotent)
- `rejimde_score_snapshots` - Periodic summaries and rankings

## API Endpoints

### Authentication
- `POST /rejimde/v1/auth/register` - User registration
- `POST /rejimde/v1/auth/login` - User login
- `POST /rejimde/v1/auth/google` - Google OAuth login

### Gamification
- `POST /rejimde/v1/gamification/earn` - Earn points
- `GET /rejimde/v1/gamification/me` - User stats
- `GET /rejimde/v1/gamification/leaderboard` - Rankings
- `GET /rejimde/v1/gamification/streak` - Streak information
- `GET /rejimde/v1/gamification/milestones` - User milestones
- `POST /rejimde/v1/gamification/milestones/log` - Log milestone achievement
- `GET /rejimde/v1/gamification/events` - Event history
- `GET /rejimde/v1/gamification/circle-account` - Circle membership and contribution

### Events
- `POST /rejimde/v1/events/dispatch` - Dispatch event for gamification

### Progress
- `GET /rejimde/v1/progress/{type}/{id}` - Get progress
- `POST /rejimde/v1/progress/{type}/{id}/start` - Mark as started
- `POST /rejimde/v1/progress/{type}/{id}/complete` - Mark as completed
- `POST /rejimde/v1/progress/blog/{id}/claim` - Claim blog reward

### Social
- `POST /rejimde/v1/profile/{id}/follow` - Follow/unfollow
- `POST /rejimde/v1/profile/{id}/high-five` - Send high-five
- `POST /rejimde/v1/comments` - Create comment
- `POST /rejimde/v1/comments/{id}/like` - Like comment

### Circles
- `GET /rejimde/v1/circles` - List circles
- `POST /rejimde/v1/circles` - Create circle
- `POST /rejimde/v1/circles/{id}/join` - Join circle

## Event System

The event-driven architecture provides a robust, scalable way to manage user actions and rewards.

### Quick Start

```php
// Dispatch an event
$dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
$result = $dispatcher->dispatch('blog_points_claimed', [
    'entity_type' => 'blog',
    'entity_id' => $post_id,
    'context' => ['is_sticky' => is_sticky($post_id)]
]);

// Check result
if ($result['success']) {
    $points = $result['points_earned'];
    $total = $result['total_score'];
}
```

### Event Types

- **Content**: `blog_points_claimed`, `diet_started`, `diet_completed`, `exercise_started`, `exercise_completed`
- **Social**: `comment_created`, `comment_liked`, `follow_accepted`, `highfive_sent`
- **System**: `login_success`, `calculator_saved`, `rating_submitted`
- **Circle**: `circle_created`, `circle_joined`

### Features

- **Idempotency**: Same event won't award points twice
- **Limits**: Daily and per-entity limits
- **Streaks**: Consecutive day tracking with grace periods
- **Milestones**: Automatic bonus points (e.g., comment likes)
- **Feature Flags**: Enable/disable specific features
- **Pro Exception**: Pro users log events but don't earn points

For detailed documentation, see [EVENT_SYSTEM_GUIDE.md](EVENT_SYSTEM_GUIDE.md)

## Configuration

### Scoring Rules

Edit `includes/Config/ScoringRules.php` to customize:
- Point values for each event
- Daily limits
- Feature flags
- Milestone thresholds
- Streak bonuses

### Feature Flags

```php
'feature_flags' => [
    'enable_water_tracking' => false,
    'enable_steps_tracking' => false,
    'enable_meal_photos' => true,
    'enable_circle_creation_points' => false,
    'enable_daily_score_cap' => false,
    'daily_score_cap_value' => 500
]
```

## User Roles

- `rejimde_user` - Standard member
- `rejimde_pro` - Expert/professional (doesn't earn points)

## Development

### File Structure

```
rejimde-core/
├── includes/
│   ├── Api/V1/           # REST API controllers
│   ├── Config/           # Configuration files
│   ├── Core/             # Core functionality
│   ├── Cron/             # Scheduled tasks
│   ├── Database/         # Database utilities
│   ├── PostTypes/        # Custom post types
│   ├── Services/         # Business logic services
│   └── Utils/            # Helper utilities
├── EVENT_SYSTEM_GUIDE.md # Event system documentation
└── rejimde-core.php      # Main plugin file
```

### Adding New Events

1. Add event definition to `includes/Config/ScoringRules.php`
2. Dispatch event in appropriate controller
3. Test with various scenarios

### Running Cron Jobs Manually

```bash
wp cron event run rejimde_create_daily_snapshots
wp cron event run rejimde_create_weekly_snapshots
wp cron event run rejimde_reset_weekly_grace
```

## Testing

Test the following scenarios:

1. **Idempotency**: Same action twice shouldn't award points twice
2. **Daily Limits**: Exceeding daily limit should be prevented
3. **Pro Users**: Pro users should log events but not earn points
4. **Streaks**: Consecutive logins should award bonuses
5. **Milestones**: Reaching thresholds should award bonus points

## Backward Compatibility

The event system maintains backward compatibility:
- Existing `rejimde_daily_logs` table is still used
- Existing `rejimde_total_score` user meta is updated
- Old API endpoints continue to work

## License

Proprietary - Hip Medya

## Support

For questions or issues, contact the development team.

## Version

1.0.2 - Event-Driven System Release