<?php
namespace Rejimde\PostTypes;

class Dictionary {

    public function register() {
        $labels = [
            'name'                  => 'Sözlük (Wiki)',
            'singular_name'         => 'Terim',
            'menu_name'             => 'Rejimde Sözlük',
            'add_new'               => 'Yeni Terim Ekle',
            'add_new_item'          => 'Yeni Terim Ekle',
            'edit_item'             => 'Terimi Düzenle',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'sozluk', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 23,
            'menu_icon'          => 'dashicons-book-alt',
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest'       => true 
        ];

        register_post_type('rejimde_dictionary', $args);

        // --- TAKSONOMİLER ---
        register_taxonomy('dictionary_category', 'rejimde_dictionary', [
            'labels' => ['name' => 'Kategoriler', 'singular_name' => 'Kategori'],
            'hierarchical' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'sozluk-kategori'],
        ]);

        register_taxonomy('muscle_group', 'rejimde_dictionary', [
            'labels' => ['name' => 'Kas Grupları', 'singular_name' => 'Kas Grubu'],
            'hierarchical' => false,
            'show_in_rest' => true,
        ]);

        register_taxonomy('equipment', 'rejimde_dictionary', [
            'labels' => ['name' => 'Ekipmanlar', 'singular_name' => 'Ekipman'],
            'hierarchical' => false,
            'show_in_rest' => true,
        ]);

        // Metabox Ekleme
        add_action('add_meta_boxes', [$this, 'add_custom_meta_boxes']);
        add_action('save_post', [$this, 'save_custom_meta_boxes']);
    }

    public function add_custom_meta_boxes() {
        add_meta_box('dict_details', 'Terim Detayları', [$this, 'render_meta_box'], 'rejimde_dictionary', 'normal', 'high');
    }

    public function render_meta_box($post) {
        $meta = get_post_meta($post->ID);
        $video_url = $meta['video_url'][0] ?? '';
        $image_url = $meta['image_url'][0] ?? ''; // YENİ
        $benefit = $meta['main_benefit'][0] ?? '';
        $difficulty = $meta['difficulty'][0] ?? '1';
        $alt_names = $meta['alt_names'][0] ?? '';

        wp_nonce_field('dict_save', 'dict_nonce');
        ?>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
            <div>
                <label><strong>Görsel URL (Dış Bağlantı):</strong></label><br>
                <input type="text" name="image_url" value="<?php echo esc_attr($image_url); ?>" style="width:100%" placeholder="https://...">
                <p class="description">Öne çıkarılan görsel yoksa bu link kullanılır.</p>
            </div>
            <div>
                <label><strong>Video URL (Youtube ID):</strong></label><br>
                <input type="text" name="video_url" value="<?php echo esc_attr($video_url); ?>" style="width:100%" placeholder="Örn: dQw4w9WgXcQ">
            </div>
            <div>
                <label><strong>Ana Fayda (Tek Cümle):</strong></label><br>
                <input type="text" name="main_benefit" value="<?php echo esc_attr($benefit); ?>" style="width:100%" placeholder="Örn: Karın kaslarını güçlendirir.">
            </div>
            <div>
                <label><strong>Zorluk Seviyesi (1-5):</strong></label><br>
                <input type="number" name="difficulty" min="1" max="5" value="<?php echo esc_attr($difficulty); ?>" style="width:100%">
            </div>
            <div style="grid-column: 1 / -1;">
                <label><strong>Alternatif İsimler:</strong></label><br>
                <input type="text" name="alt_names" value="<?php echo esc_attr($alt_names); ?>" style="width:100%" placeholder="Örn: Çömelme, Squatting">
            </div>
        </div>
        <?php
    }

    public function save_custom_meta_boxes($post_id) {
        if (!isset($_POST['dict_nonce']) || !wp_verify_nonce($_POST['dict_nonce'], 'dict_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        
        $fields = ['video_url', 'image_url', 'main_benefit', 'difficulty', 'alt_names']; // image_url eklendi
        foreach ($fields as $field) {
            if (isset($_POST[$field])) update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
}