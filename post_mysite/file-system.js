/**
 * File Exchange System - Advanced JavaScript Functions
 * Comprehensive file management and trading system
 */

// Global variables
let currentUser = '';
let userPoints = 0;
let loadingStates = new Map();

// Initialize system
document.addEventListener('DOMContentLoaded', function() {
    initializeSystem();
    initializeEventListeners();
    initializeAnimations();
    loadUserData();
});

/**
 * System Initialization
 */
function initializeSystem() {
    // Initialize matrix background
    initMatrixBackground();
    
    // Initialize tooltips and modals
    initializeTooltips();
    
    // Initialize real-time updates
    startRealTimeUpdates();
}

/**
 * Event Listeners Setup
 */
function initializeEventListeners() {
    // Tab switching with history
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('click', function() {
            const target = this.getAttribute('data-bs-target').replace('#', '');
            updateURL(target);
            loadTabContent(target);
        });
    });

    // File upload with progress
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', handleFileUpload);
    }

    // Search and filter with debounce
    const searchInput = document.getElementById('searchFiles');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(performSearch, 300));
    }

    // Drag and drop for file upload
    initializeDragAndDrop();

    // Keyboard shortcuts
    initializeKeyboardShortcuts();
}

/**
 * Advanced File Upload with Progress
 */
function handleFileUpload(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('ajax', '1');
    
    const submitButton = event.target.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    // Show upload progress
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الرفع...';
    submitButton.disabled = true;
    
    // Create progress bar
    const progressContainer = createProgressBar();
    event.target.appendChild(progressContainer);
    
    // Upload with XMLHttpRequest for progress tracking
    const xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            updateProgressBar(percentComplete);
        }
    });
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            removeProgressBar();
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
            
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        showNotification(response.message, 'success');
                        event.target.reset();
                        document.getElementById('selectedFileDisplay').style.display = 'none';
                        
                        // Refresh my files tab
                        setTimeout(() => {
                            showTab('my-files');
                            loadTabContent('my-files');
                        }, 1500);
                    } else {
                        showNotification(response.message, 'error');
                    }
                } catch (e) {
                    showNotification('حدث خطأ في معالجة الاستجابة', 'error');
                }
            } else {
                showNotification('حدث خطأ في الاتصال بالخادم', 'error');
            }
        }
    };
    
    xhr.open('POST', 'upload_file.php');
    xhr.send(formData);
}

/**
 * Progress Bar Functions
 */
function createProgressBar() {
    const container = document.createElement('div');
    container.className = 'upload-progress-container mt-3';
    container.innerHTML = `
        <div class="progress-bar-bg">
            <div class="progress-bar-fill" id="progressBar"></div>
            <div class="progress-text" id="progressText">0%</div>
        </div>
    `;
    return container;
}

function updateProgressBar(percent) {
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    
    if (progressBar && progressText) {
        progressBar.style.width = percent + '%';
        progressText.textContent = Math.round(percent) + '%';
    }
}

function removeProgressBar() {
    const progressContainer = document.querySelector('.upload-progress-container');
    if (progressContainer) {
        progressContainer.remove();
    }
}

/**
 * Advanced File Purchase with Confirmation Modal
 */
function purchaseFile(fileId, price, filename = '') {
    // Create advanced confirmation modal
    const modal = createPurchaseModal(fileId, price, filename);
    document.body.appendChild(modal);
    
    // Show modal with animation
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function createPurchaseModal(fileId, price, filename) {
    const modal = document.createElement('div');
    modal.className = 'purchase-modal';
    modal.innerHTML = `
        <div class="modal-backdrop" onclick="closePurchaseModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-shopping-cart"></i> تأكيد عملية الشراء</h3>
                <button onclick="closePurchaseModal()" class="close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="purchase-details">
                    <div class="file-preview">
                        <i class="fas fa-file-alt"></i>
                        <div>
                            <h4>${filename || 'ملف مختار'}</h4>
                            <p>هل أنت متأكد من شراء هذا الملف؟</p>
                        </div>
                    </div>
                    <div class="price-breakdown">
                        <div class="price-item">
                            <span>سعر الملف:</span>
                            <span class="price-value">${price.toLocaleString()} نقطة</span>
                        </div>
                        <div class="price-item">
                            <span>رصيدك الحالي:</span>
                            <span class="balance-value">${userPoints.toLocaleString()} نقطة</span>
                        </div>
                        <div class="price-item total">
                            <span>الرصيد بعد الشراء:</span>
                            <span class="remaining-value">${(userPoints - price).toLocaleString()} نقطة</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closePurchaseModal()" class="action-btn">إلغاء</button>
                <button onclick="confirmPurchase(${fileId})" class="action-btn primary">
                    <i class="fas fa-check"></i>
                    تأكيد الشراء
                </button>
            </div>
        </div>
    `;
    return modal;
}

function confirmPurchase(fileId) {
    closePurchaseModal();
    
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'purchase_file');
    formData.append('file_id', fileId);

    showLoadingState('جاري معالجة عملية الشراء...');

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingState();
        
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Update UI immediately
            updateUserPoints();
            removeFileFromBrowse(fileId);
            
            // Refresh tabs
            setTimeout(() => {
                loadTabContent('purchases');
                loadTabContent('transactions');
            }, 1000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoadingState();
        showNotification('حدث خطأ أثناء الشراء', 'error');
        console.error('Purchase error:', error);
    });
}

