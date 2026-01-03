<?php
namespace Rejimde\Cron;

use Rejimde\Services\TaskProgressService;

/**
 * Task Cron Jobs
 * 
 * Handles periodic task expiration
 */
class TaskCron {
    
    private $taskProgressService;
    
    public function __construct() {
        $this->taskProgressService = new TaskProgressService();
    }
    
    /**
     * Register cron schedules and hooks
     */
    public function register() {
        // Add custom cron schedules
        add_filter('cron_schedules', [$this, 'add_custom_schedules']);
        
        // Hook cron events
        add_action('rejimde_expire_daily_tasks', [$this, 'expire_daily_tasks']);
        add_action('rejimde_expire_weekly_tasks', [$this, 'expire_weekly_tasks']);
        add_action('rejimde_expire_monthly_tasks', [$this, 'expire_monthly_tasks']);
        
        // Schedule cron events if not already scheduled
        add_action('init', [$this, 'schedule_events']);
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_custom_schedules($schedules) {
        // Every day at midnight (for daily task expiration)
        if (!isset($schedules['daily_midnight'])) {
            $schedules['daily_midnight'] = [
                'interval' => 86400, // 24 hours
                'display' => __('Once Daily at Midnight', 'rejimde-core')
            ];
        }
        
        // Every week on Sunday at midnight (for weekly task expiration)
        if (!isset($schedules['weekly_sunday'])) {
            $schedules['weekly_sunday'] = [
                'interval' => 604800, // 7 days
                'display' => __('Weekly on Sunday', 'rejimde-core')
            ];
        }
        
        // Every month on the 1st at midnight (for monthly task expiration)
        if (!isset($schedules['monthly_first'])) {
            $schedules['monthly_first'] = [
                'interval' => 2592000, // ~30 days (will be adjusted by scheduler)
                'display' => __('Monthly on 1st', 'rejimde-core')
            ];
        }
        
        return $schedules;
    }
    
    /**
     * Schedule cron events
     */
    public function schedule_events() {
        // Schedule daily task expiration (runs every day at midnight)
        if (!wp_next_scheduled('rejimde_expire_daily_tasks')) {
            // Schedule for next midnight
            $tomorrow_midnight = strtotime('tomorrow midnight', current_time('timestamp'));
            wp_schedule_event($tomorrow_midnight, 'daily', 'rejimde_expire_daily_tasks');
        }
        
        // Schedule weekly task expiration (runs every Sunday at midnight)
        if (!wp_next_scheduled('rejimde_expire_weekly_tasks')) {
            // Schedule for next Sunday midnight
            $next_sunday = strtotime('next Sunday midnight', current_time('timestamp'));
            wp_schedule_event($next_sunday, 'weekly', 'rejimde_expire_weekly_tasks');
        }
        
        // Schedule monthly task expiration (runs on 1st of each month at midnight)
        if (!wp_next_scheduled('rejimde_expire_monthly_tasks')) {
            // Schedule for first day of next month
            $first_next_month = strtotime('first day of next month midnight', current_time('timestamp'));
            wp_schedule_event($first_next_month, 'monthly', 'rejimde_expire_monthly_tasks');
        }
    }
    
    /**
     * Expire daily tasks
     * Called daily at midnight
     */
    public function expire_daily_tasks() {
        error_log('[Rejimde TaskCron] Running daily task expiration');
        
        $expired = $this->taskProgressService->expireOldTasks('daily');
        
        error_log("[Rejimde TaskCron] Expired {$expired} daily tasks");
    }
    
    /**
     * Expire weekly tasks
     * Called weekly on Sunday at midnight
     */
    public function expire_weekly_tasks() {
        error_log('[Rejimde TaskCron] Running weekly task expiration');
        
        $expired = $this->taskProgressService->expireOldTasks('weekly');
        
        error_log("[Rejimde TaskCron] Expired {$expired} weekly tasks");
    }
    
    /**
     * Expire monthly tasks
     * Called monthly on the 1st at midnight
     */
    public function expire_monthly_tasks() {
        error_log('[Rejimde TaskCron] Running monthly task expiration');
        
        $expired = $this->taskProgressService->expireOldTasks('monthly');
        
        error_log("[Rejimde TaskCron] Expired {$expired} monthly tasks");
    }
    
    /**
     * Unschedule all cron events
     * Called on plugin deactivation
     */
    public static function unschedule_events() {
        $timestamp = wp_next_scheduled('rejimde_expire_daily_tasks');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'rejimde_expire_daily_tasks');
        }
        
        $timestamp = wp_next_scheduled('rejimde_expire_weekly_tasks');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'rejimde_expire_weekly_tasks');
        }
        
        $timestamp = wp_next_scheduled('rejimde_expire_monthly_tasks');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'rejimde_expire_monthly_tasks');
        }
    }
}
