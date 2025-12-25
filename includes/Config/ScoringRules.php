<?php
/**
 * Rejimde Scoring Rules Configuration
 * 
 * Merkezi puan kuralları ve gamification ayarları
 */

return [
    // LOGIN
    'login_success' => [
        'points' => 2,
        'daily_limit' => 1,
        'requires_streak' => true,
        'label' => 'Günlük Giriş'
    ],

    // BLOG
    'blog_points_claimed' => [
        'points' => ['sticky' => 50, 'normal' => 10],
        'daily_limit' => 5,
        'per_entity_limit' => 1,
        'label' => 'Blog Okuma'
    ],

    // DIET
    'diet_started' => [
        'points' => 5,
        'per_entity_limit' => 1,
        'label' => 'Diyet Başlatma'
    ],
    'diet_completed' => [
        'points' => 'dynamic', // entity meta'dan al
        'per_entity_limit' => 1,
        'label' => 'Diyet Tamamlama'
    ],

    // EXERCISE
    'exercise_started' => [
        'points' => 3,
        'per_entity_limit' => 1,
        'label' => 'Egzersiz Başlatma'
    ],
    'exercise_completed' => [
        'points' => 'dynamic',
        'per_entity_limit' => 1,
        'label' => 'Egzersiz Tamamlama'
    ],

    // CALCULATOR
    'calculator_saved' => [
        'points' => 10,
        'per_entity_limit' => 1, // calculator type başına 1 kez
        'label' => 'Hesaplayıcı Kullanımı'
    ],

    // RATING
    'rating_submitted' => [
        'points' => 20,
        'per_entity_limit' => 1, // user + target başına 1 kez
        'label' => 'Uzman Değerlendirme'
    ],

    // COMMENT
    'comment_created' => [
        'points' => 2,
        'per_entity_limit' => 1,
        'label' => 'Yorum Yapma'
    ],
    'comment_liked' => [
        'points' => 0, // Liker puan almaz, sadece log
        'label' => 'Yorum Beğenme'
    ],

    // FOLLOW
    'follow_accepted' => [
        'points' => ['follower' => 1, 'followed' => 1],
        'per_entity_limit' => 1, // aynı pair 1 kez
        'label' => 'Takip'
    ],

    // HIGHFIVE
    'highfive_sent' => [
        'points' => 1,
        'daily_pair_limit' => 1, // sender+receiver+day tekilleştirme
        'label' => 'Beşlik Çakma'
    ],

    // PROFILE (0 puan, sadece log)
    'profile_weight_updated' => ['points' => 0, 'label' => 'Kilo Güncelleme'],
    'profile_goal_updated' => ['points' => 0, 'label' => 'Hedef Güncelleme'],
    'profile_target_type_updated' => ['points' => 0, 'label' => 'Hedef Tipi Güncelleme'],
    'profile_activity_level_updated' => ['points' => 0, 'label' => 'Aktivite Seviyesi Güncelleme'],

    // WATER/STEPS/MEAL (Feature flag ile kontrol)
    'water_added' => [
        'points' => 1, // 200ml başına
        'daily_limit' => 15, // max 3 litre = 15x200ml
        'feature_flag' => 'enable_water_tracking',
        'label' => 'Su İçme'
    ],
    'steps_logged' => [
        'points' => 5, // her sync
        'daily_limit' => 1,
        'feature_flag' => 'enable_steps_tracking',
        'label' => 'Adım Senkronizasyonu'
    ],
    'meal_photo_uploaded' => [
        'points' => 15,
        'daily_limit' => 5,
        'feature_flag' => 'enable_meal_photos',
        'label' => 'Öğün Fotoğrafı'
    ],

    // CIRCLE
    'circle_created' => [
        'points' => 0, // opsiyonel, feature flag
        'feature_flag' => 'enable_circle_creation_points',
        'label' => 'Circle Oluşturma'
    ],
    'circle_joined' => [
        'points' => 0, // sadece log
        'label' => 'Circle Katılım'
    ],

    // MILESTONE TANIMLARI
    'comment_like_milestones' => [
        3 => 1, 7 => 1, 10 => 2, 25 => 2,
        50 => 5, 100 => 5, 150 => 5
        // 150+ için her 50'de +5 (kod içinde hesaplanır)
    ],

    'streak_bonuses' => [
        7 => 10,
        14 => 25,
        30 => 50,
        60 => 100,
        90 => 150
    ],

    // FEATURE FLAGS (varsayılan değerler)
    'feature_flags' => [
        'enable_water_tracking' => false,
        'enable_steps_tracking' => false,
        'enable_meal_photos' => true,
        'enable_circle_creation_points' => false,
        'enable_daily_score_cap' => false, // günlük max puan limiti
        'daily_score_cap_value' => 500
    ]
];
