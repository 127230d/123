// Initialize all card functionality
document.addEventListener('DOMContentLoaded', function() {
    // تهيئة الأزرار للبطاقات الموجودة
    initializeExistingCards();
    
    // مراقبة البطاقات الجديدة التي تضاف
    observeNewCards();
});

function initializeExistingCards() {
    // تهيئة جميع البطاقات الموجودة
    document.querySelectorAll('.smart-file-card').forEach(initializeCard);
}

function observeNewCards() {
    // مراقبة إضافة بطاقات جديدة للصفحة
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1 && node.matches('.smart-file-card')) {
                    initializeCard(node);
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
}

function initializeCard(card) {
    const fileId = card.dataset.fileId;
    const viewType = card.dataset.viewType;
    
    // أزرار التفاعل
    const previewButton = card.querySelector('.preview-button');
    const purchaseButton = card.querySelector('.purchase-button');
    const downloadButton = card.querySelector('.download-button');
    const deleteButton = card.querySelector('.delete-button');
    
    if (previewButton) {
        previewButton.addEventListener('click', (e) => {
            e.preventDefault();
            handlePreview(fileId);
        });
    }
    
    if (purchaseButton) {
        purchaseButton.addEventListener('click', (e) => {
            e.preventDefault();
            handlePurchase(fileId);
        });
    }
    
    if (downloadButton) {
        downloadButton.addEventListener('click', (e) => {
            e.preventDefault();
            handleDownload(fileId);
        });
    }
    
    if (deleteButton) {
        deleteButton.addEventListener('click', (e) => {
            e.preventDefault();
            handleDelete(fileId);
        });
    }
}

// معالجة معاينة الملف
async function handlePreview(fileId) {
    try {
        const response = await fetch(`preview_file.php?id=${fileId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) throw new Error('Network response was not ok');
        
        const data = await response.json();
        if (data.success) {
            // عرض المعاينة
            showPreviewDialog(data.preview);
        } else {
            showError(data.message || 'Could not load preview');
        }
    } catch (error) {
        showError('Error loading preview');
        console.error('Preview error:', error);
    }
}

// معالجة شراء الملف
async function handlePurchase(fileId) {
    try {
        if (!await confirmDialog('Are you sure you want to purchase this file?')) {
            return;
        }
        
        const response = await fetch('purchase_file.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `file_id=${fileId}&ajax=1`
        });
        
        if (!response.ok) throw new Error('Network response was not ok');
        
        const data = await response.json();
        if (data.success) {
            showSuccess('File purchased successfully!');
            // تحديث واجهة المستخدم
            updateUIAfterPurchase(fileId);
        } else {
            showError(data.message || 'Purchase failed');
        }
    } catch (error) {
        showError('Error during purchase');
        console.error('Purchase error:', error);
    }
}

// معالجة تحميل الملف
async function handleDownload(fileId) {
    try {
        window.location.href = `download.php?id=${fileId}`;
    } catch (error) {
        showError('Error starting download');
        console.error('Download error:', error);
    }
}

// معالجة حذف الملف
async function handleDelete(fileId) {
    try {
        if (!await confirmDialog('Are you sure you want to delete this file?')) {
            return;
        }
        
        const response = await fetch('delete_file.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `file_id=${fileId}&ajax=1`
        });
        
        if (!response.ok) throw new Error('Network response was not ok');
        
        const data = await response.json();
        if (data.success) {
            showSuccess('File deleted successfully!');
            // إزالة البطاقة من واجهة المستخدم
            removeCardFromUI(fileId);
        } else {
            showError(data.message || 'Delete failed');
        }
    } catch (error) {
        showError('Error deleting file');
        console.error('Delete error:', error);
    }
}

// وظائف مساعدة
function showError(message) {
    // إظهار رسالة خطأ للمستخدم
    alert(message); // يمكن استبدالها بطريقة عرض أفضل
}

function showSuccess(message) {
    // إظهار رسالة نجاح للمستخدم
    alert(message); // يمكن استبدالها بطريقة عرض أفضل
}

function confirmDialog(message) {
    return new Promise((resolve) => {
        resolve(confirm(message)); // يمكن استبدالها بواجهة مستخدم أفضل
    });
}

function showPreviewDialog(preview) {
    // إظهار نافذة المعاينة
    const dialog = document.createElement('div');
    dialog.className = 'preview-dialog';
    dialog.innerHTML = `
        <div class="preview-content">
            <div class="preview-header">
                <button class="close-button">&times;</button>
            </div>
            <div class="preview-body">
                ${preview}
            </div>
        </div>
    `;
    
    document.body.appendChild(dialog);
    
    dialog.querySelector('.close-button').onclick = () => {
        dialog.remove();
    };
}

function updateUIAfterPurchase(fileId) {
    const card = document.querySelector(`.smart-file-card[data-file-id="${fileId}"]`);
    if (card) {
        // تحديث حالة البطاقة
        card.classList.add('purchased');
        // تحديث الأزرار
        const purchaseButton = card.querySelector('.purchase-button');
        const downloadButton = card.querySelector('.download-button');
        
        if (purchaseButton) purchaseButton.style.display = 'none';
        if (downloadButton) downloadButton.style.display = 'inline-block';
    }
}

function removeCardFromUI(fileId) {
    const card = document.querySelector(`.smart-file-card[data-file-id="${fileId}"]`);
    if (card) {
        card.remove();
    }
}
