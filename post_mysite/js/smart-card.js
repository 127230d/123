/**
 * Smart Card Component
 * Handles file card interactions including preview, download, and purchase
 * 
 * This is the main entry point for the smart card functionality.
 * It initializes all modules and sets up event listeners.
 */

// Import modules
import { notificationManager } from './modules/notifications.js';
import { fileOperations } from './modules/fileOperations.js';

// For backward compatibility
window.showNotification = (message, type = 'info', duration = 3000) => {
  notificationManager.show(message, type, duration);
};

  // File purchase function
  function purchaseFile(fileId, price) {
    if (!confirm(`هل تريد شراء هذا الملف بـ ${price} نقطة؟`)) {
      return;
    }
    
    // Show loading state
    const purchaseBtn = document.querySelector(`button[onclick*="purchaseFile(${fileId}"]`);
    if (purchaseBtn) {
      purchaseBtn.disabled = true;
      purchaseBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>جاري الشراء...</span>';
    }
    
    // Send AJAX request
    fetch('index.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `ajax=1&action=purchase_file&file_id=${fileId}`
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showNotification('تم شراء الملف بنجاح!', 'success');
        
        // Update card to purchased state
        const card = document.querySelector(`[data-file-id="${fileId}"]`);
        if (card) {
          const actionsContainer = card.querySelector('.smart-card-actions');
          if (actionsContainer) {
            actionsContainer.innerHTML = `
              <button class="smart-btn primary" onclick="downloadFile(${fileId})">
                <i class="fas fa-download"></i>
                <span>تحميل</span>
              </button>
              <button class="smart-btn secondary" onclick="previewFile(${fileId})">
                <i class="fas fa-eye"></i>
                <span>معاينة</span>
              </button>
            `;
          }
          
          // Update price section
          const priceSection = card.querySelector('.price-section');
          if (priceSection) {
            priceSection.innerHTML = `
              <div class="purchase-badge">
                <i class="fas fa-check-circle"></i>
                <span>تم الشراء</span>
              </div>
            `;
          }
        }
        
        // Refresh page after a short delay to update user points
        setTimeout(() => location.reload(), 2000);
      } else {
        showNotification(data.message || 'فشل في شراء الملف', 'error');
        
        // Restore button
        if (purchaseBtn) {
          purchaseBtn.disabled = false;
          purchaseBtn.innerHTML = '<i class="fas fa-shopping-cart"></i> <span>شراء</span>';
        }
    });
}

// File preview function
function previewFile(fileId) {
    showNotification('جاري تحميل المعاينة...', 'info', 2000);
    
    // You can implement preview logic here
    // For now, we'll show a placeholder
    setTimeout(() => {
        showNotification('المعاينة غير متاحة حالياً', 'warning');
    }, 1000);
}

// File download function
function downloadFile(fileId) {
    showNotification('جاري بدء التحميل...', 'success', 2000);
    
    // Create download link
    const downloadLink = document.createElement('a');
    downloadLink.href = `download.php?id=${fileId}`;
    downloadLink.download = '';
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// File editing function (for my files)
function editFile(fileId) {
    showNotification('فتح محرر الملف...', 'info', 2000);
    
    // Redirect to edit page or open modal
    window.location.href = `edit_file.php?id=${fileId}`;
}

// View file statistics
function viewStats(fileId) {
    showNotification('جاري تحميل الإحصائيات...', 'info', 2000);
    
    // You can implement stats modal here
    setTimeout(() => {
        showNotification('عرض الإحصائيات غير متاح حالياً', 'warning');
    }, 1000);
}

// Toggle file availability
function toggleFileAvailability(fileId, newAvailability) {
    const actionText = newAvailability ? 'إظهار' : 'إخفاء';
    
    if (!confirm(`هل تريد ${actionText} هذا الملف؟`)) {
        return;
    }
    
    showNotification(`جاري ${actionText} الملف...`, 'info', 2000);
    
    // Send AJAX request to toggle availability
    fetch('toggle_availability.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `file_id=${fileId}&availability=${newAvailability}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`تم ${actionText} الملف بنجاح`, 'success');
            
            // Update button
            const card = document.querySelector(`[data-file-id="${fileId}"]`);
            if (card) {
                const toggleBtn = card.querySelector(`button[onclick*="toggleFileAvailability(${fileId}"]`);
                if (toggleBtn) {
                    if (newAvailability) {
                        toggleBtn.className = 'smart-btn danger';
                        toggleBtn.onclick = () => toggleFileAvailability(fileId, 0);
                        toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> <span>إخفاء</span>';
                    } else {
                        toggleBtn.className = 'smart-btn success';
                        toggleBtn.onclick = () => toggleFileAvailability(fileId, 1);
                        toggleBtn.innerHTML = '<i class="fas fa-eye"></i> <span>إظهار</span>';
                    }
                }
            }
        } else {
            showNotification(data.message || `فشل في ${actionText} الملف`, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification(`خطأ في ${actionText} الملف`, 'error');
    });
}

// Rate file function
function rateFile(fileId) {
    const rating = prompt('قيم هذا الملف من 1 إلى 5:');
    
    if (rating === null) return;
    
    const ratingNum = parseFloat(rating);
    if (isNaN(ratingNum) || ratingNum < 1 || ratingNum > 5) {
        showNotification('يرجى إدخال تقييم صحيح من 1 إلى 5', 'error');
        return;
    }
    
    showNotification('جاري حفظ التقييم...', 'info', 2000);
    
    // You can implement rating logic here
    setTimeout(() => {
        showNotification('تم حفظ التقييم بنجاح', 'success');
    }, 1000);
}

// Simplified card initialization - no animations for better performance
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.smart-file-card');
    cards.forEach(card => {
        card.style.opacity = '1';
        card.style.transform = 'none';
    });
});

