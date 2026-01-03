<?php
/**
 * Badge Definitions with Rule Engine Conditions
 */
return [
    // DAVRANIŞ ROZETLERİ (Behavior)
    'early_bird' => [
        'title' => 'Erken Kuş',
        'description' => '10 sabah egzersizi tamamla (09:00 öncesi)',
        'icon' => '🌅',
        'category' => 'behavior',
        'tier' => 'bronze',
        'max_progress' => 10,
        'conditions' => [
            'type' => 'COUNT',
            'event' => 'exercise_completed',
            'context_filter' => ['time_of_day' => 'morning'], // 06:00-09:00
        ],
    ],
    'water_keeper' => [
        'title' => 'Su Ustası',
        'description' => '14 gün su hedefini tamamla',
        'icon' => '💧',
        'category' => 'behavior',
        'tier' => 'bronze',
        'max_progress' => 14,
        'conditions' => [
            'type' => 'COUNT_UNIQUE_DAYS',
            'event' => 'water_goal_reached',
        ],
    ],
    'consistency_master' => [
        'title' => 'Tutarlılık Ustası',
        'description' => '30 gün streak',
        'icon' => '🔥',
        'category' => 'behavior',
        'tier' => 'gold',
        'max_progress' => 30,
        'conditions' => [
            'type' => 'STREAK',
            'streak_type' => 'daily_login',
            'target' => 30,
        ],
    ],
    
    // DİSİPLİN ROZETLERİ (Discipline)
    'weekly_champion' => [
        'title' => 'Haftalık Şampiyon',
        'description' => '4 hafta üst üste haftalık görevi tamamla',
        'icon' => '🏆',
        'category' => 'discipline',
        'tier' => 'gold',
        'max_progress' => 4,
        'conditions' => [
            'type' => 'CONSECUTIVE_WEEKS',
            'event' => 'weekly_task_completed',
            'target' => 4,
        ],
    ],
    'monthly_grinder' => [
        'title' => 'Aylık Savaşçı',
        'description' => 'Bir ayda 50 görev tamamla',
        'icon' => '⚔️',
        'category' => 'discipline',
        'tier' => 'silver',
        'max_progress' => 50,
        'conditions' => [
            'type' => 'COUNT_IN_PERIOD',
            'event' => 'task_completed',
            'period' => 'monthly',
            'target' => 50,
        ],
    ],
    'comeback_kid' => [
        'title' => 'Geri Dönüş',
        'description' => '7+ gün ara sonrası geri dön ve 3 gün aktif kal',
        'icon' => '🔄',
        'category' => 'discipline',
        'tier' => 'bronze',
        'max_progress' => 3,
        'conditions' => [
            'type' => 'COMEBACK',
            'min_gap_days' => 7,
            'active_days_after' => 3,
        ],
    ],
    
    // SOSYAL ROZETLERİ (Social)
    'team_player' => [
        'title' => 'Takım Oyuncusu',
        'description' => '3 farklı circle görevine en az %10 katkı sağla',
        'icon' => '🤝',
        'category' => 'social',
        'tier' => 'silver',
        'max_progress' => 3,
        'conditions' => [
            'type' => 'CIRCLE_CONTRIBUTION',
            'min_contribution_percent' => 10,
            'unique_tasks' => 3,
        ],
    ],
    'motivator' => [
        'title' => 'Motivatör',
        'description' => '10 farklı kişiye high-five veya yorum yap',
        'icon' => '🙌',
        'category' => 'social',
        'tier' => 'bronze',
        'max_progress' => 10,
        'conditions' => [
            'type' => 'COUNT_UNIQUE_USERS',
            'events' => ['highfive_sent', 'comment_created'],
        ],
    ],
    'circle_hero' => [
        'title' => 'Circle Kahramanı',
        'description' => 'Circle görevini son 24 saatte %20+ katkıyla tamamlat',
        'icon' => '🦸',
        'category' => 'social',
        'tier' => 'gold',
        'max_progress' => 1,
        'conditions' => [
            'type' => 'CIRCLE_HERO',
            'min_contribution_percent' => 20,
            'completion_window_hours' => 24,
        ],
    ],
    
    // MİLESTONE ROZETLERİ
    'first_week' => [
        'title' => 'İlk Hafta',
        'description' => 'İlk haftalık görevi tamamla',
        'icon' => '🎯',
        'category' => 'milestone',
        'tier' => 'bronze',
        'max_progress' => 1,
        'conditions' => [
            'type' => 'COUNT',
            'event' => 'weekly_task_completed',
            'target' => 1,
        ],
    ],
    'century' => [
        'title' => 'Yüzüncü',
        'description' => 'Toplam 100 görev tamamla',
        'icon' => '💯',
        'category' => 'milestone',
        'tier' => 'gold',
        'max_progress' => 100,
        'conditions' => [
            'type' => 'COUNT',
            'event' => 'task_completed',
            'target' => 100,
        ],
    ],
];
