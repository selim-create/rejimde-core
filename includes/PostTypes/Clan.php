<?php
namespace Rejimde\PostTypes;

class Clan {

    public function register() {
        $labels = [
            'name'                  => 'Klanlar',
            'singular_name'         => 'Klan',
            'menu_name'             => 'Klanlar',
            'add_new'               => 'Yeni Klan Ekle',
            'add_new_item'          => 'Yeni Klan Ekle',
            'edit_item'             => 'Klanı Düzenle',
            'new_item'              => 'Yeni Klan',
            'view_item'             => 'Klanı Görüntüle',
            'search_items'          => 'Klan Ara',
            'not_found'             => 'Klan bulunamadı',
            'not_found_in_trash'    => 'Çöp kutusunda klan bulunamadı'
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'clans', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 22,
            'menu_icon'          => 'dashicons-shield',
            // 'comments' eklendi: Yorum yapabilmek için şart!
            'supports'           => ['title', 'editor', 'thumbnail', 'author', 'custom-fields', 'comments'], 
            'show_in_rest'       => true 
        ];

        register_post_type('rejimde_clan', $args);
    }
}