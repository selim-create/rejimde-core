<?php
namespace Rejimde\Api\V1;

use Rejimde\Api\BaseController;
use Rejimde\Services\ScheduledJobs;
use Rejimde\Services\ScoreService;
use Rejimde\Utils\TimezoneHelper;
use WP_REST_Request;

/**
 * AdminController
 * 
 * Admin-only endpoints for recomputing scores
 */
class AdminController extends BaseController {
    
    protected $namespace = 'rejimde/v1';
    protected $base = 'admin';
    
    public function register_routes() {
        // POST /rejimde/v1/admin/recompute/weekly
        register_rest_route($this->namespace, '/' . $this->base . '/recompute/weekly', [
            'methods' => 'POST',
            'callback' => [$this, 'recompute_weekly'],
            'permission_callback' => [$this, 'check_admin'],
            'args' => [
                'week_start' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Week start date (Y-m-d). Defaults to last week.'
                ]
            ]
        ]);
        
        // POST /rejimde/v1/admin/recompute/monthly
        register_rest_route($this->namespace, '/' . $this->base . '/recompute/monthly', [
            'methods' => 'POST',
            'callback' => [$this, 'recompute_monthly'],
            'permission_callback' => [$this, 'check_admin'],
            'args' => [
                'month_start' => [
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Month start date (Y-m-d). Defaults to last month.'
                ]
            ]
        ]);
    }
    
    /**
     * Check if user is admin
     */
    public function check_admin($request) {
        return current_user_can('manage_options');
    }
    
    /**
     * POST /rejimde/v1/admin/recompute/weekly
     * Manually trigger weekly score recomputation
     */
    public function recompute_weekly(WP_REST_Request $request) {
        $week_start = $request->get_param('week_start');
        
        if (!$week_start) {
            // Default to last week
            $tr_now = TimezoneHelper::getNowTR();
            $tr_now->modify('-7 days');
            $week_bounds = TimezoneHelper::getWeekBoundsTR($tr_now);
            $week_start = $week_bounds['start'];
        }
        
        // Validate date format
        $date = \DateTime::createFromFormat('Y-m-d', $week_start);
        if (!$date || $date->format('Y-m-d') !== $week_start) {
            return $this->error('Invalid date format. Use Y-m-d.', 400);
        }
        
        // Run weekly close job
        try {
            ScheduledJobs::runWeeklyClose();
            
            return $this->success([
                'week_start' => $week_start,
                'message' => 'Weekly scores recomputed successfully'
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to recompute weekly scores: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /rejimde/v1/admin/recompute/monthly
     * Manually trigger monthly score recomputation
     */
    public function recompute_monthly(WP_REST_Request $request) {
        $month_start = $request->get_param('month_start');
        
        if (!$month_start) {
            // Default to last month
            $tr_now = TimezoneHelper::getNowTR();
            $tr_now->modify('-1 month');
            $month_bounds = TimezoneHelper::getMonthBoundsTR($tr_now);
            $month_start = $month_bounds['start'];
        }
        
        // Validate date format
        $date = \DateTime::createFromFormat('Y-m-d', $month_start);
        if (!$date || $date->format('Y-m-d') !== $month_start) {
            return $this->error('Invalid date format. Use Y-m-d.', 400);
        }
        
        // Run monthly close job
        try {
            ScheduledJobs::runMonthlyClose();
            
            return $this->success([
                'month_start' => $month_start,
                'message' => 'Monthly scores recomputed successfully'
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to recompute monthly scores: ' . $e->getMessage(), 500);
        }
    }
}
