<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

class FavoritesController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'favorites';

    public function register_routes() {
        // Favorileri listele
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_favorites'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Favorilere ekle/çıkar (toggle)
        register_rest_route($this->namespace, '/' . $this->base . '/toggle', [
            'methods' => 'POST',
            'callback' => [$this, 'toggle_favorite'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // Favori mi kontrol et
        register_rest_route($this->namespace, '/' . $this->base . '/check/(?P<content_type>[a-z]+)/(?P<content_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'check_favorite'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    public function check_auth() {
        return is_user_logged_in();
    }

    public function get_favorites($request) {
        $user_id = get_current_user_id();
        $content_type = $request->get_param('type'); // blog, diet, exercise

        $meta_key = 'rejimde_favorites';
        $favorites = get_user_meta($user_id, $meta_key, true);
        
        if (!is_array($favorites)) {
            $favorites = ['blog' => [], 'diet' => [], 'exercise' => []];
        }

        if ($content_type && isset($favorites[$content_type])) {
            return $this->success($favorites[$content_type]);
        }

        return $this->success($favorites);
    }

    public function toggle_favorite($request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        
        $content_type = sanitize_text_field($params['content_type'] ?? '');
        $content_id = (int) ($params['content_id'] ?? 0);

        if (empty($content_type) || empty($content_id)) {
            return new WP_Error('missing_params', 'İçerik tipi ve ID gerekli', ['status' => 400]);
        }

        $meta_key = 'rejimde_favorites';
        $favorites = get_user_meta($user_id, $meta_key, true);
        
        if (!is_array($favorites)) {
            $favorites = ['blog' => [], 'diet' => [], 'exercise' => []];
        }

        if (!isset($favorites[$content_type])) {
            $favorites[$content_type] = [];
        }

        $is_favorite = false;
        $index = array_search($content_id, $favorites[$content_type]);

        if ($index !== false) {
            // Favorilerden çıkar
            array_splice($favorites[$content_type], $index, 1);
            $message = 'Favorilerden çıkarıldı';
        } else {
            // Favorilere ekle
            $favorites[$content_type][] = $content_id;
            $is_favorite = true;
            $message = 'Favorilere eklendi! ⭐';
        }

        update_user_meta($user_id, $meta_key, $favorites);

        return $this->success([
            'is_favorite' => $is_favorite,
            'message' => $message
        ]);
    }

    public function check_favorite($request) {
        $user_id = get_current_user_id();
        $content_type = $request->get_param('content_type');
        $content_id = (int) $request->get_param('content_id');

        $meta_key = 'rejimde_favorites';
        $favorites = get_user_meta($user_id, $meta_key, true);
        
        if (!is_array($favorites) || !isset($favorites[$content_type])) {
            return $this->success(['is_favorite' => false]);
        }

        $is_favorite = in_array($content_id, $favorites[$content_type]);

        return $this->success(['is_favorite' => $is_favorite]);
    }

    protected function success($data) {
        return new WP_REST_Response(['status' => 'success', 'data' => $data], 200);
    }
}
