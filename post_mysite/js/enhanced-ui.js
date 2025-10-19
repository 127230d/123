// Enhanced File Sharing System - Modern UI JavaScript
class EnhancedUI {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.setupIntersectionObserver();
        this.setupKeyboardShortcuts();
    }

    init() {
        // Initialize animations
        this.animateOnLoad();

        // Setup tooltips
        this.setupTooltips();

        // Setup notifications
        this.setupNotifications();

        // Initialize search functionality
        this.setupSearch();

        // Setup infinite scroll
        this.setupInfiniteScroll();
    }

    setupEventListeners() {
        // Navigation enhancement
        this.setupNavigation();

        // Card interactions
        this.setupCardInteractions();

        // Modal enhancements
        this.setupModals();

        // Form enhancements
        this.setupForms();
    }

    animateOnLoad() {
        // Animate elements on page load
        const animatedElements = document.querySelectorAll('.fade-in-up');

        animatedElements.forEach((element, index) => {
            setTimeout(() => {
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }

    setupNavigation() {
        const navLinks = document.querySelectorAll('.nav-link');
        const currentPath = window.location.pathname;

        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('active');
            }

            link.addEventListener('mouseenter', (e) => {
                this.createRipple(e);
            });
        });

        // Mobile menu toggle
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => {
                this.toggleMobileMenu();
            });
        }
    }

    setupCardInteractions() {
        const cards = document.querySelectorAll('.file-card, .card');

        cards.forEach(card => {
            // Hover effects
            card.addEventListener('mouseenter', (e) => {
                this.handleCardHover(e.target, true);
            });

            card.addEventListener('mouseleave', (e) => {
                this.handleCardHover(e.target, false);
            });

            // Click effects - only for visual feedback, not functionality
            card.addEventListener('click', (e) => {
                this.handleCardClick(e);
            });

            // Setup drawer functionality for smart cards
            this.setupCardDrawer(card);
        });
    }

    setupCardDrawer(card) {
        // Find the drawer in this card
        const drawer = card.querySelector('.smart-card-drawer');
        if (!drawer) return;

        // Find the clickable area (the card body/header)
        const clickableArea = card.querySelector('.smart-card-body, .card-header, .file-card');
        if (!clickableArea) return;

        // Make the card clickable to toggle drawer
        clickableArea.style.cursor = 'pointer';

        // Remove any existing event listeners to prevent duplicates
        const newClickableArea = clickableArea.cloneNode(true);
        clickableArea.parentNode.replaceChild(newClickableArea, clickableArea);

        // Add fresh event listener
        newClickableArea.addEventListener('click', (e) => {
            // Don't trigger if clicking on buttons or links
            if (e.target.closest('button, a, .btn')) return;

            this.toggleCardDrawer(drawer, card);
        });

        // Add visual indicator for expandable cards
        card.classList.add('expandable-card');
    }

    // نظام إدارة النوافذ - إغلاق النوافذ السابقة عند فتح نافذة جديدة
    // هذا النظام يضمن عدم وجود نوافذ متعددة مفتوحة في نفس الوقت
    // ويحسن تجربة المستخدم من خلال تركيز الانتباه على نافذة واحدة فقط
    setupWindowManager() {
        this.activeWindows = new Set(); // تتبع النوافذ المفتوحة

        // مراقبة فتح النوافذ الجديدة
        this.setupWindowOpenDetection();
    }

    setupWindowOpenDetection() {
        // مراقبة إضافة العناصر الجديدة للصفحة باستخدام MutationObserver
        // هذا يسمح بتتبع النوافذ المنبثقة التي يتم إنشاؤها ديناميكياً
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // فحص إذا كان العنصر الجديد نافذة منبثقة
                        if (this.isModalWindow(node)) {
                            this.registerNewWindow(node);
                        }
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        this.windowObserver = observer;
    }

    isModalWindow(element) {
        // فحص ما إذا كان العنصر نافذة منبثقة بناءً على عدة معايير:
        // 1. وجود كلاسات محددة تشير إلى نوافذ منبثقة
        // 2. استخدام position: fixed أو absolute مع z-index عالي
        const modalClasses = ['modal', 'drawer', 'popup', 'overlay', 'dialog'];
        const classList = Array.from(element.classList || []);

        return modalClasses.some(cls => classList.includes(cls)) ||
               element.style.position === 'fixed' ||
               element.style.position === 'absolute' && element.style.zIndex > 100;
    }

    registerNewWindow(newWindow) {
        // إغلاق جميع النوافذ السابقة أولاً للحفاظ على تركيز المستخدم
        this.closeAllPreviousWindows();

        // تسجيل النافذة الجديدة في نظام التتبع
        this.activeWindows.add(newWindow);

        // إضافة معالج لإزالة النافذة من التتبع عند إغلاقها
        const removeFromActive = () => {
            this.activeWindows.delete(newWindow);
            newWindow.removeEventListener('remove', removeFromActive);
        };

        newWindow.addEventListener('remove', removeFromActive);
    }

    closeAllPreviousWindows() {
        // إغلاق جميع الـ drawers المفتوحة
        this.collapseAllDrawers();

        // إغلاق جميع النوافذ المنبثقة الأخرى
        const modals = document.querySelectorAll('.modal.show, .modal:not(.show)');
        modals.forEach(modal => {
            if (modal !== this.currentModal) {
                modal.classList.remove('show');
                setTimeout(() => modal.remove(), 300);
            }
        });

        // إغلاق أي نوافذ أخرى مخصصة قد تكون مفتوحة
        this.closeCustomWindows();
    }

    // دالة اختبار لنظام إدارة النوافذ
    testWindowManager() {
        console.log('🪟 Testing Window Manager System:');
        console.log('Active windows:', this.activeWindows ? this.activeWindows.size : 'Not initialized');
        console.log('Window observer active:', this.windowObserver ? 'Yes' : 'No');
        console.log('Modal windows in DOM:', document.querySelectorAll('.modal').length);
        console.log('Open drawers:', document.querySelectorAll('.smart-card-drawer.expanded').length);

        // اختبار إغلاق النوافذ السابقة
        console.log('Testing close all previous windows...');
        this.closeAllPreviousWindows();
        console.log('✅ Close test completed');

        return 'Window manager is working correctly';
    }

    handleGlobalDrawerClose(e) {
        // فحص ما إذا كان النقر داخل درج مفتوح أم لا
        const clickedDrawer = e.target.closest('.smart-card-drawer.expanded');
        const clickedCard = e.target.closest('.smart-file-card.expanded');

        // إذا كان النقر خارج أي درج مفتوح، أغلق جميع الـ drawers
        if (!clickedDrawer && !clickedCard) {
            this.collapseAllDrawers();
        }
    }

    handleEscapeKey(e) {
        // إغلاق جميع الـ drawers عند الضغط على Escape
        if (e.key === 'Escape') {
            this.collapseAllDrawers();
        }
    }

    toggleCardDrawer(drawer, card) {
        const isExpanded = drawer.classList.contains('expanded');

        if (isExpanded) {
            this.collapseCardDrawer(drawer, card);
        } else {
            this.expandCardDrawer(drawer, card);
        }
    }

    expandCardDrawer(drawer, card) {
        // إغلاق جميع النوافذ الأخرى أولاً
        this.closeAllPreviousWindows();

        drawer.classList.add('expanded');
        card.classList.add('expanded');

        // Animate the expansion
        drawer.style.maxHeight = drawer.scrollHeight + 'px';
        drawer.style.opacity = '1';

        // Add expanded indicator
        const indicator = card.querySelector('.expand-indicator');
        if (indicator) {
            indicator.innerHTML = '<i class="fas fa-chevron-up"></i>';
        }

        // إشعار للمستخدم
        this.showNotification('تم فتح تفاصيل الملف', 'success', 1500);
    }

    collapseCardDrawer(drawer, card) {
        drawer.classList.remove('expanded');
        card.classList.remove('expanded');
        // Animate the collapse
        drawer.style.maxHeight = '0';
        drawer.style.opacity = '0';

        // Reset expanded indicator
        const indicator = card.querySelector('.expand-indicator i');
        if (indicator) {
            indicator.className = 'fas fa-chevron-down';
        }

        // إضافة إشعار عند إغلاق الدرج
        this.showNotification('تم إغلاق تفاصيل الملف', 'info', 2000);
    }

    collapseAllDrawers() {
        document.querySelectorAll('.smart-card-drawer.expanded').forEach(drawer => {
            const card = drawer.closest('.file-card, .card');
            this.collapseCardDrawer(drawer, card);
        });
    }

    handleCardHover(card, isHovering) {
        const overlay = card.querySelector('.card-overlay');

        if (isHovering) {
            card.style.transform = 'translateY(-8px) scale(1.02)';
            if (overlay) overlay.style.opacity = '1';
        } else {
            card.style.transform = 'translateY(0) scale(1)';
            if (overlay) overlay.style.opacity = '0';
        }
    }

    handleCardClick(e) {
        const card = e.currentTarget;
        const ripple = this.createRipple(e);

        card.style.transform = 'scale(0.95)';
        setTimeout(() => {
            card.style.transform = '';
        }, 150);
    }

    createRipple(e) {
        const button = e.currentTarget;
        const rect = button.getBoundingClientRect();
        const ripple = document.createElement('span');

        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;

        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s ease-out;
            pointer-events: none;
        `;

        button.style.position = 'relative';
        button.style.overflow = 'hidden';
        button.appendChild(ripple);

        setTimeout(() => ripple.remove(), 600);

        return ripple;
    }

    setupTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');

        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target, e.target.getAttribute('data-tooltip'));
            });

            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }

    showTooltip(element, text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;

        tooltip.style.cssText = `
            position: absolute;
            background: var(--gray-900);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            white-space: nowrap;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-5px);
            transition: all 0.2s ease;
        `;

        document.body.appendChild(tooltip);

        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';

        setTimeout(() => {
            tooltip.style.opacity = '1';
            tooltip.style.transform = 'translateY(0)';
        }, 10);

        element._tooltip = tooltip;
    }

    hideTooltip() {
        const tooltip = document.querySelector('.tooltip');
        if (tooltip) {
            tooltip.style.opacity = '0';
            tooltip.style.transform = 'translateY(-5px)';
            setTimeout(() => tooltip.remove(), 200);
        }
    }

    setupNotifications() {
        // Enhanced notification system
        this.notifications = [];

        // Auto-hide notifications after 5 seconds - only if there are notifications
        this.notificationInterval = setInterval(() => {
            if (this.notifications.length > 0) {
                this.autoHideNotifications();
            }
        }, 1000);
    }

    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;

        const icon = this.getNotificationIcon(type);

        notification.innerHTML = `
            <div class="notification-icon">
                <i class="${icon}"></i>
            </div>
            <div class="notification-content">
                <div class="notification-message">${message}</div>
            </div>
            <button class="notification-close" onclick="this.parentNode.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            padding: 16px 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            max-width: 400px;
        `;

        // Style based on type
        const colors = {
            success: '#48bb78',
            error: '#f56565',
            warning: '#ed8936',
            info: '#4299e1'
        };

        notification.style.borderLeft = `4px solid ${colors[type]}`;

        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);

        this.notifications.push(notification);

        // Auto remove after duration
        if (duration > 0) {
            setTimeout(() => {
                this.removeNotification(notification);
            }, duration);
        }

        return notification;
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };

        return icons[type] || icons.info;
    }

    removeNotification(notification) {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            notification.remove();
            this.notifications = this.notifications.filter(n => n !== notification);
        }, 300);
    }

    autoHideNotifications() {
        this.notifications = this.notifications.filter(notification => {
            const rect = notification.getBoundingClientRect();
            return rect.right > 0; // Still visible
        });
    }

    setupSearch() {
        const searchInputs = document.querySelectorAll('.search-input');

        searchInputs.forEach(input => {
            // Enhanced search with debouncing
            let searchTimeout;
            input.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.performSearch(e.target.value);
                }, 300);
            });

            // Search suggestions
            input.addEventListener('focus', (e) => {
                this.showSearchSuggestions(e.target);
            });

            input.addEventListener('blur', (e) => {
                setTimeout(() => {
                    this.hideSearchSuggestions();
                }, 200);
            });
        });
    }

    performSearch(query) {
        if (query.length < 2) return;

        // Show loading state
        this.showSearchLoading();

        // Make search request
        fetch(`/search_handler.php?action=search&q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                this.hideSearchLoading();
                if (data.success) {
                    this.displaySearchResults(data.results);
                } else {
                    this.showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                this.hideSearchLoading();
                this.showNotification('Search failed', 'error');
            });
    }

    showSearchLoading() {
        // Implementation for search loading state
    }

    hideSearchLoading() {
        // Implementation for hiding search loading state
    }

    displaySearchResults(results) {
        // Implementation for displaying search results
    }

    setupInfiniteScroll() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadMoreContent();
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '100px'
        });

        // Observe load more triggers
        document.querySelectorAll('.load-more-trigger').forEach(trigger => {
            observer.observe(trigger);
        });
    }

    loadMoreContent() {
        const triggers = document.querySelectorAll('.load-more-trigger');
        triggers.forEach(trigger => {
            if (this.isElementInViewport(trigger)) {
                // Load more content logic here
                trigger.remove(); // Remove trigger after loading
            }
        });
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Global shortcuts
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'k':
                        e.preventDefault();
                        this.focusSearch();
                        break;
                    case '/':
                        e.preventDefault();
                        this.focusSearch();
                        break;
                    case 'Escape':
                        this.closeAllModals();
                        break;
                }
            }

            // Escape key for modals
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }

    focusSearch() {
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }

    closeAllModals() {
        document.querySelectorAll('.modal.show').forEach(modal => {
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 300);
        });
    }

    setupModals() {
        // Enhanced modal functionality
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-backdrop')) {
                e.target.parentNode.classList.remove('show');
                setTimeout(() => e.target.parentNode.remove(), 300);
            }
        });
    }

    setupForms() {
        const forms = document.querySelectorAll('form');

        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                    return false;
                }

                this.showFormLoading(form);
            });
        });
    }

    validateForm(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.showFieldError(field, 'This field is required');
                isValid = false;
            } else {
                this.clearFieldError(field);
            }
        });

        return isValid;
    }

    showFieldError(field, message) {
        this.clearFieldError(field);

        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = message;

        errorDiv.style.cssText = `
            color: var(--danger-color);
            font-size: 0.875rem;
            margin-top: 4px;
        `;

        field.style.borderColor = 'var(--danger-color)';
        field.parentNode.appendChild(errorDiv);
        field._errorElement = errorDiv;
    }

    clearFieldError(field) {
        if (field._errorElement) {
            field._errorElement.remove();
            delete field._errorElement;
        }
        field.style.borderColor = '';
    }

    showFormLoading(form) {
        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        }
    }

    setupIntersectionObserver() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '50px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        // Observe elements for scroll animations
        document.querySelectorAll('.scroll-animate').forEach(element => {
            observer.observe(element);
        });
    }

    toggleMobileMenu() {
        const navMenu = document.querySelector('.nav-menu');
        const mobileToggle = document.querySelector('.mobile-menu-toggle');

        if (navMenu) {
            navMenu.classList.toggle('mobile-open');
        }

        if (mobileToggle) {
            mobileToggle.classList.toggle('active');
        }
    }

    isElementInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }

    // Utility methods
    debounce(func, wait) {
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

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    // Animation utilities
    animateValue(element, start, end, duration) {
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            element.textContent = Math.floor(progress * (end - start) + start);
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    }

    // Theme utilities
    toggleTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
    }

    getPreferredTheme() {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            return savedTheme;
        }

        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
}

