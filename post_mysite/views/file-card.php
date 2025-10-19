<?php
function renderFileCard($file) {
    $file_id = htmlspecialchars($file['file_id']);
    $title = htmlspecialchars($file['title']);
    $description = htmlspecialchars(substr($file['description'], 0, 100));
    $price = number_format($file['final_price'], 2);
    $owner_name = htmlspecialchars($file['owner_name'] ?? $file['owner_id']);
    $rating = number_format($file['average_rating'], 1);
    $total_ratings = intval($file['total_ratings']);
    $total_sales = intval($file['total_sales']);
    $file_type = htmlspecialchars($file['file_type']);
    $file_size = formatFileSize($file['file_size']);

    $preview_html = '';
    if ($file['preview_type'] === 'image' && !empty($file['preview_image'])) {
        $preview_html = '<img src="/storage/previews/' . htmlspecialchars($file['preview_image']) . '" alt="Preview" class="file-preview-image">';
    } else {
        $preview_text = htmlspecialchars(substr($file['preview_text'] ?? 'لا توجد معاينة', 0, 150));
        $preview_html = '<div class="file-preview-text">' . nl2br($preview_text) . '...</div>';
    }

    $stars_html = renderStars($file['average_rating']);

    $purchase_button = '';
    if (isset($file['is_owner']) && $file['is_owner']) {
        $purchase_button = '<button class="btn-secondary" disabled>ملفك</button>';
    } elseif (isset($file['user_purchased']) && $file['user_purchased']) {
        $purchase_button = '<a href="/api/download.php?file_id=' . $file_id . '" class="btn-success">تحميل</a>';
    } else {
        $purchase_button = '<button class="btn-primary purchase-btn" data-file-id="' . $file_id . '">شراء - ' . $price . ' نقطة</button>';
    }

    return <<<HTML
    <div class="file-card" data-file-id="{$file_id}">
        <div class="file-card-header">
            {$preview_html}
        </div>
        <div class="file-card-body">
            <h3 class="file-title">{$title}</h3>
            <p class="file-description">{$description}</p>

            <div class="file-meta">
                <span class="file-type-badge">{$file_type}</span>
                <span class="file-size">{$file_size}</span>
            </div>

            <div class="file-rating">
                {$stars_html}
                <span class="rating-text">({$total_ratings})</span>
            </div>

            <div class="file-stats">
                <span><i class="icon-download"></i> {$total_sales} مبيعات</span>
            </div>

            <div class="file-owner">
                <small>البائع: {$owner_name}</small>
            </div>
        </div>
        <div class="file-card-footer">
            <div class="file-price">{$price} نقطة</div>
            <div class="file-actions">
                <button class="btn-details" onclick="showFileDetails({$file_id})">التفاصيل</button>
                {$purchase_button}
            </div>
        </div>
    </div>
HTML;
}

function renderStars($rating) {
    $fullStars = floor($rating);
    $hasHalfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);

    $html = '<div class="stars">';

    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<span class="star star-full">★</span>';
    }

    if ($hasHalfStar) {
        $html .= '<span class="star star-half">★</span>';
    }

    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<span class="star star-empty">☆</span>';
    }

    $html .= '</div>';

    return $html;
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
