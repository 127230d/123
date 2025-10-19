/**
 * Modal module for displaying dialogs
 * @module Modal
 */

import { notificationManager } from './notifications.js';

export class ModalManager {
  constructor() {
    this.modals = new Map();
    this.activeModal = null;
    this.initializeGlobalListeners();
  }

  /**
   * Initialize global event listeners for modals
   */
  initializeGlobalListeners() {
    // Close on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.activeModal) {
        this.close(this.activeModal);
      }
    });
  }

  /**
   * Create a new modal or return existing one
   * @param {string} id - Unique ID for the modal
   * @param {Object} options - Modal options
   * @param {string} [options.title=''] - Modal title
   * @param {string} [options.content=''] - Modal content
   * @param {boolean} [options.closeOnBackdropClick=true] - Whether to close when clicking outside
   * @returns {HTMLElement} The modal element
   */
  create(id, { title = '', content = '', closeOnBackdropClick = true } = {}) {
    if (this.modals.has(id)) {
      return this.modals.get(id);
    }

    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', `${id}-title`);
    modal.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      display: flex;
      justify-content: center;
      align-items: center;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease, visibility 0.3s ease;
      z-index: 1000;
    `;

    const modalContent = document.createElement('div');
    modalContent.className = 'modal-content';
    modalContent.style.cssText = `
      background: white;
      border-radius: 8px;
      width: 90%;
      max-width: 600px;
      max-height: 90vh;
      overflow-y: auto;
      transform: translateY(20px);
      transition: transform 0.3s ease;
    `;

    modalContent.innerHTML = `
      <div class="modal-header" style="padding: 16px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
        <h3 id="${id}-title" style="margin: 0; font-size: 1.25rem;">${title}</h3>
        <button class="modal-close" aria-label="Close" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
      </div>
      <div class="modal-body" style="padding: 16px;">${content}</div>
    `;

    const closeButton = modalContent.querySelector('.modal-close');
    closeButton.addEventListener('click', () => this.close(id));

    if (closeOnBackdropClick) {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          this.close(id);
        }
      });
    }

    modalContent.addEventListener('click', (e) => e.stopPropagation());
    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    this.modals.set(id, {
      element: modal,
      content: modalContent,
      isOpen: false
    });

    return modal;
  }

  /**
   * Open a modal by ID
   * @param {string} id - ID of the modal to open
   * @param {Object} [content] - Optional content to update before opening
   */
  open(id, content) {
    const modal = this.modals.get(id);
    if (!modal) return;

    if (content) {
      this.updateContent(id, content);
    }

    // Close any open modals
    if (this.activeModal && this.activeModal !== id) {
      this.close(this.activeModal);
    }

    modal.isOpen = true;
    this.activeModal = id;
    modal.element.style.visibility = 'visible';
    modal.element.style.opacity = '1';
    
    // Trigger reflow for animation
    modal.element.offsetHeight;
    
    const modalContent = modal.element.querySelector('.modal-content');
    modalContent.style.transform = 'translateY(0)';

    // Focus the close button for better keyboard navigation
    const closeButton = modal.element.querySelector('.modal-close');
    if (closeButton) {
      closeButton.focus();
    }

    // Add body class to prevent scrolling
    document.body.style.overflow = 'hidden';
  }

  /**
   * Close a modal by ID
   * @param {string} id - ID of the modal to close
   */
  close(id) {
    const modal = this.modals.get(id);
    if (!modal || !modal.isOpen) return;

    modal.isOpen = false;
    const modalElement = modal.element;
    const modalContent = modalElement.querySelector('.modal-content');
    
    modalElement.style.opacity = '0';
    modalContent.style.transform = 'translateY(20px)';

    setTimeout(() => {
      modalElement.style.visibility = 'hidden';
      if (this.activeModal === id) {
        this.activeModal = null;
      }
      
      // Remove body class if no modals are open
      if (!this.activeModal) {
        document.body.style.overflow = '';
      }
    }, 300);
  }

  /**
   * Update modal content
   * @param {string} id - Modal ID
   * @param {Object} content - Content to update
   */
  updateContent(id, { title, content }) {
    const modal = this.modals.get(id);
    if (!modal) return;

    if (title !== undefined) {
      const titleEl = modal.element.querySelector(`#${id}-title`);
      if (titleEl) titleEl.textContent = title;
    }

    if (content !== undefined) {
      const bodyEl = modal.element.querySelector('.modal-body');
      if (bodyEl) {
        if (typeof content === 'string') {
          bodyEl.innerHTML = content;
        } else if (content instanceof HTMLElement) {
          bodyEl.innerHTML = '';
          bodyEl.appendChild(content);
        }
      }
    }
  }

  /**
   * Remove a modal from the DOM
   * @param {string} id - ID of the modal to remove
   */
  remove(id) {
    const modal = this.modals.get(id);
    if (!modal) return;

    if (modal.isOpen) {
      this.close(id);
      // Wait for close animation to complete
      setTimeout(() => {
        modal.element.remove();
        this.modals.delete(id);
      }, 300);
    } else {
      modal.element.remove();
      this.modals.delete(id);
    }
  }
}

// Export a singleton instance
export const modalManager = new ModalManager();

// For backward compatibility
window.closePreviewModal = () => {
  if (modalManager.activeModal === 'preview') {
    modalManager.close('preview');
  }
};
