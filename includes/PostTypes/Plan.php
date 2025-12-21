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
            'rewrite'            => ['slug' => 'diyet-listesi'], // SEO dostu slug
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-list-view',
            'supports'           => ['title', 'editor', 'thumbnail', 'author', 'custom-fields'],
            'show_in_rest'       => true, // Gutenberg editörü ve REST API için şart
        ];

        register_post_type('rejimde_plan', $args);

        // Meta Alanlarını REST API'ye Tanıt (Yedek Güvenlik)
        add_action('rest_api_init', [$this, 'register_meta_fields']);
    }

    public function register_meta_fields() {
        // Plan Datası (JSON)
        register_post_meta('rejimde_plan', 'plan_data', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string', // JSON string olarak tutuyoruz
            'auth_callback' => function() { return current_user_can('edit_posts'); }
        ]);

        // Zorluk
        register_post_meta('rejimde_plan', 'difficulty', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ]);
        
        // Süre
        register_post_meta('rejimde_plan', 'duration', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ]);

        // Kalori
        register_post_meta('rejimde_plan', 'calories', [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ]);
    }
}