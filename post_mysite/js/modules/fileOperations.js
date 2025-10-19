/**
 * File operations module for handling file-related functionality
 * @module FileOperations
 */

import { notificationManager } from './notifications.js';
import { modalManager } from './modal.js';

/**
 * Class handling file operations like preview, download, and purchase
 */
export class FileOperations {
  constructor() {
    this.csrfToken = this.getCSRFToken();
    this.initializePreviewModal();
  }

  /**
   * Get CSRF token from meta tag
   * @returns {string} CSRF token
   */
  getCSRFToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  /**
   * Initialize the preview modal
   */
  initializePreviewModal() {
    modalManager.create('preview', {
      title: 'File Preview',
      content: '<div class="preview-loading">Loading preview...</div>',
      closeOnBackdropClick: true
    });
  }

  /**
   * Toggle file details
   * @param {string} fileId - ID of the file
   */
  toggleDetails(fileId) {
    const card = document.querySelector(`.smart-file-card[data-file-id="${CSS.escape(fileId)}"]`);
    if (!card) return;

    // Close other expanded cards
    document.querySelectorAll('.smart-file-card.expanded').forEach(c => {
      if (c !== card) c.classList.remove('expanded');
    });

    // Toggle current card
    card.classList.toggle('expanded');
  }

  /**
   * Open file preview
   * @param {string} fileId - ID of the file to preview
   */
  async openPreview(fileId) {
    if (!fileId) {
      notificationManager.show('No file selected for preview', 'error');
      return;
    }

    try {
      notificationManager.show('Loading preview...', 'info', 1500);
      
      const response = await fetch(`preview.php?id=${encodeURIComponent(fileId)}`, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': this.csrfToken
        },
        credentials: 'same-origin'
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      
      if (!data.success) {
        throw new Error(data.message || 'Failed to load preview');
      }

      let content = '';
      
      if (data.type === 'image' && data.image_url) {
        content = `
          <div class="preview-image-container" style="text-align: center;">
            <img src="${this.escapeHtml(data.image_url)}" 
                 class="preview-image" 
                 alt="Preview" 
                 style="max-width: 100%; max-height: 70vh; object-fit: contain;">
          </div>
        `;
      } else if (data.type === 'text' && data.text) {
        content = `
          <div class="preview-text" style="white-space: pre-wrap; font-family: monospace;">
            ${this.escapeHtml(data.text)}
          </div>
        `;
      } else if (data.type === 'html' && data.html) {
        // Sanitize HTML content before inserting
        content = this.sanitizeHtml(data.html);
      } else {
        content = '<p>No preview available for this file type.</p>';
      }

      // Update modal content
      modalManager.updateContent('preview', {
        title: data.title || 'File Preview',
        content: `<div class="preview-content">${content}</div>`
      });

      // Show the modal
      modalManager.open('preview');

    } catch (error) {
      console.error('Preview error:', error);
      notificationManager.show(
        error.message || 'Failed to load preview', 
        'error'
      );
    }
  }

  /**
   * Start file download
   * @param {string} fileId - ID of the file to download
   */
  startDownload(fileId) {
    if (!fileId) {
      notificationManager.show('No file selected for download', 'error');
      return;
    }

    // Show loading state
    const originalButtonText = event?.target?.innerHTML;
    if (event?.target) {
      event.target.disabled = true;
      event.target.innerHTML = 'Downloading...';
      event.target.classList.add('loading');
    }

    // Create a hidden iframe for the download
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    document.body.appendChild(iframe);

    // Set up error handling
    const cleanup = () => {
      if (event?.target) {
        event.target.disabled = false;
        event.target.innerHTML = originalButtonText;
        event.target.classList.remove('loading');
      }
      if (iframe.parentNode) {
        document.body.removeChild(iframe);
      }
    };

    // Set up timeout
    const timeout = setTimeout(() => {
      notificationManager.show('Download is taking longer than expected...', 'info', 3000);
    }, 5000);

    // Start download
    iframe.onload = () => {
      clearTimeout(timeout);
      cleanup();
      notificationManager.show('Download started', 'success');
      
      // Check for errors
      try {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        const content = iframeDoc.body.textContent || iframeDoc.body.innerText;
        
        try {
          const json = JSON.parse(content);
          if (json && json.success === false) {
            throw new Error(json.message || 'Download failed');
          }
        } catch (e) {
          // Not a JSON response, assume it's a file download
        }
      } catch (e) {
        console.error('Download error:', e);
      }
    };

    iframe.onerror = () => {
      clearTimeout(timeout);
      cleanup();
      notificationManager.show('Download failed', 'error');
    };

    // Start the download
    iframe.src = `download.php?id=${encodeURIComponent(fileId)}`;
  }

  /**
   * Purchase a file
   * @param {string} fileId - ID of the file to purchase
   * @param {string} price - Price of the file
   * @param {Event} event - The click event
   */
  async purchaseFile(fileId, price, event) {
    if (!fileId) {
      notificationManager.show('No file selected for purchase', 'error');
      return;
    }

    if (!confirm(`Purchase file for ${price} points?`)) {
      return;
    }

    const button = event?.target;
    const originalText = button?.innerHTML;
    
    try {
      // Show loading state
      if (button) {
        button.disabled = true;
        button.innerHTML = 'Processing...';
      }

      notificationManager.show('Processing purchase...', 'info');
      
      const response = await fetch('purchase.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-TOKEN': this.csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: `id=${encodeURIComponent(fileId)}`,
        credentials: 'same-origin'
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success) {
        notificationManager.show('Purchase successful!', 'success');
        // Reload the page after a short delay
        setTimeout(() => window.location.reload(), 1000);
      } else {
        throw new Error(data.message || 'Purchase failed');
      }
    } catch (error) {
      console.error('Purchase error:', error);
      notificationManager.show(
        error.message || 'An error occurred during purchase', 
        'error'
      );
    } finally {
      if (button) {
        button.disabled = false;
        button.innerHTML = originalText;
      }
    }
  }

  /**
   * Escape HTML to prevent XSS
   * @param {string} unsafe - Unsafe HTML string
   * @returns {string} Escaped HTML string
   */
  escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return '';
    return unsafe
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  /**
   * Basic HTML sanitization
   * @param {string} html - HTML to sanitize
   * @returns {string} Sanitized HTML
   */
  sanitizeHtml(html) {
    // This is a basic implementation. Consider using DOMPurify for production.
    const doc = document.implementation.createHTMLDocument('');
    const div = doc.createElement('div');
    div.innerHTML = html;
    
    // Remove script tags and other potentially dangerous elements
    const forbiddenTags = ['script', 'iframe', 'object', 'embed', 'link', 'meta', 'style'];
    forbiddenTags.forEach(tag => {
      const elements = div.getElementsByTagName(tag);
      while (elements[0]) {
        elements[0].parentNode.removeChild(elements[0]);
      }
    });

    // Remove dangerous attributes
    const allowedAttributes = ['src', 'href', 'alt', 'title', 'class', 'style'];
    const allElements = div.getElementsByTagName('*');
    for (let i = 0; i < allElements.length; i++) {
      const attrs = allElements[i].attributes;
      for (let j = attrs.length - 1; j >= 0; j--) {
        const attr = attrs[j];
        if (!allowedAttributes.includes(attr.name.toLowerCase()) || 
            attr.name.toLowerCase().startsWith('on')) {
          allElements[i].removeAttribute(attr.name);
        }
      }
    }

    return div.innerHTML;
  }
}

// Export a singleton instance
export const fileOperations = new FileOperations();
