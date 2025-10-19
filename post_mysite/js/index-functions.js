// File: /var/www/html/buttcry/post_mysite/js/index-functions.js
// Specific JavaScript functions for index.php
// These functions complement the main file-system.js and enhanced-ui.js

// IMPORTANT: This file should only contain functions that are NOT already in other JS files
// Check file-system.js and enhanced-ui.js before adding new functions here

// Toggle card expansion/collapse (Details button functionality)
// This function is called from smart_file_card.php onclick handlers
function toggleCard(fileId) {
    console.log('Toggle function called with fileId:', fileId);

    // Find the card by data-file-id attribute
    const card = document.querySelector(`[data-file-id="${fileId}"]`);
    if (card) {
        const drawer = card.querySelector('.smart-card-drawer');
        if (drawer) {
            const isExpanded = drawer.classList.contains('expanded');

            if (isExpanded) {
                // Collapse the drawer
                drawer.classList.remove('expanded');
                card.classList.remove('expanded');
                drawer.style.maxHeight = '0';
                drawer.style.opacity = '0';

                // Update indicator
                const indicator = card.querySelector('.expand-indicator i');
                if (indicator) {
                    indicator.className = 'fas fa-chevron-down';
                }

                showNotification('تم إغلاق تفاصيل الملف', 'info');
            } else {
                // Expand the drawer
                drawer.classList.add('expanded');
                card.classList.add('expanded');
                drawer.style.maxHeight = drawer.scrollHeight + 'px';
                drawer.style.opacity = '1';

                // Update indicator
                const indicator = card.querySelector('.expand-indicator i');
                if (indicator) {
                    indicator.className = 'fas fa-chevron-up';
                }

                showNotification('تم فتح تفاصيل الملف', 'success');

                // Scroll to the expanded card for better UX
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else {
            console.error('Drawer not found for fileId:', fileId);
    } else {
        console.error('Card not found for fileId:', fileId);
    }
}

// Make functions globally accessible
window.toggleCard = toggleCard;
window.previewFile = previewFile;
window.createPreviewModal = createPreviewModal;
window.closePreviewModal = closePreviewModal;
window.showLoadingState = showLoadingState;
window.hideLoadingState = hideLoadingState;
window.testPreview = testPreview;

// Debug function to test if functions are accessible
window.testIndexFunctions = function() {
    console.log('Testing Index JavaScript functions:');
    console.log('toggleCard function:', typeof window.toggleCard);
    console.log('previewFile function:', typeof window.previewFile);
    console.log('previewFileEnhanced function:', typeof window.previewFileEnhanced);
    console.log('Available cards:', document.querySelectorAll('.smart-file-card').length);
    console.log('Available drawers:', document.querySelectorAll('.smart-card-drawer').length);

    // اختبار نظام إدارة النوافذ
    if (window.enhancedUI) {
        console.log('Window manager active:', window.enhancedUI.activeWindows ? 'Yes' : 'No');
        console.log('Active windows count:', window.enhancedUI.activeWindows ? window.enhancedUI.activeWindows.size : 'N/A');
    }

    console.log('Modal windows:', document.querySelectorAll('.modal').length);
    console.log('Open drawers:', document.querySelectorAll('.smart-card-drawer.expanded').length);
};
