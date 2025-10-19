// JavaScript Translations
const translations = {
    // UI Elements
    'toggle_terminal': 'Toggle Terminal (Ctrl+Shift+T)',
    'toggle_terminal_confirmation': 'Are you sure you want to toggle the terminal?',
    'terminal_toggled': 'Terminal toggled',
    'terminal_not_available': 'Terminal system is not available',
    'terminal_initializing': 'Initializing terminal...',
    'terminal_ready': 'Terminal is ready',
    'terminal_welcome': 'Terminal System Ready. Type "help" for available commands.',
    // File Operations
    'confirm_delete': 'Are you sure you want to delete this file?',
    'confirm_purchase': 'Confirm Purchase',
    'purchase_confirmation': 'Are you sure you want to purchase this file for',
    'download_starting': 'Download starting...',
    'error_occurred': 'An error occurred',
    'file_not_found': 'File not found',
    'insufficient_points': 'Insufficient points',
    'purchase_success': 'Purchase completed successfully',
    'file_uploaded': 'File uploaded successfully',
    'file_updated': 'File updated successfully',
    'file_deleted': 'File deleted successfully',
    
    // Form Validation
    'required_field': 'This field is required',
    'invalid_price': 'Please enter a valid price',
    'file_too_large': 'File is too large',
    'invalid_file_type': 'Invalid file type',
    
    // Status Messages
    'loading': 'Loading...',
    'processing': 'Processing...',
    'success': 'Success',
    'error': 'Error',
    'no_files_found': 'No files found',
    'no_transactions_found': 'No transactions found',
    
    // Buttons & Actions
    'yes': 'Yes',
    'no': 'No',
    'ok': 'OK',
    'cancel': 'Cancel',
    'close': 'Close',
    'save': 'Save',
    'edit': 'Edit',
    'delete': 'Delete',
    'confirm': 'Confirm',
    'loading': 'Loading...',
    'processing': 'Processing...',
    'purchasing': 'Purchasing...',
    'downloading': 'Downloading...',
    'uploading': 'Uploading...',
    
    // File Status
    'pending': 'Pending',
    'approved': 'Approved',
    'rejected': 'Rejected',
    'available': 'Available',
    'sold': 'Sold',
    
    // Terminal Commands
    'cmd_help': 'Available commands: help, clear, list, search, download, upload, exit',
    'cmd_invalid': 'Invalid command. Type "help" for available commands.',
    'cmd_clear': 'Terminal cleared',
    'cmd_exit': 'Exiting terminal...',
    'cmd_list_loading': 'Loading files...',
    'cmd_search_usage': 'Usage: search [query]',
    'cmd_download_usage': 'Usage: download [file_id]',
    'cmd_upload_usage': 'Usage: upload [file_path]',
    'file_not_found_id': 'File with ID not found',
    'file_download_started': 'Download started for file',
    'file_upload_started': 'Upload started for file',
    'operation_completed': 'Operation completed successfully',
    'operation_failed': 'Operation failed',
    'not_authenticated': 'You need to be authenticated to perform this action',
    'insufficient_permissions': 'Insufficient permissions',
    
    // File Operations
    'file_preview_loading': 'Loading preview...',
    'file_preview_error': 'Error loading preview',
    'file_delete_confirm': 'Are you sure you want to delete this file?',
    'file_edit_success': 'File updated successfully',
    'file_edit_error': 'Error updating file',
    'file_upload_success': 'File uploaded successfully',
    'file_upload_error': 'Error uploading file',
    'file_download_success': 'File downloaded successfully',
    'file_download_error': 'Error downloading file',
    
    // User Feedback
    'thanks_for_rating': 'Thank you for your rating!',
    'rating_error': 'Error submitting rating',
    'login_required': 'Please log in to perform this action',
    'action_cancelled': 'Action cancelled',
    'invalid_input': 'Invalid input',
    'try_again': 'Please try again',
    
    // System Messages
    'system_error': 'A system error occurred',
    'connection_error': 'Connection error. Please check your internet connection.',
    'session_expired': 'Your session has expired. Please log in again.',
    'maintenance_mode': 'System is under maintenance. Please try again later.'
};

// Helper function to get translations in JavaScript
function __(key, replacements = {}) {
    let translation = translations[key] || key;
    
    // Replace placeholders if any
    for (const [placeholder, value] of Object.entries(replacements)) {
        translation = translation.replace(`:${placeholder}`, value);
    }
    
    return translation;
}

// Make the translation function available globally
window.__ = __;
