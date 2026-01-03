# Task and Badge Admin Panel Implementation Guide

## Overview
This implementation adds comprehensive admin panel features for managing tasks and badges in the Rejimde Core plugin.

## Features Implemented

### 1. Task Admin Panel (`/includes/Admin/TaskAdminPage.php`)

A hybrid task management system that combines:
- **Config Tasks**: Pre-defined tasks from `TaskDefinitions.php` (read-only)
- **Dynamic Tasks**: Database-driven tasks with full CRUD operations

#### Admin Interface
Access via WordPress Admin → **Görevler** menu

**Tab 1: Config Görevleri**
- Displays tasks defined in `includes/Config/TaskDefinitions.php`
- Read-only view with information badge
- Shows: slug, title, type, target, event types, rewards

**Tab 2: Dinamik Görevler**
- Lists all database tasks
- Features:
  - Active/inactive status toggle
  - Delete functionality
  - Color-coded task type badges

**Tab 3: Yeni Görev Ekle**
- Full task creation form
- Fields:
  - Başlık (Title) - Required
  - Slug - Auto-generated from title
  - Açıklama (Description)
  - Görev Tipi - daily, weekly, monthly, circle, mentor
  - Hedef Değer (Target Value)
  - İlgili Event Tipleri - Multi-select from ScoringRules
  - Ödül Puanı (Reward Points)
  - Rozet Katkısı (Badge Progress %)
  - İlişkili Rozet (Related Badge)
  - Aktif mi? (Active checkbox)

### 2. Enhanced TaskService (`/includes/Services/TaskService.php`)

#### New Methods

**`getAllTaskDefinitions(string $type = null): array`**
- Merges config and database tasks
- Config tasks marked with `source: 'config'`
- Database tasks marked with `source: 'database'`
- Database slugs can override config slugs

**`createTask(array $data): int|false`**
- Creates new dynamic task in database
- Validates required fields
- Ensures unique slug
- Returns task ID or false

**`updateTask(int $id, array $data): bool`**
- Updates existing dynamic task
- Validates allowed fields
- Handles JSON encoding for event types

**`deleteTask(int $id): bool`**
- Permanently deletes task from database
- Returns success status

**`toggleTaskStatus(int $id): int|false`**
- Toggles is_active status (0/1)
- Returns new status or false

### 3. Enhanced Badge PostType (`/includes/PostTypes/Badge.php`)

#### New Meta Fields
- `badge_category`: behavior, discipline, social, milestone
- `badge_tier`: bronze, silver, gold, platinum
- `max_progress`: Progressive badge target (1 = single, >1 = progressive)
- `badge_icon`: Emoji or dashicon class
- `badge_conditions`: JSON rule engine conditions
- `condition_type`: simple, progressive, streak, advanced

#### Admin List Columns
- İkon - Badge icon display
- Kategori - Visual category badge
- Tier - Tier emoji (🥉🥈🥇💎)
- İlerleme - Progress status

#### Conditions Meta Box
Four condition types:

**Simple (Basit)**
- Single event type
- Target count

**Progressive**
- Event type
- Period (all_time, daily, weekly, monthly)
- Consecutive option

**Streak**
- Streak type (daily_login, daily_exercise, daily_nutrition)
- Target days

**Advanced (Gelişmiş)**
- Direct JSON editing
- Full rule engine access

### 4. Enhanced BadgeService (`/includes/Services/BadgeService.php`)

**`getAllBadges(): array`** - Updated
- Merges config badges from `BadgeRules.php`
- Adds PostType badges
- PostType badges can override config badges
- Config badges marked with `source: 'config'`
- PostType badges marked with `source: 'post_type'`

### 5. Admin Assets

**CSS (`/assets/admin/css/task-admin.css`)**
- Task type color badges
- Status indicators
- Form styling
- Condition panel styling
- Responsive table layout

**JavaScript (`/assets/admin/js/task-admin.js`)**
- Auto-slug generation from title
- AJAX form submission
- Delete confirmation dialogs
- Status toggle functionality
- Success/error message display

## Usage Examples

### Creating a Dynamic Task via Admin Panel

1. Navigate to **WordPress Admin → Görevler → Yeni Görev Ekle**
2. Fill in the form:
   - Başlık: "Yaz Kampanyası"
   - Görev Tipi: weekly
   - Hedef Değer: 5
   - Event Types: Select `exercise_completed`
   - Ödül Puanı: 50
3. Click "Görevi Kaydet"

### Creating a Badge via PostType

1. Navigate to **WordPress Admin → Rozetler → Yeni Rozet Ekle**
2. Add title and description
3. Configure in "Rozet Ayarları":
   - Kategori: Behavior
   - Tier: Gold
   - Max Progress: 10
   - Icon: 🏆
4. Configure in "Kazanma Koşulları":
   - Select condition type
   - Fill in parameters
5. Publish

### Programmatic Usage

```php
// Get all tasks (config + database merged)
$taskService = new \Rejimde\Services\TaskService();
$allTasks = $taskService->getAllTaskDefinitions();
$weeklyTasks = $taskService->getAllTaskDefinitions('weekly');

// Create a new task
$newTask = $taskService->createTask([
    'slug' => 'summer_challenge',
    'title' => 'Summer Challenge',
    'task_type' => 'weekly',
    'target_value' => 7,
    'scoring_event_types' => ['exercise_completed'],
    'reward_score' => 100,
    'is_active' => 1
]);

// Get all badges (config + PostType merged)
$badgeService = new \Rejimde\Services\BadgeService();
$allBadges = $badgeService->getAllBadges();
```

## Database Schema

### rejimde_task_definitions
Already exists. Used for dynamic tasks.

### rejimde_badge PostType
Uses WordPress post_meta for new fields:
- `badge_category`
- `badge_tier`
- `max_progress`
- `badge_icon`
- `badge_conditions`
- `condition_type`

## Plugin Bootstrap

TaskAdminPage is automatically registered in `includes/Core/Loader.php`:
- Loaded only in admin context
- Registered via `register()` method
- Hooks into `admin_menu`, `admin_init`, `admin_enqueue_scripts`

## AJAX Endpoints

All AJAX actions require `manage_options` capability and nonce verification:

- `rejimde_save_task` - Create new task
- `rejimde_delete_task` - Delete task
- `rejimde_toggle_task` - Toggle task status

## Security

- Nonce verification on all forms
- Capability checks (`manage_options`)
- Input sanitization
- SQL injection protection via $wpdb->prepare()
- XSS protection via esc_* functions

## Browser Compatibility

Admin interface requires:
- jQuery (bundled with WordPress)
- Modern browser with ES5+ support

## Future Enhancements

Potential improvements:
- Task edit functionality
- Bulk operations
- Task import/export
- Badge progress preview
- Condition builder UI
- Task analytics dashboard

## Troubleshooting

**Assets not loading?**
- Clear WordPress cache
- Check file permissions
- Verify REJIMDE_URL constant

**AJAX errors?**
- Check browser console
- Verify nonce in requests
- Check user permissions

**Database errors?**
- Verify tables exist (run activation)
- Check wpdb error logs
- Validate field names

## Changelog

### Version 1.0 (2026-01-03)
- Initial implementation
- Task Admin Panel with 3 tabs
- Badge PostType enhancements
- Hybrid config/database system
- Admin assets (CSS/JS)
