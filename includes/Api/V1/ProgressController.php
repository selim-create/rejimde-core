<?php
namespace Rejimde\Api\V1;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

class ProgressController extends WP_REST_Controller {

    protected $namespace = 'rejimde/v1';
    protected $base = 'progress';
    
    private $allowed_content_types = ['blog', 'diet', 'exercise', 'dictionary'];

    public function register_routes() {
        // GET /rejimde/v1/progress/my - Get all progress for current user
        register_rest_route($this->namespace, '/' . $this->base . '/my', [
            'methods' => 'GET',
            'callback' => [$this, 'get_my_progress'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // GET /rejimde/v1/progress/{content_type}/{content_id} - Get specific progress
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<content_type>[a-z]+)/(?P<content_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_progress'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // POST /rejimde/v1/progress/{content_type}/{content_id} - Update progress
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<content_type>[a-z]+)/(?P<content_id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'update_progress'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // POST /rejimde/v1/progress/{content_type}/{content_id}/start - Mark as started
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<content_type>[a-z]+)/(?P<content_id>\d+)/start', [
            'methods' => 'POST',
            'callback' => [$this, 'start_content'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // POST /rejimde/v1/progress/{content_type}/{content_id}/complete - Mark as completed
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<content_type>[a-z]+)/(?P<content_id>\d+)/complete', [
            'methods' => 'POST',
            'callback' => [$this, 'complete_content'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // POST /rejimde/v1/progress/{content_type}/{content_id}/complete-item - Complete individual item
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<content_type>[a-z]+)/(?P<content_id>\d+)/complete-item', [
            'methods' => 'POST',
            'callback' => [$this, 'complete_item'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // POST /rejimde/v1/progress/blog/{content_id}/claim - Claim blog reading reward
        register_rest_route($this->namespace, '/' . $this->base . '/blog/(?P<content_id>\d+)/claim', [
            'methods' => 'POST',
            'callback' => [$this, 'claim_blog_reward'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // POST /rejimde/v1/progress/calculator/{calculator_type}/save - Save calculator result
        register_rest_route($this->namespace, '/' . $this->base . '/calculator/(?P<calculator_type>[a-z_]+)/save', [
            'methods' => 'POST',
            'callback' => [$this, 'save_calculator_result'],
            'permission_callback' => [$this, 'check_auth'],
        ]);

        // POST /rejimde/v1/progress/{content_type}/{content_id}/claim-reward - Claim reward
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<content_type>[a-z]+)/(?P<content_id>\d+)/claim-reward', [
            'methods' => 'POST',
            'callback' => [$this, 'claim_reward'],
            'permission_callback' => [$this, 'check_auth'],
        ]);
    }

    /**
     * GET /rejimde/v1/progress/{content_type}/{content_id}
     * Returns user's progress for specific content
     */
    public function get_progress($request) {
        $content_type = $request['content_type'];
        $content_id = (int) $request['content_id'];
        $user_id = get_current_user_id();

        if (!$this->validate_content_type($content_type)) {
            return $this->error('Invalid content_type. Allowed values: ' . implode(', ', $this->allowed_content_types), 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_progress';

        $progress = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND content_type = %s AND content_id = %d",
            $user_id, $content_type, $content_id
        ));

        if (!$progress) {
            return $this->success([
                'user_id' => $user_id,
                'content_type' => $content_type,
                'content_id' => $content_id,
                'progress_data' => [],
                'is_started' => false,
                'is_completed' => false,
                'reward_claimed' => false
            ]);
        }

        return $this->success([
            'user_id' => (int) $progress->user_id,
            'content_type' => $progress->content_type,
            'content_id' => (int) $progress->content_id,
            'progress_data' => json_decode($progress->progress_data ?: '[]', true),
            'is_started' => (bool) $progress->is_started,
            'is_completed' => (bool) $progress->is_completed,
            'reward_claimed' => (bool) $progress->reward_claimed,
            'started_at' => $progress->started_at,
            'completed_at' => $progress->completed_at
        ]);
    }

    /**
     * POST /rejimde/v1/progress/{content_type}/{content_id}
     * Updates user's progress (creates if doesn't exist)
     */
    public function update_progress($request) {
        $content_type = $request['content_type'];
        $content_id = (int) $request['content_id'];
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        if (!$this->validate_content_type($content_type)) {
            return $this->error('Invalid content_type. Allowed values: ' . implode(', ', $this->allowed_content_types), 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_progress';

        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND content_type = %s AND content_id = %d",
            $user_id, $content_type, $content_id
        ));

        $data = [];
        
        if (isset($params['progress_data'])) {
            $data['progress_data'] = is_string($params['progress_data']) 
                ? $params['progress_data'] 
                : json_encode($params['progress_data']);
        }
        
        if (isset($params['is_started'])) {
            $data['is_started'] = (bool) $params['is_started'] ? 1 : 0;
            // Set started_at if marking as started and it's not already set
            if ($data['is_started']) {
                if (!$existing) {
                    $data['started_at'] = current_time('mysql');
                } else {
                    // Check if existing record doesn't have started_at set
                    $current = $wpdb->get_row($wpdb->prepare(
                        "SELECT started_at FROM $table WHERE id = %d",
                        $existing->id
                    ));
                    if (!$current->started_at) {
                        $data['started_at'] = current_time('mysql');
                    }
                }
            }
        }
        
        if (isset($params['is_completed'])) {
            $data['is_completed'] = (bool) $params['is_completed'] ? 1 : 0;
            if ($data['is_completed']) {
                $data['completed_at'] = current_time('mysql');
            }
        }
        
        if (isset($params['reward_claimed'])) {
            $data['reward_claimed'] = (bool) $params['reward_claimed'] ? 1 : 0;
        }

        if (empty($data)) {
            return $this->error('No valid data provided', 400);
        }

        if ($existing) {
            // Update existing record
            $wpdb->update(
                $table,
                $data,
                ['id' => $existing->id]
            );
        } else {
            // Create new record
            $data['user_id'] = $user_id;
            $data['content_type'] = $content_type;
            $data['content_id'] = $content_id;
            $wpdb->insert($table, $data);
        }

        // Return updated progress
        return $this->get_progress($request);
    }

    /**
     * GET /rejimde/v1/progress/my
     * Returns all progress records for current user
     */
    public function get_my_progress($request) {
        $user_id = get_current_user_id();
        $content_type = $request->get_param('content_type');
        $is_completed = $request->get_param('is_completed');

        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_progress';

        $where = ['user_id = %d'];
        $values = [$user_id];

        if ($content_type && $this->validate_content_type($content_type)) {
            $where[] = 'content_type = %s';
            $values[] = $content_type;
        }

        if ($is_completed !== null) {
            $where[] = 'is_completed = %d';
            $values[] = (int) $is_completed;
        }

        $where_clause = implode(' AND ', $where);
        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY updated_at DESC";

        $results = $wpdb->get_results($wpdb->prepare($sql, $values));

        $progress_list = [];
        foreach ($results as $row) {
            $progress_list[] = [
                'user_id' => (int) $row->user_id,
                'content_type' => $row->content_type,
                'content_id' => (int) $row->content_id,
                'progress_data' => json_decode($row->progress_data ?: '[]', true),
                'is_started' => (bool) $row->is_started,
                'is_completed' => (bool) $row->is_completed,
                'reward_claimed' => (bool) $row->reward_claimed,
                'started_at' => $row->started_at,
                'completed_at' => $row->completed_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at
            ];
        }

        return $this->success($progress_list);
    }

    /**
     * POST /rejimde/v1/progress/{content_type}/{content_id}/start
     * Marks content as started
     */
    public function start_content($request) {
        $content_type = $request['content_type'];
        $content_id = (int) $request['content_id'];
        $user_id = get_current_user_id();

        if (!$this->validate_content_type($content_type)) {
            return $this->error('Invalid content_type. Allowed values: ' . implode(', ', $this->allowed_content_types), 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_progress';

        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_started FROM $table WHERE user_id = %d AND content_type = %s AND content_id = %d",
            $user_id, $content_type, $content_id
        ));

        if ($existing) {
            if ($existing->is_started) {
                return $this->error('Content already started', 409);
            }
            // Update existing record
            $wpdb->update(
                $table,
                [
                    'is_started' => 1,
                    'started_at' => current_time('mysql')
                ],
                ['id' => $existing->id]
            );
        } else {
            // Create new record
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'content_type' => $content_type,
                'content_id' => $content_id,
                'is_started' => 1,
                'started_at' => current_time('mysql')
            ]);
        }

        // Post meta'ya da kaydet (başlayanlar listesi için)
        $started_users = get_post_meta($content_id, 'started_users', true);
        if (!is_array($started_users)) $started_users = [];
        if (!in_array($user_id, $started_users)) {
            $started_users[] = $user_id;
            update_post_meta($content_id, 'started_users', $started_users);
        }
        
        // Dispatch event for diet/exercise start
        if (in_array($content_type, ['diet', 'exercise'])) {
            $eventType = $content_type . '_started';
            $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
            $dispatcher->dispatch($eventType, [
                'user_id' => $user_id,
                'entity_type' => $content_type,
                'entity_id' => $content_id
            ]);
        }

        return $this->success([
            'message' => 'Content marked as started',
            'content_type' => $content_type,
            'content_id' => $content_id
        ]);
    }

    /**
     * POST /rejimde/v1/progress/{content_type}/{content_id}/complete
     * Marks content as completed
     */
    public function complete_content($request) {
        $content_type = $request['content_type'];
        $content_id = (int) $request['content_id'];
        $user_id = get_current_user_id();

        if (!$this->validate_content_type($content_type)) {
            return $this->error('Invalid content_type. Allowed values: ' . implode(', ', $this->allowed_content_types), 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_progress';

        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_completed FROM $table WHERE user_id = %d AND content_type = %s AND content_id = %d",
            $user_id, $content_type, $content_id
        ));

        if ($existing) {
            if ($existing->is_completed) {
                return $this->error('Content already completed', 409);
            }
            // Check if started_at is already set
            $update_data = [
                'is_started' => 1,
                'is_completed' => 1,
                'completed_at' => current_time('mysql')
            ];
            // Set started_at if not already set
            $current = $wpdb->get_row($wpdb->prepare(
                "SELECT started_at FROM $table WHERE id = %d",
                $existing->id
            ));
            if (!$current->started_at) {
                $update_data['started_at'] = current_time('mysql');
            }
            // Update existing record
            $wpdb->update($table, $update_data, ['id' => $existing->id]);
        } else {
            // Create new record with completed status
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'content_type' => $content_type,
                'content_id' => $content_id,
                'is_started' => 1,
                'is_completed' => 1,
                'started_at' => current_time('mysql'),
                'completed_at' => current_time('mysql')
            ]);
        }

