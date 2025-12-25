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
            'edit_item'             => 'Circle Düzenle',
            'new_item'              => 'Yeni Circle',
            'view_item'             => 'Circle Görüntüle',
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
        
        // Migration: Eski rejimde_clan post'larını rejimde_circle olarak güncelle
        $this->maybe_migrate_old_clans();
    }
    
    /**
     * Eski 'rejimde_clan' post type'ını 'rejimde_circle' olarak migrate et
     * Bu fonksiyon sadece bir kez çalışır
     */
    private function maybe_migrate_old_clans() {
        // Migration yapılmış mı kontrol et
        if (get_option('rejimde_clan_to_circle_migrated_v1')) {
            return;
        }
        
        global $wpdb;
        
        // Eski clan post'larını circle olarak güncelle
        $wpdb->update(
            $wpdb->posts,
            ['post_type' => 'rejimde_circle'],
            ['post_type' => 'rejimde_clan']
        );
        
        // User meta'ları güncelle: clan_id → circle_id
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s", 'circle_id', 'clan_id'));
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s", 'circle_role', 'clan_role'));
        
        // Post meta'ları güncelle
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s", 'circle_leader_id', 'clan_leader_id'));
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s", 'circle_logo_url', 'clan_logo_url'));
        
        // Migration tamamlandı olarak işaretle
        update_option('rejimde_clan_to_circle_migrated_v1', true);
    }
}
