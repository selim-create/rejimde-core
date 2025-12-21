<?php
namespace Rejimde\Core;

class UserMeta {

    public function register() {
        add_action('rest_api_init', [$this, 'register_user_meta']);
    }

    public function register_user_meta() {
        // API'de görünmesini istediğimiz tüm alanlar
        $fields = [
            // Temel Alanlar
            'age',
            'birth_date', 
            'gender', 
            'height', 
            'current_weight', 
            'target_weight', 
            'activity_level', 
            'goals', 
            'notifications',
            'avatar_url',

            // Gaming Alanlar            
            'rejimde_total_score', // YENİ
            'rejimde_level',       // YENİ
            'current_streak',       // YENİ (Henüz logic eklemedik ama yeri olsun)
            
            // Uzman (Pro)
            'profession',      // Meslek (dietitian, pt...)
            'title',           // Unvan (Dyt. Selin)
            'bio',             // Biyografi
            'branches',        // Uzmanlık alanları
            'services',        // Hizmetler
            'client_types',    // Danışan türü
            'consultation_types', // Online/Yüz yüze
            
            // Lokasyon & İletişim
            'city',            // İl
            'district',        // İlçe
            'address',         // Açık adres
            'phone',
            'brand_name',      // Kurum adı

            // Sertifika & Onay
            'certificate_url', // Dosya URL
            'certificate_status', // pending, approved, rejected
            'is_verified',     // true/false (Genel onay)
            'rating',
            'score_impact'
        ];

        foreach ($fields as $field) {
            register_rest_field('user', $field, [
                'get_callback' => function ($user) use ($field) {
                    return get_user_meta($user['id'], $field, true);
                },
                'update_callback' => function ($value, $user, $field) {
                    return update_user_meta($user->ID, $field, $value);
                },
                'schema' => [
                    'description' => "User $field",
                    'type'        => ($field === 'goals' || $field === 'notifications') ? 'object' : 'string',
                    'context'     => ['view', 'edit'],
                ],
            ]);
        }
    }
}