        // Post meta'ya da kaydet (tamamlayanlar listesi için)
        $completed_users = get_post_meta($content_id, 'completed_users', true);
        if (!is_array($completed_users)) $completed_users = [];
        if (!in_array($user_id, $completed_users)) {
            $completed_users[] = $user_id;
            update_post_meta($content_id, 'completed_users', $completed_users);
        }
        
        // Dispatch event for diet/exercise completion
        if (in_array($content_type, ['diet', 'exercise'])) {
            $eventType = $content_type . '_completed';
            $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
            $dispatcher->dispatch($eventType, [
                'user_id' => $user_id,
                'entity_type' => $content_type,
                'entity_id' => $content_id
            ]);
        }

        return $this->success([
            'message' => 'Content marked as completed',
            'content_type' => $content_type,
            'content_id' => $content_id
        ]);
    }

    /**
     * POST /rejimde/v1/progress/{content_type}/{content_id}/claim-reward
     * Marks reward as claimed and integrates with gamification system
     */
    public function claim_reward($request) {
        $content_type = $request['content_type'];
        $content_id = (int) $request['content_id'];
        $user_id = get_current_user_id();

        if (!$this->validate_content_type($content_type)) {
            return $this->error('Invalid content_type. Allowed values: ' . implode(', ', $this->allowed_content_types), 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_progress';

        // Check if record exists and is completed
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_completed, reward_claimed FROM $table WHERE user_id = %d AND content_type = %s AND content_id = %d",
            $user_id, $content_type, $content_id
        ));

        if (!$existing) {
            return $this->error('Progress not found', 404);
        }

        if (!$existing->is_completed) {
            return $this->error('Content must be completed before claiming reward', 400);
        }

        if ($existing->reward_claimed) {
            return $this->error('Reward already claimed', 409);
        }

        // Mark reward as claimed
        $wpdb->update(
            $table,
            ['reward_claimed' => 1],
            ['id' => $existing->id]
        );

        // Integrate with gamification system
        $points_data = $this->award_gamification_points($user_id, $content_type, $content_id);

        return $this->success([
            'message' => 'Reward claimed successfully',
            'content_type' => $content_type,
            'content_id' => $content_id,
            'points_earned' => $points_data['points_earned'] ?? 0,
            'total_score' => $points_data['total_score'] ?? 0
        ]);
    }

    /**
     * Validate content type
     */
    private function validate_content_type($content_type) {
        return in_array($content_type, $this->allowed_content_types);
    }

    /**
     * Get gamification action based on content type
     */
    private function get_gamification_action($content_type) {
        $action_map = [
            'blog' => 'read_blog',
            'diet' => 'log_meal',
            'exercise' => 'complete_workout',
            'dictionary' => null // No gamification action for dictionary
        ];
        
        return $action_map[$content_type] ?? null;
    }

    /**
     * Award gamification points for content completion
     */
    private function award_gamification_points($user_id, $content_type, $content_id) {
        $gamification_action = $this->get_gamification_action($content_type);
        
        if (!$gamification_action || !class_exists('Rejimde\\Api\\V1\\GamificationController')) {
            return ['points_earned' => 0, 'total_score' => 0];
        }

        // Create a request object for the gamification controller
        $gam_request = new \WP_REST_Request('POST', '/rejimde/v1/gamification/earn');
        $gam_request->set_body_params([
            'action' => $gamification_action,
            'ref_id' => $content_id
        ]);
        
        $gam_controller = new \Rejimde\Api\V1\GamificationController();
        $gam_response = $gam_controller->earn_points($gam_request);
        
        $response_data = $gam_response->get_data();
        
        return [
            'points_earned' => $response_data['data']['earned'] ?? 0,
            'total_score' => $response_data['data']['total_score'] ?? 0
        ];
    }

    /**
     * Check authentication
     */
    public function check_auth($request) {
        return is_user_logged_in();
    }

    /**
     * Success response
     */
    protected function success($data = null, $message = 'Success', $code = 200) {
        return new WP_REST_Response([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Error response
     */
    protected function error($message = 'Error', $code = 400, $data = null) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => $message,
            'error_data' => $data
        ], $code);
    }

    /**
     * POST /rejimde/v1/progress/{content_type}/{content_id}/complete-item
     * Complete individual item (meal, exercise day, etc.)
     */
    public function complete_item($request) {
        $content_type = $request['content_type'];
        $content_id = (int) $request['content_id'];
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        $item_id = sanitize_text_field($params['item_id'] ?? '');

        if (empty($item_id)) {
            return new WP_Error('missing_item', 'Öğe ID gerekli', ['status' => 400]);
        }

        if (!$this->validate_content_type($content_type)) {
            return $this->error('Invalid content_type. Allowed values: ' . implode(', ', $this->allowed_content_types), 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_user_progress';

        // Get existing progress
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, progress_data, is_started FROM $table WHERE user_id = %d AND content_type = %s AND content_id = %d",
            $user_id, $content_type, $content_id
        ));

        if (!$existing || !$existing->is_started) {
            return new WP_Error('not_started', 'Önce başlamalısınız', ['status' => 400]);
        }

        $progress_data = json_decode($existing->progress_data ?: '{}', true);
        if (!isset($progress_data['completed_items'])) {
            $progress_data['completed_items'] = [];
        }

        if (!in_array($item_id, $progress_data['completed_items'])) {
            $progress_data['completed_items'][] = $item_id;
            
            $wpdb->update(
                $table,
                ['progress_data' => json_encode($progress_data)],
                ['id' => $existing->id]
            );
        }

        return $this->success([
            'message' => 'Öğe tamamlandı!',
            'completed_items' => $progress_data['completed_items']
        ]);
    }

    /**
     * POST /rejimde/v1/progress/blog/{content_id}/claim
     * Claim blog reading reward
     */
    public function claim_blog_reward($request) {
        $user_id = get_current_user_id();
        $content_id = (int) $request->get_param('content_id');

        // Sticky mi kontrol et
        $is_sticky = is_sticky($content_id);
        
        // Dispatch blog_points_claimed event
        $dispatcher = \Rejimde\Core\EventDispatcher::getInstance();
        $result = $dispatcher->dispatch('blog_points_claimed', [
            'user_id' => $user_id,
            'entity_type' => 'blog',
            'entity_id' => $content_id,
            'context' => [
                'is_sticky' => $is_sticky
            ]
        ]);
        
        if (!$result['success']) {
            return $this->success([
                'already_claimed' => true,
                'message' => $result['message']
            ]);
        }

        // Okuyanlar listesine ekle
        $readers = get_post_meta($content_id, 'rejimde_readers', true);
        if (!is_array($readers)) $readers = [];
        if (!in_array($user_id, $readers)) {
            $readers[] = $user_id;
            update_post_meta($content_id, 'rejimde_readers', $readers);
        }

        return $this->success([
            'message' => $result['message'],
            'earned_points' => $result['points_earned'],
            'new_total' => $result['total_score'],
            'is_sticky' => $is_sticky
        ]);
    }

    /**
     * POST /rejimde/v1/progress/calculator/{calculator_type}/save
     * Save calculator result
     */
    public function save_calculator_result($request) {
        $user_id = get_current_user_id();
        $calculator_type = $request->get_param('calculator_type');
        $params = $request->get_json_params();

        $meta_key = "rejimde_calculator_{$calculator_type}";
        $existing = get_user_meta($user_id, $meta_key, true);

        if ($existing && isset($existing['saved']) && $existing['saved']) {
            return $this->success([
                'already_saved' => true,
                'message' => 'Bu hesaplamayı zaten kaydettin!'
            ]);
        }

        // Sonucu kaydet
        $result_data = [
            'saved' => true,
            'saved_at' => current_time('mysql'),
            'result' => $params['result'] ?? null,
            'reward_claimed' => true
        ];

        // +50 puan ver
        $current_total = (int) get_user_meta($user_id, 'rejimde_total_score', true);
        $new_total = $current_total + 50;
        update_user_meta($user_id, 'rejimde_total_score', $new_total);

        update_user_meta($user_id, $meta_key, $result_data);

        return $this->success([
            'message' => 'Sonuç kaydedildi, 50 puan kazandın! 🎉',
            'earned_points' => 50,
            'new_total' => $new_total
        ]);
    }
}
