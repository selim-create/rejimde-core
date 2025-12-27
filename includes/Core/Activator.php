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
        
        // 8. Notifications Table
        $table_notifications = $wpdb->prefix . 'rejimde_notifications';
        $sql_notifications = "CREATE TABLE $table_notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(50) NOT NULL,
            category ENUM('social', 'system', 'level', 'circle', 'points', 'expert') NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            icon VARCHAR(50) DEFAULT 'fa-bell',
            action_url VARCHAR(255) DEFAULT NULL,
            actor_id BIGINT UNSIGNED DEFAULT NULL,
            entity_type VARCHAR(30) DEFAULT NULL,
            entity_id BIGINT UNSIGNED DEFAULT NULL,
            meta JSON DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            is_pushed TINYINT(1) DEFAULT 0,
            is_emailed TINYINT(1) DEFAULT 0,
            expires_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_unread (user_id, is_read, created_at),
            INDEX idx_user_category (user_id, category),
            INDEX idx_type (type),
            UNIQUE KEY unique_notification (user_id, type, entity_type, entity_id, DATE(created_at))
        ) $charset_collate;";
        dbDelta( $sql_notifications );
        
        // 9. Notification Preferences Table
        $table_preferences = $wpdb->prefix . 'rejimde_notification_preferences';
        $sql_preferences = "CREATE TABLE $table_preferences (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            category VARCHAR(50) NOT NULL,
            channel_in_app TINYINT(1) DEFAULT 1,
            channel_push TINYINT(1) DEFAULT 1,
            channel_email TINYINT(1) DEFAULT 0,
            dnd_start TIME DEFAULT NULL,
            dnd_end TIME DEFAULT NULL,
            UNIQUE KEY unique_pref (user_id, category)
        ) $charset_collate;";
        dbDelta( $sql_preferences );
        
        // 10. Expert Metrics Table
        $table_expert_metrics = $wpdb->prefix . 'rejimde_expert_metrics';
        $sql_expert_metrics = "CREATE TABLE $table_expert_metrics (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            expert_id BIGINT UNSIGNED NOT NULL,
            metric_date DATE NOT NULL,
            profile_views INT UNSIGNED DEFAULT 0,
            unique_viewers INT UNSIGNED DEFAULT 0,
            rating_count INT UNSIGNED DEFAULT 0,
            rating_sum DECIMAL(10,2) DEFAULT 0,
            content_views INT UNSIGNED DEFAULT 0,
            client_completions INT UNSIGNED DEFAULT 0,
            UNIQUE KEY unique_metric (expert_id, metric_date),
            INDEX idx_expert_date (expert_id, metric_date DESC)
        ) $charset_collate;";
        dbDelta( $sql_expert_metrics );
        
        // 11. Profile Views Table
        $table_profile_views = $wpdb->prefix . 'rejimde_profile_views';
        $sql_profile_views = "CREATE TABLE $table_profile_views (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            profile_user_id BIGINT UNSIGNED NOT NULL,
            viewer_user_id BIGINT UNSIGNED DEFAULT NULL,
            viewer_ip_hash VARCHAR(64) DEFAULT NULL,
            source VARCHAR(50) DEFAULT 'direct',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_profile_date (profile_user_id, created_at DESC),
            INDEX idx_viewer (viewer_user_id)
        ) $charset_collate;";
        dbDelta( $sql_profile_views );
        
        // 12. CRM: Expert-Client Relationships Table
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $sql_relationships = "CREATE TABLE $table_relationships (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            expert_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            status ENUM('pending', 'active', 'paused', 'archived', 'blocked') DEFAULT 'pending',
            source ENUM('marketplace', 'invite', 'manual') DEFAULT 'manual',
            invite_token VARCHAR(64) DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,
            ended_at DATETIME DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_relationship (expert_id, client_id),
            INDEX idx_expert (expert_id),
            INDEX idx_client (client_id),
            INDEX idx_status (status)
        ) $charset_collate;";
        dbDelta( $sql_relationships );
        
        // 13. CRM: Client Packages Table
        $table_packages = $wpdb->prefix . 'rejimde_client_packages';
        $sql_packages = "CREATE TABLE $table_packages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            relationship_id BIGINT UNSIGNED NOT NULL,
            package_name VARCHAR(255) NOT NULL,
            package_type ENUM('session', 'duration', 'unlimited') DEFAULT 'session',
            total_sessions INT DEFAULT NULL,
            used_sessions INT DEFAULT 0,
            start_date DATE NOT NULL,
            end_date DATE DEFAULT NULL,
            price DECIMAL(10,2) DEFAULT 0,
            status ENUM('active', 'completed', 'cancelled', 'expired') DEFAULT 'active',
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_relationship (relationship_id),
            INDEX idx_status (status)
        ) $charset_collate;";
        dbDelta( $sql_packages );
        
        // 14. CRM: Client Notes Table
        $table_notes = $wpdb->prefix . 'rejimde_client_notes';
        $sql_notes = "CREATE TABLE $table_notes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            relationship_id BIGINT UNSIGNED NOT NULL,
            expert_id BIGINT UNSIGNED NOT NULL,
            note_type ENUM('general', 'health', 'progress', 'reminder') DEFAULT 'general',
            content TEXT NOT NULL,
            is_pinned TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_relationship (relationship_id)
        ) $charset_collate;";
        dbDelta( $sql_notes );
        
        // 15. Migrate rejimde_level to rejimde_rank (if needed)
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