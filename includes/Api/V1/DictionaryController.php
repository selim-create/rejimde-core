<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Query;
use WP_Error;

class DictionaryController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'dictionary';

    public function register_routes() {
        // Liste ve Arama
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => '__return_true',
        ]);

        // Yeni Terim Ekle (YENİ)
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'create_item'],
            'permission_callback' => [$this, 'check_pro_auth'], // Sadece uzmanlar
        ]);

        // Terim Güncelle (YENİ)
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'update_item'],
            'permission_callback' => [$this, 'check_pro_auth'],
        ]);

        // Terim Sil (YENİ)
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_item'],
            'permission_callback' => [$this, 'check_pro_auth'],
        ]);

        // Tekil Detay (Slug ile)
        register_rest_route($this->namespace, '/' . $this->base . '/slug/(?P<slug>[a-zA-Z0-9-_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_item_by_slug'],
            'permission_callback' => '__return_true',
        ]);
        
        // Tekil Detay (ID ile)
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_item_by_id'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function check_pro_auth() {
        // Sadece giriş yapmış ve 'rejimde_pro' rolüne sahip kullanıcılar işlem yapabilir
        if (!is_user_logged_in()) return false;
        $user = wp_get_current_user();
        return in_array('rejimde_pro', (array) $user->roles) || current_user_can('manage_options');
    }

    public function get_items($request) {
        $args = [
            'post_type' => 'rejimde_dictionary',
            'posts_per_page' => 20,
            'post_status' => 'publish'
        ];

        if ($request->get_param('search')) {
            $args['s'] = sanitize_text_field($request->get_param('search'));
        }
        
        if ($request->get_param('category')) {
            $args['tax_query'] = [[
                'taxonomy' => 'dictionary_category',
                'field' => 'slug',
                'terms' => sanitize_text_field($request->get_param('category'))
            ]];
        }

        $query = new WP_Query($args);
        $data = [];

        foreach ($query->posts as $post) {
            $data[] = $this->prepare_item($post);
        }

        return new WP_REST_Response($data, 200);
    }

    public function get_item_by_slug($request) {
        $slug = $request->get_param('slug');
        $args = ['name' => $slug, 'post_type' => 'rejimde_dictionary', 'numberposts' => 1];
        $posts = get_posts($args);

        if (empty($posts)) return new WP_Error('not_found', 'Terim bulunamadı', ['status' => 404]);

        return new WP_REST_Response($this->prepare_item($posts[0]), 200);
    }
    
    public function get_item_by_id($request) {
        $id = $request->get_param('id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'rejimde_dictionary') {
             return new WP_Error('not_found', 'Terim bulunamadı', ['status' => 404]);
        }

        return new WP_REST_Response($this->prepare_item($post), 200);
    }

    /**
     * TERİM OLUŞTURMA
     */
    public function create_item($request) {
        $params = $request->get_json_params();
        
        if (empty($params['title'])) {
            return new WP_Error('missing_title', 'Başlık zorunludur.', ['status' => 400]);
        }

        $post_data = [
            'post_title'   => sanitize_text_field($params['title']),
            'post_content' => wp_kses_post($params['content'] ?? ''),
            'post_excerpt' => sanitize_text_field($params['excerpt'] ?? ''),
            'post_status'  => 'publish',
            'post_type'    => 'rejimde_dictionary',
            'post_author'  => get_current_user_id()
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) return $post_id;

        $this->update_meta_and_terms($post_id, $params);

        return new WP_REST_Response([
            'success' => true, 
            'id' => $post_id, 
            'slug' => get_post_field('post_name', $post_id),
            'message' => 'Terim başarıyla oluşturuldu.'
        ], 201);
    }

    /**
     * TERİM GÜNCELLEME
     */
    public function update_item($request) {
        $post_id = $request->get_param('id');
        $params = $request->get_json_params();
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'rejimde_dictionary') {
            return new WP_Error('not_found', 'Terim bulunamadı.', ['status' => 404]);
        }
        
        // Yetki kontrolü (Kendi içeriği mi?)
        if ($post->post_author != get_current_user_id() && !current_user_can('manage_options')) {
             return new WP_Error('forbidden', 'Bu içeriği düzenleme yetkiniz yok.', ['status' => 403]);
        }

        $update_data = ['ID' => $post_id];
        if (isset($params['title'])) $update_data['post_title'] = sanitize_text_field($params['title']);
        if (isset($params['content'])) $update_data['post_content'] = wp_kses_post($params['content']);
        if (isset($params['excerpt'])) $update_data['post_excerpt'] = sanitize_text_field($params['excerpt']);

        wp_update_post($update_data);
        $this->update_meta_and_terms($post_id, $params);

        return new WP_REST_Response([
            'success' => true, 
            'id' => $post_id,
            'message' => 'Terim güncellendi.'
        ], 200);
    }

    /**
     * TERİM SİLME
     */
    public function delete_item($request) {
        $post_id = $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'rejimde_dictionary') {
            return new WP_Error('not_found', 'Terim bulunamadı.', ['status' => 404]);
        }

        if ($post->post_author != get_current_user_id() && !current_user_can('manage_options')) {
             return new WP_Error('forbidden', 'Bu içeriği silme yetkiniz yok.', ['status' => 403]);
        }

        wp_delete_post($post_id, true); // Kalıcı sil

        return new WP_REST_Response(['success' => true, 'message' => 'Terim silindi.'], 200);
    }

    // Yardımcı: Meta ve Term Güncellemeleri
    private function update_meta_and_terms($post_id, $params) {
        // Meta Veriler (image_url eklendi)
        $meta_fields = ['video_url', 'image_url', 'main_benefit', 'difficulty', 'alt_names'];
        foreach ($meta_fields as $key) {
            if (isset($params[$key])) {
                update_post_meta($post_id, $key, sanitize_text_field($params[$key]));
            }
        }
        
        // Kategori (Taxonomy: dictionary_category)
        if (!empty($params['category'])) {
            wp_set_object_terms($post_id, $params['category'], 'dictionary_category');
        }

        // Kas Grupları (Taxonomy: muscle_group)
        if (isset($params['muscles']) && is_array($params['muscles'])) {
            wp_set_object_terms($post_id, $params['muscles'], 'muscle_group');
        }

        // Ekipmanlar (Taxonomy: equipment)
        if (isset($params['equipment']) && is_array($params['equipment'])) {
            wp_set_object_terms($post_id, $params['equipment'], 'equipment');
        }
    }

    private function prepare_item($post) {
        $terms = wp_get_post_terms($post->ID, 'dictionary_category', ['fields' => 'names']);
        $muscles = wp_get_post_terms($post->ID, 'muscle_group', ['fields' => 'names']);
        $equipment = wp_get_post_terms($post->ID, 'equipment', ['fields' => 'names']);
      // GÖRSEL MANTIĞI: Önce Featured Image, yoksa Meta'daki URL
        $image = get_the_post_thumbnail_url($post->ID, 'large');
        if (!$image) {
            $image = get_post_meta($post->ID, 'image_url', true);
        }
        
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'content' => $post->post_content,
            'excerpt' => get_the_excerpt($post),
            'image' => $image, // Güncellendi
            'category' => !empty($terms) ? $terms[0] : 'Genel',
            'muscles' => $muscles,
            'equipment' => $equipment,
            'video_url' => get_post_meta($post->ID, 'video_url', true),
            'benefit' => get_post_meta($post->ID, 'main_benefit', true),
            'difficulty' => (int) get_post_meta($post->ID, 'difficulty', true),
            'alt_names' => get_post_meta($post->ID, 'alt_names', true),
        ];
    }
}