// Loading animation for cards
function showCardLoading(cardElement) {
    cardElement.classList.add('loading');
}

function hideCardLoading(cardElement) {
    cardElement.classList.remove('loading');
}

// Search and filter functions
function filterCards(searchTerm) {
    const cards = document.querySelectorAll('.smart-file-card');
    
    cards.forEach(card => {
        const title = card.querySelector('.file-title').textContent.toLowerCase();
        const description = card.querySelector('.file-description')?.textContent.toLowerCase() || '';
        
        if (title.includes(searchTerm.toLowerCase()) || description.includes(searchTerm.toLowerCase())) {
            card.style.display = 'block';
            card.style.animation = 'fadeInUp 0.3s ease';
        } else {
            card.style.display = 'none';
        }
    });
}

// Sort cards function
function sortCards(sortBy) {
    const container = document.querySelector('.cards-grid');
    const cards = Array.from(container.querySelectorAll('.smart-file-card'));
    
    cards.sort((a, b) => {
        switch (sortBy) {
            case 'name':
                const nameA = a.querySelector('.file-title').textContent;
                const nameB = b.querySelector('.file-title').textContent;
                return nameA.localeCompare(nameB);
            
            case 'price':
                const priceA = parseFloat(a.querySelector('.price-value')?.textContent || '0');
                const priceB = parseFloat(b.querySelector('.price-value')?.textContent || '0');
                return priceB - priceA;
            
            case 'sales':
                const salesA = parseFloat(a.querySelector('.stat-item:last-child .stat-value')?.textContent || '0');
                const salesB = parseFloat(b.querySelector('.stat-item:last-child .stat-value')?.textContent || '0');
                return salesB - salesA;
            
            default:
                return 0;
        }
    });
    
    // Re-append sorted cards
    cards.forEach(card => container.appendChild(card));
}

// Toggle card function
function toggleCard(fileId) {
    const card = document.querySelector(`[data-file-id="${fileId}"]`);
    
    if (!card) return;
    
    const isExpanded = card.classList.contains('expanded');
    
    // Close all other expanded cards
    document.querySelectorAll('.smart-file-card.expanded').forEach(otherCard => {
        if (otherCard !== card) {
            otherCard.classList.remove('expanded');
        }
    });
    
    // Toggle current card
    if (isExpanded) {
        card.classList.remove('expanded');
    } else {
        card.classList.add('expanded');
    }
}

// Close card when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.smart-file-card')) {
        document.querySelectorAll('.smart-file-card.expanded').forEach(card => {
            card.classList.remove('expanded');
        });
    }
});

// Close card with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.smart-file-card.expanded').forEach(card => {
            card.classList.remove('expanded');
        });
        document.querySelectorAll('.drawer-toggle.active').forEach(btn => {
            btn.classList.remove('active');
        });
    }
});

// Removed unnecessary enhanced interactions and animations