function closePurchaseModal() {
    const modal = document.querySelector('.purchase-modal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    }
}

/**
 * Advanced Search and Filtering
 */
function performSearch() {
    const searchTerm = document.getElementById('searchFiles')?.value.toLowerCase() || '';
    const sortBy = document.getElementById('sortFiles')?.value || 'newest';
    const filterType = document.getElementById('filterType')?.value || '';
    
    const fileCards = Array.from(document.querySelectorAll('#availableFilesGrid .file-card'));
    let visibleCount = 0;
    
    // Advanced filtering
    fileCards.forEach(card => {
        const title = card.querySelector('.file-title')?.textContent.toLowerCase() || '';
        const description = card.querySelector('.file-description')?.textContent.toLowerCase() || '';
        const owner = card.querySelector('.file-meta-value')?.textContent.toLowerCase() || '';
        const fileType = card.dataset.fileType?.toLowerCase() || '';
        
        const matchesSearch = !searchTerm || 
            title.includes(searchTerm) || 
            description.includes(searchTerm) || 
            owner.includes(searchTerm);
        
        const matchesType = !filterType || fileType === filterType;
        
        if (matchesSearch && matchesType) {
            card.style.display = 'block';
            card.style.animationDelay = (visibleCount * 0.05) + 's';
            card.classList.add('fade-in-search');
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update results count
    updateSearchResults(visibleCount);
    
    // Apply sorting
    applySorting(sortBy);
}

function applySorting(sortBy) {
    const grid = document.getElementById('availableFilesGrid');
    const cards = Array.from(grid.querySelectorAll('.file-card:not([style*="display: none"])'));
    
    cards.sort((a, b) => {
        switch(sortBy) {
            case 'oldest':
                return new Date(a.dataset.date) - new Date(b.dataset.date);
            case 'price-low':
                return parseInt(a.dataset.price) - parseInt(b.dataset.price);
            case 'price-high':
                return parseInt(b.dataset.price) - parseInt(a.dataset.price);
            case 'popular':
                return parseInt(b.dataset.sales) - parseInt(a.dataset.sales);
            case 'alphabetical':
                return a.querySelector('.file-title').textContent.localeCompare(
                    b.querySelector('.file-title').textContent
                );
            default: // newest
                return new Date(b.dataset.date) - new Date(a.dataset.date);
        }
    });
    
    // Re-append sorted cards with staggered animation
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.05) + 's';
        grid.appendChild(card);
    });
}

function updateSearchResults(count) {
    let resultInfo = document.getElementById('searchResultInfo');
    if (!resultInfo) {
        resultInfo = document.createElement('div');
        resultInfo.id = 'searchResultInfo';
        resultInfo.className = 'search-result-info';
        document.querySelector('.filter-section').appendChild(resultInfo);
    }
    
    if (count === 0) {
        resultInfo.innerHTML = `
            <div class="no-results">
                <i class="fas fa-search-minus"></i>
                <span>لا توجد نتائج مطابقة للبحث</span>
            </div>
        `;
    } else {
        resultInfo.innerHTML = `
            <div class="results-count">
                <i class="fas fa-check-circle"></i>
                <span>تم العثور على ${count} ملف</span>
            </div>
        `;
    }
}

/**
 * File Management Functions
 */
function editFile(fileId) {
    const modal = createEditFileModal(fileId);
    document.body.appendChild(modal);
    
    // Load current file data
    loadFileDataForEdit(fileId);
    
    setTimeout(() => modal.classList.add('show'), 10);
}

function createEditFileModal(fileId) {
    const modal = document.createElement('div');
    modal.className = 'edit-modal';
    modal.innerHTML = `
        <div class="modal-backdrop" onclick="closeEditModal()"></div>
        <div class="modal-content large">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> تعديل معلومات الملف</h3>
                <button onclick="closeEditModal()" class="close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="editFileForm" data-file-id="${fileId}">
                    <div class="form-group">
                        <label class="form-label">اسم الملف</label>
                        <input type="text" class="form-control" name="filename" id="editFilename" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">وصف الملف</label>
                        <textarea class="form-control" name="description" id="editDescription" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">السعر (بالنقاط)</label>
                        <div class="price-input-group">
                            <input type="number" class="form-control" name="price" id="editPrice" min="1" max="10000" required>
                            <div class="price-currency">نقطة</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button onclick="closeEditModal()" class="action-btn">إلغاء</button>
                <button onclick="saveFileEdit()" class="action-btn primary">
                    <i class="fas fa-save"></i>
                    حفظ التعديلات
                </button>
            </div>
        </div>
    `;
    return modal;
}

function loadFileDataForEdit(fileId) {
    showLoadingState('جاري تحميل بيانات الملف...');
    
    fetch(`get_file_details.php?file_id=${fileId}`)
        .then(response => response.json())
        .then(data => {
            hideLoadingState();
            
            if (data.success) {
                document.getElementById('editFilename').value = data.file.filename;
                document.getElementById('editDescription').value = data.file.description;
                document.getElementById('editPrice').value = data.file.price;
            } else {
                showNotification('فشل في تحميل بيانات الملف', 'error');
                closeEditModal();
            }
        })
        .catch(error => {
            hideLoadingState();
            showNotification('حدث خطأ أثناء تحميل البيانات', 'error');
            console.error('Load file data error:', error);
        });
}

function saveFileEdit() {
    const form = document.getElementById('editFileForm');
    const fileId = form.dataset.fileId;
    const formData = new FormData(form);
    formData.append('ajax', '1');
    formData.append('action', 'edit_file');
    formData.append('file_id', fileId);
    
    showLoadingState('جاري حفظ التعديلات...');
    
    fetch('manage_file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingState();
        
        if (data.success) {
            showNotification(data.message, 'success');
            closeEditModal();
            loadTabContent('my-files');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoadingState();
        showNotification('حدث خطأ أثناء حفظ التعديلات', 'error');
        console.error('Save edit error:', error);
    });
}

function closeEditModal() {
    const modal = document.querySelector('.edit-modal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    }
}

/**
 * File Preview Function - Updated to match PHP calls and use enhanced preview system
 */
function previewFile(fileId, previewType = 'text', previewText = '', previewImage = '') {
    showLoadingState('جاري تحميل معاينة الملف...');

    // Use the enhanced preview system if available
    if (typeof window.previewFile === 'function' && window.previewFile !== previewFile) {
        window.previewFile(fileId);
        return;
    }

    // Use the new preview handler for enhanced previews
    fetch(`preview_handler.php?action=preview&file_id=${fileId}`)
        .then(response => response.json())
        .then(data => {
            hideLoadingState();

            if (data.success) {
                createPreviewModal(data.file, data.preview);
            } else {
                // Fallback to old preview system if enhanced fails
                fallbackPreview(fileId, previewType, previewText, previewImage);
            }
        })
        .catch(error => {
            hideLoadingState();
            // Fallback to old preview system if enhanced fails
            fallbackPreview(fileId, previewType, previewText, previewImage);
            console.error('Enhanced preview error:', error);
        });
}

/**
 * Fallback preview function for compatibility
 */
function fallbackPreview(fileId, previewType, previewText, previewImage) {
    showLoadingState('جاري تحميل معاينة الملف...');

    fetch(`preview_file.php?ajax=1&file_id=${fileId}`)
        .then(response => response.json())
        .then(data => {
            hideLoadingState();

            if (data.success) {
                createPreviewModal(data.file, data.preview);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            hideLoadingState();
            showNotification('حدث خطأ أثناء تحميل المعاينة', 'error');
            console.error('Preview error:', error);
        });
}

function createPreviewModal(file, preview) {
    const modal = document.createElement('div');
    modal.className = 'preview-modal';
    modal.innerHTML = `
        <div class="modal-backdrop" onclick="closePreviewModal()"></div>
        <div class="modal-content preview">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> معاينة الملف: ${file.filename}</h3>
                <button onclick="closePreviewModal()" class="close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="file-info-preview">
                    <div class="info-item">
                        <span>الحجم:</span>
                        <span>${formatFileSize(file.file_size)}</span>
                    </div>
                    <div class="info-item">
                        <span>النوع:</span>
                        <span>${file.file_type}</span>
                    </div>
                    <div class="info-item">
                        <span>الناشر:</span>
                        <span>${file.owner_name}</span>
                    </div>
                </div>
                <div class="preview-content">
                    ${preview}
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closePreviewModal()" class="action-btn">إغلاق</button>
                <button onclick="purchaseFile(${file.id}, ${file.price}, '${file.filename}')" class="action-btn primary">
                    <i class="fas fa-shopping-cart"></i>
                    شراء بـ ${file.price} نقطة
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('show'), 10);
}

function closePreviewModal() {
    const modal = document.querySelector('.preview-modal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    }
}

/**
 * Sales Statistics Modal
 */
function viewSalesStats(fileId) {
    showLoadingState('جاري تحميل الإحصائيات...');
    
    fetch(`get_sales_stats.php?file_id=${fileId}`)
        .then(response => response.json())
        .then(data => {
            hideLoadingState();
            
            if (data.success) {
                createStatsModal(data.stats, data.file);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            hideLoadingState();
            showNotification('حدث خطأ أثناء تحميل الإحصائيات', 'error');
            console.error('Stats error:', error);
        });
}

function createStatsModal(stats, file) {
    const modal = document.createElement('div');
    modal.className = 'stats-modal';
    modal.innerHTML = `
        <div class="modal-backdrop" onclick="closeStatsModal()"></div>
        <div class="modal-content stats">
            <div class="modal-header">
                <h3><i class="fas fa-chart-bar"></i> إحصائيات المبيعات: ${file.filename}</h3>
                <button onclick="closeStatsModal()" class="close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                        <div class="stat-details">
                            <div class="stat-value">${stats.total_sales}</div>
                            <div class="stat-label">إجمالي المبيعات</div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-coins"></i></div>
                        <div class="stat-details">
                            <div class="stat-value">${stats.total_revenue.toLocaleString()}</div>
                            <div class="stat-label">إجمالي الإيرادات</div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-eye"></i></div>
                        <div class="stat-details">
                            <div class="stat-value">${stats.total_views}</div>
                            <div class="stat-label">عدد المشاهدات</div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-percentage"></i></div>
                        <div class="stat-details">
                            <div class="stat-value">${stats.conversion_rate}%</div>
                            <div class="stat-label">معدل التحويل</div>
                        </div>
                    </div>
                </div>
                
                ${stats.recent_buyers && stats.recent_buyers.length > 0 ? `
                <div class="recent-buyers">
                    <h4>المشترون الحديثون</h4>
                    <div class="buyers-list">
                        ${stats.recent_buyers.map(buyer => `
                            <div class="buyer-item">
                                <div class="user-avatar">${buyer.username.substring(0, 2).toUpperCase()}</div>
                                <div class="buyer-details">
                                    <div class="buyer-name">${buyer.username}</div>
                                    <div class="buyer-date">${timeAgoJS(buyer.purchase_date)}</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
            </div>
            <div class="modal-footer">
                <button onclick="closeStatsModal()" class="action-btn primary">إغلاق</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('show'), 10);
}

function closeStatsModal() {
    const modal = document.querySelector('.stats-modal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    }
}

/**
 * Real-time Updates
 */
function startRealTimeUpdates() {
    // Update user points every 30 seconds
    setInterval(updateUserPoints, 30000);
    
    // Update file availability every 60 seconds
    setInterval(updateFileAvailability, 60000);
    
    // Update notifications every 45 seconds
    setInterval(checkNewNotifications, 45000);
}

function updateUserPoints() {
    fetch('get_user_data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                userPoints = data.points;
                document.querySelector('.points-count').textContent = data.points.toLocaleString();
                
                // Update dashboard stats
                document.querySelector('.stat-card .stat-value').textContent = data.points.toLocaleString();
            }
        })
        .catch(error => console.error('Update points error:', error));
}

function updateFileAvailability() {
    const currentTab = document.querySelector('.tab-pane.active').id;
    if (currentTab === 'browse') {
        loadTabContent('browse');
    }
}

function checkNewNotifications() {
    fetch('get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notifications.length > 0) {
                data.notifications.forEach(notification => {
                    showNotification(notification.message, notification.type, 4000);
                });
            }
        })
        .catch(error => console.error('Notifications error:', error));
}

