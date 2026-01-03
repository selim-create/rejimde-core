<?php
/**
 * Default Task Definitions
 * These are seeded to database on plugin activation
 */
return [
    // GÜNLÜK GÖREVLER
    'daily_water' => [
        'title' => 'Su hedefini tamamla',
        'description' => 'Günlük su hedefinin %80\'ini tamamla',
        'task_type' => 'daily',
        'target_value' => 1,
        'scoring_event_types' => ['water_goal_reached'],
        'reward_score' => 5,
    ],
    'daily_exercise' => [
        'title' => '10 dakika hareket',
        'description' => 'En az 10 dakikalık bir egzersiz yap',
        'task_type' => 'daily',
        'target_value' => 1,
        'scoring_event_types' => ['exercise_completed'],
        'reward_score' => 10,
    ],
    'daily_content' => [
        'title' => '1 içerik oku',
        'description' => 'Bugün en az 1 blog veya tarif oku',
        'task_type' => 'daily',
        'target_value' => 1,
        'scoring_event_types' => ['blog_points_claimed'],
        'reward_score' => 5,
    ],
    
    // HAFTALIK GÖREVLER
    'weekly_4_exercise' => [
        'title' => '4 gün egzersiz',
        'description' => 'Bu hafta 4 farklı gün egzersiz tamamla',
        'task_type' => 'weekly',
        'target_value' => 4,
        'scoring_event_types' => ['exercise_completed'],
        'reward_score' => 25,
        'badge_progress_contribution' => 25, // weekly_champion rozetine %25 katkı
    ],
    'weekly_5_nutrition' => [
        'title' => '5 gün beslenme takibi',
        'description' => 'Bu hafta 5 gün kalori hedefinde kal',
        'task_type' => 'weekly',
        'target_value' => 5,
        'scoring_event_types' => ['nutrition_goal_reached', 'diet_completed'],
        'reward_score' => 30,
    ],
    'weekly_3_mindful' => [
        'title' => '3 gün zihinsel egzersiz',
        'description' => 'Bu hafta 3 farklı gün meditasyon veya nefes egzersizi yap',
        'task_type' => 'weekly',
        'target_value' => 3,
        'scoring_event_types' => ['mindful_exercise_completed'],
        'reward_score' => 20,
    ],
    
    // AYLIK GÖREVLER
    'monthly_20_active_days' => [
        'title' => '20 aktif gün',
        'description' => 'Bu ay 20 gün aktif ol',
        'task_type' => 'monthly',
        'target_value' => 20,
        'scoring_event_types' => ['login_success'],
        'reward_score' => 100,
        'badge_progress_contribution' => 100, // monthly_warrior rozetini kazandırır
    ],
    'monthly_50_tasks' => [
        'title' => '50 görev tamamla',
        'description' => 'Bu ay toplam 50 görev (günlük/haftalık/circle) tamamla',
        'task_type' => 'monthly',
        'target_value' => 50,
        'scoring_event_types' => ['task_completed'],
        'reward_score' => 150,
    ],
    
    // CIRCLE GÖREVLER
    'circle_150_exercise' => [
        'title' => 'Toplam 150 egzersiz',
        'description' => 'Circle olarak toplam 150 egzersiz tamamlayın',
        'task_type' => 'circle',
        'target_value' => 150,
        'scoring_event_types' => ['exercise_completed'],
        'reward_score' => 50, // Her üyeye
    ],
    'circle_300k_steps' => [
        'title' => '300.000 adım',
        'description' => 'Circle olarak toplam 300.000 adım atın',
        'task_type' => 'circle',
        'target_value' => 300000,
        'scoring_event_types' => ['steps_logged'],
        'reward_score' => 75,
    ],
    'circle_20_day_streak' => [
        'title' => '20 gün aktif circle',
        'description' => '20 gün üst üste en az 1 üye aktif olsun',
        'task_type' => 'circle',
        'target_value' => 20,
        'scoring_event_types' => ['circle_daily_active'],
        'reward_score' => 100,
    ],
];
