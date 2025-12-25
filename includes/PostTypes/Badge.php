<?php
namespace Rejimde\PostTypes;

class Badge {

    public function register() {
        $labels = [
            'name'                  => 'Rozetler',
            'singular_name'         => 'Rozet',
            'menu_name'             => 'Rozetler',
            'add_new'               => 'Yeni Rozet Ekle',
            'add_new_item'          => 'Yeni Rozet Ekle',
            'edit_item'             => 'Rozeti Düzenle',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 22,
            'menu_icon'          => 'dashicons-awards',
            'supports'           => ['title', 'thumbnail', 'editor'],
            'show_in_rest'       => true 
        ];

        register_post_type('rejimde_badge', $args);

        // Metabox Ekleme (Puan Eşiği)
        add_action('add_meta_boxes', [$this, 'add_badge_meta_boxes']);
        add_action('save_post', [$this, 'save_badge_meta_boxes']);
    }

    public function add_badge_meta_boxes() {
        add_meta_box(
            'rejimde_badge_details',
            'Rozet Ayarları',
            [$this, 'render_meta_box'],
            'rejimde_badge',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        $threshold = get_post_meta($post->ID, 'points_required', true);
        $action_req = get_post_meta($post->ID, 'action_required', true); // Opsiyonel: Belirli bir eylem sayısı
        
        wp_nonce_field('rejimde_badge_save', 'rejimde_badge_nonce');
        ?>
        <p>
            <label for="points_required"><strong>Gerekli Toplam Puan:</strong></label><br>
            <input type="number" name="points_required" id="points_required" value="<?php echo esc_attr($threshold); ?>" style="width:100%">
            <span class="description">Kullanıcı bu toplam puana ulaştığında rozeti kazanır.</span>
        </p>
        <p>
            <label for="action_required"><strong>Özel Koşul (Opsiyonel):</strong></label><br>
            <input type="text" name="action_required" id="action_required" value="<?php echo esc_attr($action_req); ?>" style="width:100%" placeholder="Örn: 7_day_streak">
        </p>
        <?php
    }

    public function save_badge_meta_boxes($post_id) {
        if (!isset($_POST['rejimde_badge_nonce']) || !wp_verify_nonce($_POST['rejimde_badge_nonce'], 'rejimde_badge_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        
        if (isset($_POST['points_required'])) {
            update_post_meta($post_id, 'points_required', sanitize_text_field($_POST['points_required']));
        }
        if (isset($_POST['action_required'])) {
            update_post_meta($post_id, 'action_required', sanitize_text_field($_POST['action_required']));
        }
    }
}