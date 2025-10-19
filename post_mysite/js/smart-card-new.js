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
import { modalManager } from './modules/modal.js';

// For backward compatibility
window.showNotification = (message, type = 'info', duration = 3000) => {
  notificationManager.show(message, type, duration);
};

// Initialize when DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
  // Initialize cards
  initializeCards();
  
  // Set up event listeners
  setupEventListeners();
});

/**
 * Initialize card elements
 */
function initializeCards() {
  const cards = document.querySelectorAll('.smart-file-card');
  cards.forEach(card => {
    card.style.opacity = '1';
    card.style.transform = 'none';
  });
}

/**
 * Set up event listeners for card interactions
 */
function setupEventListeners() {
  // Click handler for card actions
  document.addEventListener('click', (e) => {
    const actionBtn = e.target.closest('[data-action]');
    if (!actionBtn) return;
    
    const action = actionBtn.dataset.action;
    const card = actionBtn.closest('.smart-file-card');
    const fileId = card?.dataset.fileId;
    
    if (!fileId) return;
    
    e.preventDefault();
    
    switch (action) {
      case 'preview':
        fileOperations.openPreview(fileId);
        break;
        
      case 'download':
        fileOperations.startDownload(fileId, e);
        break;
        
      case 'purchase':
        const price = actionBtn.dataset.price || '0';
        fileOperations.purchaseFile(fileId, price, e);
        break;
        
      case 'edit':
        window.location.href = `edit_file.php?id=${encodeURIComponent(fileId)}`;
        break;
        
      case 'toggle-availability':
        const newAvailability = actionBtn.dataset.available === '1' ? '0' : '1';
        toggleFileAvailability(fileId, newAvailability, actionBtn);
        break;
        
      case 'rate':
        rateFile(fileId);
        break;
        
      case 'view-stats':
        viewStats(fileId);
        break;
    }
  });
  
  // Close card when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.smart-file-card')) {
      document.querySelectorAll('.smart-file-card.expanded').forEach(card => {
        card.classList.remove('expanded');
      });
    }
  });
  
  // Close card with Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.smart-file-card.expanded').forEach(card => {
        card.classList.remove('expanded');
      });
      document.querySelectorAll('.drawer-toggle.active').forEach(btn => {
        btn.classList.remove('active');
      });
    }
  });
}

/**
 * Toggle file availability
 * @param {string} fileId - ID of the file
 * @param {string} newAvailability - New availability status ('0' or '1')
 * @param {HTMLElement} button - The button that was clicked
 */
async function toggleFileAvailability(fileId, newAvailability, button) {
  const actionText = newAvailability === '1' ? 'إظهار' : 'إخفاء';
  
  if (!confirm(`هل تريد ${actionText} هذا الملف؟`)) {
    return;
  }
  
  const originalButtonHtml = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  
  try {
    const response = await fetch('toggle_availability.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': fileOperations.getCSRFToken(),
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: `file_id=${fileId}&availability=${newAvailability}`
    });
    
    const data = await response.json();
    
    if (data.success) {
      notificationManager.show(`تم ${actionText} الملف بنجاح`, 'success');
      
      // Update button state
      if (newAvailability === '1') {
        button.className = 'smart-btn danger';
        button.dataset.available = '1';
        button.innerHTML = '<i class="fas fa-eye-slash"></i> <span>إخفاء</span>';
      } else {
        button.className = 'smart-btn success';
        button.dataset.available = '0';
        button.innerHTML = '<i class="fas fa-eye"></i> <span>إظهار</span>';
      }
    } else {
      throw new Error(data.message || `فشل في ${actionText} الملف`);
    }
  } catch (error) {
    console.error('Error:', error);
    notificationManager.show(
      error.message || `خطأ في ${actionText} الملف`,
      'error'
    );
  } finally {
    button.disabled = false;
  }
}

/**
 * Rate a file
 * @param {string} fileId - ID of the file to rate
 */
async function rateFile(fileId) {
  const rating = prompt('قيم هذا الملف من 1 إلى 5:');
  
  if (rating === null) return;
  
  const ratingNum = parseFloat(rating);
  if (isNaN(ratingNum) || ratingNum < 1 || ratingNum > 5) {
    notificationManager.show('يرجى إدخال تقييم صحيح من 1 إلى 5', 'error');
    return;
  }
  
  try {
    notificationManager.show('جاري حفظ التقييم...', 'info');
    
    const response = await fetch('rate_file.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-TOKEN': fileOperations.getCSRFToken(),
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: `file_id=${fileId}&rating=${ratingNum}`
    });
    
    const data = await response.json();
    
    if (data.success) {
      notificationManager.show('تم حفظ التقييم بنجاح', 'success');
      
      // Update UI if needed
      const ratingElement = document.querySelector(`[data-file-id="${fileId}"] .file-rating`);
      if (ratingElement) {
        ratingElement.textContent = data.average_rating || ratingNum;
      }
    } else {
      throw new Error(data.message || 'فشل في حفظ التقييم');
    }
  } catch (error) {
    console.error('Error:', error);
    notificationManager.show(
      error.message || 'حدث خطأ أثناء حفظ التقييم',
      'error'
    );
  }
}

/**
 * View file statistics
 * @param {string} fileId - ID of the file
 */
async function viewStats(fileId) {
  try {
    notificationManager.show('جاري تحميل الإحصائيات...', 'info');
    
    const response = await fetch(`get_file_stats.php?id=${encodeURIComponent(fileId)}`, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    
    const data = await response.json();
    
    if (data.success) {
      // Create stats modal content
      const statsHtml = `
        <div class="stats-container">
          <h3>إحصائيات الملف</h3>
          <div class="stats-grid">
            <div class="stat-item">
              <i class="fas fa-eye"></i>
              <span class="stat-value">${data.views || 0}</span>
              <span class="stat-label">مشاهدة</span>
            </div>
            <div class="stat-item">
              <i class="fas fa-download"></i>
              <span class="stat-value">${data.downloads || 0}</span>
              <span class="stat-label">تحميل</span>
            </div>
            <div class="stat-item">
              <i class="fas fa-star"></i>
              <span class="stat-value">${data.average_rating || 'N/A'}</span>
              <span class="stat-label">تقييم</span>
            </div>
          </div>
        </div>
      `;
      
      // Show stats in modal
      modalManager.create('file-stats', {
        title: 'إحصائيات الملف',
        content: statsHtml,
        closeOnBackdropClick: true
      });
      
      modalManager.open('file-stats');
    } else {
      throw new Error(data.message || 'فشل في تحميل الإحصائيات');
    }
  } catch (error) {
    console.error('Error:', error);
    notificationManager.show(
      error.message || 'حدث خطأ أثناء تحميل الإحصائيات',
      'error'
    );
  }
}

// Export for debugging
if (process.env.NODE_ENV === 'development') {
  window.fileOperations = fileOperations;
  window.modalManager = modalManager;
  window.notificationManager = notificationManager;
}
