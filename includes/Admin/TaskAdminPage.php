<?php
namespace Rejimde\Admin;

/**
 * Task Admin Page
 * 
 * WordPress admin panel for managing tasks (hybrid config + database system)
 */
class TaskAdminPage {
    
    private $taskService;
    
    public function __construct() {
        if (class_exists('\Rejimde\Services\TaskService')) {
            $this->taskService = new \Rejimde\Services\TaskService();
        }
    }
    
    public function register() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_rejimde_save_task', [$this, 'ajax_save_task']);
        add_action('wp_ajax_rejimde_delete_task', [$this, 'ajax_delete_task']);
        add_action('wp_ajax_rejimde_toggle_task', [$this, 'ajax_toggle_task']);
    }
    
    public function add_menu_page() {
        add_menu_page(
            'Rejimde Görevler',
            'Görevler',
            'manage_options',
            'rejimde-tasks',
            [$this, 'render_page'],
            'dashicons-clipboard',
            23
        );
    }
    
    public function register_settings() {
        // Register any settings if needed
    }
    
    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_rejimde-tasks') {
            return;
        }
        
        wp_enqueue_style(
            'rejimde-task-admin',
            REJIMDE_URL . 'assets/admin/css/task-admin.css',
            [],
            REJIMDE_VERSION
        );
        
        wp_enqueue_script(
            'rejimde-task-admin',
            REJIMDE_URL . 'assets/admin/js/task-admin.js',
            ['jquery'],
            REJIMDE_VERSION,
            true
        );
        
        wp_localize_script('rejimde-task-admin', 'rejimdeTaskAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rejimde_task_admin'),
        ]);
    }
    
    public function render_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'config';
        
        ?>
        <div class="wrap">
            <h1>Rejimde Görevler</h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=rejimde-tasks&tab=config" 
                   class="nav-tab <?php echo $active_tab === 'config' ? 'nav-tab-active' : ''; ?>">
                    Config Görevleri
                </a>
                <a href="?page=rejimde-tasks&tab=dynamic" 
                   class="nav-tab <?php echo $active_tab === 'dynamic' ? 'nav-tab-active' : ''; ?>">
                    Dinamik Görevler
                </a>
                <a href="?page=rejimde-tasks&tab=new" 
                   class="nav-tab <?php echo $active_tab === 'new' ? 'nav-tab-active' : ''; ?>">
                    Yeni Görev Ekle
                </a>
            </h2>
            
            <div class="rejimde-task-content">
                <?php
                switch ($active_tab) {
                    case 'config':
                        $this->render_config_tasks();
                        break;
                    case 'dynamic':
                        $this->render_dynamic_tasks();
                        break;
                    case 'new':
                        $this->render_new_task_form();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_config_tasks() {
        $configTasks = require REJIMDE_PATH . 'includes/Config/TaskDefinitions.php';
        
        ?>
        <div class="rejimde-tab-content">
            <div class="notice notice-info inline">
                <p><strong>ℹ️ Bilgi:</strong> Bu görevler sistem tarafından tanımlanmıştır ve düzenlenemez.</p>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Slug</th>
                        <th>Başlık</th>
                        <th>Tip</th>
                        <th>Hedef</th>
                        <th>Event Tipleri</th>
                        <th>Ödül Puanı</th>
                        <th>Rozet Katkısı</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($configTasks as $slug => $task): ?>
                    <tr>
                        <td><code><?php echo esc_html($slug); ?></code></td>
                        <td><strong><?php echo esc_html($task['title']); ?></strong><br>
                            <small><?php echo esc_html($task['description'] ?? ''); ?></small>
                        </td>
                        <td><span class="task-type-badge task-type-<?php echo esc_attr($task['task_type']); ?>">
                            <?php echo esc_html(ucfirst($task['task_type'])); ?>
                        </span></td>
                        <td><?php echo esc_html($task['target_value']); ?></td>
                        <td><small><?php echo esc_html(implode(', ', $task['scoring_event_types'])); ?></small></td>
                        <td><?php echo esc_html($task['reward_score']); ?> puan</td>
                        <td><?php echo isset($task['badge_progress_contribution']) ? esc_html($task['badge_progress_contribution']) . '%' : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function render_dynamic_tasks() {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_task_definitions';
        $tasks = $wpdb->get_results("SELECT * FROM $table ORDER BY task_type, id", ARRAY_A);
        
        ?>
        <div class="rejimde-tab-content">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Başlık</th>
                        <th>Tip</th>
                        <th>Hedef</th>
                        <th>Ödül</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tasks)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:40px;">
                            Henüz dinamik görev eklenmemiş. <a href="?page=rejimde-tasks&tab=new">Yeni görev ekle</a>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                        <tr data-task-id="<?php echo esc_attr($task['id']); ?>">
                            <td>
                                <strong><?php echo esc_html($task['title']); ?></strong><br>
                                <small>Slug: <code><?php echo esc_html($task['slug']); ?></code></small>
                            </td>
                            <td><span class="task-type-badge task-type-<?php echo esc_attr($task['task_type']); ?>">
                                <?php echo esc_html(ucfirst($task['task_type'])); ?>
                            </span></td>
                            <td><?php echo esc_html($task['target_value']); ?></td>
                            <td><?php echo esc_html($task['reward_score']); ?> puan</td>
                            <td>
                                <?php if ($task['is_active']): ?>
                                    <span class="status-active">✅ Aktif</span>
                                <?php else: ?>
                                    <span class="status-inactive">⏸️ Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="button button-small toggle-task" data-task-id="<?php echo esc_attr($task['id']); ?>">
                                    <?php echo $task['is_active'] ? 'Devre Dışı Bırak' : 'Aktif Et'; ?>
                                </button>
                                <button class="button button-small button-link-delete delete-task" data-task-id="<?php echo esc_attr($task['id']); ?>">
                                    Sil
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function render_new_task_form() {
        // Get scoring rules for event types
        $scoringRules = require REJIMDE_PATH . 'includes/Config/ScoringRules.php';
        $eventTypes = array_keys($scoringRules);
        // Remove non-event entries
        $eventTypes = array_filter($eventTypes, function($key) {
            return !in_array($key, ['comment_like_milestones', 'streak_bonuses', 'feature_flags']);
        });
        
        // Get badges for selection
        $badges = get_posts([
            'post_type' => 'rejimde_badge',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        ?>
        <div class="rejimde-tab-content">
            <form id="rejimde-new-task-form" method="post">
                <?php wp_nonce_field('rejimde_new_task', 'rejimde_task_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="task_title">Başlık *</label></th>
                        <td>
                            <input type="text" name="task_title" id="task_title" class="regular-text" required>
                            <p class="description">Görevin görünen adı</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="task_slug">Slug</label></th>
                        <td>
                            <input type="text" name="task_slug" id="task_slug" class="regular-text">
                            <p class="description">Boş bırakılırsa başlıktan otomatik oluşturulur (benzersiz olmalı)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="task_description">Açıklama</label></th>
                        <td>
                            <textarea name="task_description" id="task_description" rows="3" class="large-text"></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="task_type">Görev Tipi *</label></th>
                        <td>
                            <select name="task_type" id="task_type" required>
                                <option value="">-- Seçiniz --</option>
                                <option value="daily">Günlük (Daily)</option>
                                <option value="weekly">Haftalık (Weekly)</option>
                                <option value="monthly">Aylık (Monthly)</option>
                                <option value="circle">Circle Görevi</option>
                                <option value="mentor">Mentor Görevi</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="target_value">Hedef Değer *</label></th>
                        <td>
                            <input type="number" name="target_value" id="target_value" min="1" value="1" required>
                            <p class="description">Görevi tamamlamak için gereken sayı</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label>İlgili Event Tipleri</label></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span>Event Tipleri</span></legend>
                                <?php foreach ($eventTypes as $eventType): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="scoring_event_types[]" value="<?php echo esc_attr($eventType); ?>">
                                    <?php echo esc_html($eventType); ?>
                                    <?php if (isset($scoringRules[$eventType]['label'])): ?>
                                        <small>(<?php echo esc_html($scoringRules[$eventType]['label']); ?>)</small>
                                    <?php endif; ?>
                                </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description">Görevin ilerlemesini tetikleyen event'ler</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="reward_score">Ödül Puanı *</label></th>
                        <td>
                            <input type="number" name="reward_score" id="reward_score" min="0" value="10" required>
                            <p class="description">Görev tamamlandığında verilecek puan</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="badge_progress_contribution">Rozet Katkısı (%)</label></th>
                        <td>
                            <input type="number" name="badge_progress_contribution" id="badge_progress_contribution" min="0" max="100" value="0">
                            <p class="description">İlişkili rozete katkı yüzdesi (0-100)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="reward_badge_id">İlişkili Rozet</label></th>
                        <td>
                            <select name="reward_badge_id" id="reward_badge_id">
                                <option value="">-- Rozet Seçiniz --</option>
                                <?php foreach ($badges as $badge): ?>
                                <option value="<?php echo esc_attr($badge->ID); ?>">
                                    <?php echo esc_html($badge->post_title); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Opsiyonel: Bu görev hangi rozete katkı sağlar?</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="is_active">Aktif mi?</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                                Görevi aktif olarak oluştur
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">Görevi Kaydet</button>
                </p>
            </form>
        </div>
        <?php
    }
    
    public function ajax_save_task() {
        check_ajax_referer('rejimde_task_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Yetkisiz erişim']);
        }
        
        $data = [
            'slug' => sanitize_title($_POST['task_slug'] ?: $_POST['task_title']),
            'title' => sanitize_text_field($_POST['task_title']),
            'description' => sanitize_textarea_field($_POST['task_description'] ?? ''),
            'task_type' => sanitize_text_field($_POST['task_type']),
            'target_value' => intval($_POST['target_value']),
            'scoring_event_types' => isset($_POST['scoring_event_types']) ? array_map('sanitize_text_field', $_POST['scoring_event_types']) : [],
            'reward_score' => intval($_POST['reward_score']),
            'badge_progress_contribution' => intval($_POST['badge_progress_contribution'] ?? 0),
            'reward_badge_id' => !empty($_POST['reward_badge_id']) ? intval($_POST['reward_badge_id']) : null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        
        $result = $this->taskService->createTask($data);
        
        if ($result) {
            wp_send_json_success(['message' => 'Görev başarıyla oluşturuldu', 'task_id' => $result]);
        } else {
            wp_send_json_error(['message' => 'Görev oluşturulamadı']);
        }
    }
    
    public function ajax_delete_task() {
        check_ajax_referer('rejimde_task_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Yetkisiz erişim']);
        }
        
        $task_id = intval($_POST['task_id']);
        $result = $this->taskService->deleteTask($task_id);
        
        if ($result) {
            wp_send_json_success(['message' => 'Görev silindi']);
        } else {
            wp_send_json_error(['message' => 'Görev silinemedi']);
        }
    }
    
    public function ajax_toggle_task() {
        check_ajax_referer('rejimde_task_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Yetkisiz erişim']);
        }
        
        $task_id = intval($_POST['task_id']);
        $result = $this->taskService->toggleTaskStatus($task_id);
        
        if ($result !== false) {
            wp_send_json_success([
                'message' => 'Görev durumu güncellendi',
                'is_active' => $result
            ]);
        } else {
            wp_send_json_error(['message' => 'Görev durumu güncellenemedi']);
        }
    }
}
