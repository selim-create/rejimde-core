<?php
namespace Rejimde\Core;

class UserMeta {

    public function register() {
        add_action('rest_api_init', [$this, 'register_user_meta']);
        add_filter('rest_prepare_user', [$this, 'add_user_info_to_rest'], 10, 3);
        add_filter('rest_prepare_comment', [$this, 'add_custom_avatar_to_comment'], 10, 3);
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

    /**
     * Add custom avatar and role information to REST API user response
     * Combines avatar_url override and role information
     * 
     * @param WP_REST_Response $response The response object
     * @param WP_User $user The user object
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response Modified response
     */
    public function add_user_info_to_rest($response, $user, $request) {
        $data = $response->get_data();
        
        // 1. Custom Avatar Logic: Use custom avatar if exists, otherwise use DiceBear
        $custom_avatar = get_user_meta($user->ID, 'avatar_url', true);
        
        if ($custom_avatar && !empty($custom_avatar)) {
            $data['avatar_url'] = $custom_avatar;
        } else {
            $data['avatar_url'] = 'https://api.dicebear.com/9.x/personas/svg?seed=' . urlencode($user->user_nicename);
        }
        
        // Override Gravatar URLs with custom avatar
        if (isset($data['avatar_urls'])) {
            foreach ($data['avatar_urls'] as $size => $url) {
                $data['avatar_urls'][$size] = $data['avatar_url'];
            }
        }
        
        // 2. Add Role Information
        $data['roles'] = (array) $user->roles;
        
        // 3. Add is_expert flag for easy frontend checking
        $data['is_expert'] = in_array('rejimde_pro', (array) $user->roles);
        
        $response->set_data($data);
        return $response;
    }

    /**
     * Add custom avatar to comment REST API response
     * Ensures comment author avatars use custom uploads or DiceBear
     * 
     * @param WP_REST_Response $response The response object
     * @param WP_Comment $comment The comment object
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response Modified response
     */
    public function add_custom_avatar_to_comment($response, $comment, $request) {
        $data = $response->get_data();
        
        // Only process if comment has a user_id (registered user comment)
        if ($comment->user_id) {
            $custom_avatar = get_user_meta($comment->user_id, 'avatar_url', true);
            
            if ($custom_avatar && !empty($custom_avatar)) {
                // Override all avatar sizes with custom avatar
                $data['author_avatar_urls'] = [
                    '24' => $custom_avatar,
                    '48' => $custom_avatar,
                    '96' => $custom_avatar,
                ];
            } else {
                // Use DiceBear as fallback
                $user_nicename = get_the_author_meta('user_nicename', $comment->user_id);
                $dicebear_url = 'https://api.dicebear.com/9.x/personas/svg?seed=' . urlencode($user_nicename);
                $data['author_avatar_urls'] = [
                    '24' => $dicebear_url,
                    '48' => $dicebear_url,
                    '96' => $dicebear_url,
                ];
            }
        }
        
        $response->set_data($data);
        return $response;
    }
}