// Initialize Enhanced UI when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.enhancedUI = new EnhancedUI();

    // إضافة خاصية إغلاق الدرج عند النقر خارج منطقته
    window.enhancedUI.setupGlobalDrawerClose();

    // إضافة نظام إدارة النوافذ المتقدم
    window.enhancedUI.setupWindowManager();
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }

    .scroll-animate {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.6s ease, transform 0.6s ease;
    }

    .scroll-animate.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .field-error {
        animation: shake 0.5s ease-in-out;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    .mobile-menu-toggle {
        display: none;
        flex-direction: column;
        gap: 4px;
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px;
    }

    .mobile-menu-toggle span {
        width: 25px;
        height: 3px;
        background: white;
        border-radius: 2px;
        transition: 0.3s;
    }

    .mobile-menu-toggle.active span:nth-child(1) {
        transform: rotate(45deg) translate(6px, 6px);
    }

    .mobile-menu-toggle.active span:nth-child(2) {
        opacity: 0;
    }

    .mobile-menu-toggle.active span:nth-child(3) {
        transform: rotate(-45deg) translate(6px, -6px);
    }

    @media (max-width: 768px) {
        .mobile-menu-toggle {
            display: flex;
        }

        .nav-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--primary-gradient);
            flex-direction: column;
            padding: 20px;
            transform: translateY(-100%);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .nav-menu.mobile-open {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }
    }
`;

document.head.appendChild(style);

// Export for use in other scripts
window.EnhancedUI = EnhancedUI;

// إضافة دوال الاختبار للوصول العام
if (typeof window.enhancedUI !== 'undefined') {
    window.testWindowManager = () => window.enhancedUI.testWindowManager();
}
