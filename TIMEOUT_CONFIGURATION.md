# Timeout Error Handling Configuration

This document explains how the timeout error handling is configured and what steps to take if you encounter timeout issues.

## What We've Implemented

### 1. **Custom Error Page** ✅
- Created `resources/views/errors/timeout.blade.php`
- User-friendly error message with helpful suggestions
- Shows what happened and what to do next

### 2. **Exception Handler** ✅
- Updated `bootstrap/app.php` to catch timeout errors
- Returns HTTP 504 Gateway Timeout status
- Shows custom error page instead of default Laravel error

### 3. **Environment Configuration** ✅
- Added `PHP_MAX_EXECUTION_TIME=300` to `.env` (5 minutes)
- Used in critical operations via `set_time_limit()`

### 4. **Critical Operations Protected** ✅
- **PDF Generation** (InvoiceController::download)
- **Payroll Submission** (Timesheet::saveSubmission)
- **Payroll Edit Submission** (TimesheetEdit::saveSubmission)

## XAMPP PHP Configuration (Important!)

The `.env` setting alone is NOT enough. You must also update XAMPP's `php.ini` file:

### Steps to Update php.ini:

1. **Open XAMPP Control Panel**
2. **Click "Config" next to Apache**
3. **Select "PHP (php.ini)"**
4. **Find and update these settings:**

```ini
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
post_max_size = 50M
upload_max_filesize = 50M
```

5. **Save the file**
6. **Restart Apache in XAMPP**

### Alternative: Direct File Edit

The php.ini file is usually located at:
```
C:\xampp\php\php.ini
```

## How It Works

### When Timeout Occurs:

1. **User sees friendly error page** instead of:
   ```
   Symfony\Component\ErrorHandler\Error\FatalError
   Maximum execution time of 60 seconds exceeded
   ```

2. **Error page shows:**
   - What happened
   - Why it happened
   - What to do next
   - Action buttons (Go Back, Dashboard, Refresh)

3. **User gets helpful suggestions:**
   - Try refreshing to see if action completed
   - Split large payrolls into smaller batches
   - Wait and try again
   - Contact support if persists

### For API Requests:

Returns JSON response:
```json
{
  "message": "The request took too long to process...",
  "error": "timeout"
}
```

## Operations with Extended Timeout

These operations automatically get 5 minutes (300 seconds):

| Operation | Location | Why |
|-----------|----------|-----|
| PDF Download | InvoiceController@download | Large PDFs with many workers |
| Payroll Submit | Timesheet@saveSubmission | Complex calculations for many workers |
| Payroll Edit Submit | TimesheetEdit@saveSubmission | Complex calculations for many workers |

## Troubleshooting

### If timeout still occurs after configuration:

1. **Check XAMPP php.ini was updated**
   - Verify `max_execution_time = 300`
   - Ensure Apache was restarted

2. **Check operation type**
   - Large payrolls (>50 workers): Consider splitting
   - Complex calculations: May need to increase to 600 seconds

3. **Increase timeout further if needed**
   - Update `.env`: `PHP_MAX_EXECUTION_TIME=600`
   - Update `php.ini`: `max_execution_time = 600`
   - Restart Apache

4. **Check server resources**
   - Memory limit: Should be at least 512M
   - Database connections: Check for slow queries

## Best Practices

### To Avoid Timeouts:

1. **For Large Payrolls:**
   - Process in batches if possible
   - Submit workers in groups rather than all at once

2. **For PDF Generation:**
   - Generate PDFs asynchronously using queues (future enhancement)

3. **For Calculations:**
   - Optimize SOCSO/EPF calculation queries
   - Use database indexing on worker_id fields

4. **Monitoring:**
   - Check Laravel logs for slow operations
   - Monitor database query performance

## Future Enhancements

Consider implementing:
- **Queue System**: Process large payrolls in background
- **Progress Indicators**: Show loading state for long operations
- **Batch Processing**: Split large submissions automatically
- **Caching**: Cache SOCSO table lookups

## Support

If users encounter timeout errors:

1. **Check their XAMPP php.ini configuration**
2. **Verify they restarted Apache after changes**
3. **Check the operation they were performing**
4. **Review Laravel logs** at `storage/logs/laravel.log`
5. **Consider increasing timeout** for their specific use case

---

**Last Updated:** 2025-12-11
**Configuration Version:** 1.0
