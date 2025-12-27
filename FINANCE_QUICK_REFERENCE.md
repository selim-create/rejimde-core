# Finance Module - Quick Reference

## Installation

The finance module tables are automatically created when the plugin is activated via `Activator.php`.

To manually trigger table creation:
1. Deactivate the plugin
2. Reactivate the plugin

Or run this SQL directly:
```sql
-- See Activator.php lines 439-503 for full table definitions
```

## Quick Start

### 1. Create a Service/Package

```bash
curl -X POST https://yoursite.com/wp-json/rejimde/v1/pro/finance/services \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Online Danışmanlık",
    "description": "60 dakikalık online görüşme",
    "type": "session",
    "price": 500.00,
    "duration_minutes": 60
  }'
```

### 2. Create a Payment

```bash
curl -X POST https://yoursite.com/wp-json/rejimde/v1/pro/finance/payments \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 456,
    "service_id": 1,
    "amount": 500.00,
    "payment_method": "bank_transfer",
    "payment_date": "2025-12-27",
    "status": "paid"
  }'
```

### 3. Get Dashboard

```bash
curl https://yoursite.com/wp-json/rejimde/v1/pro/finance/dashboard?period=this_month \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 4. Get Monthly Report

```bash
curl "https://yoursite.com/wp-json/rejimde/v1/pro/finance/reports/monthly?year=2025&month=12" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Common Use Cases

### Track Payment Status

1. Create pending payment with due date
2. System automatically marks as overdue when due_date passes
3. Use `mark-paid` endpoint to mark as paid
4. Or use `partial` endpoint for partial payments

### Service Management

- Create different service types: `session`, `package`, `subscription`, `one_time`
- Use `color` field for UI customization
- Use `sort_order` for display ordering
- Soft delete via `DELETE` endpoint (sets `is_active = 0`)

### Revenue Analytics

- Dashboard provides overview with monthly comparison
- Monthly report shows weekly breakdown and top clients
- Yearly report shows month-by-month revenue
- Revenue by service shows which services generate most income

## Database Schema Quick Reference

### Services Table
```
wp_rejimde_services (id, expert_id, name, type, price, ...)
├── Types: session, package, subscription, one_time
└── Soft delete via is_active flag
```

### Payments Table
```
wp_rejimde_payments (id, expert_id, client_id, amount, status, ...)
├── Status: pending, paid, partial, overdue, cancelled, refunded
├── Tracks both amount and paid_amount for partial payments
└── Links to service_id, package_id, relationship_id
```

### Payment Reminders Table
```
wp_rejimde_payment_reminders (id, payment_id, reminder_date, type, ...)
├── Types: upcoming, due, overdue
└── Tracks sent status
```

## Performance Notes

- Uses JOINs to avoid N+1 queries
- Bulk updates for overdue payment status
- Indexed on expert_id, client_id, payment_date, status
- Service usage counts fetched in single GROUP BY query

## Security

- All endpoints require authenticated expert user
- Ownership verified on update/delete operations
- Input validation on all create/update operations
- SQL injection prevented via prepared statements

## Integration Points

### With CRM Module
- Links to `relationship_id` from `wp_rejimde_relationships`
- Links to `package_id` from `wp_rejimde_client_packages`

### With Notification Module (Future)
- Payment reminders can trigger notifications
- Overdue payment alerts
- Payment received confirmations

## Error Handling

All endpoints return consistent error format:
```json
{
  "status": "error",
  "message": "Description of error"
}
```

Database errors include `$wpdb->last_error` for debugging.

## File Locations

```
includes/
├── Core/
│   ├── Activator.php           # Database tables (lines 439-503)
│   └── Loader.php              # Registration (lines 35, 76, 133)
├── Services/
│   └── FinanceService.php      # Business logic
└── Api/V1/
    └── FinanceController.php   # REST endpoints
```

## Testing Checklist

- [ ] Plugin activation creates tables
- [ ] Service CRUD operations work
- [ ] Payment CRUD operations work
- [ ] Partial payment calculation correct
- [ ] Overdue status updates automatically
- [ ] Dashboard calculations accurate
- [ ] Monthly report data correct
- [ ] Yearly report data correct
- [ ] Expert auth check prevents unauthorized access
- [ ] Ownership verification on updates/deletes

## Troubleshooting

### Tables not created?
Check `wp-admin` > Tools > Site Health > Info > Database
Look for `wp_rejimde_services`, `wp_rejimde_payments`, `wp_rejimde_payment_reminders`

### 401 Unauthorized?
Ensure user has `is_professional` meta set to true:
```php
update_user_meta($user_id, 'is_professional', true);
```

### Empty dashboard?
Create some test payments with `payment_date` in current month and `status = 'paid'`

### N+1 queries?
All optimized! Check these optimizations:
- Line 67: JOIN for service names
- Line 372: Bulk usage count query
- Line 721: Bulk overdue status update

## Next Steps

1. Install plugin and activate
2. Create a few services
3. Create some test payments
4. Check dashboard
5. Generate reports
6. Integrate with frontend

For full API documentation, see `FINANCE_API_DOCUMENTATION.md`
