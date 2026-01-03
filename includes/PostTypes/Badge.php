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
        
        // Admin columns
        add_filter('manage_rejimde_badge_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_rejimde_badge_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
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
        
        add_meta_box(
            'rejimde_badge_conditions',
            'Kazanma Koşulları (Rule Engine)',
            [$this, 'render_conditions_meta_box'],
            'rejimde_badge',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        $threshold = get_post_meta($post->ID, 'points_required', true);
        $action_req = get_post_meta($post->ID, 'action_required', true);
        $category = get_post_meta($post->ID, 'badge_category', true) ?: 'behavior';
        $tier = get_post_meta($post->ID, 'badge_tier', true) ?: 'bronze';
        $max_progress = get_post_meta($post->ID, 'max_progress', true) ?: 1;
        $icon = get_post_meta($post->ID, 'badge_icon', true);
        
        wp_nonce_field('rejimde_badge_save', 'rejimde_badge_nonce');
        ?>
        
        <!-- Kategori -->
        <p>
            <label for="badge_category"><strong>Kategori:</strong></label><br>
            <select name="badge_category" id="badge_category" style="width:100%">
                <option value="behavior" <?php selected($category, 'behavior'); ?>>🟢 Davranış (Behavior)</option>
                <option value="discipline" <?php selected($category, 'discipline'); ?>>🔵 Disiplin (Discipline)</option>
                <option value="social" <?php selected($category, 'social'); ?>>🟣 Sosyal (Social)</option>
                <option value="milestone" <?php selected($category, 'milestone'); ?>>🟡 Milestone</option>
            </select>
        </p>
        
        <!-- Tier -->
        <p>
            <label for="badge_tier"><strong>Seviye (Tier):</strong></label><br>
            <select name="badge_tier" id="badge_tier" style="width:100%">
                <option value="bronze" <?php selected($tier, 'bronze'); ?>>🥉 Bronze</option>
                <option value="silver" <?php selected($tier, 'silver'); ?>>🥈 Silver</option>
                <option value="gold" <?php selected($tier, 'gold'); ?>>🥇 Gold</option>
                <option value="platinum" <?php selected($tier, 'platinum'); ?>>💎 Platinum</option>
            </select>
        </p>
        
        <!-- Max Progress (Progressive Badge) -->
        <p>
            <label for="max_progress"><strong>Maksimum İlerleme:</strong></label><br>
            <input type="number" name="max_progress" id="max_progress" 
                   value="<?php echo esc_attr($max_progress); ?>" 
                   min="1" style="width:100%">
            <span class="description">
                Progressive rozet için hedef değer (örn: 10 gün, 30 streak, 5 görev).<br>
                1 = Tek seferlik rozet, >1 = Progressive rozet (ilerleme çubuğu gösterilir)
            </span>
        </p>
        
        <!-- Icon (Emoji veya URL) -->
        <p>
            <label for="badge_icon"><strong>İkon:</strong></label><br>
            <input type="text" name="badge_icon" id="badge_icon" 
                   value="<?php echo esc_attr($icon); ?>" 
                   style="width:100%" placeholder="🏆 veya dashicons-awards">
            <span class="description">Emoji veya dashicon class</span>
        </p>
        
        <!-- Mevcut alanlar -->
        <p>
            <label for="points_required"><strong>Gerekli Toplam Puan (Eski Sistem):</strong></label><br>
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
        if (get_post_type($post_id) !== 'rejimde_badge') return;
        
        // Yeni alanları kaydet
        $fields = ['badge_category', 'badge_tier', 'max_progress', 'badge_icon'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Conditions JSON oluştur
        $condition_type = $_POST['condition_type'] ?? 'simple';
        $conditions = [];
        
        switch ($condition_type) {
            case 'simple':
                $conditions = [
                    'type' => 'COUNT',
                    'event' => sanitize_text_field($_POST['simple_event'] ?? ''),
                    'target' => (int) ($_POST['simple_count'] ?? 1)
                ];
                break;
                
            case 'progressive':
                $conditions = [
                    'type' => 'COUNT_UNIQUE_DAYS',
                    'event' => sanitize_text_field($_POST['progressive_event'] ?? ''),
                    'period' => sanitize_text_field($_POST['progressive_period'] ?? 'all_time'),
                    'consecutive' => isset($_POST['progressive_consecutive'])
                ];
                break;
                
            case 'streak':
                $conditions = [
                    'type' => 'STREAK',
                    'streak_type' => sanitize_text_field($_POST['streak_type'] ?? 'daily_login'),
                    'target' => (int) ($_POST['streak_target'] ?? 7)
                ];
                break;
                
            case 'advanced':
                $json = $_POST['badge_conditions_json'] ?? '';
                $conditions = json_decode(stripslashes($json), true) ?: [];
                break;
        }
        
        update_post_meta($post_id, 'badge_conditions', $conditions);
        update_post_meta($post_id, 'condition_type', $condition_type);
        
        // Mevcut alanlar
        if (isset($_POST['points_required'])) {
            update_post_meta($post_id, 'points_required', sanitize_text_field($_POST['points_required']));
        }
        if (isset($_POST['action_required'])) {
            update_post_meta($post_id, 'action_required', sanitize_text_field($_POST['action_required']));
        }
    }
    
    public function render_conditions_meta_box($post) {
        $conditions = get_post_meta($post->ID, 'badge_conditions', true);
        $conditions_json = $conditions ? json_encode($conditions, JSON_PRETTY_PRINT) : '';
        $condition_type = get_post_meta($post->ID, 'condition_type', true) ?: 'simple';
        
        // Extract values from conditions for form population
        $simple_event = '';
        $simple_count = 1;
        $progressive_event = '';
        $progressive_period = 'all_time';
        $progressive_consecutive = false;
        $streak_type = 'daily_login';
        $streak_target = 7;
        
        if (is_array($conditions)) {
            switch ($condition_type) {
                case 'simple':
                    $simple_event = $conditions['event'] ?? '';
                    $simple_count = $conditions['target'] ?? 1;
                    break;
                case 'progressive':
                    $progressive_event = $conditions['event'] ?? '';
                    $progressive_period = $conditions['period'] ?? 'all_time';
                    $progressive_consecutive = $conditions['consecutive'] ?? false;
                    break;
                case 'streak':
                    $streak_type = $conditions['streak_type'] ?? 'daily_login';
                    $streak_target = $conditions['target'] ?? 7;
                    break;
            }
        }
        ?>
        <p>
            <label><strong>Koşul Tipi:</strong></label><br>
            <select name="condition_type" id="condition_type" style="width:100%">
                <option value="simple" <?php selected($condition_type, 'simple'); ?>>Basit (Puan Eşiği / Tek Event)</option>
                <option value="progressive" <?php selected($condition_type, 'progressive'); ?>>Progressive (Event Sayacı)</option>
                <option value="streak" <?php selected($condition_type, 'streak'); ?>>Streak Bazlı</option>
                <option value="advanced" <?php selected($condition_type, 'advanced'); ?>>Gelişmiş (JSON)</option>
            </select>
        </p>
        
        <!-- Basit Koşul -->
        <div id="simple_conditions" class="condition-panel">
            <p>
                <label><strong>Gerekli Event:</strong></label><br>
                <select name="simple_event" style="width:100%">
                    <option value="">-- Event Seçin --</option>
                    <option value="exercise_completed" <?php selected($simple_event, 'exercise_completed'); ?>>Egzersiz Tamamlama</option>
                    <option value="diet_completed" <?php selected($simple_event, 'diet_completed'); ?>>Diyet Tamamlama</option>
                    <option value="blog_points_claimed" <?php selected($simple_event, 'blog_points_claimed'); ?>>Blog Okuma</option>
                    <option value="water_goal_reached" <?php selected($simple_event, 'water_goal_reached'); ?>>Su Hedefi</option>
                    <option value="weekly_task_completed" <?php selected($simple_event, 'weekly_task_completed'); ?>>Haftalık Görev</option>
                    <option value="monthly_task_completed" <?php selected($simple_event, 'monthly_task_completed'); ?>>Aylık Görev</option>
                    <option value="circle_task_contributed" <?php selected($simple_event, 'circle_task_contributed'); ?>>Circle Katkısı</option>
                    <option value="highfive_sent" <?php selected($simple_event, 'highfive_sent'); ?>>High-Five</option>
                    <option value="comment_created" <?php selected($simple_event, 'comment_created'); ?>>Yorum</option>
                    <option value="follow_accepted" <?php selected($simple_event, 'follow_accepted'); ?>>Takip</option>
                </select>
            </p>
            <p>
                <label><strong>Gerekli Tekrar Sayısı:</strong></label><br>
                <input type="number" name="simple_count" min="1" value="<?php echo esc_attr($simple_count); ?>" style="width:100%">
            </p>
        </div>
        
        <!-- Progressive Koşul -->
        <div id="progressive_conditions" class="condition-panel" style="display:none">
            <p>
                <label><strong>Sayılacak Event:</strong></label><br>
                <select name="progressive_event" style="width:100%">
                    <option value="">-- Event Seçin --</option>
                    <option value="exercise_completed" <?php selected($progressive_event, 'exercise_completed'); ?>>Egzersiz Tamamlama</option>
                    <option value="diet_completed" <?php selected($progressive_event, 'diet_completed'); ?>>Diyet Tamamlama</option>
                    <option value="blog_points_claimed" <?php selected($progressive_event, 'blog_points_claimed'); ?>>Blog Okuma</option>
                    <option value="water_goal_reached" <?php selected($progressive_event, 'water_goal_reached'); ?>>Su Hedefi</option>
                </select>
            </p>
            <p>
                <label><strong>Periyot:</strong></label><br>
                <select name="progressive_period" style="width:100%">
                    <option value="all_time" <?php selected($progressive_period, 'all_time'); ?>>Tüm Zamanlar</option>
                    <option value="daily" <?php selected($progressive_period, 'daily'); ?>>Günlük (Unique günler sayılır)</option>
                    <option value="weekly" <?php selected($progressive_period, 'weekly'); ?>>Haftalık</option>
                    <option value="monthly" <?php selected($progressive_period, 'monthly'); ?>>Aylık</option>
                </select>
            </p>
            <p>
                <label><strong>Ardışık mı?</strong></label><br>
                <input type="checkbox" name="progressive_consecutive" value="1" <?php checked($progressive_consecutive); ?>>
                <span class="description">Ardışık günler/haftalar gerekli mi?</span>
            </p>
        </div>
        
        <!-- Streak Koşul -->
        <div id="streak_conditions" class="condition-panel" style="display:none">
            <p>
                <label><strong>Streak Tipi:</strong></label><br>
                <select name="streak_type" style="width:100%">
                    <option value="daily_login" <?php selected($streak_type, 'daily_login'); ?>>Günlük Giriş</option>
                    <option value="daily_exercise" <?php selected($streak_type, 'daily_exercise'); ?>>Günlük Egzersiz</option>
                    <option value="daily_nutrition" <?php selected($streak_type, 'daily_nutrition'); ?>>Günlük Beslenme</option>
                </select>
            </p>
            <p>
                <label><strong>Gerekli Streak:</strong></label><br>
                <input type="number" name="streak_target" min="1" value="<?php echo esc_attr($streak_target); ?>" style="width:100%">
            </p>
        </div>
        
        <!-- Gelişmiş JSON -->
        <div id="advanced_conditions" class="condition-panel" style="display:none">
            <p>
                <label><strong>Koşullar (JSON):</strong></label><br>
                <textarea name="badge_conditions_json" rows="10" style="width:100%; font-family:monospace"><?php 
                    echo esc_textarea($conditions_json); 
                ?></textarea>
                <span class="description">
                    Örnek:<br>
                    <code>{"type": "COUNT", "event": "exercise_completed", "target": 10}</code>
                </span>
            </p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#condition_type').on('change', function() {
                $('.condition-panel').hide();
                $('#' + $(this).val() + '_conditions').show();
            }).trigger('change');
        });
        </script>
        <?php
    }
    
    /**
     * Admin list columns
     */
    public function add_admin_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['badge_icon'] = 'İkon';
                $new_columns['badge_category'] = 'Kategori';
                $new_columns['badge_tier'] = 'Tier';
                $new_columns['max_progress'] = 'İlerleme';
            }
        }
        return $new_columns;
    }
    
    public function render_admin_columns($column, $post_id) {
        switch ($column) {
            case 'badge_icon':
                echo esc_html(get_post_meta($post_id, 'badge_icon', true) ?: '🏅');
                break;
            case 'badge_category':
                $cat = get_post_meta($post_id, 'badge_category', true);
                $labels = [
                    'behavior' => '🟢 Davranış',
                    'discipline' => '🔵 Disiplin',
                    'social' => '🟣 Sosyal',
                    'milestone' => '🟡 Milestone'
                ];
                echo $labels[$cat] ?? '-';
                break;
            case 'badge_tier':
                $tier = get_post_meta($post_id, 'badge_tier', true);
                $labels = [
                    'bronze' => '🥉',
                    'silver' => '🥈',
                    'gold' => '🥇',
                    'platinum' => '💎'
                ];
                echo $labels[$tier] ?? '-';
                break;
            case 'max_progress':
                $max = get_post_meta($post_id, 'max_progress', true) ?: 1;
                echo $max > 1 ? "0/{$max}" : 'Tek seferlik';
                break;
        }
    }
}