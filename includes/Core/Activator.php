<?php
namespace Rejimde\Core;

class Activator {

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 1. Ölçümler Tablosu (Kilo, Bel, Basen vb.)
        $table_measurements = $wpdb->prefix . 'rejimde_measurements';
        $sql_measurements = "CREATE TABLE $table_measurements (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            type varchar(50) NOT NULL, 
            value float NOT NULL,
            source varchar(20) DEFAULT 'manual',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY type (type)
        ) $charset_collate;";
        dbDelta( $sql_measurements );

        // 2. Günlük Loglar (Skor, Su, Kalori)
        $table_logs = $wpdb->prefix . 'rejimde_daily_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            log_date date NOT NULL,
            score_daily int(11) DEFAULT 0,
            water_intake int(11) DEFAULT 0,
            calories_in int(11) DEFAULT 0,
            steps int(11) DEFAULT 0,
            data_json longtext,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_date (user_id, log_date)
        ) $charset_collate;";
        dbDelta( $sql_logs );

        // 3. User Progress Tracking
        $table_progress = $wpdb->prefix . 'rejimde_user_progress';
        $sql_progress = "CREATE TABLE $table_progress (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            content_type varchar(50) NOT NULL,
            content_id bigint(20) unsigned NOT NULL,
            progress_data longtext,
            is_started tinyint(1) DEFAULT 0,
            is_completed tinyint(1) DEFAULT 0,
            reward_claimed tinyint(1) DEFAULT 0,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_content (user_id, content_type, content_id),
            KEY idx_user_id (user_id),
            KEY idx_content (content_type, content_id)
        ) $charset_collate;";
        dbDelta( $sql_progress );
        
        // 4. Gamification v2.0 Tables
        
        // 4.1 Events Table
        $table_events = $wpdb->prefix . 'rejimde_events';
        $sql_events = "CREATE TABLE $table_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            event_type varchar(100) NOT NULL,
            entity_type varchar(50) DEFAULT NULL,
            entity_id bigint(20) unsigned DEFAULT NULL,
            occurred_at datetime NOT NULL,
            metadata longtext DEFAULT NULL,
            idempotency_key varchar(255) NOT NULL,
            source varchar(50) DEFAULT 'web',
            status varchar(20) DEFAULT 'valid',
            rejection_reason varchar(255) DEFAULT NULL,
            points_awarded int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_idempotency (idempotency_key),
            KEY idx_user_event (user_id, event_type),
            KEY idx_occurred (occurred_at),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_status (status)
        ) $charset_collate;";
        dbDelta( $sql_events );
        
        // 4.2 Points Ledger Table
        $table_ledger = $wpdb->prefix . 'rejimde_points_ledger';
        $sql_ledger = "CREATE TABLE $table_ledger (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            points_delta int NOT NULL,
            reason varchar(100) NOT NULL,
            related_event_id bigint(20) unsigned DEFAULT NULL,
            balance_after int DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_created (user_id, created_at),
            KEY idx_event (related_event_id),
            KEY idx_reason (reason)
        ) $charset_collate;";
        dbDelta( $sql_ledger );
        
        // 4.3 Daily Counters Table
        $table_counters = $wpdb->prefix . 'rejimde_daily_counters';
        $sql_counters = "CREATE TABLE $table_counters (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            counter_date date NOT NULL,
            counter_type varchar(100) NOT NULL,
            counter_value int NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY idx_user_date_type (user_id, counter_date, counter_type),
            KEY idx_date (counter_date)
        ) $charset_collate;";
        dbDelta( $sql_counters );
        
        // 4.4 User Scores Table
        $table_scores = $wpdb->prefix . 'rejimde_user_scores';
        $sql_scores = "CREATE TABLE $table_scores (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            period_type varchar(20) NOT NULL,
            period_start date NOT NULL,
            period_end date NOT NULL,
            score int NOT NULL DEFAULT 0,
            rank_position int DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_user_period (user_id, period_type, period_start)
        ) $charset_collate;";
        dbDelta( $sql_scores );
        
        // 4.5 Levels Table
        $table_levels = $wpdb->prefix . 'rejimde_levels';
        $sql_levels = "CREATE TABLE $table_levels (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            rank_order int NOT NULL,
            min_score int DEFAULT 0,
            max_score int DEFAULT NULL,
            icon varchar(50) DEFAULT NULL,
            color varchar(50) DEFAULT NULL,
            description text DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_slug (slug)
        ) $charset_collate;";
        dbDelta( $sql_levels );
        
        // 4.6 User Levels Table
        $table_user_levels = $wpdb->prefix . 'rejimde_user_levels';
        $sql_user_levels = "CREATE TABLE $table_user_levels (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            level_id bigint(20) unsigned NOT NULL,
            joined_at datetime NOT NULL,
            left_at datetime DEFAULT NULL,
            is_current tinyint(1) DEFAULT 1,
            transition_type varchar(20) DEFAULT 'initial',
            week_id varchar(20) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_user_current (user_id, is_current)
        ) $charset_collate;";
        dbDelta( $sql_user_levels );
        
        // 4.7 Level Snapshots Table
        $table_level_snapshots = $wpdb->prefix . 'rejimde_level_snapshots';
        $sql_level_snapshots = "CREATE TABLE $table_level_snapshots (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            level_id bigint(20) unsigned NOT NULL,
            week_start date NOT NULL,
            week_end date NOT NULL,
            weekly_score int NOT NULL DEFAULT 0,
            position int NOT NULL,
            position_reward int DEFAULT 0,
            transition varchar(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_user_level_week (user_id, level_id, week_start)
        ) $charset_collate;";
        dbDelta( $sql_level_snapshots );
        
        // 4.8 Circle Scores Table
        $table_circle_scores = $wpdb->prefix . 'rejimde_circle_scores';
        $sql_circle_scores = "CREATE TABLE $table_circle_scores (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            circle_id bigint(20) unsigned NOT NULL,
            period_type varchar(20) NOT NULL,
            period_start date NOT NULL,
            period_end date NOT NULL,
            total_points int NOT NULL DEFAULT 0,
            total_score int NOT NULL DEFAULT 0,
            member_count int DEFAULT 0,
            rank_position int DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_circle_period (circle_id, period_type, period_start)
        ) $charset_collate;";
        dbDelta( $sql_circle_scores );
        
        // 4.9 Comment Likes Table
        $table_comment_likes = $wpdb->prefix . 'rejimde_comment_likes';
        $sql_comment_likes = "CREATE TABLE $table_comment_likes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            comment_id bigint(20) unsigned NOT NULL,
            liker_user_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_comment_liker (comment_id, liker_user_id)
        ) $charset_collate;";
        dbDelta( $sql_comment_likes );
        
        // 4.10 Comment Milestones Table
        $table_comment_milestones = $wpdb->prefix . 'rejimde_comment_milestones';
        $sql_comment_milestones = "CREATE TABLE $table_comment_milestones (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            comment_id bigint(20) unsigned NOT NULL,
            milestone int NOT NULL,
            rewarded_user_id bigint(20) unsigned NOT NULL,
            points_awarded int NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_comment_milestone (comment_id, milestone)
        ) $charset_collate;";
        dbDelta( $sql_comment_milestones );
        
        // 4.11 Follows Table
        $table_follows = $wpdb->prefix . 'rejimde_follows';
        $sql_follows = "CREATE TABLE $table_follows (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            follower_id bigint(20) unsigned NOT NULL,
            following_id bigint(20) unsigned NOT NULL,
            status varchar(20) DEFAULT 'accepted',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            accepted_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_pair (follower_id, following_id)
        ) $charset_collate;";
        dbDelta( $sql_follows );
        
        // 4.12 Gamification Config Table
        $table_config = $wpdb->prefix . 'rejimde_gamification_config';
        $sql_config = "CREATE TABLE $table_config (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            config_key varchar(100) NOT NULL,
            config_value longtext NOT NULL,
            description text DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_key (config_key)
        ) $charset_collate;";
        dbDelta( $sql_config );
        
        // 5. Seed default data
        self::seedDefaultData();
        
        // 6. Migrate rejimde_level to rejimde_rank (if needed)
        self::migrate_level_to_rank();
    }
    
    /**
     * Seed default data for gamification v2.0
     */
    private static function seedDefaultData() {
        global $wpdb;
        
        // Seed default levels (only if table is empty)
        $levels_table = $wpdb->prefix . 'rejimde_levels';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $levels_table");
        
        if ($count == 0) {
            $default_levels = [
                ['name' => 'Begin', 'slug' => 'begin', 'rank_order' => 1, 'min_score' => 0, 'max_score' => 199, 'icon' => 'fa-seedling', 'color' => 'gray', 'description' => 'Başlangıç seviyesi'],
                ['name' => 'Adapt', 'slug' => 'adapt', 'rank_order' => 2, 'min_score' => 200, 'max_score' => 299, 'icon' => 'fa-sync', 'color' => 'orange', 'description' => 'Uyum seviyesi'],
                ['name' => 'Commit', 'slug' => 'commit', 'rank_order' => 3, 'min_score' => 300, 'max_score' => 499, 'icon' => 'fa-check-circle', 'color' => 'green', 'description' => 'Bağlılık seviyesi'],
                ['name' => 'Balance', 'slug' => 'balance', 'rank_order' => 4, 'min_score' => 500, 'max_score' => 999, 'icon' => 'fa-scale-balanced', 'color' => 'blue', 'description' => 'Denge seviyesi'],
                ['name' => 'Strengthen', 'slug' => 'strengthen', 'rank_order' => 5, 'min_score' => 1000, 'max_score' => 1999, 'icon' => 'fa-dumbbell', 'color' => 'red', 'description' => 'Güçlenme seviyesi'],
                ['name' => 'Sustain', 'slug' => 'sustain', 'rank_order' => 6, 'min_score' => 2000, 'max_score' => 3999, 'icon' => 'fa-infinity', 'color' => 'teal', 'description' => 'Sürdürülebilirlik seviyesi'],
                ['name' => 'Mastery', 'slug' => 'mastery', 'rank_order' => 7, 'min_score' => 4000, 'max_score' => 5999, 'icon' => 'fa-crown', 'color' => 'yellow', 'description' => 'Ustalık seviyesi'],
                ['name' => 'Transform', 'slug' => 'transform', 'rank_order' => 8, 'min_score' => 6000, 'max_score' => null, 'icon' => 'fa-star', 'color' => 'purple', 'description' => 'Dönüşüm seviyesi'],
            ];
            
            foreach ($default_levels as $level) {
                $wpdb->insert($levels_table, $level);
            }
        }
        
        // Seed default config (only if table is empty)
        $config_table = $wpdb->prefix . 'rejimde_gamification_config';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $config_table");
        
        if ($count == 0) {
            $default_configs = [
                ['config_key' => 'steps_daily_max_points', 'config_value' => '20', 'description' => 'Maximum daily points from steps'],
                ['config_key' => 'comment_like_milestone_daily_cap_enabled', 'config_value' => 'false', 'description' => 'Enable daily cap for comment like milestone rewards'],
                ['config_key' => 'circle_create_points_enabled', 'config_value' => 'false', 'description' => 'Enable points for creating a circle'],
                ['config_key' => 'circle_create_points', 'config_value' => '0', 'description' => 'Points awarded for creating a circle'],
            ];
            
            foreach ($default_configs as $config) {
                $wpdb->insert($config_table, $config);
            }
        }
    }
    
    /**
     * Migrate rejimde_level meta key to rejimde_rank
     * This is a one-time migration for terminology fix
     */
    private static function migrate_level_to_rank() {
        global $wpdb;
        
        // Check if migration has already been done
        $migration_done = get_option('rejimde_level_to_rank_migration', false);
        
        if (!$migration_done) {
            // Update all rejimde_level meta keys to rejimde_rank
            $wpdb->query(
                "UPDATE {$wpdb->usermeta} 
                SET meta_key = 'rejimde_rank' 
                WHERE meta_key = 'rejimde_level'"
            );
            
            // Mark migration as complete
            update_option('rejimde_level_to_rank_migration', true);
        }
    }
}