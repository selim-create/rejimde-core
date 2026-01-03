# Task & Badge Admin Panel - Architecture Diagram

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     WordPress Admin Interface                    │
└─────────────────────────────────────────────────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    │                         │
                    ▼                         ▼
         ┌─────────────────┐      ┌─────────────────┐
         │  Görevler Menu  │      │  Rozetler Menu  │
         │  (Tasks Admin)  │      │  (Badge CPT)    │
         └─────────────────┘      └─────────────────┘
                    │                         │
         ┌──────────┼──────────┐             │
         │          │          │             │
         ▼          ▼          ▼             ▼
    ┌───────┐  ┌───────┐  ┌───────┐   ┌──────────┐
    │Config │  │Dynamic│  │ New   │   │  Badge   │
    │ Tasks │  │ Tasks │  │ Task  │   │  Editor  │
    └───────┘  └───────┘  └───────┘   └──────────┘
         │          │          │             │
         │          │          │             │
         └──────────┴──────────┴─────────────┘
                          │
                          ▼
         ┌────────────────────────────────────┐
         │     TaskAdminPage.php              │
         │  - Tab routing                     │
         │  - AJAX handlers                   │
         │  - Asset enqueuing                 │
         └────────────────────────────────────┘
                          │
         ┌────────────────┴─────────────────┐
         │                                  │
         ▼                                  ▼
┌──────────────────┐              ┌──────────────────┐
│   TaskService    │              │  BadgeService    │
│                  │              │                  │
│ - getAllTask     │              │ - getAllBadges   │
│   Definitions()  │              │   (merged)       │
│ - createTask()   │              │                  │
│ - updateTask()   │              │                  │
│ - deleteTask()   │              │                  │
│ - toggleTask     │              │                  │
│   Status()       │              │                  │
└──────────────────┘              └──────────────────┘
         │                                  │
         │                                  │
    ┌────┴─────┐                      ┌────┴─────┐
    │          │                      │          │
    ▼          ▼                      ▼          ▼
┌───────┐  ┌──────┐              ┌───────┐  ┌──────┐
│Config │  │ DB   │              │Config │  │ CPT  │
│Tasks  │  │Tasks │              │Badges │  │Posts │
└───────┘  └──────┘              └───────┘  └──────┘
    │          │                      │          │
    ▼          ▼                      ▼          ▼
┌─────────────────────┐        ┌──────────────────┐
│ TaskDefinitions.php │        │  BadgeRules.php  │
└─────────────────────┘        └──────────────────┘
                │                        │
                ▼                        ▼
┌────────────────────────────────────────────────┐
│     wp_rejimde_task_definitions Table          │
│ - slug, title, task_type, target_value        │
│ - scoring_event_types (JSON)                  │
│ - reward_score, badge_contribution            │
└────────────────────────────────────────────────┘
                                │
                                ▼
┌────────────────────────────────────────────────┐
│        wp_posts (post_type=rejimde_badge)      │
│        + wp_postmeta                           │
│ - badge_category, badge_tier                  │
│ - max_progress, badge_icon                    │
│ - badge_conditions (JSON)                     │
└────────────────────────────────────────────────┘
```

## Data Flow - Create Task

```
┌──────────────┐
│ User fills   │
│ task form    │
└──────┬───────┘
       │
       ▼
┌──────────────────┐
│ Auto-generate    │
│ slug from title  │
│ (Turkish chars)  │
└──────┬───────────┘
       │
       ▼
┌──────────────────┐
│ Submit via AJAX  │
│ + nonce + data   │
└──────┬───────────┘
       │
       ▼
┌──────────────────┐
│ TaskAdminPage    │
│ ajax_save_task() │
└──────┬───────────┘
       │
       ├─→ Verify nonce
       ├─→ Check capability (manage_options)
       ├─→ Sanitize input
       │
       ▼
┌──────────────────┐
│ TaskService      │
│ createTask()     │
└──────┬───────────┘
       │
       ├─→ Validate required fields
       ├─→ Check slug uniqueness
       ├─→ Prepare data
       │
       ▼
┌──────────────────┐
│ $wpdb->insert()  │
│ to database      │
└──────┬───────────┘
       │
       ▼
┌──────────────────┐
│ Return task_id   │
│ or false         │
└──────┬───────────┘
       │
       ▼
┌──────────────────┐
│ Show success msg │
│ Redirect to list │
└──────────────────┘
```

## Data Flow - Get All Tasks (Hybrid)

```
┌──────────────────┐
│ TaskService::    │
│ getAllTask       │
│ Definitions()    │
└──────┬───────────┘
       │
       ├──────────────────────┐
       │                      │
       ▼                      ▼
┌────────────┐         ┌────────────┐
│ Read       │         │ Query      │
│ Config PHP │         │ Database   │
│ File       │         │ Table      │
└─────┬──────┘         └─────┬──────┘
      │                      │
      │ TaskDefinitions.php  │ wp_rejimde_task_definitions
      │                      │
      ▼                      ▼
┌────────────┐         ┌────────────┐
│ Config     │         │ DB Tasks   │
│ Tasks      │         │            │
│ (array)    │         │ (array)    │
└─────┬──────┘         └─────┬──────┘
      │                      │
      │ source='config'      │ source='database'
      │                      │
      └──────────┬───────────┘
                 │
                 ▼
         ┌───────────────┐
         │ Merge arrays  │
         │ (DB overrides │
         │  config slug) │
         └───────┬───────┘
                 │
                 ▼
         ┌───────────────┐
         │ Return merged │
         │ task array    │
         └───────────────┘