/**
 * Loading States Management
 */
function showLoadingState(message = 'جاري التحميل...') {
    let loader = document.getElementById('globalLoader');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'globalLoader';
        loader.className = 'global-loader';
        document.body.appendChild(loader);
    }
    
    loader.innerHTML = `
        <div class="loader-backdrop"></div>
        <div class="loader-content">
            <div class="spinner"></div>
            <div class="loader-text">${message}</div>
        </div>
    `;
    
    setTimeout(() => loader.classList.add('show'), 10);
}

function hideLoadingState() {
    const loader = document.getElementById('globalLoader');
    if (loader) {
        loader.classList.remove('show');
        setTimeout(() => loader.remove(), 300);
    }
}

/**
 * Tab Content Dynamic Loading
 */
function loadTabContent(tabName) {
    const tabPane = document.getElementById(tabName);
    if (!tabPane) return;
    
    // Add loading state to tab
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'tab-loading';
    loadingDiv.innerHTML = '<div class="spinner"></div><span>جاري تحديث المحتوى...</span>';
    
    switch(tabName) {
        case 'browse':
            refreshBrowseTab();
            break;
        case 'my-files':
            refreshMyFilesTab();
            break;
        case 'purchases':
            refreshPurchasesTab();
            break;
        case 'transactions':
            refreshTransactionsTab();
            break;
    }
}

