<?php
namespace Rejimde\PostTypes;

class Plan {

    public function register() {
        $labels = [
            'name'                  => 'Diyet Listeleri',
            'singular_name'         => 'Diyet Listesi',
            'menu_name'             => 'Diyet Listeleri',
            'add_new'               => 'Yeni Plan Ekle',
            'add_new_item'          => 'Yeni Diyet Listesi Ekle',
            'edit_item'             => 'Planı Düzenle',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'diyet-listesi'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-list-view',
            'supports'           => ['title', 'editor', 'thumbnail', 'author', 'custom-fields', 'comments'], // Yorumlar eklendi
            'show_in_rest'       => true,
        ];

        register_post_type('rejimde_plan', $args);

        add_action('rest_api_init', [$this, 'register_meta_fields']);
    }

    public function register_meta_fields() {
        $auth_callback = function() { return current_user_can('edit_posts'); };
        
        $meta_fields = [
            'plan_data' => ['type' => 'string', 'single' => true],
            'shopping_list' => ['type' => 'string', 'single' => true],
            'tags' => ['type' => 'string', 'single' => true],
            'difficulty' => ['type' => 'string', 'single' => true],
            'duration' => ['type' => 'string', 'single' => true],
            'calories' => ['type' => 'string', 'single' => true],
            'score_reward' => ['type' => 'string', 'single' => true],
            'diet_category' => ['type' => 'string', 'single' => true],
            
            // Yeni Alanlar
            'is_verified' => ['type' => 'boolean', 'single' => true],
            'approved_by' => ['type' => 'integer', 'single' => true], // Onaylayan Uzman ID
            'completed_users' => ['type' => 'string', 'single' => true], // JSON array of user IDs
            'started_users' => ['type' => 'string', 'single' => true], // JSON array of user IDs

            'rank_math_title' => ['type' => 'string', 'single' => true],
            'rank_math_description' => ['type' => 'string', 'single' => true],
            'rank_math_focus_keyword' => ['type' => 'string', 'single' => true],
        ];

        foreach ($meta_fields as $key => $args) {
            register_post_meta('rejimde_plan', $key, [
                'show_in_rest' => true,
                'single'       => $args['single'],
                'type'         => $args['type'],
                'auth_callback' => $auth_callback
            ]);
        }
    }
}