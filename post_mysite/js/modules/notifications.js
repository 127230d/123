/**
 * Notification module for displaying user feedback
 * @module Notifications
 */

export class NotificationManager {
  constructor() {
    this.notificationContainer = null;
    this.initializeContainer();
  }

  /**
   * Initialize the notification container if it doesn't exist
   */
  initializeContainer() {
    this.notificationContainer = document.createElement('div');
    this.notificationContainer.className = 'notification-container';
    this.notificationContainer.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1600;
      display: flex;
      flex-direction: column;
      gap: 10px;
    `;
    document.body.appendChild(this.notificationContainer);
  }

  /**
   * Show a notification to the user
   * @param {string} message - The message to display
   * @param {'info'|'success'|'error'|'warning'} type - The type of notification
   * @param {number} [duration=3000] - How long to show the notification in ms
   * @returns {void}
   */
  show(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `site-notification ${type}`;
    notification.setAttribute('role', 'alert');
    notification.setAttribute('aria-live', 'polite');
    
    const typeColors = {
      success: '#00b35a',
      error: '#b02a37',
      warning: '#ffc107',
      info: '#0d6efd'
    };

    notification.style.cssText = `
      padding: 12px 16px;
      border-radius: 8px;
      background: ${typeColors[type] || '#333'};
      color: white;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      display: flex;
      align-items: center;
      gap: 10px;
      max-width: 350px;
      opacity: 0;
      transform: translateX(100%);
      transition: opacity 0.3s ease, transform 0.3s ease;
    `;

    // Add icon based on type
    const iconMap = {
      success: '✓',
      error: '✕',
      warning: '⚠',
      info: 'ℹ'
    };

    const icon = document.createElement('span');
    icon.textContent = iconMap[type] || 'ℹ';
    icon.style.fontWeight = 'bold';
    notification.appendChild(icon);

    const messageEl = document.createElement('span');
    messageEl.textContent = message;
    notification.appendChild(messageEl);

    this.notificationContainer.appendChild(notification);

    // Trigger reflow to enable CSS transition
    setTimeout(() => {
      notification.style.opacity = '1';
      notification.style.transform = 'translateX(0)';
    }, 10);

    // Auto-remove notification after duration
    const timeout = setTimeout(() => {
      notification.style.opacity = '0';
      notification.style.transform = 'translateX(100%)';
      setTimeout(() => {
        if (notification.parentNode === this.notificationContainer) {
          this.notificationContainer.removeChild(notification);
        }
      }, 300);
    }, duration);

    // Allow manual dismissal
    notification.addEventListener('click', () => {
      clearTimeout(timeout);
      notification.style.opacity = '0';
      notification.style.transform = 'translateX(100%)';
      setTimeout(() => {
        if (notification.parentNode === this.notificationContainer) {
          this.notificationContainer.removeChild(notification);
        }
      }, 300);
    });
  }
}

// Export a singleton instance
export const notificationManager = new NotificationManager();

// For backward compatibility
window.showNotification = (message, type = 'info', duration = 3000) => {
  notificationManager.show(message, type, duration);
};