function refreshBrowseTab() {
    fetch('get_available_files.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateBrowseGrid(data.files);
            }
        })
        .catch(error => console.error('Refresh browse error:', error));
}

function updateBrowseGrid(files) {
    const grid = document.getElementById('availableFilesGrid');
    if (!grid) return;
    
    // Animate out existing cards
    const existingCards = grid.querySelectorAll('.file-card');
    existingCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(-20px)';
        }, index * 50);
    });
    
    // Clear and rebuild after animation
    setTimeout(() => {
        grid.innerHTML = '';
        
        files.forEach((file, index) => {
            const card = createFileCard(file);
            card.style.animationDelay = (index * 0.1) + 's';
            grid.appendChild(card);
        });
    }, existingCards.length * 50 + 200);
}

function createFileCard(file) {
    const card = document.createElement('div');
    card.className = 'file-card glow-on-hover';
    card.dataset.fileType = file.extension;
    card.dataset.price = file.price;
    card.dataset.date = file.created_at;
    card.dataset.sales = file.sales_count;
    
    card.innerHTML = `
        <div class="file-header">
            <div class="file-icon-large">
                <i class="${getFileIconJS(file.filename)}"></i>
            </div>
            <h3 class="file-title">${file.filename}</h3>
        </div>
        
        <div class="file-meta">
            <div class="file-meta-item">
                <span>الناشر:</span>
                <span class="file-meta-value">${file.owner_name}</span>
            </div>
            <div class="file-meta-item">
                <span>الحجم:</span>
                <span class="file-meta-value">${formatFileSize(file.file_size)}</span>
            </div>
            <div class="file-meta-item">
                <span>تاريخ النشر:</span>
                <span class="file-meta-value">${timeAgoJS(file.created_at)}</span>
            </div>
            <div class="file-meta-item">
                <span>عدد المبيعات:</span>
                <span class="file-meta-value">${file.sales_count}</span>
            </div>
        </div>
        
        ${file.description ? `
        <div class="file-description">
            ${file.description}
        </div>
        ` : ''}
        
        <div class="file-actions">
            <button class="action-btn primary" onclick="purchaseFile(${file.id}, ${file.price}, '${file.filename}')">
                <i class="fas fa-shopping-cart"></i>
                شراء بـ ${file.price.toLocaleString()} نقطة
            </button>
            <button class="action-btn" onclick="previewFile(${file.id})">
                <i class="fas fa-eye"></i>
                معاينة
            </button>
        </div>
    `;
    
    return card;
}

