<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Query;
use WP_Error;

class CircleController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'circles';

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'create_item'],
            'permission_callback' => [$this, 'check_pro_permission'], // Sadece Pro kullanıcılar
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'update_item'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/(?P<slug>[a-zA-Z0-9-_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_item'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/join', [
            'methods' => 'POST',
            'callback' => [$this, 'join_circle'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/leave', [
            'methods' => 'POST',
            'callback' => [$this, 'leave_circle'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // YENİ: Circle Ayarları
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/settings', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
        
        // Circle Görevleri
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/tasks', [
            'methods' => 'GET',
            'callback' => [$this, 'get_tasks'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/tasks', [
            'methods' => 'POST',
            'callback' => [$this, 'create_task'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/tasks/(?P<task_id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_task'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/tasks/(?P<task_id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_task'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/tasks/(?P<task_id>\d+)/assign', [
            'methods' => 'POST',
            'callback' => [$this, 'assign_task'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Üye yönetimi
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/members', [
            'methods' => 'GET',
            'callback' => [$this, 'get_members'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)/members/(?P<member_id>\d+)/remove', [
            'methods' => 'POST',
            'callback' => [$this, 'remove_member'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    public function check_auth() {
        return is_user_logged_in();
    }
    
    /**
     * Sadece rejimde_pro kullanıcılar Circle oluşturabilir
     */
    public function check_pro_permission() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        $allowed_roles = ['administrator', 'editor', 'rejimde_pro'];
        
        return !empty(array_intersect($allowed_roles, (array) $user->roles));
    }

    public function get_items($request) {
        $args = [
            'post_type'      => 'rejimde_circle',
            'posts_per_page' => 20,
            'post_status'    => 'publish',
            'meta_key'       => 'total_score',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC'
        ];
        
        if ($request->get_param('search')) {
            $args['s'] = sanitize_text_field($request->get_param('search'));
        }

        $query = new WP_Query($args);
        $circles = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $circles[] = [
                    'id'           => $post_id,
                    'name'         => get_the_title(),
                    'slug'         => get_post_field('post_name', $post_id),
                    'description'  => wp_trim_words(get_the_content(), 20),
                    'logo'         => get_post_meta($post_id, 'circle_logo_url', true),
                    'total_score'  => (int) get_post_meta($post_id, 'total_score', true),
                    'member_count' => (int) get_post_meta($post_id, 'member_count', true),
                    'privacy'      => get_post_meta($post_id, 'privacy', true) ?: 'public'
                ];
            }
            wp_reset_postdata();
        }

        return new WP_REST_Response($circles, 200);
    }

    public function create_item($request) {
        $user_id = get_current_user_id();
        
        // Kullanıcı zaten bir circle'da mı?
        $current_circle = get_user_meta($user_id, 'circle_id', true);
        if ($current_circle) {
            // Circle gerçekten var mı kontrol et
            $circle = get_post($current_circle);
            if ($circle && $circle->post_type === 'rejimde_circle' && $circle->post_status === 'publish') {
                return new WP_Error('already_in_circle', 'Zaten bir Circle\'dasınız.', ['status' => 400]);
            }
            
            // Circle artık yok veya geçersiz - kullanıcı meta'sını temizle
            delete_user_meta($user_id, 'circle_id');
            delete_user_meta($user_id, 'circle_role');
        }

        $params = $request->get_json_params();
        $name = sanitize_text_field($params['name'] ?? '');
        $desc = sanitize_textarea_field($params['description'] ?? '');
        $motto = sanitize_text_field($params['motto'] ?? '');
        
        // Motto varsa description'a ekle
        if (!empty($motto) && empty($desc)) {
            $desc = $motto;
        }
        
        if (empty($name)) {
            return new WP_Error('missing_name', 'Circle adı zorunludur.', ['status' => 400]);
        }

        $post_id = wp_insert_post([
            'post_title'     => $name,
            'post_content'   => $desc,
            'post_status'    => 'publish',
            'post_type'      => 'rejimde_circle',
            'post_author'    => $user_id,
            'comment_status' => ($params['chat_status'] ?? 'open') === 'open' ? 'open' : 'closed',
            'ping_status'    => 'closed'
        ]);

        if (is_wp_error($post_id)) return $post_id;

        // Meta verileri kaydet
        update_post_meta($post_id, 'total_score', 0);
        update_post_meta($post_id, 'member_count', 1);
        update_post_meta($post_id, 'circle_leader_id', $user_id);
        update_post_meta($post_id, 'circle_mentor_id', $user_id); // Mentor = Leader
        update_post_meta($post_id, 'privacy', $params['privacy'] ?? 'public');
        
        if (!empty($motto)) {
            update_post_meta($post_id, 'motto', $motto);
        }
        
        if (!empty($params['logo'])) {
            update_post_meta($post_id, 'circle_logo_url', esc_url_raw($params['logo']));
        }
        
        // Kullanıcıyı circle'a ekle
        update_user_meta($user_id, 'circle_id', $post_id);
        update_user_meta($user_id, 'circle_role', 'mentor'); // Oluşturan kişi Circle Mentor
        
        // Dispatch circle_created event
        $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
        $dispatcher->dispatch('circle_created', [
            'user_id' => $user_id,
            'entity_type' => 'circle',
            'entity_id' => $post_id
        ]);

        return new WP_REST_Response([
            'id' => $post_id, 
            'message' => 'Circle kuruldu! Circle Mentor sensin.', 
            'slug' => get_post_field('post_name', $post_id)
        ], 201);
    }

    public function update_item($request) {
        $user_id = get_current_user_id();
        $circle_id = $request->get_param('id');
        $params = $request->get_json_params();

        $circle = get_post($circle_id);
        if (!$circle || $circle->post_type !== 'rejimde_circle') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }

        $leader_id = get_post_meta($circle_id, 'circle_leader_id', true);
        if ((int)$leader_id !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Bu işlemi yapmaya yetkiniz yok.', ['status' => 403]);
        }

        $update_data = ['ID' => $circle_id];
        
        if (!empty($params['name'])) $update_data['post_title'] = sanitize_text_field($params['name']);
        if (isset($params['description'])) $update_data['post_content'] = sanitize_textarea_field($params['description']);
        
        if (isset($params['comment_status']) || isset($params['chat_status'])) {
            $chat = $params['chat_status'] ?? $params['comment_status'];
            $update_data['comment_status'] = $chat === 'open' ? 'open' : 'closed';
        }

        wp_update_post($update_data);

        if (isset($params['privacy'])) update_post_meta($circle_id, 'privacy', sanitize_text_field($params['privacy']));
        if (isset($params['logo'])) update_post_meta($circle_id, 'circle_logo_url', esc_url_raw($params['logo']));
        if (isset($params['motto'])) update_post_meta($circle_id, 'motto', sanitize_text_field($params['motto']));

        return new WP_REST_Response(['id' => $circle_id, 'message' => 'Circle güncellendi!'], 200);
    }

    public function join_circle($request) {
        $user_id = get_current_user_id();
        $circle_id = (int) $request->get_param('id');
        
        // Check if user is already in a circle
        $current_circle_id = get_user_meta($user_id, 'circle_id', true);
        if ($current_circle_id) {
            // Circle gerçekten var mı kontrol et
            $existing_circle = get_post($current_circle_id);
            if ($existing_circle && $existing_circle->post_type === 'rejimde_circle' && $existing_circle->post_status === 'publish') {
                return new WP_Error('already_in_circle', 'Önce mevcut circle\'dan ayrılmalısınız.', ['status' => 400]);
            }
            
            // Eski circle artık yok - meta'yı temizle
            delete_user_meta($user_id, 'circle_id');
            delete_user_meta($user_id, 'circle_role');
        }

        // Validate circle exists and is published
        $circle = get_post($circle_id);
        if (!$circle || $circle->post_type !== 'rejimde_circle') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }
        
        if ($circle->post_status !== 'publish') {
            return new WP_Error('circle_not_available', 'Bu circle şu anda kullanılamıyor.', ['status' => 400]);
        }
        
        // Check privacy settings (if private, may need approval in future)
        $privacy = get_post_meta($circle_id, 'privacy', true);
        
        // Update member count
        $count = (int) get_post_meta($circle_id, 'member_count', true);
        update_post_meta($circle_id, 'member_count', $count + 1);

        // Add user to circle
        update_user_meta($user_id, 'circle_id', $circle_id);
        update_user_meta($user_id, 'circle_role', 'member');
        
        // Get user's current score to contribute to circle
        $user_score = (int) get_user_meta($user_id, 'rejimde_total_score', true);
        
        // Update circle total score
        $circle_score = (int) get_post_meta($circle_id, 'total_score', true);
        update_post_meta($circle_id, 'total_score', $circle_score + $user_score);
        
        // Dispatch circle_joined event
        $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
        $dispatcher->dispatch('circle_joined', [
            'user_id' => $user_id,
            'entity_type' => 'circle',
            'entity_id' => $circle_id
        ]);

        return new WP_REST_Response([
            'message' => 'Circle\'a katıldınız!',
            'circle' => [
                'id' => $circle_id,
                'name' => $circle->post_title,
                'member_count' => $count + 1,
                'total_score' => $circle_score + $user_score
            ]
        ], 200);
    }

    public function leave_circle($request) {
        $user_id = get_current_user_id();
        $circle_id = get_user_meta($user_id, 'circle_id', true);

        if (!$circle_id) {
            return new WP_Error('no_circle', 'Herhangi bir circle\'da değilsiniz.', ['status' => 400]);
        }
        
        // Get user's score to subtract from circle total
        $user_score = (int) get_user_meta($user_id, 'rejimde_total_score', true);
        
        // Update circle total score
        $circle_score = (int) get_post_meta($circle_id, 'total_score', true);
        $new_circle_score = max(0, $circle_score - $user_score); // Ensure non-negative
        update_post_meta($circle_id, 'total_score', $new_circle_score);
        
        // Update member count
        $count = (int) get_post_meta($circle_id, 'member_count', true);
        if ($count > 0) update_post_meta($circle_id, 'member_count', $count - 1);

        // Remove user from circle
        delete_user_meta($user_id, 'circle_id');
        delete_user_meta($user_id, 'circle_role');

        return new WP_REST_Response([
            'message' => 'Circle\'dan ayrıldınız.',
            'circle' => [
                'id' => $circle_id,
                'member_count' => max(0, $count - 1),
                'total_score' => $new_circle_score
            ]
        ], 200);
    }

    public function get_item($request) {
        $slug = $request->get_param('slug');
        
        if (is_numeric($slug)) {
            $args = ['p' => $slug, 'post_type' => 'rejimde_circle'];
        } else {
            $args = ['name' => $slug, 'post_type' => 'rejimde_circle'];
        }

        $query = new WP_Query($args);
        
        if (!$query->have_posts()) {
            return new WP_Error('not_found', 'Circle bulunamadı', ['status' => 404]);
        }

        $query->the_post();
        $post = get_post();
        $post_id = get_the_ID();
        $leader_id = get_post_meta($post_id, 'circle_leader_id', true);
        
        $members = get_users([
            'meta_key' => 'circle_id',
            'meta_value' => $post_id,
            'number' => 10
        ]);
        
        $members_data = [];
        foreach ($members as $member) {
            $members_data[] = [
                'id' => $member->ID,
                'name' => $member->display_name,
                'avatar' => get_user_meta($member->ID, 'avatar_url', true),
                'role' => get_user_meta($member->ID, 'circle_role', true)
            ];
        }

        $data = [
            'id'             => $post_id,
            'name'           => get_the_title(),
            'slug'           => get_post_field('post_name', $post_id),
            'description'    => get_the_content(),
            'motto'          => get_post_meta($post_id, 'motto', true),
            'logo'           => get_post_meta($post_id, 'circle_logo_url', true),
            'total_score'    => (int) get_post_meta($post_id, 'total_score', true),
            'member_count'   => (int) get_post_meta($post_id, 'member_count', true),
            'leader_id'      => (int) $leader_id,
            'mentor_id'      => (int) $leader_id, // Circle Mentor = Leader
            'members'        => $members_data,
            'privacy'        => get_post_meta($post_id, 'privacy', true) ?: 'public',
            'comment_status' => $post->comment_status
        ];

        return new WP_REST_Response($data, 200);
    }

    /**
     * Circle Ayarlarını Getir
     */
    public function get_settings($request) {
        $user_id = get_current_user_id();
        $circle_id = $request->get_param('id');

        $circle = get_post($circle_id);
        if (!$circle || $circle->post_type !== 'rejimde_circle') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }

        $mentor_id = get_post_meta($circle_id, 'circle_mentor_id', true);
        if ((int)$mentor_id !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Bu ayarları görüntülemeye yetkiniz yok.', ['status' => 403]);
        }

        $settings = [
            'privacy' => get_post_meta($circle_id, 'privacy', true) ?: 'public',
            'chat_status' => $circle->comment_status === 'open' ? 'open' : 'closed',
            'member_approval' => get_post_meta($circle_id, 'member_approval', true) ?: 'auto',
            'notifications' => [
                'new_member' => (bool) get_post_meta($circle_id, 'notify_new_member', true),
                'new_comment' => (bool) get_post_meta($circle_id, 'notify_new_comment', true),
            ],
            'visibility' => [
                'show_members' => (bool) get_post_meta($circle_id, 'show_members', true) !== false,
                'show_score' => (bool) get_post_meta($circle_id, 'show_score', true) !== false,
            ]
        ];

        return new WP_REST_Response($settings, 200);
    }

    /**
     * Circle Ayarlarını Güncelle
     */
    public function update_settings($request) {
        $user_id = get_current_user_id();
        $circle_id = $request->get_param('id');
        $params = $request->get_json_params();

        $circle = get_post($circle_id);
        if (!$circle || $circle->post_type !== 'rejimde_circle') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }

        $mentor_id = get_post_meta($circle_id, 'circle_mentor_id', true);
        if ((int)$mentor_id !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Bu ayarları güncellemeye yetkiniz yok.', ['status' => 403]);
        }

        // Privacy ayarı
        if (isset($params['privacy'])) {
            update_post_meta($circle_id, 'privacy', sanitize_text_field($params['privacy']));
        }

        // Chat status
        if (isset($params['chat_status'])) {
            $comment_status = $params['chat_status'] === 'closed' ? 'closed' : 'open';
            wp_update_post([
                'ID' => $circle_id,
                'comment_status' => $comment_status
            ]);
        }

        // Member approval
        if (isset($params['member_approval'])) {
            update_post_meta($circle_id, 'member_approval', sanitize_text_field($params['member_approval']));
        }

        // Notifications
        if (isset($params['notifications']) && is_array($params['notifications'])) {
            if (isset($params['notifications']['new_member'])) {
                update_post_meta($circle_id, 'notify_new_member', (bool) $params['notifications']['new_member']);
            }
            if (isset($params['notifications']['new_comment'])) {
                update_post_meta($circle_id, 'notify_new_comment', (bool) $params['notifications']['new_comment']);
            }
        }

        // Visibility
        if (isset($params['visibility']) && is_array($params['visibility'])) {
            if (isset($params['visibility']['show_members'])) {
                update_post_meta($circle_id, 'show_members', (bool) $params['visibility']['show_members']);
            }
            if (isset($params['visibility']['show_score'])) {
                update_post_meta($circle_id, 'show_score', (bool) $params['visibility']['show_score']);
            }
        }

        return new WP_REST_Response(['message' => 'Ayarlar güncellendi!'], 200);
    }
    
    /**
     * Recalculate and update circle total score
     * 
     * Helper method to ensure circle score accuracy
     * 
     * Note: For optimal performance with large user bases, ensure wp_usermeta 
     * table has an index on (meta_key, meta_value). This query is typically 
     * cached and runs infrequently (only on manual recalculation).
     * 
     * @param int $circle_id Circle ID
     * @return int New total score
     */
    private function recalculate_circle_score($circle_id) {
        // Get all members
        $members = get_users([
            'meta_key' => 'circle_id',
            'meta_value' => $circle_id,
            'fields' => 'ID'
        ]);
        
        $total_score = 0;
        foreach ($members as $member_id) {
            $total_score += (int) get_user_meta($member_id, 'rejimde_total_score', true);
        }
        
        // Update circle total score
        update_post_meta($circle_id, 'total_score', $total_score);
        
        return $total_score;
    }
    
    /**
     * Circle görevlerini getir
     */
    public function get_tasks($request) {
        $circle_id = $request->get_param('id');
        
        $circle = get_post($circle_id);
        if (!$circle || $circle->post_type !== 'rejimde_circle') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }
        
        $tasks = get_post_meta($circle_id, 'circle_tasks', true);
        if (!is_array($tasks)) $tasks = [];
        
        return new WP_REST_Response($tasks, 200);
    }

    /**
     * Yeni görev oluştur
     */
    public function create_task($request) {
        $user_id = get_current_user_id();
        $circle_id = $request->get_param('id');
        $params = $request->get_json_params();
        
        $circle = get_post($circle_id);
        if (!$circle || $circle->post_type !== 'rejimde_circle') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }
        
        // Sadece mentor görev oluşturabilir
        $mentor_id = get_post_meta($circle_id, 'circle_mentor_id', true);
        if ((int)$mentor_id !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Görev oluşturma yetkiniz yok.', ['status' => 403]);
        }
        
        $tasks = get_post_meta($circle_id, 'circle_tasks', true);
        if (!is_array($tasks)) $tasks = [];
        
        $new_task = [
            'id' => time() . '_' . wp_rand(1000, 9999),
            'title' => sanitize_text_field($params['title'] ?? ''),
            'description' => sanitize_textarea_field($params['description'] ?? ''),
            'points' => (int) ($params['points'] ?? 10),
            'deadline' => sanitize_text_field($params['deadline'] ?? ''),
            'assigned_to' => [],
            'completed_by' => [],
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'created_by' => $user_id
        ];
        
        if (empty($new_task['title'])) {
            return new WP_Error('missing_title', 'Görev başlığı zorunludur.', ['status' => 400]);
        }
        
        $tasks[] = $new_task;
        update_post_meta($circle_id, 'circle_tasks', $tasks);
        
        return new WP_REST_Response([
            'message' => 'Görev oluşturuldu!',
            'task' => $new_task
        ], 201);
    }

    /**
     * Görev güncelle
     */
    public function update_task($request) {
        $user_id = get_current_user_id();
        $circle_id = $request->get_param('id');
        $task_id = $request->get_param('task_id');
        $params = $request->get_json_params();
        
        $circle = get_post($circle_id);
        if (!$circle || $circle->post_type !== 'rejimde_circle') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }
        
        $mentor_id = get_post_meta($circle_id, 'circle_mentor_id', true);
        if ((int)$mentor_id !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Bu işlem için yetkiniz yok.', ['status' => 403]);
        }
        
        $tasks = get_post_meta($circle_id, 'circle_tasks', true);
        if (!is_array($tasks)) $tasks = [];
        
        $task_index = array_search($task_id, array_column($tasks, 'id'));
        if ($task_index === false) {
            return new WP_Error('not_found', 'Görev bulunamadı.', ['status' => 404]);
        }
        
        if (isset($params['title'])) $tasks[$task_index]['title'] = sanitize_text_field($params['title']);
        if (isset($params['description'])) $tasks[$task_index]['description'] = sanitize_textarea_field($params['description']);
        if (isset($params['points'])) $tasks[$task_index]['points'] = (int) $params['points'];
        if (isset($params['deadline'])) $tasks[$task_index]['deadline'] = sanitize_text_field($params['deadline']);
        if (isset($params['status'])) $tasks[$task_index]['status'] = sanitize_text_field($params['status']);
        
        update_post_meta($circle_id, 'circle_tasks', $tasks);
        
        return new WP_REST_Response([
            'message' => 'Görev güncellendi!',
            'task' => $tasks[$task_index]
        ], 200);
    }

    /**
     * Görev sil
     */
    public function delete_task($request) {
        $user_id = get_current_user_id();
        $circle_id = $request->get_param('id');
        $task_id = $request->get_param('task_id');
        
        $circle = get_post($circle_id);
        if (!$circle || $circle->post_type !== 'rejimde_circle') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }
        
        $mentor_id = get_post_meta($circle_id, 'circle_mentor_id', true);
        if ((int)$mentor_id !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Bu işlem için yetkiniz yok.', ['status' => 403]);
        }
        
        $tasks = get_post_meta($circle_id, 'circle_tasks', true);
        if (!is_array($tasks)) $tasks = [];
        
        $tasks = array_filter($tasks, function($task) use ($task_id) {
            return $task['id'] !== $task_id;
        });
        
        update_post_meta($circle_id, 'circle_tasks', array_values($tasks));
        
        return new WP_REST_Response(['message' => 'Görev silindi!'], 200);
    }

    /**
     * Görevi üyeye ata
     */
    public function assign_task($request) {
        $user_id = get_current_user_id();
        $circle_id = $request->get_param('id');
        $task_id = $request->get_param('task_id');
        $params = $request->get_json_params();
        
        $circle = get_post($circle_id);
        if (!$circle || $circle->post_type !== 'rejimde_circle') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }
        
        $mentor_id = get_post_meta($circle_id, 'circle_mentor_id', true);
        if ((int)$mentor_id !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Görev atama yetkiniz yok.', ['status' => 403]);
        }
        
        $member_ids = $params['member_ids'] ?? [];
        if (empty($member_ids) || !is_array($member_ids)) {
            return new WP_Error('invalid_members', 'En az bir üye seçmelisiniz.', ['status' => 400]);
        }
        
        $tasks = get_post_meta($circle_id, 'circle_tasks', true);
        if (!is_array($tasks)) $tasks = [];
        
        $task_index = array_search($task_id, array_column($tasks, 'id'));
        if ($task_index === false) {
            return new WP_Error('not_found', 'Görev bulunamadı.', ['status' => 404]);
        }
        
        $tasks[$task_index]['assigned_to'] = array_map('intval', $member_ids);
        update_post_meta($circle_id, 'circle_tasks', $tasks);
        
        return new WP_REST_Response([
            'message' => 'Görev atandı!',
            'task' => $tasks[$task_index]
        ], 200);
    }

    /**
     * Circle üyelerini getir (detaylı)
     */
    public function get_members($request) {
        $circle_id = $request->get_param('id');
        
        $circle = get_post($circle_id);
        if (!$circle || $circle->post_type !== 'rejimde_circle') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }
        
        $members = get_users([
            'meta_key' => 'circle_id',
            'meta_value' => $circle_id
        ]);
        
        $members_data = [];
        foreach ($members as $member) {
            $members_data[] = [
                'id' => $member->ID,
                'name' => $member->display_name,
                'email' => $member->user_email,
                'avatar' => get_user_meta($member->ID, 'avatar_url', true),
                'role' => get_user_meta($member->ID, 'circle_role', true),
                'score' => (int) get_user_meta($member->ID, 'rejimde_total_score', true),
                'joined_at' => get_user_meta($member->ID, 'circle_joined_at', true)
            ];
        }
        
        // Skora göre sırala
        usort($members_data, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        return new WP_REST_Response($members_data, 200);
    }

    /**
     * Üyeyi circle'dan çıkar
     */
    public function remove_member($request) {
        $user_id = get_current_user_id();
        $circle_id = $request->get_param('id');
        $member_id = (int) $request->get_param('member_id');
        
        $circle = get_post($circle_id);
        if (!$circle || $circle->post_type !== 'rejimde_circle') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }
        
        $mentor_id = get_post_meta($circle_id, 'circle_mentor_id', true);
        if ((int)$mentor_id !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Üye çıkarma yetkiniz yok.', ['status' => 403]);
        }
        
        // Mentor kendini çıkaramaz
        if ($member_id === (int)$mentor_id) {
            return new WP_Error('forbidden', 'Mentor kendini circle\'dan çıkaramaz.', ['status' => 400]);
        }
        
        // Üye bu circle'da mı kontrol et
        $member_circle = get_user_meta($member_id, 'circle_id', true);
        if ((int)$member_circle !== (int)$circle_id) {
            return new WP_Error('not_member', 'Bu kullanıcı circle üyesi değil.', ['status' => 400]);
        }
        
        // Üyenin puanını circle'dan düş
        $member_score = (int) get_user_meta($member_id, 'rejimde_total_score', true);
        $circle_score = (int) get_post_meta($circle_id, 'total_score', true);
        update_post_meta($circle_id, 'total_score', max(0, $circle_score - $member_score));
        
        // Üye sayısını güncelle
        $count = (int) get_post_meta($circle_id, 'member_count', true);
        if ($count > 0) update_post_meta($circle_id, 'member_count', $count - 1);
        
        // Üyeyi circle'dan çıkar
        delete_user_meta($member_id, 'circle_id');
        delete_user_meta($member_id, 'circle_role');
        
        return new WP_REST_Response(['message' => 'Üye circle\'dan çıkarıldı.'], 200);
    }
}
