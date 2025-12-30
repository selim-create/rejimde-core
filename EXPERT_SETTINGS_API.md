# Expert Settings API Documentation

## Overview
The Expert Settings API allows experts to manage their business settings including bank information, addresses, and business details.

## Endpoints

### 1. Get Expert Settings
Get all settings for the authenticated expert.

**Endpoint:** `GET /rejimde/v1/pro/settings`

**Authentication:** Required (rejimde_pro or administrator role)

**Response:**
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "expert_id": 123,
    "bank_name": "Example Bank",
    "iban": "TR123456789012345678901234",
    "account_holder": "John Doe",
    "company_name": "Doe Nutrition",
    "tax_number": "1234567890",
    "business_phone": "+90 555 123 4567",
    "business_email": "business@example.com",
    "addresses": [
      {
        "id": 1,
        "title": "Main Office",
        "address": "123 Main St",
        "city": "Istanbul",
        "district": "Kadıköy",
        "is_default": true
      }
    ],
    "default_meeting_link": "https://meet.google.com/abc-defg-hij",
    "auto_confirm_appointments": false
  }
}
```

### 2. Update Expert Settings
Update settings for the authenticated expert.

**Endpoint:** `POST /rejimde/v1/pro/settings`

**Authentication:** Required (rejimde_pro or administrator role)

**Request Body:**
```json
{
  "bank_name": "Example Bank",
  "iban": "TR123456789012345678901234",
  "account_holder": "John Doe",
  "company_name": "Doe Nutrition",
  "tax_number": "1234567890",
  "business_phone": "+90 555 123 4567",
  "business_email": "business@example.com",
  "default_meeting_link": "https://meet.google.com/abc-defg-hij",
  "auto_confirm_appointments": true
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "message": "Settings updated successfully"
  }
}
```

### 3. Get Addresses
Get all addresses for the authenticated expert.

**Endpoints:** 
- `GET /rejimde/v1/pro/addresses` (recommended)
- `GET /rejimde/v1/pro/settings/addresses` (also supported)

**Authentication:** Required (rejimde_pro or administrator role)

**Response:**
```json
{
  "status": "success",
  "message": "Success",
  "data": [
    {
      "id": 1,
      "title": "Main Office",
      "address": "123 Main St",
      "city": "Istanbul",
      "district": "Kadıköy",
      "is_default": true
    },
    {
      "id": 2,
      "title": "Secondary Office",
      "address": "456 Second Ave",
      "city": "Ankara",
      "district": "Çankaya",
      "is_default": false
    }
  ]
}
```

### 4. Add Address
Add a new address for the authenticated expert.

**Endpoint:** `POST /rejimde/v1/pro/settings/addresses`

**Authentication:** Required (rejimde_pro or administrator role)

**Request Body:**
```json
{
  "title": "Main Office",
  "address": "123 Main St",
  "city": "Istanbul",
  "district": "Kadıköy",
  "is_default": true
}
```

**Required Fields:**
- `title` (string)
- `address` (string)

**Optional Fields:**
- `city` (string)
- `district` (string)
- `is_default` (boolean) - If true, other addresses will be set to non-default

**Response:**
```json
{
  "status": "success",
  "message": "Address added",
  "data": {
    "id": 1,
    "message": "Address added successfully"
  }
}
```

### 5. Update Address
Update an existing address.

**Endpoints:**
- `PATCH /rejimde/v1/pro/addresses/{id}` (recommended)
- `PATCH /rejimde/v1/pro/settings/addresses/{id}` (also supported)

**Authentication:** Required (rejimde_pro or administrator role)

**URL Parameters:**
- `id` (integer) - Address ID

**Request Body:**
```json
{
  "title": "Updated Office",
  "address": "789 New St",
  "city": "Istanbul",
  "district": "Beşiktaş",
  "is_default": true
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "message": "Address updated successfully"
  }
}
```

### 6. Delete Address
Delete an address.

**Endpoints:**
- `DELETE /rejimde/v1/pro/addresses/{id}` (recommended)
- `DELETE /rejimde/v1/pro/settings/addresses/{id}` (also supported)

**Authentication:** Required (rejimde_pro or administrator role)

**URL Parameters:**
- `id` (integer) - Address ID

**Response:**
```json
{
  "status": "success",
  "message": "Success",
  "data": {
    "message": "Address deleted successfully"
  }
}
```

**Note:** If the deleted address was the default and other addresses exist, the first remaining address will be set as default.

## Error Responses

### 400 Bad Request
```json
{
  "status": "error",
  "message": "Title and address are required"
}
```

### 401 Unauthorized
User is not authenticated or doesn't have expert permissions.

### 404 Not Found
```json
{
  "status": "error",
  "message": "Address not found or failed to update"
}
```

### 500 Internal Server Error
```json
{
  "status": "error",
  "message": "Failed to update settings"
}
```

## Database Schema

The expert settings are stored in the `wp_rejimde_expert_settings` table:

```sql
CREATE TABLE wp_rejimde_expert_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    expert_id BIGINT UNSIGNED NOT NULL,
    bank_name VARCHAR(255) DEFAULT NULL,
    iban VARCHAR(50) DEFAULT NULL,
    account_holder VARCHAR(255) DEFAULT NULL,
    company_name VARCHAR(255) DEFAULT NULL,
    tax_number VARCHAR(50) DEFAULT NULL,
    business_phone VARCHAR(50) DEFAULT NULL,
    business_email VARCHAR(255) DEFAULT NULL,
    addresses LONGTEXT DEFAULT NULL COMMENT 'JSON array',
    default_meeting_link VARCHAR(500) DEFAULT NULL,
    auto_confirm_appointments TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_expert (expert_id)
);
```

## Example Usage

### JavaScript (Fetch API)
```javascript
// Get settings
fetch('/wp-json/rejimde/v1/pro/settings', {
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN'
  }
})
.then(res => res.json())
.then(data => console.log(data));

// Update settings
fetch('/wp-json/rejimde/v1/pro/settings', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    company_name: 'My Company',
    business_email: 'info@example.com'
  })
})
.then(res => res.json())
.then(data => console.log(data));

// Add address
fetch('/wp-json/rejimde/v1/pro/settings/addresses', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    title: 'Main Office',
    address: '123 Main St',
    city: 'Istanbul',
    is_default: true
  })
})
.then(res => res.json())
.then(data => console.log(data));
```

### cURL
```bash
# Get settings
curl -X GET "https://yoursite.com/wp-json/rejimde/v1/pro/settings" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Update settings
curl -X POST "https://yoursite.com/wp-json/rejimde/v1/pro/settings" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "company_name": "My Company",
    "business_email": "info@example.com"
  }'

# Add address
curl -X POST "https://yoursite.com/wp-json/rejimde/v1/pro/settings/addresses" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Main Office",
    "address": "123 Main St",
    "city": "Istanbul",
    "is_default": true
  }'

# Delete address
curl -X DELETE "https://yoursite.com/wp-json/rejimde/v1/pro/settings/addresses/1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```
