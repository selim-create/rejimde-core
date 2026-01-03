<?php
namespace Rejimde\Core;

class Deactivator {

    public static function deactivate() {
        // Rewrite kurallarını temizle ki CPT linkleri 404 vermesin
        flush_rewrite_rules();
        
        // Unschedule task cron events
        if (class_exists('Rejimde\\Cron\\TaskCron')) {
            \Rejimde\Cron\TaskCron::unschedule_events();
        }
    }
}