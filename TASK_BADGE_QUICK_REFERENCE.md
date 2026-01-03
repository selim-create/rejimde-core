# Quick Reference - Task & Badge Admin Panel

## 🎯 Access Points

### Task Admin Panel
**Path**: WordPress Admin → Görevler
**Menu Position**: 23 (below Comments)
**Icon**: dashicons-clipboard

### Badge PostType
**Path**: WordPress Admin → Rozetler
**Menu Position**: 22
**Icon**: dashicons-awards

## 📊 Task Management

### Tab Structure
```
┌─────────────────────────────────────────┐
│ Config Görevleri │ Dinamik Görevler │ Yeni Görev Ekle │
└─────────────────────────────────────────┘
```

### Task Fields
```
Required:
- Başlık (Title)
- Görev Tipi (daily/weekly/monthly/circle/mentor)
- Hedef Değer (Target Value)
- Ödül Puanı (Reward Points)

Optional:
- Slug (auto-generated)
- Açıklama (Description)
- İlgili Event Tipleri (Event Types)
- Rozet Katkısı (Badge Contribution %)
- İlişkili Rozet (Related Badge)
- Aktif mi? (Active status)
```

### Task Types
| Type | Icon | Description |
|------|------|-------------|
| daily | 🟢 | Günlük görevler |
| weekly | 🔵 | Haftalık görevler |
| monthly | 🟠 | Aylık görevler |
| circle | 🟣 | Circle görevleri |
| mentor | ⚫ | Mentor görevleri |

## 🏅 Badge Management

### Badge Fields
```
Basic Info:
- Kategori (behavior/discipline/social/milestone)
- Tier (bronze/silver/gold/platinum)
- Max Progress (1 = single, >1 = progressive)
- İkon (Emoji or dashicon)

Conditions:
- Koşul Tipi (simple/progressive/streak/advanced)
- Event parameters based on type
```

### Badge Categories
| Category | Icon | Description |
|----------|------|-------------|
| behavior | 🟢 | Davranış rozetleri |
| discipline | 🔵 | Disiplin rozetleri |
| social | 🟣 | Sosyal rozetleri |
| milestone | 🟡 | Milestone rozetleri |

### Badge Tiers
| Tier | Icon | Description |
|------|------|-------------|
| bronze | 🥉 | Bronze seviye |
| silver | 🥈 | Silver seviye |
| gold | 🥇 | Gold seviye |
| platinum | 💎 | Platinum seviye |

### Condition Types
```
1. Simple (Basit)
   - Single event type
   - Target count
   Example: 10 exercises completed

2. Progressive
   - Event counting over period
   - Optional consecutive requirement
   Example: Exercise on 30 unique days

3. Streak
   - Streak-based achievement
   - Daily/exercise/nutrition types
   Example: 7-day login streak

4. Advanced (Gelişmiş)
   - Direct JSON editing
   - Full rule engine access
   Example: Complex multi-condition rules
```

## 🔌 PHP API Examples

### Tasks
```php
$taskService = new \Rejimde\Services\TaskService();

// Get all tasks
$tasks = $taskService->getAllTaskDefinitions();

// Get weekly tasks only
$weeklyTasks = $taskService->getAllTaskDefinitions('weekly');

// Create task
$id = $taskService->createTask([
    'slug' => 'summer_workout',
    'title' => 'Summer Workout',
    'task_type' => 'weekly',
    'target_value' => 5,
    'scoring_event_types' => ['exercise_completed'],
    'reward_score' => 50,
    'is_active' => 1
]);

// Toggle status
$newStatus = $taskService->toggleTaskStatus($id);

// Delete task
$taskService->deleteTask($id);
```

### Badges
```php
$badgeService = new \Rejimde\Services\BadgeService();

// Get all badges (config + PostType merged)
$badges = $badgeService->getAllBadges();

// Filter by source
foreach ($badges as $badge) {
    if ($badge['source'] === 'config') {
        // Config badge (read-only)
    } else if ($badge['source'] === 'post_type') {
        // PostType badge (editable)
    }
}
```

