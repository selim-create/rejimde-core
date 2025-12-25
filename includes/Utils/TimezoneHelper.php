<?php
namespace Rejimde\Utils;

use DateTime;
use DateTimeZone;

/**
 * TimezoneHelper
 * 
 * Helper class for Europe/Istanbul timezone calculations
 */
class TimezoneHelper {
    
    private static $timezone = 'Europe/Istanbul';
    
    /**
     * Get today's date in Turkey (Y-m-d format)
     * 
     * @return string
     */
    public static function getTodayTR() {
        $dt = new DateTime('now', new DateTimeZone(self::$timezone));
        return $dt->format('Y-m-d');
    }
    
    /**
     * Get current datetime in Turkey
     * 
     * @return DateTime
     */
    public static function getNowTR() {
        return new DateTime('now', new DateTimeZone(self::$timezone));
    }
    
    /**
     * Get week boundaries (Monday-Sunday) for Turkey timezone
     * 
     * @param DateTime|null $date Optional date, defaults to now
     * @return array ['start' => 'Y-m-d', 'end' => 'Y-m-d']
     */
    public static function getWeekBoundsTR($date = null) {
        if ($date === null) {
            $date = self::getNowTR();
        } else {
            $date = clone $date;
            $date->setTimezone(new DateTimeZone(self::$timezone));
        }
        
        // Get Monday of current week
        $monday = clone $date;
        $monday->modify('Monday this week');
        
        // Get Sunday of current week
        $sunday = clone $date;
        $sunday->modify('Sunday this week');
        
        return [
            'start' => $monday->format('Y-m-d'),
            'end' => $sunday->format('Y-m-d')
        ];
    }
    
    /**
     * Get month boundaries for Turkey timezone
     * 
     * @param DateTime|null $date Optional date, defaults to now
     * @return array ['start' => 'Y-m-d', 'end' => 'Y-m-d']
     */
    public static function getMonthBoundsTR($date = null) {
        if ($date === null) {
            $date = self::getNowTR();
        } else {
            $date = clone $date;
            $date->setTimezone(new DateTimeZone(self::$timezone));
        }
        
        $start = clone $date;
        $start->modify('first day of this month');
        
        $end = clone $date;
        $end->modify('last day of this month');
        
        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d')
        ];
    }
    
    /**
     * Check if current time is Sunday 23:59:59 in Turkey
     * Used for week-end detection
     * 
     * @return bool
     */
    public static function isWeekEndTR() {
        $now = self::getNowTR();
        
        // Check if Sunday
        if ($now->format('N') != 7) {
            return false;
        }
        
        // Check if between 23:59:00 and 23:59:59
        $hour = (int) $now->format('H');
        $minute = (int) $now->format('i');
        
        return $hour === 23 && $minute === 59;
    }
    
    /**
     * Convert a date string to Turkey timezone
     * 
     * @param string $dateString
     * @return DateTime
     */
    public static function toTR($dateString) {
        $dt = new DateTime($dateString);
        $dt->setTimezone(new DateTimeZone(self::$timezone));
        return $dt;
    }
    
    /**
     * Format a datetime for database storage (MySQL datetime format)
     * 
     * @param DateTime|null $date
     * @return string
     */
    public static function formatForDB($date = null) {
        if ($date === null) {
            $date = self::getNowTR();
        }
        return $date->format('Y-m-d H:i:s');
    }
}
