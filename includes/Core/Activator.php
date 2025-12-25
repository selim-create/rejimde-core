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
        
        // 4. Events Table (Event-Driven Logging)
        $table_events = $wpdb->prefix . 'rejimde_events';
        $sql_events = "CREATE TABLE $table_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            entity_type VARCHAR(30) DEFAULT NULL,
            entity_id BIGINT UNSIGNED DEFAULT NULL,
            points SMALLINT DEFAULT 0,
            context JSON DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_date (user_id, created_at),
            INDEX idx_event_type (event_type),
            INDEX idx_entity (entity_type, entity_id)
        ) $charset_collate;";
        dbDelta( $sql_events );
        
        // 5. Streaks Table (Streak Tracking)
        $table_streaks = $wpdb->prefix . 'rejimde_streaks';
        $sql_streaks = "CREATE TABLE $table_streaks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            streak_type VARCHAR(30) NOT NULL,
            current_count SMALLINT UNSIGNED DEFAULT 0,
            longest_count SMALLINT UNSIGNED DEFAULT 0,
            last_activity_date DATE NOT NULL,
            grace_used_this_week TINYINT UNSIGNED DEFAULT 0,
            UNIQUE KEY unique_user_streak (user_id, streak_type),
            INDEX idx_activity (last_activity_date)
        ) $charset_collate;";
        dbDelta( $sql_streaks );
        
        // 6. Milestones Table (Idempotent Rewards)
        $table_milestones = $wpdb->prefix . 'rejimde_milestones';
        $sql_milestones = "CREATE TABLE $table_milestones (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            milestone_type VARCHAR(50) NOT NULL,
            entity_id BIGINT UNSIGNED DEFAULT NULL,
            milestone_value INT UNSIGNED NOT NULL,
            points_awarded SMALLINT NOT NULL,
            awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_milestone (user_id, milestone_type, entity_id, milestone_value)
        ) $charset_collate;";
        dbDelta( $sql_milestones );
        
        // 7. Score Snapshots Table (Periodic Summaries)
        $table_snapshots = $wpdb->prefix . 'rejimde_score_snapshots';
        $sql_snapshots = "CREATE TABLE $table_snapshots (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            period_type ENUM('daily', 'weekly', 'monthly') NOT NULL,
            period_key VARCHAR(10) NOT NULL,
            score INT UNSIGNED DEFAULT 0,
            rank_position SMALLINT UNSIGNED DEFAULT NULL,
            event_counts JSON DEFAULT NULL,
            UNIQUE KEY unique_snapshot (user_id, period_type, period_key),
            INDEX idx_period_rank (period_type, period_key, score DESC)
        ) $charset_collate;";
        dbDelta( $sql_snapshots );
        
        // 8. Migrate rejimde_level to rejimde_rank (if needed)
        self::migrate_level_to_rank();
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