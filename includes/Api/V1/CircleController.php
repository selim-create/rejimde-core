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
            'permission_callback' => [$this, 'check_auth'],
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
    }

    public function check_auth() {
        return is_user_logged_in();
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
                    'motto'        => get_post_meta($post_id, 'circle_motto', true),
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
        $user = wp_get_current_user();
        
        // Sadece rejimde_pro kullanıcılar circle oluşturabilir
        if (!in_array('rejimde_pro', (array) $user->roles) && !current_user_can('manage_options')) {
            return new WP_Error('permission_denied', 'Circle oluşturmak için Rejimde Uzmanı olmalısınız.', ['status' => 403]);
        }
        
        $current_circle = get_user_meta($user_id, 'circle_id', true);
        if ($current_circle) {
            return new WP_Error('already_in_circle', 'Zaten bir circle\'dasınız.', ['status' => 400]);
        }

        $params = $request->get_json_params();
        $name = sanitize_text_field($params['name'] ?? '');
        $desc = sanitize_textarea_field($params['description'] ?? '');
        $motto = sanitize_text_field($params['motto'] ?? '');
        
        if (empty($name)) {
            return new WP_Error('missing_name', 'Circle adı zorunludur.', ['status' => 400]);
        }

        // chat_status alanını comment_status'a çeviriyoruz
        $chat_status = isset($params['chat_status']) ? sanitize_text_field($params['chat_status']) : 'open';
        $comment_status = ($chat_status === 'closed') ? 'closed' : 'open';

        $post_id = wp_insert_post([
            'post_title'     => $name,
            'post_content'   => $desc,
            'post_status'    => 'publish',
            'post_type'      => 'rejimde_circle',
            'post_author'    => $user_id,
            'comment_status' => $comment_status, 
            'ping_status'    => 'closed'
        ]);

        if (is_wp_error($post_id)) return $post_id;

        update_post_meta($post_id, 'total_score', 0);
        update_post_meta($post_id, 'member_count', 1);
        update_post_meta($post_id, 'circle_mentor_id', $user_id); // Circle Mentor
        update_post_meta($post_id, 'privacy', $params['privacy'] ?? 'public');
        
        if (!empty($motto)) {
            update_post_meta($post_id, 'circle_motto', $motto);
        }
        
        if (!empty($params['logo'])) {
            update_post_meta($post_id, 'circle_logo_url', esc_url_raw($params['logo']));
        }
        
        // Kullanıcıyı Circle'a ekle ve Circle Mentor yap
        update_user_meta($user_id, 'circle_id', $post_id);
        update_user_meta($user_id, 'circle_role', 'mentor'); // mentor olarak değiştirdik

        return new WP_REST_Response([
            'id' => $post_id, 
            'message' => 'Circle kuruldu!', 
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

        $mentor_id = get_post_meta($circle_id, 'circle_mentor_id', true);
        if ((int)$mentor_id !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Bu işlemi yapmaya yetkiniz yok.', ['status' => 403]);
        }

        $update_data = ['ID' => $circle_id];
        
        if (!empty($params['name'])) $update_data['post_title'] = sanitize_text_field($params['name']);
        if (isset($params['description'])) $update_data['post_content'] = sanitize_textarea_field($params['description']);
        
        // chat_status parametresini comment_status'a çevir
        if (isset($params['chat_status'])) {
            $update_data['comment_status'] = $params['chat_status'] === 'closed' ? 'closed' : 'open';
        }
        
        // YENİ: Yorum durumunu güncelle (eski uyumluluk için)
        if (isset($params['comment_status'])) {
            $update_data['comment_status'] = $params['comment_status'] === 'open' ? 'open' : 'closed';
        }

        wp_update_post($update_data);

        if (isset($params['privacy'])) update_post_meta($circle_id, 'privacy', sanitize_text_field($params['privacy']));
        if (isset($params['logo'])) update_post_meta($circle_id, 'circle_logo_url', esc_url_raw($params['logo']));
        if (isset($params['motto'])) update_post_meta($circle_id, 'circle_motto', sanitize_text_field($params['motto']));

        return new WP_REST_Response(['id' => $circle_id, 'message' => 'Circle güncellendi!'], 200);
    }

    public function join_circle($request) {
        $user_id = get_current_user_id();
        $circle_id = $request->get_param('id');
        
        if (get_user_meta($user_id, 'circle_id', true)) {
            return new WP_Error('already_in_circle', 'Önce mevcut circle\'dan ayrılmalısınız.', ['status' => 400]);
        }

        $circle = get_post($circle_id);
        if (!$circle || $circle->post_type !== 'rejimde_circle') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }

        $count = (int) get_post_meta($circle_id, 'member_count', true);
        update_post_meta($circle_id, 'member_count', $count + 1);

        update_user_meta($user_id, 'circle_id', $circle_id);
        update_user_meta($user_id, 'circle_role', 'member');

        return new WP_REST_Response(['message' => 'Circle\'a katıldınız!'], 200);
    }

    public function leave_circle($request) {
        $user_id = get_current_user_id();
        $circle_id = get_user_meta($user_id, 'circle_id', true);

        if (!$circle_id) {
            return new WP_Error('no_circle', 'Herhangi bir circle\'da değilsiniz.', ['status' => 400]);
        }
        
        $count = (int) get_post_meta($circle_id, 'member_count', true);
        if ($count > 0) update_post_meta($circle_id, 'member_count', $count - 1);

        delete_user_meta($user_id, 'circle_id');
        delete_user_meta($user_id, 'circle_role');

        return new WP_REST_Response(['message' => 'Circle\'dan ayrıldınız.'], 200);
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
        $mentor_id = get_post_meta($post_id, 'circle_mentor_id', true);
        
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
            'id'           => $post_id,
            'name'         => get_the_title(),
            'slug'         => get_post_field('post_name', $post_id),
            'description'  => get_the_content(),
            'motto'        => get_post_meta($post_id, 'circle_motto', true),
            'logo'         => get_post_meta($post_id, 'circle_logo_url', true),
            'total_score'  => (int) get_post_meta($post_id, 'total_score', true),
            'member_count' => (int) get_post_meta($post_id, 'member_count', true),
            'mentor_id'    => (int) $mentor_id,
            'members'      => $members_data,
            'privacy'      => get_post_meta($post_id, 'privacy', true) ?: 'public',
            'chat_status'  => $post->comment_status === 'open' ? 'open' : 'closed', // comment_status'u chat_status olarak döndür
            'comment_status' => $post->comment_status // Eski uyumluluk için
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
}