```

## Badge Condition Types

```
┌─────────────────────────────────────────────────┐
│              Condition Selector                  │
└──────────┬──────────────────────────────────────┘
           │
    ┌──────┴──────┬──────────┬─────────┐
    │             │          │         │
    ▼             ▼          ▼         ▼
┌────────┐  ┌──────────┐ ┌────────┐ ┌──────────┐
│ Simple │  │Progressive│ │Streak  │ │Advanced  │
└───┬────┘  └────┬─────┘ └───┬────┘ └────┬─────┘
    │            │            │           │
    ▼            ▼            ▼           ▼
┌────────────────────────────────────────────────┐
│               Saved as JSON in                 │
│           badge_conditions meta                │
└────────────────────────────────────────────────┘
    │
    ▼
{
  "type": "COUNT",           OR  "COUNT_UNIQUE_DAYS"  OR  "STREAK"
  "event": "...",
  "target": 10,
  "period": "...",
  "consecutive": true/false,
  "streak_type": "..."
}
```

## Component Interaction

```
┌────────────────────────────────────────────────────┐
│                   WordPress Core                    │
├────────────────────────────────────────────────────┤
│                                                     │
│  ┌──────────────────────────────────────────────┐ │
│  │            Admin Interface Layer              │ │
│  │  ┌────────────────┐  ┌──────────────────┐   │ │
│  │  │ TaskAdminPage  │  │  Badge PostType  │   │ │
│  │  │   (PHP/HTML)   │  │   (Meta Boxes)   │   │ │
│  │  └────────────────┘  └──────────────────┘   │ │
│  └──────────────────────────────────────────────┘ │
│                         │                          │
│  ┌──────────────────────┼──────────────────────┐ │
│  │        Assets Layer  │                       │ │
│  │  ┌──────────────┐    │  ┌──────────────┐   │ │
│  │  │task-admin.css│    │  │task-admin.js │   │ │
│  │  └──────────────┘    │  └──────────────┘   │ │
│  └──────────────────────┼──────────────────────┘ │
│                         │                          │
│  ┌──────────────────────┼──────────────────────┐ │
│  │      Service Layer   │                       │ │
│  │  ┌──────────────┐    │  ┌──────────────┐   │ │
│  │  │ TaskService  │    │  │BadgeService  │   │ │
│  │  └──────────────┘    │  └──────────────┘   │ │
│  └──────────────────────┼──────────────────────┘ │
│                         │                          │
│  ┌──────────────────────┼──────────────────────┐ │
│  │       Data Layer     │                       │ │
│  │  ┌──────────────┐    │  ┌──────────────┐   │ │
│  │  │Config Files  │    │  │   Database   │   │ │
│  │  │  .php        │    │  │  wp_tables   │   │ │
│  │  └──────────────┘    │  └──────────────┘   │ │
│  └──────────────────────┴──────────────────────┘ │
│                                                     │
└────────────────────────────────────────────────────┘
```

## Security Layers

```
┌─────────────────────────────────────┐
│         User Request                 │
└──────────────┬──────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│  Layer 1: WordPress Authentication   │
│  - is_admin() check                  │
│  - User session validation           │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│  Layer 2: Capability Check           │
│  - manage_options required           │
│  - Role verification                 │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│  Layer 3: Nonce Verification         │
│  - wp_nonce_field()                  │
│  - wp_verify_nonce()                 │
│  - check_ajax_referer()              │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│  Layer 4: Input Sanitization         │
│  - sanitize_text_field()             │
│  - sanitize_textarea_field()         │
│  - sanitize_title()                  │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│  Layer 5: SQL Injection Protection   │
│  - $wpdb->prepare()                  │
│  - Parameterized queries             │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│  Layer 6: Output Escaping            │
│  - esc_html()                        │
│  - esc_attr()                        │
│  - esc_url()                         │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│         Secure Response              │
└──────────────────────────────────────┘
```

## File Structure Tree

```
rejimde-core/
├── includes/
│   ├── Admin/
│   │   ├── TaskAdminPage.php          ★ NEW
│   │   ├── CoreSettings.php
│   │   ├── VerificationPage.php
│   │   ├── ImporterPage.php
│   │   └── MascotSettings.php
│   │
│   ├── Services/
│   │   ├── TaskService.php            ★ ENHANCED
│   │   ├── BadgeService.php           ★ ENHANCED
│   │   ├── PeriodService.php
│   │   ├── BadgeRuleEngine.php
│   │   └── ...
│   │
│   ├── PostTypes/
│   │   ├── Badge.php                  ★ ENHANCED
│   │   ├── Plan.php
│   │   └── ...
│   │
│   ├── Config/
│   │   ├── TaskDefinitions.php        (source)
│   │   ├── BadgeRules.php             (source)
│   │   └── ScoringRules.php           (reference)
│   │
│   └── Core/
│       ├── Loader.php                 ★ UPDATED
│       └── ...
│
├── assets/
│   └── admin/
│       ├── css/
│       │   └── task-admin.css         ★ NEW
│       └── js/
│           └── task-admin.js          ★ NEW
│
├── TASK_BADGE_ADMIN_GUIDE.md          ★ NEW
├── TASK_BADGE_QUICK_REFERENCE.md      ★ NEW
└── TASK_BADGE_ARCHITECTURE.md         ★ THIS FILE

Legend:
★ NEW = New file created
★ ENHANCED = Significant changes
★ UPDATED = Minor updates
```

## Version History

### v1.0.0 (2026-01-03)
- Initial implementation
- Task Admin Panel with 3 tabs
- Badge PostType enhancements
- Hybrid config/database system
- Full documentation suite

---

**Status**: Production Ready ✅
**PHP Version**: 8.0+
**WordPress Version**: 5.0+
**Database**: MySQL/MariaDB