## 🔒 Security Checklist

✅ Nonce verification on all forms
✅ `manage_options` capability required
✅ Input sanitization (`sanitize_text_field`, etc.)
✅ Output escaping (`esc_html`, `esc_attr`, etc.)
✅ SQL injection protection (`$wpdb->prepare()`)
✅ AJAX nonce validation
✅ XSS prevention

## 🎨 CSS Classes

### Task Type Badges
```css
.task-type-badge
.task-type-daily (green)
.task-type-weekly (blue)
.task-type-monthly (orange)
.task-type-circle (purple)
.task-type-mentor (gray)
```

### Status Indicators
```css
.status-active (green)
.status-inactive (gray)
```

### Condition Panels
```css
.condition-panel
#simple_conditions
#progressive_conditions
#streak_conditions
#advanced_conditions
```

## 📝 JavaScript API

### Auto-slug Generation
```javascript
// Triggered on title blur
// Converts: "Yaz Kampanyası" → "yaz_kampanyasi"
// Handles Turkish characters: ğ→g, ü→u, ş→s, ı→i, ö→o, ç→c
```

### AJAX Actions
```javascript
// Save task
action: 'rejimde_save_task'

// Delete task
action: 'rejimde_delete_task'
params: { task_id }

// Toggle status
action: 'rejimde_toggle_task'
params: { task_id }
```

## 🗄️ Database Tables

### Tasks
```sql
wp_rejimde_task_definitions
- id, slug, title, description
- task_type, target_value
- scoring_event_types (JSON)
- reward_score, badge_progress_contribution
- reward_badge_id, is_active
```

### Badges (PostType)
```sql
wp_posts (post_type = 'rejimde_badge')
wp_postmeta (keys):
- badge_category
- badge_tier
- max_progress
- badge_icon
- badge_conditions (JSON)
- condition_type
```

## 🚀 Quick Start

### Create Your First Task
1. Go to **Görevler → Yeni Görev Ekle**
2. Enter title: "İlk Görevim"
3. Select type: "daily"
4. Set target: 1
5. Set reward: 10
6. Click "Görevi Kaydet"

### Create Your First Badge
1. Go to **Rozetler → Yeni Rozet Ekle**
2. Enter title: "İlk Rozetim"
3. Select category: "milestone"
4. Select tier: "bronze"
5. Set max progress: 1
6. Choose condition type: "simple"
7. Configure condition
8. Click "Yayımla"

## 🐛 Troubleshooting

**Menu not showing?**
- Check user has `manage_options` capability
- Clear WordPress cache
- Check Loader.php registration

**AJAX not working?**
- Check browser console for errors
- Verify nonce in network tab
- Check user permissions

**Styles not loading?**
- Clear browser cache
- Check file permissions
- Verify REJIMDE_URL constant

**Form not saving?**
- Check nonce field
- Verify post_type on save
- Check for JavaScript errors

## 📚 File Locations

```
includes/
├── Admin/
│   └── TaskAdminPage.php          (main admin page)
├── Services/
│   ├── TaskService.php            (task CRUD)
│   └── BadgeService.php           (badge merging)
├── PostTypes/
│   └── Badge.php                  (badge meta boxes)
└── Core/
    └── Loader.php                 (registration)

assets/
└── admin/
    ├── css/
    │   └── task-admin.css         (styling)
    └── js/
        └── task-admin.js          (interactions)
```

## ✨ Features at a Glance

✅ Hybrid architecture (config + database)
✅ Full CRUD for tasks
✅ 4 badge condition types
✅ Auto-slug generation
✅ AJAX operations
✅ Turkish character support
✅ Visual category/tier indicators
✅ Progressive badge support
✅ Admin list columns
✅ Form value preservation
✅ Security best practices
✅ Backwards compatible

---

**Version**: 1.0
**Date**: 2026-01-03
**Status**: Production Ready ✅