/**
 * Utility Functions
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function updateURL(tab) {
    const newURL = new URL(window.location);
    newURL.searchParams.set('tab', tab);
    window.history.pushState({}, '', newURL);
}

function timeAgoJS(datetime) {
    const time = new Date().getTime() - new Date(datetime).getTime();
    const seconds = Math.floor(time / 1000);
    
    if (seconds < 60) return 'الآن';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' دقائق';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' ساعات';
    if (seconds < 2592000) return Math.floor(seconds / 86400) + ' أيام';
    if (seconds < 31536000) return Math.floor(seconds / 2592000) + ' أشهر';
    return Math.floor(seconds / 31536000) + ' سنوات';
}

function removeFileFromBrowse(fileId) {
    const fileCard = document.querySelector(`[data-file-id="${fileId}"]`);
    if (fileCard) {
        fileCard.style.transition = 'all 0.3s ease';
        fileCard.style.opacity = '0';
        fileCard.style.transform = 'translateY(-20px)';
        setTimeout(() => fileCard.remove(), 300);
    }
}

/**
 * Drag and Drop Initialization
 */
function initializeDragAndDrop() {
    const dropZone = document.querySelector('.file-drop-zone');
    if (!dropZone) return;

    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('drag-over');
    });

    dropZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        if (!this.contains(e.relatedTarget)) {
            this.classList.remove('drag-over');
        }
    });

    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const fileInput = document.getElementById('fileUpload');
            fileInput.files = files;
            displaySelectedFile(fileInput);
            
            showNotification(`تم اختيار الملف: ${files[0].name}`, 'success', 2000);
        }
    });
}

