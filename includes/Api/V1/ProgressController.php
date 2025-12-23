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
            if ($data['is_started'] && !$existing) {
                $data['started_at'] = current_time('mysql');
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
            // Update existing record
            $wpdb->update(
                $table,
                [
                    'is_started' => 1,
                    'is_completed' => 1,
                    'completed_at' => current_time('mysql')
                ],
                ['id' => $existing->id]
            );
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
        $gamification_action = $this->get_gamification_action($content_type);
        if ($gamification_action && class_exists('Rejimde\\Api\\V1\\GamificationController')) {
            // Create a mock request for the gamification controller
            $gam_request = new \WP_REST_Request('POST', '/rejimde/v1/gamification/earn');
            $gam_request->set_body_params([
                'action' => $gamification_action,
                'ref_id' => $content_id
            ]);
            
            $gam_controller = new \Rejimde\Api\V1\GamificationController();
            $gam_response = $gam_controller->earn_points($gam_request);
            
            $response_data = $gam_response->get_data();
            
            return $this->success([
                'message' => 'Reward claimed successfully',
                'content_type' => $content_type,
                'content_id' => $content_id,
                'points_earned' => $response_data['data']['earned'] ?? 0,
                'total_score' => $response_data['data']['total_score'] ?? 0
            ]);
        }

        return $this->success([
            'message' => 'Reward claimed successfully',
            'content_type' => $content_type,
            'content_id' => $content_id
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
}
