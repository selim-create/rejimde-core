<?php
/**
 * Notification Types Configuration
 * 
 * Defines all notification templates and their metadata
 */

return [
    // POINTS NOTIFICATIONS
    'points_earned' => [
        'category' => 'points',
        'icon' => 'fa-coins',
        'title' => '{points} puan kazandın!',
        'body' => '{event_label} tamamladın.',
        'expires_days' => 7
    ],
    
    'daily_limit_reached' => [
        'category' => 'points',
        'icon' => 'fa-exclamation-circle',
        'title' => 'Günlük limit doldu',
        'body' => '{event_label} için bugünkü puan limitine ulaştın.',
        'expires_days' => 1
    ],
    
    // SYSTEM NOTIFICATIONS
    'streak_continued' => [
        'category' => 'system',
        'icon' => 'fa-fire',
        'title' => '{streak_count} günlük seri devam ediyor! 🔥',
        'body' => 'Harika gidiyorsun! Serisini bozmamak için yarın da giriş yap.',
        'expires_days' => 2
    ],
    
    'streak_milestone' => [
        'category' => 'system',
        'icon' => 'fa-trophy',
        'title' => '{streak_count} günlük seri! +{bonus_points} bonus puan! 🏆',
        'body' => 'Muhteşem bir başarı! Serisini sürdürmeye devam et.',
        'expires_days' => 7
    ],
    
    'streak_broken' => [
        'category' => 'system',
        'icon' => 'fa-heart-broken',
        'title' => 'Serin kırıldı 💔',
        'body' => 'Üzülme! Bugün yeniden başlayabilirsin.',
        'expires_days' => 3
    ],
    
    'grace_used' => [
        'category' => 'system',
        'icon' => 'fa-shield-alt',
        'title' => 'Hoşgörü hakkı kullanıldı',
        'body' => 'Bu hafta {remaining} hoşgörü hakkın kaldı.',
        'expires_days' => 2
    ],
    
    // LEVEL NOTIFICATIONS
    'level_up' => [
        'category' => 'level',
        'icon' => 'fa-level-up-alt',
        'title' => 'Seviye atladın! 🎉',
        'body' => '{old_rank} → {new_rank} oldun!',
        'expires_days' => 30
    ],
    
    'rank_changed' => [
        'category' => 'level',
        'icon' => 'fa-medal',
        'title' => 'Sıralamanda değişiklik!',
        'body' => 'Haftalık sıralamada {rank}. sıradasın!',
        'expires_days' => 7
    ],
    
    'badge_earned' => [
        'category' => 'level',
        'icon' => 'fa-award',
        'title' => 'Yeni rozet kazandın! 🏅',
        'body' => '{badge_name} rozetini kazandın!',
        'expires_days' => 30
    ],
    
    // SOCIAL NOTIFICATIONS
    'new_follower' => [
        'category' => 'social',
        'icon' => 'fa-user-plus',
        'title' => 'Yeni takipçi!',
        'body' => '{actor_name} seni takip etmeye başladı.',
        'action_url' => '/profile/{actor_id}',
        'expires_days' => 7
    ],
    
    'follow_accepted' => [
        'category' => 'social',
        'icon' => 'fa-user-check',
        'title' => 'Takip kabul edildi!',
        'body' => '{actor_name} takip isteğini kabul etti.',
        'action_url' => '/profile/{actor_id}',
        'expires_days' => 7
    ],
    
    'highfive_received' => [
        'category' => 'social',
        'icon' => 'fa-hand-paper',
        'title' => 'Beşlik geldi! ✋',
        'body' => '{actor_name} sana beşlik çaktı!',
        'action_url' => '/profile/{actor_id}',
        'expires_days' => 3
    ],
    
    'comment_reply' => [
        'category' => 'social',
        'icon' => 'fa-comment',
        'title' => 'Yorumuna yanıt var!',
        'body' => '{actor_name} yorumuna yanıt verdi.',
        'action_url' => '/{entity_type}/{entity_id}#comment-{comment_id}',
        'expires_days' => 7
    ],
    
    'comment_like_milestone' => [
        'category' => 'social',
        'icon' => 'fa-heart',
        'title' => 'Yorumun {like_count} beğeni aldı! 💖',
        'body' => '+{points} puan kazandın!',
        'expires_days' => 7
    ],
    
    // CIRCLE NOTIFICATIONS
    'circle_joined' => [
        'category' => 'circle',
        'icon' => 'fa-users',
        'title' => 'Circle\'a katıldın!',
        'body' => '{circle_name} circle\'ına hoş geldin!',
        'action_url' => '/circle/{entity_id}',
        'expires_days' => 7
    ],
    
    'circle_new_member' => [
        'category' => 'circle',
        'icon' => 'fa-user-plus',
        'title' => 'Circle\'a yeni üye!',
        'body' => '{actor_name}, {circle_name} circle\'ına katıldı.',
        'action_url' => '/circle/{entity_id}',
        'expires_days' => 3
    ],
    
    'circle_activity' => [
        'category' => 'circle',
        'icon' => 'fa-bell',
        'title' => 'Circle aktivitesi',
        'body' => '{circle_name} circle\'ında yeni aktivite var.',
        'action_url' => '/circle/{entity_id}',
        'expires_days' => 3
    ],
    
    // CONTENT COMPLETION
    'content_completed' => [
        'category' => 'points',
        'icon' => 'fa-check-circle',
        'title' => 'Tebrikler! ✅',
        'body' => '{content_name} içeriğini tamamladın! +{points} puan',
        'expires_days' => 7
    ],
    
    // EXPERT NOTIFICATIONS (for rejimde_pro users)
    'rating_received' => [
        'category' => 'expert',
        'icon' => 'fa-star',
        'title' => 'Yeni değerlendirme! ⭐',
        'body' => '{actor_name} seni {rating}/5 ile değerlendirdi.',
        'action_url' => '/expert/ratings',
        'expires_days' => 7
    ],
    
    'profile_view_milestone' => [
        'category' => 'expert',
        'icon' => 'fa-eye',
        'title' => 'Profilin {view_count} kez görüntülendi! 👁️',
        'body' => 'Harika! İlgi görmeye devam ediyorsun.',
        'action_url' => '/expert/metrics',
        'expires_days' => 7
    ],
    
    'client_completed' => [
        'category' => 'expert',
        'icon' => 'fa-graduation-cap',
        'title' => 'Danışan içerik tamamladı!',
        'body' => '{actor_name}, {content_name} içeriğini tamamladı.',
        'action_url' => '/expert/clients',
        'expires_days' => 7
    ],
    
    'client_activity' => [
        'category' => 'expert',
        'icon' => 'fa-chart-line',
        'title' => 'Danışan aktivitesi',
        'body' => '{actor_name} yeni aktivite kaydetti.',
        'action_url' => '/expert/clients/{actor_id}',
        'expires_days' => 3
    ],
    
    // WEEKLY RANKING (Sent by cron)
    'weekly_ranking' => [
        'category' => 'level',
        'icon' => 'fa-trophy',
        'title' => 'Haftalık sıralama açıklandı! 🏆',
        'body' => 'Bu hafta {rank}. olarak {points} puan kazandın!',
        'action_url' => '/leaderboard',
        'expires_days' => 7
    ]
];
