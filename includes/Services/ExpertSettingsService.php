<?php
namespace Rejimde\Services;

/**
 * Expert Settings Service
 * 
 * Handles business logic for expert settings including bank info, addresses, and business details
 */
class ExpertSettingsService {

    /**
     * Get expert settings
     * 
     * @param int $expertId Expert user ID
     * @return array
     */
    public function getSettings(int $expertId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_expert_settings';
        
        $settings = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE expert_id = %d",
            $expertId
        ), ARRAY_A);
        
        if (!$settings) {
            // Return default empty settings
            return [
                'expert_id' => $expertId,
                'bank_name' => null,
                'iban' => null,
                'account_holder' => null,
                'company_name' => null,
                'tax_number' => null,
                'business_phone' => null,
                'business_email' => null,
                'addresses' => [],
                'default_meeting_link' => null,
                'auto_confirm_appointments' => false
            ];
        }
        
        // Parse JSON addresses
        $addresses = [];
        if ($settings['addresses']) {
            $decoded = json_decode($settings['addresses'], true);
            $addresses = is_array($decoded) ? $decoded : [];
        }
        
        return [
            'expert_id' => (int) $settings['expert_id'],
            'bank_name' => $settings['bank_name'],
            'iban' => $settings['iban'],
            'account_holder' => $settings['account_holder'],
            'company_name' => $settings['company_name'],
            'tax_number' => $settings['tax_number'],
            'business_phone' => $settings['business_phone'],
            'business_email' => $settings['business_email'],
            'addresses' => $addresses,
            'default_meeting_link' => $settings['default_meeting_link'],
            'auto_confirm_appointments' => (bool) $settings['auto_confirm_appointments']
        ];
    }

    /**
     * Update expert settings
     * 
     * @param int $expertId Expert user ID
     * @param array $data Settings data
     * @return bool
     */
    public function updateSettings(int $expertId, array $data): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'rejimde_expert_settings';
        
        // Check if settings exist
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE expert_id = %d",
            $expertId
        ));
        
        $updateData = [];
        $formats = [];
        
        // Allowed fields
        $allowedFields = [
            'bank_name', 'iban', 'account_holder',
            'company_name', 'tax_number', 'business_phone', 'business_email',
            'default_meeting_link'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
                $formats[] = '%s';
            }
        }
        
        // Handle auto_confirm_appointments separately (boolean)
        if (array_key_exists('auto_confirm_appointments', $data)) {
            $updateData['auto_confirm_appointments'] = $data['auto_confirm_appointments'] ? 1 : 0;
            $formats[] = '%d';
        }
        
        // Handle addresses (must be array)
        if (array_key_exists('addresses', $data)) {
            $addresses = is_array($data['addresses']) ? $data['addresses'] : [];
            $updateData['addresses'] = json_encode($addresses, JSON_UNESCAPED_UNICODE);
            $formats[] = '%s';
        }
        
        if (empty($updateData)) {
            return true; // Nothing to update
        }
        
        if ($exists) {
            // Update existing
            $result = $wpdb->update(
                $table,
                $updateData,
                ['expert_id' => $expertId],
                $formats,
                ['%d']
            );
        } else {
            // Insert new
            $updateData['expert_id'] = $expertId;
            $formats[] = '%d';
            $result = $wpdb->insert($table, $updateData, $formats);
        }
        
        return $result !== false;
    }

    /**
     * Get addresses for an expert
     * 
     * @param int $expertId Expert user ID
     * @return array
     */
    public function getAddresses(int $expertId): array {
        $settings = $this->getSettings($expertId);
        return $settings['addresses'];
    }

    /**
     * Add address for an expert
     * 
     * @param int $expertId Expert user ID
     * @param array $addressData Address data (title, address, city, district, is_default)
     * @return int|false Address ID on success, false on failure
     */
    public function addAddress(int $expertId, array $addressData): int|false {
        $addresses = $this->getAddresses($expertId);
        
        // Generate new ID
        $newId = 1;
        if (!empty($addresses)) {
            $maxId = max(array_column($addresses, 'id'));
            $newId = $maxId + 1;
        }
        
        // If this is the first address or marked as default, make it default
        $isDefault = empty($addresses) || !empty($addressData['is_default']);
        
        // If marked as default, unset other defaults
        if ($isDefault && !empty($addresses)) {
            foreach ($addresses as &$addr) {
                $addr['is_default'] = false;
            }
            unset($addr);
        }
        
        $newAddress = [
            'id' => $newId,
            'title' => $addressData['title'] ?? '',
            'address' => $addressData['address'] ?? '',
            'city' => $addressData['city'] ?? '',
            'district' => $addressData['district'] ?? '',
            'is_default' => $isDefault
        ];
        
        $addresses[] = $newAddress;
        
        $result = $this->updateSettings($expertId, ['addresses' => $addresses]);
        
        return $result ? $newId : false;
    }

    /**
     * Update address for an expert
     * 
     * @param int $expertId Expert user ID
     * @param int $addressId Address ID
     * @param array $addressData Address data
     * @return bool
     */
    public function updateAddress(int $expertId, int $addressId, array $addressData): bool {
        $addresses = $this->getAddresses($expertId);
        
        $found = false;
        foreach ($addresses as $index => &$addr) {
            if ((int) $addr['id'] === $addressId) {
                $found = true;
                
                // Update fields
                if (isset($addressData['title'])) $addr['title'] = $addressData['title'];
                if (isset($addressData['address'])) $addr['address'] = $addressData['address'];
                if (isset($addressData['city'])) $addr['city'] = $addressData['city'];
                if (isset($addressData['district'])) $addr['district'] = $addressData['district'];
                
                // Handle is_default
                if (isset($addressData['is_default']) && $addressData['is_default']) {
                    // Unset all other defaults
                    foreach ($addresses as &$a) {
                        $a['is_default'] = false;
                    }
                    unset($a);
                    $addr['is_default'] = true;
                } elseif (isset($addressData['is_default']) && !$addressData['is_default']) {
                    $addr['is_default'] = false;
                }
                
                break;
            }
        }
        unset($addr);
        
        if (!$found) {
            return false;
        }
        
        return $this->updateSettings($expertId, ['addresses' => $addresses]);
    }

    /**
     * Delete address for an expert
     * 
     * @param int $expertId Expert user ID
     * @param int $addressId Address ID
     * @return bool
     */
    public function deleteAddress(int $expertId, int $addressId): bool {
        $addresses = $this->getAddresses($expertId);
        
        $found = false;
        $wasDefault = false;
        
        foreach ($addresses as $index => $addr) {
            if ((int) $addr['id'] === $addressId) {
                $found = true;
                $wasDefault = !empty($addr['is_default']);
                unset($addresses[$index]);
                break;
            }
        }
        
        if (!$found) {
            return false;
        }
        
        // Re-index array
        $addresses = array_values($addresses);
        
        // If deleted address was default and there are remaining addresses, make first one default
        if ($wasDefault && !empty($addresses)) {
            $addresses[0]['is_default'] = true;
        }
        
        return $this->updateSettings($expertId, ['addresses' => $addresses]);
    }
}
