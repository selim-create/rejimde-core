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
        
        // 4. Migrate rejimde_level to rejimde_rank (if needed)
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