/**
 * Keyboard Shortcuts
 */
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + number keys for tab switching
        if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey) {
            const num = parseInt(e.key);
            if (num >= 1 && num <= 5) {
                e.preventDefault();
                const tabs = ['browse', 'upload', 'my-files', 'purchases', 'transactions'];
                if (tabs[num - 1]) {
                    showTab(tabs[num - 1]);
                }
            }
        }
        
        // ESC to close modals
        if (e.key === 'Escape') {
            closeAllModals();
        }
        
        // Ctrl/Cmd + F for search focus
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.getElementById('searchFiles');
            if (searchInput && searchInput.offsetParent !== null) {
                searchInput.focus();
            }
        }
    });
}

function closeAllModals() {
    document.querySelectorAll('.purchase-modal, .edit-modal, .preview-modal, .stats-modal').forEach(modal => {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    });
}

/**
 * Tooltips Initialization
 */
function initializeTooltips() {
    // Add tooltips to action buttons
    document.querySelectorAll('.action-btn, .stat-card').forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const element = e.target;
    const tooltipText = element.getAttribute('data-tooltip');
    if (!tooltipText) return;
    
    const tooltip = document.createElement('div');
    tooltip.className = 'custom-tooltip';
    tooltip.textContent = tooltipText;
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
    
    setTimeout(() => tooltip.classList.add('show'), 10);
}

function hideTooltip(e) {
    const tooltip = document.querySelector('.custom-tooltip');
    if (tooltip) {
        tooltip.classList.remove('show');
        setTimeout(() => tooltip.remove(), 200);
    }
}

/**
 * Animations Initialization
 */
function initializeAnimations() {
    // Intersection Observer for scroll animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, { threshold: 0.1 });
    
    // Observe all animatable elements
    document.querySelectorAll('.file-card, .stat-card, .upload-form').forEach(el => {
        observer.observe(el);
    });
}

/**
 * Load User Data
 */
function loadUserData() {
    fetch('get_user_data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentUser = data.username;
                userPoints = data.points;
                
                // Update UI elements
                updateDashboardStats(data);
            }
        })
        .catch(error => console.error('Load user data error:', error));
}

function updateDashboardStats(userData) {
    // Update stats cards with animation
    const statsCards = document.querySelectorAll('.stat-card .stat-value');
    statsCards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('success-indicator');
            setTimeout(() => card.classList.remove('success-indicator'), 1000);
        }, index * 200);
    });
}

// Export functions for global use
window.FileExchangeSystem = {
    showNotification,
    purchaseFile,
    editFile,
    deleteFile,
    previewFile,
    viewSalesStats,
    showTab,
    loadTabContent
};
