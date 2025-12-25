<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Query;
use WP_Error;

class ProfessionalController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'professionals';

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_items'],
            'permission_callback' => function() { return true; },
        ]);
        
        // Slug regex'ini _ (alt çizgi) destekleyecek şekilde güncelledik
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<slug>[a-zA-Z0-9-_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_item'],
            'permission_callback' => function() { return true; },
        ]);
    }

    /**
     * Uzmanları Listele
     */
    public function get_items($request) {
        $args = [
            'post_type'      => 'rejimde_pro',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
        ];

        $type = $request->get_param('type');
        if (!empty($type)) {
            $args['meta_query'][] = [
                'key'     => 'uzmanlik_tipi',
                'value'   => sanitize_text_field($type),
                'compare' => '='
            ];
        }

        $query = new WP_Query($args);
        $experts = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $user_id = get_post_meta($post_id, 'related_user_id', true);
                
                // Username bilgisini al
                $username = '';
                if ($user_id) {
                    $user_info = get_userdata($user_id);
                    if ($user_info) {
                        $username = $user_info->user_login;
                    }
                }

                $image = 'https://placehold.co/150';
                if (has_post_thumbnail()) {
                    $image = get_the_post_thumbnail_url($post_id, 'medium');
                } elseif ($user_id) {
                    $user_avatar = get_user_meta($user_id, 'avatar_url', true);
                    if ($user_avatar) $image = $user_avatar;
                }

                // is_claimed verisini güvenli al
                $is_claimed_meta = get_post_meta($post_id, 'is_claimed', true);
                $is_claimed = ($is_claimed_meta === '1' || $is_claimed_meta === true);

                $experts[] = [
                    'id'            => $post_id,
                    'name'          => get_the_title(),
                    'slug'          => get_post_field('post_name', $post_id),
                    'username'      => $username, // EKLENDİ
                    'type'          => get_post_meta($post_id, 'uzmanlik_tipi', true) ?: 'dietitian',
                    'title'         => get_post_meta($post_id, 'unvan', true) ?: 'Uzman',
                    'image'         => $image,
                    'rating'        => get_post_meta($post_id, 'puan', true) ?: '0.0',
                    'score_impact'  => get_post_meta($post_id, 'skor_etkisi', true) ?: '--',
                    'is_verified'   => get_post_meta($post_id, 'onayli', true) === '1',
                    'is_featured'   => get_post_meta($post_id, 'editor_secimi', true) === '1',
                    'is_online'     => true,
                    'location'      => get_post_meta($post_id, 'konum', true),
                    'brand'         => get_post_meta($post_id, 'kurum', true),
                    'is_claimed'    => $is_claimed
                ];
            }
            wp_reset_postdata();
        }

        return new WP_REST_Response($experts, 200);
    }
    
    /**
     * Tekil Uzman Getir
     */
    public function get_item($request) {
        $slug = $request->get_param('slug');
        
        $args = [
            'name'        => $slug,
            'post_type'   => 'rejimde_pro',
            'numberposts' => 1,
        ];
        
        $posts = get_posts($args);
        
        if (empty($posts)) {
            return new WP_Error('not_found', 'Uzman bulunamadı', ['status' => 404]);
        }
        
        $post = $posts[0];
        $post_id = $post->ID;
        $user_id = get_post_meta($post_id, 'related_user_id', true);
        
        // Username bilgisini al
        $username = '';
        if ($user_id) {
            $user_info = get_userdata($user_id);
            if ($user_info) {
                $username = $user_info->user_login;
            }
        }

        $image = 'https://placehold.co/300';
        if (has_post_thumbnail($post_id)) {
             $image = get_the_post_thumbnail_url($post_id, 'large');
        } elseif ($user_id) {
             $user_avatar = get_user_meta($user_id, 'avatar_url', true);
             if ($user_avatar) $image = $user_avatar;
        }

        // is_claimed verisini güvenli al
        $is_claimed_meta = get_post_meta($post_id, 'is_claimed', true);
        $is_claimed = ($is_claimed_meta === '1' || $is_claimed_meta === true);

        $data = [
            'id'            => $post_id,
            'name'          => $post->post_title,
            'slug'          => $post->post_name,
            'username'      => $username, // EKLENDİ
            'bio'           => $post->post_content,
            'type'          => get_post_meta($post_id, 'uzmanlik_tipi', true) ?: 'dietitian',
            'title'         => get_post_meta($post_id, 'unvan', true) ?: 'Uzman',
            'image'         => $image,
            'rating'        => get_post_meta($post_id, 'puan', true) ?: '0.0',
            'score_impact'  => get_post_meta($post_id, 'skor_etkisi', true) ?: '--',
            'is_verified'   => get_post_meta($post_id, 'onayli', true) === '1',
            'is_featured'   => get_post_meta($post_id, 'editor_secimi', true) === '1',
            'location'      => get_post_meta($post_id, 'konum', true),
            'brand'         => get_post_meta($post_id, 'kurum', true),
            'branches'      => get_post_meta($post_id, 'branslar', true),
            'services'      => get_post_meta($post_id, 'hizmetler', true),
            'is_claimed'    => $is_claimed,
            'client_types'  => get_user_meta($user_id, 'client_types', true),
            'consultation_types' => get_user_meta($user_id, 'consultation_types', true),
            'address'       => get_user_meta($user_id, 'address', true)
        ];

        return new WP_REST_Response($data, 200);
    }
}