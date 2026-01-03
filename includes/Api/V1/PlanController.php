<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;
use WP_User;

class PlanController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'plans';

    public function register_routes() {
        // YENİ: Listeleme (Tüm Planlar)
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => '__return_true', // Herkes görebilir
        ]);

        // ... (Diğer route'lar aynı kalıyor: create, update, get_item, approve, start, complete)
        register_rest_route($this->namespace, '/' . $this->base . '/create', [
            'methods' => 'POST',
            'callback' => [$this, 'create_item'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/update/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'update_item'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_item'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/approve/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'approve_plan'],
            'permission_callback' => [$this, 'check_expert_permission'], 
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/start/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'start_plan'],
            'permission_callback' => [$this, 'check_auth_permission'], 
        ]);
        
        register_rest_route($this->namespace, '/' . $this->base . '/complete/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'complete_plan'],
            'permission_callback' => [$this, 'check_auth_permission'],
        ]);
    }

    public function create_item($request) { return $this->handle_save($request, 'create'); }
    public function update_item($request) { return $this->handle_save($request, 'update'); }

    // ... (handle_save aynı kalıyor) ...
    private function handle_save($request, $action) {
        $params = $request->get_json_params();
        
        if (empty($params['title'])) {
            return new WP_Error('missing_title', 'Başlık zorunludur.', ['status' => 400]);
        }

        $post_data = [
            'post_title'   => sanitize_text_field($params['title']),
            'post_content' => wp_kses_post($params['content'] ?? ''),
            'post_status'  => 'publish',
            'post_type'    => 'rejimde_plan',
            'post_author'  => get_current_user_id(),
        ];

        if ($action === 'update') {
            $post_id = $request['id'];
            $post_data['ID'] = $post_id;
            
            $existing = get_post($post_id);
            if (!$existing || $existing->post_type !== 'rejimde_plan') {
                return new WP_Error('not_found', 'Plan bulunamadı.', ['status' => 404]);
            }
            
            if (!current_user_can('manage_options') && $existing->post_author != get_current_user_id()) {
                return new WP_Error('forbidden', 'Bu planı düzenleme yetkiniz yok.', ['status' => 403]);
            }

            $result = wp_update_post($post_data);
        } else {
            $result = wp_insert_post($post_data);
        }

        if (is_wp_error($result)) {
            return $result;
        }

        $post_id = $result;

        if (isset($params['plan_data'])) {
            $json_plan = is_string($params['plan_data']) ? $params['plan_data'] : json_encode($params['plan_data'], JSON_UNESCAPED_UNICODE);
            update_post_meta($post_id, 'plan_data', $json_plan);
        }

        if (!empty($params['featured_media_id'])) {
            set_post_thumbnail($post_id, intval($params['featured_media_id']));
        }

        if (!empty($params['meta']) && is_array($params['meta'])) {
            $meta = $params['meta'];
            $simple_fields = ['difficulty', 'duration', 'calories', 'score_reward', 'diet_category', 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword'];

            foreach ($simple_fields as $key) {
                if (isset($meta[$key])) {
                    update_post_meta($post_id, $key, sanitize_text_field($meta[$key]));
                }
            }

            if (isset($meta['shopping_list'])) {
                $shop_list = is_string($meta['shopping_list']) ? $meta['shopping_list'] : json_encode($meta['shopping_list'], JSON_UNESCAPED_UNICODE);
                update_post_meta($post_id, 'shopping_list', $shop_list);
            }

            if (isset($meta['tags'])) {
                $tags = is_string($meta['tags']) ? $meta['tags'] : json_encode($meta['tags'], JSON_UNESCAPED_UNICODE);
                update_post_meta($post_id, 'tags', $tags);
            }
        }

        $post = get_post($post_id);
        return $this->success([
            'id' => $post->ID,
            'slug' => $post->post_name,
            'message' => $action === 'create' ? 'Plan oluşturuldu.' : 'Plan güncellendi.'
        ]);
    }

    // --- YENİ: Listeleme Metodu ---
    public function get_items($request) {
        $args = [
            'post_type' => 'rejimde_plan',
            'post_status' => 'publish',
            'posts_per_page' => -1, // Tümünü getir
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        // Author filtresi - GET parametresinden veya request'ten alınır
        $author = $request->get_param('author');
        if ($author) {
            $args['author'] = intval($author);
        }

        // Filtreleme parametreleri eklenebilir
        // if ($request['category']) ...

        $posts = get_posts($args);
        $data = [];

        foreach ($posts as $post) {
            $author_id = $post->post_author;
            $author_name = get_the_author_meta('display_name', $author_id);
            $author_user = get_userdata($author_id);
            $author_avatar = get_avatar_url($author_id); // Varsayılan WP avatarı, frontendde dicebear fallback var

            $completed_users_raw = get_post_meta($post->ID, 'completed_users', true);
            $completed_users = $this->safe_json_decode($completed_users_raw);
            
        // Son 3 tamamlayan (avatar için) - GÜNCEL
        $last_completed_avatars = [];
        $count = 0;
        if (is_array($completed_users)) {
            foreach (array_reverse($completed_users) as $uid) {
                if ($count >= 3) break;

                $u = get_userdata($uid);
                if ($u) {
                    $last_completed_avatars[] = [
                        'id' => $uid,
                        'name' => $u->display_name,
                        // gravatar yerine: kullanıcı profilinde seçtiği/yüklediği avatar_url
                        'avatar' => get_user_meta($uid, 'avatar_url', true) ?: get_avatar_url($uid),
                        'slug' => $u->user_nicename,
                        'is_expert' => in_array('rejimde_pro', (array) $u->roles) || in_array('administrator', (array) $u->roles),
                    ];
                    $count++;
                }
            }
        }

            $data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => wp_trim_words($post->post_content, 20),
                'content' => $post->post_content, // Full içerik gerekirse
                'slug' => $post->post_name,
                'image' => get_the_post_thumbnail_url($post->ID, 'medium_large') ?: '',
                'date' => $post->post_date,
                'meta' => [
                    'difficulty' => get_post_meta($post->ID, 'difficulty', true),
                    'duration' => get_post_meta($post->ID, 'duration', true),
                    'calories' => get_post_meta($post->ID, 'calories', true),
                    'score_reward' => get_post_meta($post->ID, 'score_reward', true),
                    'diet_category' => get_post_meta($post->ID, 'diet_category', true),
                    'is_verified' => (bool) get_post_meta($post->ID, 'is_verified', true),
                ],
                'author' => [
                    'name' => $author_name,
                    'avatar' => $author_avatar,
                    'slug' => $author_user ? $author_user->user_nicename : ''
                ],
                'completed_users' => $last_completed_avatars, // Sadece avatar listesi
                'completed_count' => count($completed_users)
            ];
        }

        return $this->success($data);
    }

    // ... (approve, start, complete, get_item metodları aynı kalıyor) ...
    public function approve_plan($request) {
        $post_id = (int) $request['id'];
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);

        // Yetki kontrolü
        $roles = (array) $user->roles;
        if (!in_array('rejimde_pro', $roles) && !in_array('administrator', $roles)) {
            return new WP_Error('forbidden', 'Bu işlem için yetkiniz yok.', ['status' => 403]);
        }

        // Mevcut onaylayanları al
        $approved_by = get_post_meta($post_id, 'approved_by', true);
        if (!is_array($approved_by)) {
            // Eski format (tek kişi) - yeni formata çevir
            $old_approver = $approved_by;
            $approved_by = $old_approver ? [$old_approver] : [];
        }

        // Bu uzman zaten onaylamış mı?
        if (in_array($user_id, $approved_by)) {
            return new WP_Error('already_approved', 'Bu içeriği zaten onayladınız.', ['status' => 400]);
        }

        // Onaylayanlara ekle
        $approved_by[] = $user_id;
        update_post_meta($post_id, 'approved_by', $approved_by);
        update_post_meta($post_id, 'is_verified', true);

        // Onaylayan bilgilerini hazırla
        $approvers_info = [];
        foreach ($approved_by as $approver_id) {
            $approver = get_userdata($approver_id);
            if ($approver) {
                $approvers_info[] = [
                    'id' => $approver_id,
                    'name' => $approver->display_name,
                    'avatar' => get_user_meta($approver_id, 'avatar_url', true) ?: get_avatar_url($approver_id),
                    'slug' => $approver->user_nicename,
                    'title' => get_user_meta($approver_id, 'title', true) ?: 'Uzman'
                ];
            }
        }

        // Geriye uyumluluk için approved_by_users meta'sını da güncelle
        update_post_meta($post_id, 'approved_by_users', $approved_by);

        return $this->success([
            'message' => 'İçerik başarıyla onaylandı!',
            'approved_by' => $approvers_info,
            'approval_count' => count($approved_by)
        ]);
    }

    public function start_plan($request) {
        $post_id = (int) $request['id'];
        $user_id = get_current_user_id();
        
        // Validate plan exists
        $plan = get_post($post_id);
        if (!$plan || $plan->post_type !== 'rejimde_plan') {
            return new WP_Error('plan_not_found', 'Plan bulunamadı.', ['status' => 404]);
        }
        
        // Check if plan is published
        if ($plan->post_status !== 'publish') {
            return new WP_Error('plan_not_available', 'Bu plan şu anda kullanılamıyor.', ['status' => 400]);
        }
        
        // Get started users list
        $started_users_raw = get_post_meta($post_id, 'started_users', true);
        $started_users = $this->safe_json_decode($started_users_raw);
        
        // Check if user already started
        $already_started = in_array($user_id, $started_users);
        
        // Add user to started list if not already there
        if (!$already_started) {
            $started_users[] = $user_id;
            update_post_meta($post_id, 'started_users', json_encode($started_users));
            
            // Log start timestamp for user
            $user_plan_data = get_user_meta($user_id, 'rejimde_plan_' . $post_id, true);
            if (!is_array($user_plan_data)) {
                $user_plan_data = [];
            }
            $user_plan_data['started_at'] = current_time('mysql');
            update_user_meta($user_id, 'rejimde_plan_' . $post_id, $user_plan_data);
            
            // Dispatch event for gamification
            $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
            $dispatcher->dispatch('diet_started', [
                'user_id' => $user_id,
                'entity_type' => 'plan',
                'entity_id' => $post_id
            ]);
        }
        
        return $this->success([
            'message' => $already_started ? 'Bu plana zaten başlamıştınız.' : 'Plana başarıyla başladınız.',
            'already_started' => $already_started,
            'plan' => [
                'id' => $post_id,
                'title' => $plan->post_title,
                'started_count' => count($started_users)
            ]
        ]);
    }
    
    public function complete_plan($request) {
        $post_id = (int) $request['id'];
        $user_id = get_current_user_id();
        
        // Validate plan exists
        $plan = get_post($post_id);
        if (!$plan || $plan->post_type !== 'rejimde_plan') {
            return new WP_Error('plan_not_found', 'Plan bulunamadı.', ['status' => 404]);
        }
        
        // Check if plan is published
        if ($plan->post_status !== 'publish') {
            return new WP_Error('plan_not_available', 'Bu plan şu anda kullanılamıyor.', ['status' => 400]);
        }
        
        // Verify user has started the plan
        $started_users_raw = get_post_meta($post_id, 'started_users', true);
        $started_users = $this->safe_json_decode($started_users_raw);
        
        if (!in_array($user_id, $started_users)) {
            return new WP_Error('plan_not_started', 'Bu planı tamamlamadan önce başlatmalısınız.', ['status' => 400]);
        }
        
        // Get completed users list
        $completed_users_raw = get_post_meta($post_id, 'completed_users', true);
        $completed_users = $this->safe_json_decode($completed_users_raw);
        
        // Check if user already completed
        $already_completed = in_array($user_id, $completed_users);
        
        // Add user to completed list if not already there
        if (!$already_completed) {
            $completed_users[] = $user_id;
            update_post_meta($post_id, 'completed_users', json_encode($completed_users));
            
            // Log completion timestamp for user
            $user_plan_data = get_user_meta($user_id, 'rejimde_plan_' . $post_id, true);
            if (!is_array($user_plan_data)) {
                $user_plan_data = [];
            }
            $user_plan_data['completed_at'] = current_time('mysql');
            update_user_meta($user_id, 'rejimde_plan_' . $post_id, $user_plan_data);
            
            // Dispatch event for gamification (diet_completed gives dynamic points based on plan meta)
            $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
            $dispatcher->dispatch('diet_completed', [
                'user_id' => $user_id,
                'entity_type' => 'plan',
                'entity_id' => $post_id
            ]);
        }
        
        return $this->success([
            'message' => $already_completed ? 'Bu planı zaten tamamlamıştınız.' : 'Plan tamamlandı! Tebrikler!',
            'already_completed' => $already_completed,
            'plan' => [
                'id' => $post_id,
                'title' => $plan->post_title,
                'completed_count' => count($completed_users),
                'reward_points' => (int) get_post_meta($post_id, 'score_reward', true) ?: 0
            ]
        ]);
    }

    public function get_item($request) {
        $slug = $request['slug'];
        $posts = get_posts(['name' => $slug, 'post_type' => 'rejimde_plan', 'post_status' => 'publish', 'numberposts' => 1]);

        if (empty($posts)) { return new WP_Error('not_found', 'Plan bulunamadı.', ['status' => 404]); }

        $post = $posts[0];
        $post_id = $post->ID;

        // Meta Çözümleme
        $plan_data = json_decode(get_post_meta($post_id, 'plan_data', true)) ?: [];
        $shopping_list = json_decode(get_post_meta($post_id, 'shopping_list', true)) ?: [];
        $tags = json_decode(get_post_meta($post_id, 'tags', true)) ?: [];
        
        // Yazar Bilgisi
        $author_id = $post->post_author;
        $author_user = get_userdata($author_id);
        $author_data = [
            'id' => $author_id,
            'name' => $author_user->display_name,
            'avatar' => get_avatar_url($author_id),
            'slug' => $author_user->user_nicename,
            'is_expert' => in_array('rejimde_pro', (array) $author_user->roles) || in_array('administrator', (array) $author_user->roles)
        ];

        // Onaylayan Uzman Bilgisi
        $approved_by_id = get_post_meta($post_id, 'approved_by', true);
        $approved_by = null;
        if ($approved_by_id) {
            $approver = get_userdata($approved_by_id);
            if ($approver) {
                $approved_by = [
                    'name' => $approver->display_name,
                    'avatar' => get_avatar_url($approved_by_id),
                    'slug' => $approver->user_nicename
                ];
            }
        }

        // Onaylayan Uzmanlar (Çoklu)
        $approved_by_users = get_post_meta($post_id, 'approved_by_users', true);
        $approvers = [];
        if (is_array($approved_by_users)) {
            foreach ($approved_by_users as $approver_id) {
                $approver = get_userdata($approver_id);
                if ($approver) {
                    $approvers[] = [
                        'id' => $approver_id,
                        'name' => $approver->display_name,
                        'avatar' => get_user_meta($approver_id, 'avatar_url', true) ?: get_avatar_url($approver_id),
                        'slug' => $approver->user_nicename,
                        'profession' => get_user_meta($approver_id, 'profession', true)
                    ];
                }
            }
        }
        
        // Tamamlayan Kullanıcılar (Son 5 - Avatar için)
        $completed_users_ids = $this->safe_json_decode(get_post_meta($post_id, 'completed_users', true));
        $completed_users = [];
        $count = 0;
        foreach (array_reverse($completed_users_ids) as $uid) {
            if ($count >= 5) break;
            $u = get_userdata($uid);
            if ($u) {
                $completed_users[] = [
                    'id' => $uid,
                    'name' => $u->display_name,
                    'avatar' => get_user_meta($uid, 'avatar_url', true) ?: get_avatar_url($uid),
                    'slug' => $u->user_nicename,
                    'is_expert' => in_array('rejimde_pro', (array) $u->roles)
                ];
                $count++;
            }
        }

        $data = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'slug' => $post->post_name,
            'image' => get_the_post_thumbnail_url($post->ID, 'large') ?: '',
            'plan_data' => $plan_data,
            'shopping_list' => $shopping_list,
            'tags' => $tags,
            'meta' => [
                'difficulty' => get_post_meta($post_id, 'difficulty', true),
                'duration' => get_post_meta($post_id, 'duration', true),
                'calories' => get_post_meta($post_id, 'calories', true),
                'score_reward' => get_post_meta($post_id, 'score_reward', true),
                'diet_category' => get_post_meta($post_id, 'diet_category', true),
                'is_verified' => (bool) get_post_meta($post_id, 'is_verified', true),
            ],
            'author' => $author_data,
            'approved_by' => $approved_by,
            'approvers' => $approvers,
            'completed_users' => $completed_users,
            'completed_count' => count($completed_users_ids),
            'date' => get_the_date('d F Y', $post_id)
        ];

        return $this->success($data);
    }

    public function check_permission() { return current_user_can('edit_posts'); }
    public function check_expert_permission() { 
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
        $allowed_roles = ['administrator', 'editor', 'rejimde_pro'];
        return !empty(array_intersect($allowed_roles, (array) $user->roles));
    }
    public function check_auth_permission() { return is_user_logged_in(); }

    /**
     * Safely decode JSON data that might already be an array
     * 
     * @param mixed $value The value to decode (can be string, array, or other)
     * @return array Always returns an array
     */
    private function safe_json_decode($value) {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    protected function success($data) {
        return new WP_REST_Response([
            'status' => 'success',
            'data' => $data
        ], 200);
    }
}