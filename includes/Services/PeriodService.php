<?php
namespace Rejimde\Services;

/**
 * Period Management Service
 * 
 * Handles daily/weekly/monthly period calculations
 */
class PeriodService {
    
    /**
     * Get current period key by type
     * 
     * @param string $type 'daily', 'weekly', 'monthly'
     * @return string "2026-01-03" | "2026-W01" | "2026-01"
     */
    public function getCurrentPeriodKey(string $type): string {
        $now = new \DateTime('now', new \DateTimeZone('Europe/Istanbul'));
        
        switch ($type) {
            case 'daily':
                return $now->format('Y-m-d');
                
            case 'weekly':
                return $this->getWeekNumber($now);
                
            case 'monthly':
                return $now->format('Y-m');
                
            default:
                return $now->format('Y-m-d');
        }
    }
    
    /**
     * Get period end timestamp
     * 
     * @param string $type 'daily', 'weekly', 'monthly'
     * @return int Unix timestamp
     */
    public function getPeriodEndTimestamp(string $type): int {
        $now = new \DateTime('now', new \DateTimeZone('Europe/Istanbul'));
        
        switch ($type) {
            case 'daily':
                $end = clone $now;
                $end->setTime(23, 59, 59);
                return $end->getTimestamp();
                
            case 'weekly':
                // End of Sunday
                $end = clone $now;
                $end->modify('Sunday this week');
                $end->setTime(23, 59, 59);
                return $end->getTimestamp();
                
            case 'monthly':
                // End of month
                $end = clone $now;
                $end->modify('last day of this month');
                $end->setTime(23, 59, 59);
                return $end->getTimestamp();
                
            default:
                return time() + 86400; // +1 day
        }
    }
    
    /**
     * Check if period has expired
     * 
     * @param string $periodKey Period key to check
     * @param string $type Period type
     * @return bool True if expired
     */
    public function isPeriodExpired(string $periodKey, string $type): bool {
        $current = $this->getCurrentPeriodKey($type);
        return $periodKey !== $current;
    }
    
    /**
     * Get week number (ISO 8601)
     * 
     * @param \DateTime|null $date Date to get week number for
     * @return string "2026-W01"
     */
    public function getWeekNumber(\DateTime $date = null): string {
        if ($date === null) {
            $date = new \DateTime('now', new \DateTimeZone('Europe/Istanbul'));
        } else {
            // Ensure timezone is set
            $date = clone $date;
            $date->setTimezone(new \DateTimeZone('Europe/Istanbul'));
        }
        
        return $date->format('o-\WW'); // ISO 8601 week number
    }
    
    /**
     * Get period start date
     * 
     * @param string $periodKey Period key
     * @param string $type Period type
     * @return \DateTime|null Start date or null if invalid
     */
    public function getPeriodStartDate(string $periodKey, string $type): ?\DateTime {
        try {
            $tz = new \DateTimeZone('Europe/Istanbul');
            
            switch ($type) {
                case 'daily':
                    return new \DateTime($periodKey, $tz);
                    
                case 'weekly':
                    // Parse "2026-W01" format
                    if (preg_match('/^(\d{4})-W(\d{2})$/', $periodKey, $matches)) {
                        $year = $matches[1];
                        $week = $matches[2];
                        $date = new \DateTime();
                        $date->setISODate($year, $week, 1); // Monday
                        $date->setTimezone($tz);
                        return $date;
                    }
                    return null;
                    
                case 'monthly':
                    return new \DateTime($periodKey . '-01', $tz);
                    
                default:
                    return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get period end date
     * 
     * @param string $periodKey Period key
     * @param string $type Period type
     * @return \DateTime|null End date or null if invalid
     */
    public function getPeriodEndDate(string $periodKey, string $type): ?\DateTime {
        $start = $this->getPeriodStartDate($periodKey, $type);
        if (!$start) {
            return null;
        }
        
        switch ($type) {
            case 'daily':
                $end = clone $start;
                $end->setTime(23, 59, 59);
                return $end;
                
            case 'weekly':
                $end = clone $start;
                $end->modify('+6 days');
                $end->setTime(23, 59, 59);
                return $end;
                
            case 'monthly':
                $end = clone $start;
                $end->modify('last day of this month');
                $end->setTime(23, 59, 59);
                return $end;
                
            default:
                return null;
        }
    }
}
