<?php
namespace Rejimde\PostTypes;

class Circle {

    public function register() {
        $labels = [
            'name'                  => 'Circles',
            'singular_name'         => 'Circle',
            'menu_name'             => 'Circles',
            'add_new'               => 'Yeni Circle Ekle',
            'add_new_item'          => 'Yeni Circle Ekle',
            'edit_item'             => 'Circle\'ı Düzenle',
            'new_item'              => 'Yeni Circle',
            'view_item'             => 'Circle\'ı Görüntüle',
            'search_items'          => 'Circle Ara',
            'not_found'             => 'Circle bulunamadı',
            'not_found_in_trash'    => 'Çöp kutusunda circle bulunamadı'
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'circles', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 22,
            'menu_icon'          => 'dashicons-groups',
            'supports'           => ['title', 'editor', 'thumbnail', 'author', 'custom-fields', 'comments'],
            'show_in_rest'       => true
        ];

        register_post_type('rejimde_circle', $args);
    }
}
