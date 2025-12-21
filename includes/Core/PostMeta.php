<?php
namespace Rejimde\Core;

class PostMeta {

    public function register() {
        add_action('rest_api_init', [$this, 'register_post_meta']);
    }

    public function register_post_meta() {
        // RankMath / SEO Alanları ve Diğerleri
        $meta_keys = [
            'rank_math_title',
            'rank_math_description',
            'rank_math_focus_keyword',
            '_rejimde_featured_image_url', // URL olarak saklamak istersek
        ];

        foreach ($meta_keys as $key) {
            register_post_meta('post', $key, [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
                'auth_callback' => function() { return current_user_can('edit_posts'); }
            ]);
        }
    }
}