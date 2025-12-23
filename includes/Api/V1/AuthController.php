<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;
use WP_User;
use WP_Query;

class AuthController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'auth';

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->base . '/register', [
            'methods' => 'POST',
            'callback' => [$this, 'register_user'],
            'permission_callback' => function() { return true; },
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/login', [
            'methods' => 'POST',
            'callback' => [$this, 'login_user'],
            'permission_callback' => function() { return true; },
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/google', [
            'methods' => 'POST',
            'callback' => [$this, 'google_login'],
            'permission_callback' => function() { return true; },
        ]);
    }

    /**
     * KULLANICI KAYDI
     */
    public function register_user($request) {
        $params = $request->get_json_params();
        if (empty($params)) $params = $request->get_params();
        
        $username = sanitize_text_field($params['username'] ?? '');
        $email = sanitize_email($params['email'] ?? '');
        $password = sanitize_text_field($params['password'] ?? '');
        $raw_role = sanitize_text_field($params['role'] ?? 'rejimde_user');
        $requested_role = ($raw_role === 'rejimde_pro') ? 'rejimde_pro' : 'rejimde_user';
        
        $meta = $params['meta'] ?? [];

        if (empty($username) || empty($email) || empty($password)) {
            return $this->error('Kullanıcı adı, e-posta ve şifre zorunludur.', 400);
        }

        if (username_exists($username) || email_exists($email)) {
            return $this->error('Bu kullanıcı adı veya e-posta zaten kayıtlı.', 409);
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return $this->error($user_id->get_error_message(), 500);
        }

        // ROL ATAMA
        $user = new WP_User($user_id);
        $user->set_role($requested_role);

        // Ad Soyad
        if (isset($meta['name'])) {
            wp_update_user([
                'ID' => $user_id,
                'display_name' => sanitize_text_field($meta['name'])
            ]);
        }

        // Meta Verilerini Kaydet
        if (!empty($meta) && is_array($meta)) {
             // Kilo
            if (isset($meta['weight'])) {
                update_user_meta($user_id, 'current_weight', sanitize_text_field($meta['weight']));
            }
            // Hedef
            if (isset($meta['goal'])) {
                $goals_structure = ['weight_loss' => false, 'muscle_gain' => false, 'healthy_living' => false];
                $selected_goal = sanitize_key($meta['goal']);
                if (array_key_exists($selected_goal, $goals_structure)) {
                    $goals_structure[$selected_goal] = true;
                }
                update_user_meta($user_id, 'goals', $goals_structure);
            }
            // Diğer Alanlar
            $direct_fields = ['birth_date', 'height', 'gender', 'activity_level', 'profession', 'title', 'phone', 'location', 'bio', 'branches', 'client_types', 'consultation_types', 'services', 'address', 'brand_name'];
            foreach ($direct_fields as $field) {
                if (isset($meta[$field])) {
                    update_user_meta($user_id, $field, sanitize_text_field($meta[$field]));
                }
            }
        }

        // Varsayılanlar
        update_user_meta($user_id, 'notifications', ['email' => true, 'push' => true]);
        if (!get_user_meta($user_id, 'avatar_url', true)) {
            $default_avatar = 'https://api.dicebear.com/9.x/personas/svg?seed=' . $username;
            update_user_meta($user_id, 'avatar_url', $default_avatar);
        }

        // --- UZMAN PROFİLİ YÖNETİMİ (GÜNCELLENDİ: Eşleştirme Mantığı) ---
        if ($requested_role === 'rejimde_pro') {
            
            // 1. Önce bu e-posta ile oluşturulmuş "Sahipsiz" bir kart var mı bak?
            $args = [
                'post_type'  => 'rejimde_pro',
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key'     => 'email',
                        'value'   => $email,
                        'compare' => '='
                    ],
                    [
                        'key'     => 'is_claimed', // Sadece sahipsizleri ara (veya 0 olanları)
                        'value'   => '1',
                        'compare' => '!=' 
                    ]
                ]
            ];
            $query = new WP_Query($args);
            
            $post_id = 0;

            if ($query->have_posts()) {
                // Eşleşen sahipsiz kart bulundu! Onu sahipleniyoruz.
                $query->the_post();
                $post_id = get_the_ID();
                
                // Postu güncelle (Başlık, içerik vb.)
                wp_update_post([
                    'ID'           => $post_id,
                    'post_title'   => isset($meta['name']) ? $meta['name'] : $username,
                    'post_content' => isset($meta['bio']) ? $meta['bio'] : '',
                    'post_author'  => $user_id,
                    'post_name'    => $username // DÜZELTME: Sahiplenirken slug'ı username yap
                ]);
                
            } else {
                // Eşleşen yok, yeni kart oluştur.
                $pro_post_data = [
                    'post_title'    => isset($meta['name']) ? $meta['name'] : $username,
                    'post_content'  => isset($meta['bio']) ? $meta['bio'] : '',
                    'post_status'   => 'publish',
                    'post_type'     => 'rejimde_pro',
                    'post_author'   => $user_id,
                    'post_name'     => $username // DÜZELTME: Oluştururken slug'ı username yap
                ];
                $post_id = wp_insert_post($pro_post_data);
            }

            if ($post_id && !is_wp_error($post_id)) {
                // İlişkileri Kur
                update_user_meta($user_id, 'related_pro_post_id', $post_id);
                update_post_meta($post_id, 'related_user_id', $user_id);
                
                // SAHİPLENDİ OLARAK İŞARETLE
                update_post_meta($post_id, 'is_claimed', '1'); 

                // Diğer metaları güncelle
                $meta_map = [
                    'profession' => 'uzmanlik_tipi',
                    'title' => 'unvan',
                    'location' => 'konum',
                    'brand_name' => 'kurum',
                    'branches' => 'branslar',
                    'services' => 'hizmetler'
                ];

                foreach ($meta_map as $user_key => $post_key) {
                    if (isset($meta[$user_key])) {
                        update_post_meta($post_id, $post_key, sanitize_text_field($meta[$user_key]));
                    }
                }
                
                // Eğer yeni oluşturulduysa varsayılanlar
                if (!get_post_meta($post_id, 'puan', true)) update_post_meta($post_id, 'puan', '0.0');
                if (!get_post_meta($post_id, 'onayli', true)) update_post_meta($post_id, 'onayli', '0');
            }
            wp_reset_postdata();
        }

        $token_data = $this->generate_token($user);
        
        if (is_wp_error($token_data)) {
             return $this->success(['user_id' => $user_id], 'Kayıt başarılı, giriş yapınız.');
        }

        return $this->success($token_data, 'Kayıt başarılı!');
    }

    /**
     * KULLANICI GİRİŞİ
     */
    public function login_user($request) {
        $params = $request->get_json_params();
        if (empty($params)) $params = $request->get_params();

        $username = sanitize_text_field($params['username'] ?? '');
        $password = sanitize_text_field($params['password'] ?? '');

        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            return $this->error('Kullanıcı adı veya şifre hatalı.', 401);
        }

        $token_data = $this->generate_token($user);
        
        if (is_wp_error($token_data)) {
            return $this->error($token_data->get_error_message(), 500);
        }

        return $this->success($token_data, 'Giriş başarılı.');
    }

    /**
     * GOOGLE LOGIN (DÜZELTİLDİ: Role Zorlama)
     */
    public function google_login($request) {
        $params = $request->get_json_params();
        if (empty($params)) $params = $request->get_params();

        $id_token = isset($params['id_token']) ? $params['id_token'] : '';

        if (empty($id_token)) {
            return $this->error('ID Token gerekli.', 400);
        }

        $response = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . $id_token);
        
        if (is_wp_error($response)) {
             return $this->error('Google sunucularına ulaşılamadı.', 500);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return $this->error('Geçersiz Google Token: ' . $body['error_description'], 401);
        }

        $server_client_id = get_option('rejimde_google_client_id');
        if ($server_client_id && $body['aud'] !== $server_client_id) {
             return $this->error('Client ID eşleşmiyor.', 401);
        }

        $email = $body['email'];
        $name = $body['name'];
        
        $user = get_user_by('email', $email);

        if (!$user) {
            // --- YENİ KULLANICI OLUŞTURMA ---
            $username = sanitize_user(current(explode('@', $email)));
            $i = 1;
            $original_username = $username;
            while (username_exists($username)) {
                $username = $original_username . $i++;
            }
            
            $random_password = wp_generate_password(16, false);
            $user_id = wp_create_user($username, $random_password, $email);
            
            if (is_wp_error($user_id)) {
                return $this->error($user_id->get_error_message(), 500);
            }
            
            // CRITICAL FIX: Google ile gelenleri ZORLA 'rejimde_user' yapıyoruz
            $user_obj = new WP_User($user_id);
            $user_obj->set_role('rejimde_user');
            
            // Bilgileri Güncelle
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $name,
                'first_name' => $body['given_name'] ?? '',
                'last_name' => $body['family_name'] ?? '',
            ]);

            if (isset($body['picture'])) {
                update_user_meta($user_id, 'avatar_url', $body['picture']);
            }
            
            // Bildirim Ayarları
            update_user_meta($user_id, 'notifications', ['email' => true, 'push' => true]);
            
            // Nesneyi güncelle
            $user = get_user_by('id', $user_id);
        }

        // Token Üret
        $token_data = $this->generate_token($user);
        
        if (is_wp_error($token_data)) {
            return $this->error($token_data->get_error_message(), 500);
        }

        return $this->success($token_data, 'Google ile giriş başarılı!');
    }

    private function generate_token($user) {
        if (class_exists('Firebase\JWT\JWT')) {
            $secret = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : '';
            if (empty($secret)) return new WP_Error('jwt_error', 'JWT Secret tanımlı değil.');

            $time = time();
            $payload = [
                'iss' => get_bloginfo('url'),
                'iat' => $time,
                'nbf' => $time,
                'exp' => $time + (7 * 24 * 60 * 60), // 1 hafta
                'data' => [
                    'user' => [
                        'id' => $user->ID
                    ]
                ]
            ];

            try {
                $token = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');
                $avatar_url = get_user_meta($user->ID, 'avatar_url', true);
                if (!$avatar_url) {
                    $avatar_url = 'https://api.dicebear.com/9.x/personas/svg?seed=' . $user->user_login;
                }

                // Rolleri dizi olarak al
                $roles = (array) $user->roles;
                
                return [
                    'token' => $token,
                    'user_email' => $user->user_email,
                    'user_display_name' => $user->display_name,
                    'user_nicename' => $user->user_nicename,
                    'avatar_url' => $avatar_url,
                    'user_id' => $user->ID,
                    'roles' => $roles 
                ];
            } catch (\Exception $e) {
                return new WP_Error('jwt_error', $e->getMessage());
            }
        }
        return new WP_Error('jwt_error', 'JWT kütüphanesi yok.');
    }

    protected function success($data = null, $message = 'Success', $code = 200) {
        return new WP_REST_Response(['status' => 'success', 'message' => $message, 'data' => $data], $code);
    }

    protected function error($message = 'Error', $code = 400, $data = null) {
        return new WP_REST_Response(['status' => 'error', 'message' => $message, 'error_data' => $data], $code);
    }
}