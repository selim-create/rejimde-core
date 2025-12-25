<?php
namespace Rejimde\PostTypes;

class ExercisePlan {

    public function register() {
        $labels = [
            'name'                  => 'Egzersiz Planları',
            'singular_name'         => 'Egzersiz Planı',
            'menu_name'             => 'Egzersiz Planları',
            'add_new'               => 'Yeni Plan Ekle',
            'add_new_item'          => 'Yeni Egzersiz Planı Ekle',
            'edit_item'             => 'Planı Düzenle',
            'view_item'             => 'Planı Görüntüle',
            'all_items'             => 'Tüm Egzersiz Planları',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'egzersiz-programi'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 21,
            'menu_icon'          => 'dashicons-universal-access', // Uygun bir ikon
            'supports'           => ['title', 'editor', 'thumbnail', 'author', 'custom-fields', 'comments'],
            'show_in_rest'       => true,
        ];

        register_post_type('rejimde_exercise', $args);

        // Meta Alanlarını REST API'ye Tanıt
        add_action('rest_api_init', [$this, 'register_meta_fields']);
    }

    public function register_meta_fields() {
        $auth_callback = function() { return current_user_can('edit_posts'); };
        
        $meta_fields = [
            // JSON Olarak Saklanan Büyük Veriler
            'plan_data' => ['type' => 'string', 'single' => true], // Günler ve Hareketler
            'equipment_list' => ['type' => 'string', 'single' => true], // Ekipmanlar
            'tags' => ['type' => 'string', 'single' => true], // Etiketler

            // Basit Metin/Sayı Alanları
            'difficulty' => ['type' => 'string', 'single' => true],
            'duration' => ['type' => 'string', 'single' => true],
            'calories' => ['type' => 'string', 'single' => true],
            'score_reward' => ['type' => 'string', 'single' => true],
            'exercise_category' => ['type' => 'string', 'single' => true],

            // Durum ve Kullanıcı Bilgileri
            'is_verified' => ['type' => 'boolean', 'single' => true],
            'approved_by' => ['type' => 'integer', 'single' => true],
            'completed_users' => ['type' => 'string', 'single' => true],
            'started_users' => ['type' => 'string', 'single' => true],

            // SEO Alanları
            'rank_math_title' => ['type' => 'string', 'single' => true],
            'rank_math_description' => ['type' => 'string', 'single' => true],
            'rank_math_focus_keyword' => ['type' => 'string', 'single' => true],
        ];

        foreach ($meta_fields as $key => $args) {
            register_post_meta('rejimde_exercise', $key, [
                'show_in_rest' => true,
                'single'       => $args['single'],
                'type'         => $args['type'],
                'auth_callback' => $auth_callback
            ]);
        }
    }
}