<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Query;
use WP_Error;

class ClanController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'clans';

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
            'callback' => [$this, 'join_clan'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/leave', [
            'methods' => 'POST',
            'callback' => [$this, 'leave_clan'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // /circles ALIAS ROUTES (Frontend uyumluluğu için)
        register_rest_route($this->namespace, '/circles', [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/circles', [
            'methods' => 'POST',
            'callback' => [$this, 'create_item'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/circles/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'update_item'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/circles/(?P<slug>[a-zA-Z0-9-_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_item'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/circles/(?P<id>\d+)/join', [
            'methods' => 'POST',
            'callback' => [$this, 'join_clan'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        register_rest_route($this->namespace, '/circles/leave', [
            'methods' => 'POST',
            'callback' => [$this, 'leave_clan'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    public function check_auth() {
        return is_user_logged_in();
    }

    public function get_items($request) {
        $args = [
            'post_type'      => 'rejimde_clan',
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
        $clans = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $clans[] = [
                    'id'           => $post_id,
                    'name'         => get_the_title(),
                    'slug'         => get_post_field('post_name', $post_id),
                    'description'  => wp_trim_words(get_the_content(), 20),
                    'logo'         => get_post_meta($post_id, 'clan_logo_url', true),
                    'total_score'  => (int) get_post_meta($post_id, 'total_score', true),
                    'member_count' => (int) get_post_meta($post_id, 'member_count', true),
                    'privacy'      => get_post_meta($post_id, 'privacy', true) ?: 'public'
                ];
            }
            wp_reset_postdata();
        }

        return new WP_REST_Response($clans, 200);
    }

    public function create_item($request) {
        $user_id = get_current_user_id();
        
        $current_clan = get_user_meta($user_id, 'clan_id', true);
        if ($current_clan) {
            return new WP_Error('already_in_circle', 'Zaten bir Circle\'dasınız.', ['status' => 400]);
        }

        $params = $request->get_json_params();
        $name = sanitize_text_field($params['name'] ?? '');
        $desc = sanitize_textarea_field($params['description'] ?? '');
        
        // Motto alanını da description'a ekle
        if (empty($desc) && !empty($params['motto'])) {
            $desc = sanitize_textarea_field($params['motto']);
        }
        
        if (empty($name)) {
            return new WP_Error('missing_name', 'Circle adı zorunludur.', ['status' => 400]);
        }

        $post_id = wp_insert_post([
            'post_title'     => $name,
            'post_content'   => $desc,
            'post_status'    => 'publish',
            'post_type'      => 'rejimde_clan',
            'post_author'    => $user_id,
            'comment_status' => 'open', 
            'ping_status'    => 'closed'
        ]);

        if (is_wp_error($post_id)) return $post_id;

        update_post_meta($post_id, 'total_score', 0);
        update_post_meta($post_id, 'member_count', 1);
        update_post_meta($post_id, 'clan_leader_id', $user_id);
        update_post_meta($post_id, 'privacy', $params['privacy'] ?? 'public');
        
        if (!empty($params['logo'])) {
            update_post_meta($post_id, 'clan_logo_url', esc_url_raw($params['logo']));
        }
        
        update_user_meta($user_id, 'clan_id', $post_id);
        update_user_meta($user_id, 'clan_role', 'leader');

        return new WP_REST_Response(['id' => $post_id, 'message' => 'Circle kuruldu!', 'slug' => get_post_field('post_name', $post_id)], 201);
    }

    public function update_item($request) {
        $user_id = get_current_user_id();
        $clan_id = $request->get_param('id');
        $params = $request->get_json_params();

        $clan = get_post($clan_id);
        if (!$clan || $clan->post_type !== 'rejimde_clan') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }

        $leader_id = get_post_meta($clan_id, 'clan_leader_id', true);
        if ((int)$leader_id !== $user_id && !current_user_can('manage_options')) {
            return new WP_Error('forbidden', 'Bu işlemi yapmaya yetkiniz yok.', ['status' => 403]);
        }

        $update_data = ['ID' => $clan_id];
        
        if (!empty($params['name'])) $update_data['post_title'] = sanitize_text_field($params['name']);
        if (isset($params['description'])) $update_data['post_content'] = sanitize_textarea_field($params['description']);
        
        // YENİ: Yorum durumunu güncelle
        if (isset($params['comment_status'])) {
            $update_data['comment_status'] = $params['comment_status'] === 'open' ? 'open' : 'closed';
        }

        wp_update_post($update_data);

        if (isset($params['privacy'])) update_post_meta($clan_id, 'privacy', sanitize_text_field($params['privacy']));
        if (isset($params['logo'])) update_post_meta($clan_id, 'clan_logo_url', esc_url_raw($params['logo']));

        return new WP_REST_Response(['id' => $clan_id, 'message' => 'Circle güncellendi!'], 200);
    }

    public function join_clan($request) {
        $user_id = get_current_user_id();
        $clan_id = $request->get_param('id');
        
        if (get_user_meta($user_id, 'clan_id', true)) {
            return new WP_Error('already_in_circle', 'Önce mevcut Circle\'dan ayrılmalısınız.', ['status' => 400]);
        }

        $clan = get_post($clan_id);
        if (!$clan || $clan->post_type !== 'rejimde_clan') {
            return new WP_Error('not_found', 'Circle bulunamadı.', ['status' => 404]);
        }

        $count = (int) get_post_meta($clan_id, 'member_count', true);
        update_post_meta($clan_id, 'member_count', $count + 1);

        update_user_meta($user_id, 'clan_id', $clan_id);
        update_user_meta($user_id, 'clan_role', 'member');

        return new WP_REST_Response(['message' => 'Circle\'a katıldınız!'], 200);
    }

    public function leave_clan($request) {
        $user_id = get_current_user_id();
        $clan_id = get_user_meta($user_id, 'clan_id', true);

        if (!$clan_id) {
            return new WP_Error('no_circle', 'Herhangi bir Circle\'da değilsiniz.', ['status' => 400]);
        }
        
        $count = (int) get_post_meta($clan_id, 'member_count', true);
        if ($count > 0) update_post_meta($clan_id, 'member_count', $count - 1);

        delete_user_meta($user_id, 'clan_id');
        delete_user_meta($user_id, 'clan_role');

        return new WP_REST_Response(['message' => 'Circle\'dan ayrıldınız.'], 200);
    }

    public function get_item($request) {
        $slug = $request->get_param('slug');
        
        if (is_numeric($slug)) {
            $args = ['p' => $slug, 'post_type' => 'rejimde_clan'];
        } else {
            $args = ['name' => $slug, 'post_type' => 'rejimde_clan'];
        }

        $query = new WP_Query($args);
        
        if (!$query->have_posts()) {
            return new WP_Error('not_found', 'Circle bulunamadı', ['status' => 404]);
        }

        $query->the_post();
        $post = get_post(); // Post objesini tam al
        $post_id = get_the_ID();
        $leader_id = get_post_meta($post_id, 'clan_leader_id', true);
        
        $members = get_users([
            'meta_key' => 'clan_id',
            'meta_value' => $post_id,
            'number' => 10 
        ]);
        
        $members_data = [];
        foreach ($members as $member) {
            $members_data[] = [
                'id' => $member->ID,
                'name' => $member->display_name,
                'avatar' => get_user_meta($member->ID, 'avatar_url', true),
                'role' => get_user_meta($member->ID, 'clan_role', true)
            ];
        }

        $data = [
            'id'           => $post_id,
            'name'         => get_the_title(),
            'slug'         => get_post_field('post_name', $post_id),
            'description'  => get_the_content(),
            'logo'         => get_post_meta($post_id, 'clan_logo_url', true),
            'total_score'  => (int) get_post_meta($post_id, 'total_score', true),
            'member_count' => (int) get_post_meta($post_id, 'member_count', true),
            'leader_id'    => (int) $leader_id,
            'members'      => $members_data,
            'privacy'      => get_post_meta($post_id, 'privacy', true) ?: 'public',
            'comment_status' => $post->comment_status // YENİ: Yorum durumu (open/closed)
        ];

        return new WP_REST_Response($data, 200);
    }
}