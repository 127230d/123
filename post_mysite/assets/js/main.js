class FileMarketplace {
    constructor() {
        this.currentPage = 1;
        this.filters = {};
        this.init();
    }

    init() {
        this.loadFiles();
        this.setupEventListeners();
    }

    setupEventListeners() {
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('purchase-btn')) {
                const fileId = e.target.dataset.fileId;
                this.purchaseFile(fileId);
            }

            if (e.target.classList.contains('modal-close') || e.target.classList.contains('modal')) {
                this.closeModal();
            }
        });

        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.filters.search = e.target.value;
                    this.currentPage = 1;
                    this.loadFiles();
                }, 500);
            });
        }

        const sortSelect = document.getElementById('sort-select');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                this.filters.sort = e.target.value;
                this.currentPage = 1;
                this.loadFiles();
            });
        }
    }

    async loadFiles() {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                limit: 20,
                ...this.filters
            });

            const response = await fetch(`/api/files-list.php?${params}`);
            const result = await response.json();

            if (result.success) {
                this.renderFiles(result.data.files);
                this.renderPagination(result.data.pagination);
            } else {
                this.showNotification(result.error, 'error');
            }
        } catch (error) {
            this.showNotification('حدث خطأ في تحميل الملفات', 'error');
            console.error(error);
        }
    }

    renderFiles(files) {
        const container = document.getElementById('files-container');
        if (!container) return;

        if (files.length === 0) {
            container.innerHTML = '<div class="no-files">لا توجد ملفات متاحة</div>';
            return;
        }

        container.innerHTML = files.map(file => this.createFileCard(file)).join('');
    }

    createFileCard(file) {
        const previewHtml = file.preview_type === 'image' && file.preview_image
            ? `<img src="/storage/previews/${file.preview_image}" alt="Preview" class="file-preview-image">`
            : `<div class="file-preview-text">${this.escapeHtml(file.preview_text || 'لا توجد معاينة').substring(0, 150)}...</div>`;

        const starsHtml = this.renderStars(file.average_rating);

        let actionButton = '';
        if (file.is_owner) {
            actionButton = '<button class="btn-secondary" disabled>ملفك</button>';
        } else if (file.user_purchased) {
            actionButton = `<a href="/api/download.php?file_id=${file.file_id}" class="btn-success">تحميل</a>`;
        } else {
            actionButton = `<button class="btn-primary purchase-btn" data-file-id="${file.file_id}">شراء - ${file.final_price.toFixed(2)} نقطة</button>`;
        }

        return `
            <div class="file-card" data-file-id="${file.file_id}">
                <div class="file-card-header">
                    ${previewHtml}
                </div>
                <div class="file-card-body">
                    <h3 class="file-title">${this.escapeHtml(file.title)}</h3>
                    <p class="file-description">${this.escapeHtml(file.description || '').substring(0, 100)}</p>

                    <div class="file-meta">
                        <span class="file-type-badge">${this.escapeHtml(file.file_type)}</span>
                        <span class="file-size">${this.formatFileSize(file.file_size)}</span>
                    </div>

                    <div class="file-rating">
                        ${starsHtml}
                        <span class="rating-text">(${file.total_ratings})</span>
                    </div>

                    <div class="file-stats">
                        <span>🛒 ${file.total_sales} مبيعات</span>
                    </div>

                    <div class="file-owner">
                        <small>البائع: ${this.escapeHtml(file.owner_name || file.owner_id)}</small>
                    </div>
                </div>
                <div class="file-card-footer">
                    <div class="file-price">${file.final_price.toFixed(2)} نقطة</div>
                    <div class="file-actions">
                        <button class="btn-details" onclick="marketplace.showFileDetails(${file.file_id})">التفاصيل</button>
                        ${actionButton}
                    </div>
                </div>
            </div>
        `;
    }

    renderStars(rating) {
        const fullStars = Math.floor(rating);
        const hasHalfStar = (rating - fullStars) >= 0.5;
        const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);

        let html = '<div class="stars">';

        for (let i = 0; i < fullStars; i++) {
            html += '<span class="star star-full">★</span>';
        }

        if (hasHalfStar) {
            html += '<span class="star star-half">★</span>';
        }

        for (let i = 0; i < emptyStars; i++) {
            html += '<span class="star star-empty">☆</span>';
        }

        html += '</div>';
        return html;
    }

    renderPagination(pagination) {
        const container = document.getElementById('pagination');
        if (!container) return;

        let html = '<div class="pagination-controls">';

        if (pagination.has_prev) {
            html += `<button class="btn-primary" onclick="marketplace.goToPage(${pagination.current_page - 1})">السابق</button>`;
        }

        html += `<span class="page-info">صفحة ${pagination.current_page} من ${pagination.total_pages}</span>`;

        if (pagination.has_next) {
            html += `<button class="btn-primary" onclick="marketplace.goToPage(${pagination.current_page + 1})">التالي</button>`;
        }

        html += '</div>';
        container.innerHTML = html;
    }

    goToPage(page) {
        this.currentPage = page;
        this.loadFiles();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async showFileDetails(fileId) {
        try {
            const response = await fetch(`/api/file-details.php?file_id=${fileId}`);
            const result = await response.json();

            if (result.success) {
                this.renderFileDetailsModal(result.data);
            } else {
                this.showNotification(result.error, 'error');
            }
        } catch (error) {
            this.showNotification('حدث خطأ في تحميل التفاصيل', 'error');
            console.error(error);
        }
    }

    renderFileDetailsModal(data) {
        const file = data.file;
        const modal = document.getElementById('file-details-modal') || this.createModal('file-details-modal');

        const starsHtml = this.renderStars(file.average_rating);
        const ratingsBreakdown = this.renderRatingsBreakdown(data.ratings_breakdown);

        let purchaseSection = '';
        if (data.is_owner) {
            purchaseSection = '<div class="alert alert-info">هذا ملفك</div>';
        } else if (data.has_purchased) {
            purchaseSection = `
                <a href="/api/download.php?file_id=${file.file_id}" class="btn-success btn-large">تحميل الملف</a>
            `;
        } else {
            purchaseSection = `
                <button class="btn-primary btn-large purchase-btn" data-file-id="${file.file_id}">
                    شراء الآن - ${file.final_price.toFixed(2)} نقطة
                </button>
            `;
        }

        const previewHtml = file.preview_type === 'image' && file.preview_image
            ? `<img src="/storage/previews/${file.preview_image}" alt="Preview" style="max-width: 100%; border-radius: 8px;">`
            : `<div class="file-preview-text" style="background: var(--background); padding: 20px; border-radius: 8px;">${this.escapeHtml(file.preview_text || 'لا توجد معاينة')}</div>`;

        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">${this.escapeHtml(file.title)}</h2>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="detail-section">
                        <h3>معاينة الملف</h3>
                        ${previewHtml}
                    </div>

                    <div class="detail-section">
                        <h3>الوصف</h3>
                        <p>${this.escapeHtml(file.description || 'لا يوجد وصف')}</p>
                    </div>

                    <div class="detail-section">
                        <h3>تفاصيل الملف</h3>
                        <table class="details-table">
                            <tr><td><strong>نوع الملف:</strong></td><td>${this.escapeHtml(file.file_type)}</td></tr>
                            <tr><td><strong>الامتداد:</strong></td><td>${this.escapeHtml(file.file_extension)}</td></tr>
                            <tr><td><strong>الحجم:</strong></td><td>${this.formatFileSize(file.file_size)}</td></tr>
                            <tr><td><strong>السعر:</strong></td><td>${file.final_price.toFixed(2)} نقطة</td></tr>
                            <tr><td><strong>البائع:</strong></td><td>${this.escapeHtml(file.owner_name || file.owner_id)}</td></tr>
                            <tr><td><strong>المبيعات:</strong></td><td>${file.total_sales}</td></tr>
                            <tr><td><strong>المشاهدات:</strong></td><td>${file.total_views}</td></tr>
                            <tr><td><strong>تاريخ النشر:</strong></td><td>${new Date(file.created_at).toLocaleDateString('ar-EG')}</td></tr>
                        </table>
                    </div>

                    <div class="detail-section">
                        <h3>التقييمات</h3>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                            ${starsHtml}
                            <span style="font-size: 24px; font-weight: bold;">${file.average_rating.toFixed(1)}</span>
                            <span style="color: var(--text-secondary);">(${file.total_ratings} تقييم)</span>
                        </div>
                        ${ratingsBreakdown}
                    </div>

                    ${data.has_purchased ? `
                        <div class="detail-section">
                            <div class="rating-form">
                                <h3>قيّم هذا الملف</h3>
                                <form id="rating-form">
                                    <input type="hidden" name="file_id" value="${file.file_id}">
                                    <div class="form-group">
                                        <label class="form-label">التقييم:</label>
                                        <div class="star-rating-input" id="star-rating-input">
                                            ${[1, 2, 3, 4, 5].map(i => `<span class="star ${data.user_rating && data.user_rating.rating_value >= i ? 'active' : ''}" data-rating="${i}">★</span>`).join('')}
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">تعليقك (اختياري):</label>
                                        <textarea name="review" placeholder="شارك تجربتك مع هذا الملف...">${data.user_review ? this.escapeHtml(data.user_review.review_text) : ''}</textarea>
                                    </div>
                                    <button type="submit" class="btn-primary">إرسال التقييم</button>
                                </form>
                            </div>
                        </div>
                    ` : ''}

                    ${data.reviews.length > 0 ? `
                        <div class="detail-section">
                            <h3>التعليقات (${data.reviews.length})</h3>
                            <div class="reviews-list">
                                ${data.reviews.map(review => `
                                    <div class="review-item">
                                        <div class="review-header">
                                            <div>
                                                <strong class="review-author">${this.escapeHtml(review.full_name || review.username)}</strong>
                                                ${review.is_verified_purchase ? '<span class="verified-badge">شراء موثق</span>' : ''}
                                                ${review.rating_value ? this.renderStars(review.rating_value) : ''}
                                            </div>
                                            <span class="review-date">${new Date(review.created_at).toLocaleDateString('ar-EG')}</span>
                                        </div>
                                        <p class="review-text">${this.escapeHtml(review.review_text)}</p>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}

                    <div class="detail-section">
                        ${purchaseSection}
                    </div>
                </div>
            </div>
        `;

        modal.classList.add('active');

        const ratingForm = document.getElementById('rating-form');
        if (ratingForm) {
            this.setupRatingForm(ratingForm);
        }
    }

    setupRatingForm(form) {
        const starContainer = form.querySelector('#star-rating-input');
        let selectedRating = 0;

        starContainer.querySelectorAll('.star').forEach(star => {
            star.addEventListener('click', () => {
                selectedRating = parseInt(star.dataset.rating);
                starContainer.querySelectorAll('.star').forEach((s, index) => {
                    s.classList.toggle('active', index < selectedRating);
                });
            });
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (selectedRating === 0) {
                this.showNotification('يرجى اختيار تقييم', 'error');
                return;
            }

            const formData = new FormData(form);
            formData.append('rating', selectedRating);

            try {
                const response = await fetch('/api/rating.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    this.showNotification(result.message, 'success');
                    this.closeModal();
                    this.loadFiles();
                } else {
                    this.showNotification(result.error, 'error');
                }
            } catch (error) {
                this.showNotification('حدث خطأ في إرسال التقييم', 'error');
                console.error(error);
            }
        });
    }

    renderRatingsBreakdown(breakdown) {
        let html = '<div class="ratings-breakdown">';

        for (let i = 5; i >= 1; i--) {
            const data = breakdown[i] || { count: 0, percentage: 0 };
            html += `
                <div class="rating-bar">
                    <span class="rating-label">${i} نجوم</span>
                    <div class="bar-container">
                        <div class="bar-fill" style="width: ${data.percentage}%"></div>
                    </div>
                    <span class="rating-count">${data.count}</span>
                </div>
            `;
        }

        html += '</div>';
        return html;
    }

    async purchaseFile(fileId) {
        if (!confirm('هل أنت متأكد من شراء هذا الملف؟')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('file_id', fileId);

            const response = await fetch('/api/purchase.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(result.message, 'success');
                this.closeModal();
                this.loadFiles();

                if (result.data.new_balance !== undefined) {
                    const balanceElement = document.querySelector('.user-balance');
                    if (balanceElement) {
                        balanceElement.textContent = `${result.data.new_balance.toFixed(2)} نقطة`;
                    }
                }
            } else {
                this.showNotification(result.error, 'error');
            }
        } catch (error) {
            this.showNotification('حدث خطأ في عملية الشراء', 'error');
            console.error(error);
        }
    }

    createModal(id) {
        const modal = document.createElement('div');
        modal.id = id;
        modal.className = 'modal';
        document.body.appendChild(modal);
        return modal;
    }

    closeModal() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.remove('active');
        });
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background: ${type === 'success' ? 'var(--success-color)' : type === 'error' ? 'var(--danger-color)' : 'var(--primary-color)'};
            color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    formatFileSize(bytes) {
        if (bytes >= 1073741824) {
            return (bytes / 1073741824).toFixed(2) + ' GB';
        } else if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(2) + ' MB';
        } else if (bytes >= 1024) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return bytes + ' B';
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

const marketplace = new FileMarketplace();
