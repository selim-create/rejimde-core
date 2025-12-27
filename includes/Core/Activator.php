<?php
namespace Rejimde\Core;

class Activator {

    public static function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Aktivasyon izleme (çıktı üretmez)
        error_log('[Rejimde Core] Activator::activate started');

        // 1. Ölçümler Tablosu (Kilo, Bel, Basen vb.)
        $table_measurements = $wpdb->prefix . 'rejimde_measurements';
        $sql_measurements = "CREATE TABLE {$table_measurements} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            type varchar(50) NOT NULL,
            value float NOT NULL,
            source varchar(20) DEFAULT 'manual',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY type (type)
        ) {$charset_collate};";
        dbDelta($sql_measurements);

        // 2. Günlük Loglar (Skor, Su, Kalori)
        $table_logs = $wpdb->prefix . 'rejimde_daily_logs';
        $sql_logs = "CREATE TABLE {$table_logs} (
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
        ) {$charset_collate};";
        dbDelta($sql_logs);

        // 3. User Progress Tracking
        $table_progress = $wpdb->prefix . 'rejimde_user_progress';
        $sql_progress = "CREATE TABLE {$table_progress} (
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
        ) {$charset_collate};";
        dbDelta($sql_progress);

        // 4. Events Table (Event-Driven Logging)
        // JSON -> LONGTEXT (Hostinger/MariaDB uyumu)
        $table_events = $wpdb->prefix . 'rejimde_events';
        $sql_events = "CREATE TABLE {$table_events} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            entity_type VARCHAR(30) DEFAULT NULL,
            entity_id BIGINT UNSIGNED DEFAULT NULL,
            points SMALLINT DEFAULT 0,
            context LONGTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user_date (user_id, created_at),
            INDEX idx_event_type (event_type),
            INDEX idx_entity (entity_type, entity_id)
        ) {$charset_collate};";
        dbDelta($sql_events);

        // 5. Streaks Table (Streak Tracking)
        $table_streaks = $wpdb->prefix . 'rejimde_streaks';
        $sql_streaks = "CREATE TABLE {$table_streaks} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            streak_type VARCHAR(30) NOT NULL,
            current_count SMALLINT UNSIGNED DEFAULT 0,
            longest_count SMALLINT UNSIGNED DEFAULT 0,
            last_activity_date DATE NOT NULL,
            grace_used_this_week TINYINT UNSIGNED DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_streak (user_id, streak_type),
            INDEX idx_activity (last_activity_date)
        ) {$charset_collate};";
        dbDelta($sql_streaks);

        // 6. Milestones Table (Idempotent Rewards)
        $table_milestones = $wpdb->prefix . 'rejimde_milestones';
        $sql_milestones = "CREATE TABLE {$table_milestones} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            milestone_type VARCHAR(50) NOT NULL,
            entity_id BIGINT UNSIGNED DEFAULT NULL,
            milestone_value INT UNSIGNED NOT NULL,
            points_awarded SMALLINT NOT NULL,
            awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_milestone (user_id, milestone_type, entity_id, milestone_value)
        ) {$charset_collate};";
        dbDelta($sql_milestones);

        // 7. Score Snapshots Table (Periodic Summaries)
        // JSON -> LONGTEXT, DESC index kaldırıldı
        $table_snapshots = $wpdb->prefix . 'rejimde_score_snapshots';
        $sql_snapshots = "CREATE TABLE {$table_snapshots} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            period_type ENUM('daily', 'weekly', 'monthly') NOT NULL,
            period_key VARCHAR(10) NOT NULL,
            score INT UNSIGNED DEFAULT 0,
            rank_position SMALLINT UNSIGNED DEFAULT NULL,
            event_counts LONGTEXT DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_snapshot (user_id, period_type, period_key),
            INDEX idx_period_rank (period_type, period_key, score)
        ) {$charset_collate};";
        dbDelta($sql_snapshots);

        // 8. Notifications Table
        // meta JSON -> LONGTEXT
        // DATE(created_at) fonksiyonlu unique kaldırıldı
        // created_date eklenip unique buna bağlandı
        $table_notifications = $wpdb->prefix . 'rejimde_notifications';
        $sql_notifications = "CREATE TABLE {$table_notifications} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
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
            meta LONGTEXT DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            is_pushed TINYINT(1) DEFAULT 0,
            is_emailed TINYINT(1) DEFAULT 0,
            expires_at DATETIME DEFAULT NULL,
            created_date DATE DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user_unread (user_id, is_read, created_at),
            INDEX idx_user_category (user_id, category),
            INDEX idx_type (type),
            UNIQUE KEY unique_notification (user_id, type, entity_type, entity_id, created_date)
        ) {$charset_collate};";
        dbDelta($sql_notifications);

        // created_date NULL kalmasın: mevcut satırlar için doldur
        // (Yeni insertlerde service katmanında set edilmesi ideal ama burada safe patch)
        $wpdb->query(
            "UPDATE {$table_notifications}
             SET created_date = DATE(created_at)
             WHERE created_date IS NULL"
        );

        // 9. Notification Preferences Table
        $table_preferences = $wpdb->prefix . 'rejimde_notification_preferences';
        $sql_preferences = "CREATE TABLE {$table_preferences} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            category VARCHAR(50) NOT NULL,
            channel_in_app TINYINT(1) DEFAULT 1,
            channel_push TINYINT(1) DEFAULT 1,
            channel_email TINYINT(1) DEFAULT 0,
            dnd_start TIME DEFAULT NULL,
            dnd_end TIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_pref (user_id, category)
        ) {$charset_collate};";
        dbDelta($sql_preferences);

        // 10. Expert Metrics Table
        // DESC index kaldırıldı (dbDelta uyumu)
        $table_expert_metrics = $wpdb->prefix . 'rejimde_expert_metrics';
        $sql_expert_metrics = "CREATE TABLE {$table_expert_metrics} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            expert_id BIGINT UNSIGNED NOT NULL,
            metric_date DATE NOT NULL,
            profile_views INT UNSIGNED DEFAULT 0,
            unique_viewers INT UNSIGNED DEFAULT 0,
            rating_count INT UNSIGNED DEFAULT 0,
            rating_sum DECIMAL(10,2) DEFAULT 0,
            content_views INT UNSIGNED DEFAULT 0,
            client_completions INT UNSIGNED DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY unique_metric (expert_id, metric_date),
            INDEX idx_expert_date (expert_id, metric_date)
        ) {$charset_collate};";
        dbDelta($sql_expert_metrics);

        // 11. Profile Views Table
        $table_profile_views = $wpdb->prefix . 'rejimde_profile_views';
        $sql_profile_views = "CREATE TABLE {$table_profile_views} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            profile_user_id BIGINT UNSIGNED NOT NULL,
            viewer_user_id BIGINT UNSIGNED DEFAULT NULL,
            viewer_ip_hash VARCHAR(64) DEFAULT NULL,
            source VARCHAR(50) DEFAULT 'direct',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_profile_date (profile_user_id, created_at),
            INDEX idx_viewer (viewer_user_id)
        ) {$charset_collate};";
        dbDelta($sql_profile_views);

        // 12. CRM: Expert-Client Relationships Table
        $table_relationships = $wpdb->prefix . 'rejimde_relationships';
        $sql_relationships = "CREATE TABLE {$table_relationships} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
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
            PRIMARY KEY (id),
            UNIQUE KEY unique_relationship (expert_id, client_id),
            INDEX idx_expert (expert_id),
            INDEX idx_client (client_id),
            INDEX idx_status (status)
        ) {$charset_collate};";
        dbDelta($sql_relationships);

        // 13. CRM: Client Packages Table
        $table_packages = $wpdb->prefix . 'rejimde_client_packages';
        $sql_packages = "CREATE TABLE {$table_packages} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
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
            PRIMARY KEY (id),
            INDEX idx_relationship (relationship_id),
            INDEX idx_status (status)
        ) {$charset_collate};";
        dbDelta($sql_packages);

        // 14. CRM: Client Notes Table
        $table_notes = $wpdb->prefix . 'rejimde_client_notes';
        $sql_notes = "CREATE TABLE {$table_notes} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            relationship_id BIGINT UNSIGNED NOT NULL,
            expert_id BIGINT UNSIGNED NOT NULL,
            note_type ENUM('general', 'health', 'progress', 'reminder') DEFAULT 'general',
            content TEXT NOT NULL,
            is_pinned TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_relationship (relationship_id)
        ) {$charset_collate};";
        dbDelta($sql_notes);

        // 15. Inbox: Threads Table
        $table_threads = $wpdb->prefix . 'rejimde_threads';
        $sql_threads = "CREATE TABLE {$table_threads} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            relationship_id BIGINT UNSIGNED NOT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            status ENUM('open', 'closed', 'archived') DEFAULT 'open',
            last_message_at DATETIME DEFAULT NULL,
            last_message_by BIGINT UNSIGNED DEFAULT NULL,
            unread_expert INT DEFAULT 0,
            unread_client INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_relationship (relationship_id),
            INDEX idx_status (status),
            INDEX idx_last_message (last_message_at)
        ) {$charset_collate};";
        dbDelta($sql_threads);

        // 16. Inbox: Messages Table
        $table_messages = $wpdb->prefix . 'rejimde_messages';
        $sql_messages = "CREATE TABLE {$table_messages} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            thread_id BIGINT UNSIGNED NOT NULL,
            sender_id BIGINT UNSIGNED NOT NULL,
            sender_type ENUM('expert', 'client') NOT NULL,
            content TEXT NOT NULL,
            content_type ENUM('text', 'image', 'file', 'voice', 'plan_link') DEFAULT 'text',
            attachments LONGTEXT DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            read_at DATETIME DEFAULT NULL,
            is_ai_generated TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_thread (thread_id),
            INDEX idx_sender (sender_id),
            INDEX idx_created (created_at)
        ) {$charset_collate};";
        dbDelta($sql_messages);

        // 17. Inbox: Message Templates Table
        $table_templates = $wpdb->prefix . 'rejimde_message_templates';
        $sql_templates = "CREATE TABLE {$table_templates} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            expert_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            category VARCHAR(50) DEFAULT 'general',
            usage_count INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_expert (expert_id)
        ) {$charset_collate};";
        dbDelta($sql_templates);

        // 18. Calendar: Expert Availability Template
        $table_availability = $wpdb->prefix . 'rejimde_availability';
        $sql_availability = "CREATE TABLE {$table_availability} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            expert_id BIGINT UNSIGNED NOT NULL,
            day_of_week TINYINT NOT NULL COMMENT '0=Pazar, 1=Pazartesi, ..., 6=Cumartesi',
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            slot_duration INT DEFAULT 60 COMMENT 'Dakika cinsinden slot süresi',
            buffer_time INT DEFAULT 15 COMMENT 'Randevular arası boşluk',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_expert (expert_id),
            INDEX idx_day (day_of_week)
        ) {$charset_collate};";
        dbDelta($sql_availability);

        // 19. Calendar: Appointments
        $table_appointments = $wpdb->prefix . 'rejimde_appointments';
        $sql_appointments = "CREATE TABLE {$table_appointments} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            expert_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            relationship_id BIGINT UNSIGNED DEFAULT NULL,
            service_id BIGINT UNSIGNED DEFAULT NULL,
            title VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            appointment_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            duration INT DEFAULT 60,
            status ENUM('pending', 'confirmed', 'cancelled', 'completed', 'no_show') DEFAULT 'pending',
            type ENUM('online', 'in_person', 'phone') DEFAULT 'online',
            location VARCHAR(255) DEFAULT NULL,
            meeting_link VARCHAR(500) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            cancellation_reason TEXT DEFAULT NULL,
            cancelled_by BIGINT UNSIGNED DEFAULT NULL,
            reminder_sent TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_expert (expert_id),
            INDEX idx_client (client_id),
            INDEX idx_date (appointment_date),
            INDEX idx_status (status),
            INDEX idx_expert_date (expert_id, appointment_date)
        ) {$charset_collate};";
        dbDelta($sql_appointments);

        // 20. Calendar: Appointment Requests
        $table_appointment_requests = $wpdb->prefix . 'rejimde_appointment_requests';
        $sql_appointment_requests = "CREATE TABLE {$table_appointment_requests} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            expert_id BIGINT UNSIGNED NOT NULL,
            requester_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL ise guest',
            requester_name VARCHAR(255) NOT NULL,
            requester_email VARCHAR(255) NOT NULL,
            requester_phone VARCHAR(50) DEFAULT NULL,
            service_id BIGINT UNSIGNED DEFAULT NULL,
            preferred_date DATE NOT NULL,
            preferred_time TIME NOT NULL,
            alternative_date DATE DEFAULT NULL,
            alternative_time TIME DEFAULT NULL,
            message TEXT DEFAULT NULL,
            status ENUM('pending', 'approved', 'rejected', 'expired') DEFAULT 'pending',
            rejection_reason TEXT DEFAULT NULL,
            created_appointment_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_expert (expert_id),
            INDEX idx_status (status),
            INDEX idx_date (preferred_date)
        ) {$charset_collate};";
        dbDelta($sql_appointment_requests);

        // 21. Calendar: Blocked Times
        $table_blocked_times = $wpdb->prefix . 'rejimde_blocked_times';
        $sql_blocked_times = "CREATE TABLE {$table_blocked_times} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            expert_id BIGINT UNSIGNED NOT NULL,
            blocked_date DATE NOT NULL,
            start_time TIME DEFAULT NULL COMMENT 'NULL ise tüm gün bloke',
            end_time TIME DEFAULT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            is_recurring TINYINT(1) DEFAULT 0,
            recurrence_pattern VARCHAR(50) DEFAULT NULL COMMENT 'weekly, monthly',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_expert_date (expert_id, blocked_date)
        ) {$charset_collate};";
        dbDelta($sql_blocked_times);

        // 22. Finance: Services Table
        $table_services = $wpdb->prefix . 'rejimde_services';
        $sql_services = "CREATE TABLE {$table_services} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            expert_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            type ENUM('session', 'package', 'subscription', 'one_time') DEFAULT 'session',
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            currency VARCHAR(3) DEFAULT 'TRY',
            duration_minutes INT DEFAULT 60 COMMENT 'Seans süresi',
            session_count INT DEFAULT NULL COMMENT 'Paket içi seans sayısı',
            validity_days INT DEFAULT NULL COMMENT 'Paket geçerlilik süresi',
            is_active TINYINT(1) DEFAULT 1,
            is_featured TINYINT(1) DEFAULT 0,
            color VARCHAR(7) DEFAULT '#3B82F6' COMMENT 'UI renk kodu',
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_expert (expert_id),
            INDEX idx_active (is_active)
        ) {$charset_collate};";
        dbDelta($sql_services);

        // 23. Finance: Payments Table
        $table_payments = $wpdb->prefix . 'rejimde_payments';
        $sql_payments = "CREATE TABLE {$table_payments} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            expert_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            relationship_id BIGINT UNSIGNED DEFAULT NULL,
            package_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'client_packages tablosundaki ID',
            service_id BIGINT UNSIGNED DEFAULT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'TRY',
            payment_method ENUM('cash', 'bank_transfer', 'credit_card', 'online', 'other') DEFAULT 'cash',
            payment_date DATE NOT NULL,
            due_date DATE DEFAULT NULL,
            status ENUM('pending', 'paid', 'partial', 'overdue', 'cancelled', 'refunded') DEFAULT 'pending',
            paid_amount DECIMAL(10,2) DEFAULT 0,
            description VARCHAR(500) DEFAULT NULL,
            receipt_url VARCHAR(500) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_expert (expert_id),
            INDEX idx_client (client_id),
            INDEX idx_status (status),
            INDEX idx_date (payment_date),
            INDEX idx_expert_date (expert_id, payment_date)
        ) {$charset_collate};";
        dbDelta($sql_payments);

        // 24. Finance: Payment Reminders Table
        $table_payment_reminders = $wpdb->prefix . 'rejimde_payment_reminders';
        $sql_payment_reminders = "CREATE TABLE {$table_payment_reminders} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            payment_id BIGINT UNSIGNED NOT NULL,
            reminder_date DATE NOT NULL,
            reminder_type ENUM('upcoming', 'due', 'overdue') NOT NULL,
            is_sent TINYINT(1) DEFAULT 0,
            sent_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_payment (payment_id),
            INDEX idx_date (reminder_date)
        ) {$charset_collate};";
        dbDelta($sql_payment_reminders);

        // 25. Terminoloji Migrasyonu
        self::migrate_level_to_rank();

        error_log('[Rejimde Core] Activator::activate finished');
    }

    /**
     * Migrate rejimde_level meta key to rejimde_rank
     * One-time migration
     */
    private static function migrate_level_to_rank() {
        global $wpdb;

        $migration_done = get_option('rejimde_level_to_rank_migration', false);

        if (!$migration_done) {
            $wpdb->query(
                "UPDATE {$wpdb->usermeta}
                 SET meta_key = 'rejimde_rank'
                 WHERE meta_key = 'rejimde_level'"
            );

            update_option('rejimde_level_to_rank_migration', true);
        }
    }
}
