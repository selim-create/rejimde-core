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
            'location',
            'description',

            // Oyunlaştırma
            'rejimde_total_score', 
            'rejimde_rank',       
            'current_streak',
            'rejimde_earned_badges', // Rozetler (Array)

            // Sosyal (YENİ)
            'rejimde_followers', // Takipçi ID listesi
            'rejimde_following', // Takip edilen ID listesi
            'rejimde_high_fives', // Alınan beşlik sayısı
            'followers_count',
            'following_count',
            'high_fives',

            // CIRCLE (YENİ)
            'circle_id',      // Kullanıcının üye olduğu circle ID'si
            'circle_role',    // 'mentor', 'member'
            
            // Uzman (Pro)
            'profession',      // Meslek (dietitian, pt...)
            'title',           // Unvan (Dyt. Selin)
            'bio',             // Biyografi
            'branches',        // Uzmanlık alanları
            'services',        // Hizmetler
            'client_types',    // Danışan türü
            'consultation_types', // Online/Yüz yüze
            'career_start_date',  // Mesleğe başlama tarihi
            
            // Kimlik & Profil
            'motto',           // Motto / Yaklaşım (max 150 karakter)
            
            // Hizmet & Dil
            'service_languages', // Hizmet dilleri - JSON array
            
            // Mesleki Deneyim
            'education',       // Eğitim bilgileri - JSON array
            'certificates',    // Sertifikalar - JSON array
            
            // Uzmanlık & Etiketler
            'expertise_tags',  // Uzmanlık etiketleri - JSON array
            'goal_tags',       // Çalıştığı hedefler - JSON array
            'level_suitability', // Seviye uygunluğu - JSON array
            'age_groups',      // Yaş grupları - JSON array
            
            // Danışan Bilgileri
            'client_type',     // Danışan türü - string (woman, man, child, all)
            
            // Çalışmadığı Durumlar
            'excluded_cases',  // Çalışmadığı durumlar - JSON array
            'referral_note',   // Yönlendirme notu - string
            
            // Çalışma & İletişim
            'working_hours',   // Çalışma saatleri - JSON object
            'response_time',   // Yanıt süresi - string (1h, 24h, 48h, 3d)
            'communication_preference', // İletişim tercihi - JSON array
            
            // Görünürlük & Mahremiyet
            'privacy_settings', // Gizlilik ayarları - JSON object
            'kvkk_consent',    // KVKK onayı - boolean
            'emergency_disclaimer', // Acil durum uyarısı onayı - boolean
            
            // Lokasyon & İletişim
            'city',            // İl
            'district',        // İlçe
            'country',         // Ülke
            'address',         // Açık adres
            'phone',
            'brand_name',      // Kurum adı

            // Sertifika & Onay
            'certificate_url', // Dosya URL
            'certificate_status', // pending, approved, rejected
            'is_verified',     // true/false (Genel onay)
            'is_featured',     // Öne çıkarılmış mı
            'last_online',     // Son online zamanı
            'rating',
            'score_impact'
        ];

        // JSON alanları listesi
        $json_fields = [
            'goals', 
            'notifications', 
            'rejimde_earned_badges',
            'rejimde_followers',
            'rejimde_following',
            'service_languages',
            'education',
            'certificates',
            'expertise_tags',
            'goal_tags',
            'level_suitability',
            'age_groups',
            'excluded_cases',
            'working_hours',
            'communication_preference',
            'privacy_settings'
        ];

        foreach ($fields as $field) {
            register_rest_field('user', $field, [
                'get_callback' => function ($user) use ($field, $json_fields) {
                    $value = get_user_meta($user['id'], $field, true);
                    // JSON alanları için decode
                    if (in_array($field, $json_fields) && is_string($value) && !empty($value)) {
                        $decoded = json_decode($value, true);
                        return $decoded !== null ? $decoded : $value;
                    }
                    return $value;
                },
                'update_callback' => function ($value, $user, $field) use ($json_fields) {
                    // JSON alanları için encode
                    if (in_array($field, $json_fields) && (is_array($value) || is_object($value))) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                    return update_user_meta($user->ID, $field, $value);
                },
                'schema' => [
                    'description' => "User $field",
                    'type'        => $this->get_field_schema_type($field, $json_fields),
                    'context'     => ['view', 'edit'],
                ],
            ]);
        }

        // Roles alanını da expose et
        register_rest_field('user', 'roles', [
            'get_callback' => function($user) {
                $user_obj = get_userdata($user['id']);
                return $user_obj ? (array) $user_obj->roles : [];
            },
            'schema' => ['type' => 'array', 'context' => ['view', 'edit']]
        ]);
    }

    /**
     * Get the appropriate schema type for a field
     * 
     * @param string $field Field name
     * @param array $json_fields Array of fields that should be JSON
     * @return string Schema type
     */
    private function get_field_schema_type($field, $json_fields) {
        // Boolean alanlar
        if (in_array($field, ['kvkk_consent', 'emergency_disclaimer', 'is_verified', 'is_featured'])) {
            return 'boolean';
        }
        
        // Object alanlar (JSON objects)
        if (in_array($field, ['working_hours', 'privacy_settings'])) {
            return 'object';
        }
        
        // Array alanlar (JSON arrays veya basit arrays)
        if (in_array($field, $json_fields)) {
            return 'array';
        }
        
        // Varsayılan olarak string
        return 'string';
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
        
        // Avatar
        $custom_avatar = get_user_meta($user->ID, 'avatar_url', true);
        $data['avatar_url'] = $custom_avatar ?: 'https://api.dicebear.com/9.x/personas/svg?seed=' . urlencode($user->user_nicename);
        
        // Rozetleri ve Sosyal Verileri Formatla
        $earned_badges = get_user_meta($user->ID, 'rejimde_earned_badges', true);
        $data['earned_badges'] = is_array($earned_badges) ? array_map('intval', $earned_badges) : [];

        $followers = get_user_meta($user->ID, 'rejimde_followers', true);
        $data['followers_count'] = is_array($followers) ? count($followers) : 0;
        
        $following = get_user_meta($user->ID, 'rejimde_following', true);
        $data['following_count'] = is_array($following) ? count($following) : 0;

        $data['high_fives'] = (int) get_user_meta($user->ID, 'rejimde_high_fives', true);

        // Mevcut kullanıcı bu profili takip ediyor mu?
        $current_user_id = get_current_user_id();
        if ($current_user_id) {
            $data['is_following'] = is_array($followers) && in_array($current_user_id, $followers);
        } else {
            $data['is_following'] = false;
        }

        // Klan ve Rol Bilgisi
        $data['roles'] = (array) $user->roles;
        $data['is_expert'] = in_array('rejimde_pro', (array) $user->roles);
        $data['username'] = $user->user_login;
        
        $circle_id = get_user_meta($user->ID, 'circle_id', true);
        if ($circle_id) {
            $circle = get_post($circle_id);
            if ($circle && $circle->post_status === 'publish') {
                $data['circle'] = [
                    'id' => $circle->ID,
                    'name' => $circle->post_title,
                    'slug' => $circle->post_name,
                    'logo' => get_post_meta($circle->ID, 'circle_logo_url', true)
                ];
            }
        }

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