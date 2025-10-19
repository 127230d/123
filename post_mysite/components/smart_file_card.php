<?php
class SmartFileCard
{
    private $file;
    private $view_type;
    private $current_user_id;
    private $is_purchased;
    private $allowed_view_types = ['browse', 'my_files', 'purchases'];

    public function __construct($file, $view_type = 'browse', $current_user_id = null, $is_purchased = false)
    {
        $this->validateInputs($file, $view_type, $current_user_id, $is_purchased);
        $this->file = $file;
        $this->view_type = $view_type;
        $this->current_user_id = $current_user_id;
        $this->is_purchased = $is_purchased;
    }

    private function validateInputs($file, $view_type, $current_user_id, $is_purchased)
    {
        if (!is_array($file)) {
            throw new InvalidArgumentException('File data must be an array');
        }

        if (!in_array($view_type, $this->allowed_view_types)) {
            throw new InvalidArgumentException('Invalid view type. Allowed types: ' . implode(', ', $this->allowed_view_types));
        }

        if ($current_user_id !== null && !is_numeric($current_user_id)) {
            throw new InvalidArgumentException('Current user ID must be numeric or null');
        }

        if (!is_bool($is_purchased)) {
            throw new InvalidArgumentException('is_purchased must be a boolean');
        }
    }

    public function render()
    {
        try {
            $file = $this->file;
            $view_type = $this->view_type;
            $current_user_id = $this->current_user_id;
            $is_purchased = $this->is_purchased;

            // File icon detection
            $file_extension = strtolower(pathinfo($file['filename'] ?? '', PATHINFO_EXTENSION));
            $icon_class = $this->getFileIcon($file_extension);

            // Format file size
            $file_size = $this->formatFileSize($file['file_size'] ?? 0);

            // Time calculations
            $upload_time = $this->timeAgo($file['upload_date'] ?? date('Y-m-d H:i:s'));

            // Owner info
            $is_own_file = ($current_user_id == ($file['user_id'] ?? null));

            // Statistics with validation
            $views = max(0, (int)($file['views'] ?? 0));
            $downloads = max(0, (int)($file['downloads'] ?? 0));
            $rating = max(0, min(5, (float)($file['rating'] ?? 0)));
            $sales = max(0, (int)($file['sales'] ?? 0));

            ob_start();
?>
            <div class="smart-file-card"
                data-file-id="<?php echo $this->escapeHtml($file['id'] ?? 0); ?>"
                data-view-type="<?php echo $this->escapeHtml($view_type); ?>">
                <div class="smart-card-header">
                    <div class="file-icon-container">
                        <i class="<?php echo $icon_class; ?>"></i>
                    </div>
                    <div class="file-info">
                        <h3 class="file-title" title="<?php echo $this->escapeHtml($file['filename'] ?? ''); ?>">
                            <span class="file-title-text" dir="auto"><bdi><?php echo $this->escapeHtml($this->truncateFilenameSmart($file['filename'] ?? 'Unspecified file', 60)); ?></bdi></span>
                            <?php if (!empty($file_extension)): ?>
                                <span class="ext-badge" aria-label="File extension"><?php echo strtoupper($this->escapeHtml($file_extension)); ?></span>
                            <?php endif; ?>
                        </h3>
                        <?php if (!empty($file['username'])): ?>
                            <div class="publisher-line" title="Publisher name">
                                <i class="fas fa-user" aria-hidden="true"></i>
                                <span class="publisher-label">Publisher:</span>
                                <span class="publisher-name"><?php echo $this->escapeHtml($file['username']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="file-details-strip">
                        <div class="file-meta-row">
                            <div class="file-type" title="<?php echo t('file_type'); ?>">
                                <i class="fas fa-database" aria-hidden="true"></i>
                                <span><?php echo $this->escapeHtml($file_size); ?></span>
                            </div>
                            <div class="file-price">
                                <i class="fas fa-gem" aria-hidden="true"></i>
                                <span><?php echo number_format(max(0, (float)($file['price'] ?? 0)), 0); ?> <?php echo t('points'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="smart-card-actions">
                    <button class="smart-btn info drawer-toggle" onclick="toggleCard(<?php echo $file['id']; ?>)">
                        <i class="fas fa-info-circle"></i> <?php echo t('details'); ?>
                    </button>

                    <?php if ($view_type === 'browse'): ?>
                        <?php if ($is_own_file): ?>
                            <button class="smart-btn secondary" onclick="previewFile(<?php echo $file['id']; ?>, '<?php echo addslashes($file['preview_type'] ?? 'text'); ?>', '<?php echo addslashes($file['preview_text'] ?? ''); ?>', '<?php echo addslashes($file['preview_image'] ?? ''); ?>')">
                                <i class="fas fa-eye"></i> <?php echo t('preview'); ?>
                            </button>
                            <button class="smart-btn primary" onclick="editFile(<?php echo $file['id']; ?>)">
                                <i class="fas fa-edit"></i> <?php echo t('edit'); ?>
                            </button>
                        <?php elseif ($is_purchased): ?>
                            <button class="smart-btn success" onclick="downloadFile(<?php echo $file['id']; ?>)">
                                <i class="fas fa-download"></i> <?php echo t('download'); ?>
                            </button>
                            <button class="smart-btn secondary" onclick="previewFile(<?php echo $file['id']; ?>, '<?php echo addslashes($file['preview_type'] ?? 'text'); ?>', '<?php echo addslashes($file['preview_text'] ?? ''); ?>', '<?php echo addslashes($file['preview_image'] ?? ''); ?>')">
                                <i class="fas fa-eye"></i> <?php echo t('preview'); ?>
                            </button>
                        <?php else: ?>
                            <button class="smart-btn secondary" onclick="previewFile(<?php echo $file['id']; ?>, '<?php echo addslashes($file['preview_type'] ?? 'text'); ?>', '<?php echo addslashes($file['preview_text'] ?? ''); ?>', '<?php echo addslashes($file['preview_image'] ?? ''); ?>')">
                                <i class="fas fa-eye"></i> <?php echo t('preview'); ?>
                            </button>
                            <button class="smart-btn primary purchase-btn" onclick="purchaseFile(<?php echo $file['id']; ?>, <?php echo $file['price']; ?>)">
                                <i class="fas fa-shopping-cart"></i> <?php echo t('purchase'); ?>
                            </button>
                        <?php endif; ?>
                    <?php elseif ($view_type === 'my_files'): ?>
                        <button class="smart-btn secondary" onclick="previewFile(<?php echo $file['id']; ?>, '<?php echo addslashes($file['preview_type'] ?? 'text'); ?>', '<?php echo addslashes($file['preview_text'] ?? ''); ?>', '<?php echo addslashes($file['preview_image'] ?? ''); ?>')">
                            <i class="fas fa-eye"></i> <?php echo t('preview'); ?>
                        </button>
                        <button class="smart-btn primary" onclick="editFile(<?php echo $file['id']; ?>)">
                            <i class="fas fa-edit"></i> <?php echo t('edit'); ?>
                        </button>
                        <button class="smart-btn danger" onclick="deleteFile(<?php echo $file['id']; ?>)">
                            <i class="fas fa-trash"></i> <?php echo t('delete'); ?>
                        </button>
                    <?php elseif ($view_type === 'purchases'): ?>
                        <button class="smart-btn success" onclick="downloadFile(<?php echo $file['id']; ?>)">
                            <i class="fas fa-download"></i> <?php echo t('download'); ?>
                        </button>
                        <button class="smart-btn secondary" onclick="previewFile(<?php echo $file['id']; ?>, '<?php echo addslashes($file['preview_type'] ?? 'text'); ?>', '<?php echo addslashes($file['preview_text'] ?? ''); ?>', '<?php echo addslashes($file['preview_image'] ?? ''); ?>')">
                            <i class="fas fa-eye"></i> <?php echo t('preview'); ?>
                        </button>
                        <button class="smart-btn" onclick="rateFile(<?php echo $file['id']; ?>)">
                            <i class="fas fa-star"></i> <?php echo t('rate'); ?>
                        </button>
                    <?php endif; ?>
                </div>
                <div class="smart-card-body">
                    <div class="card-info-unified">
                        <!-- Primary emphasis: price + rating up-front for the buyer -->
                        <div class="price-section primary-highlight">
                            <?php if ($view_type === 'browse' && !$is_own_file): ?>
                                <div class="price-tag prominent" title="<?php echo t('price'); ?>">
                                    <i class="fas fa-coins" aria-hidden="true"></i>
                                    <span class="price-value"><?php echo number_format(max(0, (float)($file['price'] ?? 0)), 0); ?></span>
                                    <span class="price-label"><?php echo t('points'); ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="rating-badge" title="<?php echo t('rating'); ?>">
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <span class="rating-value"><?php echo number_format($rating, 1); ?></span>
                            </div>

                            <?php if ($view_type === 'my_files' && isset($file['total_revenue'])): ?>
                                <div class="revenue-info compact" title="<?php echo t('total_revenue'); ?>">
                                    <i class="fas fa-chart-line" aria-hidden="true"></i>
                                    <span><?php echo number_format($file['total_revenue']); ?> <?php echo t('points_total'); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($view_type === 'purchases' && isset($file['purchase_date'])): ?>
                                <div class="purchase-info compact">
                                    <div class="purchase-price" title="<?php echo t('purchase_price'); ?>">
                                        <i class="fas fa-receipt" aria-hidden="true"></i>
                                        <span><?php echo t('purchased_for'); ?> <?php echo number_format($file['purchase_price'] ?? $file['price']); ?> <?php echo t('points'); ?></span>
                                    </div>
                                    <div class="file-upload-date" title="<?php echo t('uploaded_on'); ?>">
                                        <i class="fas fa-calendar" aria-hidden="true"></i>
                                        <?php echo $this->timeAgo($file['purchase_date']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($file['description'])): ?>
                            <div class="file-description emphasis" dir="rtl">
                                <p><?php echo $this->formatShortDescription($file['description'], 160, 3); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Expand Indicator -->
                        <div class="expand-indicator" title="Click to expand for more details">
                            <i class="fas fa-chevron-down"></i>
                        </div>

                        <!-- Compact key stats (kept, but visually simplified) -->
                        <?php if (!$is_own_file && $view_type === 'browse' && !empty($file['username'])): ?>
                            <div class="owner-info subtle">
                                <div class="owner-details">
                                    <div class="owner-badges">
                                        <?php if (!empty($file['user_level'])): ?>
                                            <span class="badge badge-level" title="<?php echo t('level'); ?>">
                                                <i class="fas fa-user-shield" aria-hidden="true"></i>
                                                <span>Lv <?php echo (int)$file['user_level']; ?></span>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (isset($file['seller_rating'])): ?>
                                            <span class="badge badge-metric" title="<?php echo t('seller_rating'); ?>">
                                                <i class="fas fa-star" aria-hidden="true"></i>
                                                <span><?php echo number_format((float)$file['seller_rating'], 1); ?></span>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (isset($file['seller_total_sales'])): ?>
                                            <span class="badge badge-metric" title="<?php echo t('total_sales'); ?>">
                                                <i class="fas fa-coins" aria-hidden="true"></i>
                                                <span><?php echo (int)$file['seller_total_sales']; ?></span>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (isset($file['seller_files_count'])): ?>
                                            <span class="badge badge-metric" title="<?php echo t('files_published'); ?>">
                                                <i class="fas fa-file" aria-hidden="true"></i>
                                                <span><?php echo (int)$file['seller_files_count']; ?></span>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($file['member_since'])): ?>
                                            <span class="badge badge-metric" title="<?php echo t('member_since'); ?>">
                                                <i class="fas fa-clock" aria-hidden="true"></i>
                                                <span><?php echo $this->timeAgo($file['member_since']); ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Keep meta, but make it lighter/compact below the main content -->
                        <div class="file-meta-expanded subtle-meta">
                            <div class="meta-item" title="<?php echo t('upload_date'); ?>">
                                <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                                <span class="meta-label"><?php echo t('upload_date'); ?>:</span>
                                <span class="meta-value"><?php echo $this->formatDate($file['upload_date'] ?? date('Y-m-d H:i:s')); ?></span>
                            </div>
                            <div class="file-size" title="<?php echo t('file_size'); ?>">
                                <i class="fas fa-hdd" aria-hidden="true"></i>
                                <span class="meta-label"><?php echo t('file_size'); ?>:</span>
                                <span class="meta-value"><?php echo $this->escapeHtml($file_size); ?></span>
                            </div>
                        </div>
                    </div><!-- end card-info-unified -->
                </div>



                <!-- Expandable Drawer -->
                <div class="smart-card-drawer" id="drawer-<?php echo $file['id']; ?>">
                    <div class="drawer-content">
                        <!-- Unified container: description first, then details, then stats -->
                        <?php if (!empty($file['description'])): ?>
                            <div class="drawer-section primary-content" dir="rtl">
                                <h4><i class="fas fa-align-left"></i> Full Description</h4>
                                <div class="full-description">
                                    <?php echo $this->formatFullDescription($file['description']); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="drawer-section">
                            <h4><i class="fas fa-info-circle"></i> Detailed Information</h4>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <span class="detail-label">File name:</span>
                                    <span class="detail-value" dir="auto"><bdi><?php echo $this->escapeHtml($file['filename']); ?></bdi></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Upload date:</span>
                                    <span class="detail-value"><?php echo date('Y/m/d H:i', strtotime($file['upload_date'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">File type:</span>
                                    <span class="detail-value"><?php echo strtoupper(pathinfo($file['filename'], PATHINFO_EXTENSION)); ?></span>
                                </div>
                                <?php if ($view_type === 'my_files' && isset($file['total_revenue'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Total revenue:</span>
                                        <span class="detail-value revenue"><?php echo number_format($file['total_revenue']); ?> points</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Premium buyers:</span>
                                        <span class="detail-value"><?php echo $file['unique_buyers'] ?? 0; ?> مشتري</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($view_type === 'purchases'): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Purchase date:</span>
                                        <span class="detail-value"><?php echo date('Y/m/d H:i', strtotime($file['purchase_date'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Price paid:</span>
                                        <span class="detail-value price"><?php echo number_format($file['purchase_price']); ?> points</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="drawer-section">
                            <h4><i class="fas fa-chart-bar"></i> Advanced Statistics</h4>
                            <div class="advanced-stats">
                                <div class="stat-row">
                                    <div class="stat-col">
                                        <i class="fas fa-eye"></i>
                                        <span class="stat-number"><?php echo $views; ?></span>
                                        <span class="stat-text">views</span>
                                    </div>
                                    <div class="stat-col">
                                        <i class="fas fa-download"></i>
                                        <span class="stat-number"><?php echo $downloads; ?></span>
                                        <span class="stat-text">downloads</span>
                                    </div>
                                    <div class="stat-col">
                                        <i class="fas fa-star"></i>
                                        <span class="stat-number"><?php echo number_format($rating, 1); ?></span>
                                        <span class="stat-text">rating</span>
                                    </div>
                                    <?php if ($view_type === 'my_files'): ?>
                                        <div class="stat-col">
                                            <i class="fas fa-coins"></i>
                                            <span class="stat-number"><?php echo $sales; ?></span>
                                            <span class="stat-text">sales</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
<?php
            return ob_get_clean();
        } catch (Exception $e) {
            // Log error in production
            error_log('SmartFileCard render error: ' . $e->getMessage());

            // Return fallback content
            return '<div class="smart-file-card error">خطأ في downloads معلومات الملف</div>';
        }
    }

    private function getFileIcon($extension)
    {
        // Grouped file types for better organization and performance
        static $iconMap = null;

        if ($iconMap === null) {
            $iconMap = [
                // Images - Green theme
                'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico', 'tiff'],
                'image_icon' => 'fas fa-image text-success',

                // Documents - Blue theme
                'document' => ['pdf', 'doc', 'docx', 'rtf', 'odt'],
                'document_icon' => 'fas fa-file-alt text-primary',

                // Spreadsheets - Green theme
                'spreadsheet' => ['xls', 'xlsx', 'csv', 'ods'],
                'spreadsheet_icon' => 'fas fa-file-excel text-success',

                // Presentations - Orange theme
                'presentation' => ['ppt', 'pptx', 'odp'],
                'presentation_icon' => 'fas fa-file-powerpoint text-warning',

                // Code files - Various colors
                'code' => ['php', 'js', 'html', 'css', 'py', 'java', 'cpp', 'c', 'cs', 'rb', 'go', 'rs'],
                'code_icons' => [
                    'php' => 'fab fa-php text-info',
                    'js' => 'fab fa-js text-warning',
                    'html' => 'fab fa-html5 text-danger',
                    'css' => 'fab fa-css3 text-info',
                    'py' => 'fab fa-python text-success',
                    'java' => 'fab fa-java text-danger',
                    'cpp' => 'fas fa-code text-primary',
                    'c' => 'fas fa-code text-primary',
                    'cs' => 'fas fa-code text-primary',
                    'rb' => 'fas fa-gem text-danger',
                    'go' => 'fas fa-code text-info',
                    'rs' => 'fas fa-code text-orange',
                ],

                // Archives - Orange theme
                'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'],
                'archive_icon' => 'fas fa-file-archive text-warning',

                // Audio - Purple theme
                'audio' => ['mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a'],
                'audio_icon' => 'fas fa-file-audio text-purple',

                // Video - Red theme
                'video' => ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm'],
                'video_icon' => 'fas fa-file-video text-danger',

                // Applications - Gray theme
                'application' => ['exe', 'msi', 'deb', 'rpm', 'dmg', 'pkg'],
                'application_icon' => 'fas fa-cog text-secondary',

                // Mobile apps
                'mobile' => ['apk', 'ipa'],
                'mobile_icon' => 'fab fa-android text-success',

                // Text files
                'text' => ['txt', 'md', 'log', 'ini', 'conf'],
                'text_icon' => 'fas fa-file-alt text-secondary',
            ];
        }

        // Check specific code file icons first
        if (isset($iconMap['code_icons'][$extension])) {
            return $iconMap['code_icons'][$extension];
        }

        // Check file type groups
        foreach ($iconMap as $type => $data) {
            if (is_array($data) && in_array($extension, $data)) {
                $iconKey = $type . '_icon';
                if (isset($iconMap[$iconKey])) {
                    return $iconMap[$iconKey];
                }
            }
        }

        // Default fallback
        return 'fas fa-file text-secondary';
    }

    private function formatFileSize($bytes)
    {
        if ($bytes == 0) return '0 B';

        // Use bit shifting for better performance
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = 0;
        $size = $bytes;

        // More efficient calculation using bit shifting
        while ($size >= 1024 && $i < count($units) - 1) {
            $size >>= 10; // Divide by 1024 using bit shift
            $i++;
        }

        // Format with appropriate decimal places
        $decimals = $i === 0 ? 0 : ($size < 10 ? 2 : 1);
        return number_format($size, $decimals) . ' ' . $units[$i];
    }

    private function timeAgo($datetime)
    {
        // Set the default timezone to UTC to avoid timezone issues
        date_default_timezone_set('UTC');
        
        $timestamp = strtotime($datetime);
        if ($timestamp === false) return 'Invalid date';

        // Get current time in UTC
        $now = time();
        
        // Calculate the difference in seconds
        $time = $now - $timestamp;

        // Use constants for better performance and readability
        static $intervals = [
            31536000 => 'year',    // 1 year
            2592000  => 'month',    // 1 month (30 days)
            86400    => 'day',    // 1 day
            3600     => 'hour',   // 1 hour
            60       => 'minute',  // 1 minute
        ];

        // If less than 60 seconds, show 'الآن' (now)
        if ($time < 60) return 'Just now';

        // Define the intervals with their Arabic labels
        $intervals = [
            31536000 => 'year',    // 1 year
            2592000  => 'month',    // 1 month (30 days)
            86400    => 'day',    // 1 day
            3600     => 'hour',   // 1 hour
            60       => 'minute',  // 1 minute
            1        => 'ثانية'   // 1 second
        ];

        // Find the largest interval that fits
        foreach ($intervals as $seconds => $label) {
            if ($time >= $seconds) {
                $value = floor($time / $seconds);
                return 'منذ ' . $value . ' ' . $this->getArabicPlural($value, $label);
            }
        }

        return 'الآن';
    }


    /**
     * Helper function to handle Arabic plural forms
     */
    private function getArabicPlural($number, $word) {
        if ($number === 1) {
            return $word;
        }
        
        // Handle Arabic dual and plural forms
        if ($number === 2) {
            switch ($word) {
                case 'year': return 'سنتين';
                case 'month': return 'monthين';
                case 'day': return 'dayين';
                case 'hour': return 'ساعتين';
                case 'minute': return 'دقيقتين';
                case 'ثانية': return 'ثانيتين';
            }
        }
        
        // Plural form
        switch ($word) {
            case 'year': return 'سنوات';
            case 'month': return 'أmonth';
            case 'day': return 'أيام';
            case 'hour': return 'ساعات';
            case 'minute': return 'دقائق';
            case 'ثانية': return 'ثواني';
            default: return $word;
        }
    }
    
    private function truncateText($text, $length)
    {
        if (empty($text) || strlen($text) <= $length) {
            return $text;
        }

        // Use mb_substr for better Unicode support
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $length, 'UTF-8') . '...';
        }

        return substr($text, 0, $length) . '...';
    }

    /**
     * Truncate filename with middle ellipsis while preserving extension
     */
    private function truncateFilenameSmart($filename, $maxLen = 60)
    {
        if (!is_string($filename) || $maxLen <= 0) return '';

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = $ext !== '' ? substr($filename, 0, - (strlen($ext) + 1)) : $filename; // remove ".ext"

        // Helper safe length functions with mb support
        $len = function($s){ return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); };
        $sub = function($s,$start,$length=null){
            if (function_exists('mb_substr')) {
                return $length === null ? mb_substr($s, $start, null, 'UTF-8') : mb_substr($s, $start, $length, 'UTF-8');
            }
            return $length === null ? substr($s, $start) : substr($s, $start, $length);
        };

        $full = $filename;
        if ($len($full) <= $maxLen) return $full;

        $extWithDot = $ext !== '' ? ('.' . $ext) : '';

        // Reserve space for extension and ellipsis
        $ellipsis = '…';
        $reserve = $len($extWithDot) + $len($ellipsis) + 2; // at least 1 char on each side
        if ($reserve >= $maxLen) {
            // fallback: hard truncate keeping the end
            return $ellipsis . $sub($full, -($maxLen - $len($ellipsis)));
        }

        $avail = $maxLen - $len($extWithDot) - $len($ellipsis);
        $front = (int)floor($avail * 0.6);
        $back = $avail - $front;

        $startPart = $sub($name, 0, $front);
        $endPart = $sub($name, -$back);

        return $startPart . $ellipsis . $endPart . $extWithDot;
    }

    /**
     * Clean and normalize description text
     */
    private function sanitizeDescription($text)
    {
        if (!is_string($text)) return '';

        // Normalize line endings and trim
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);

        // Collapse excessive blank lines to a single blank line
        $text = preg_replace("/\n{3,}/u", "\n\n", $text);

        // Collapse multiple spaces while preserving single spaces and newlines
        $text = preg_replace("/[\t ]{2,}/u", " ", $text);

        return $text;
    }

    /**
     * Truncate text by word boundary up to max characters
     */
    private function truncateByWords($text, $maxChars)
    {
        if ($maxChars <= 0) return '';

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') <= $maxChars) return $text;
            $slice = mb_substr($text, 0, $maxChars, 'UTF-8');
        } else {
            if (strlen($text) <= $maxChars) return $text;
            $slice = substr($text, 0, $maxChars);
        }

        // Avoid cutting in the middle of a word
        if (preg_match('/^(.+?)\b[\s\S]*$/u', $slice, $m)) {
            $slice = $m[1];
        }

        return rtrim($slice) . '...';
    }

    /**
     * Format short description: clean, limit lines and characters, keep line breaks
     */
    private function formatShortDescription($text, $maxChars = 160, $maxLines = 3)
    {
        $clean = $this->sanitizeDescription($text);

        // Split into lines
        $lines = explode("\n", $clean);
        $lines = array_filter($lines, function($l){ return trim($l) !== ''; });
        $lines = array_values($lines);

        // Keep up to maxLines
        $lines = array_slice($lines, 0, max(1, (int)$maxLines));

        // Join and apply char limit without breaking words
        $joined = implode("\n", $lines);
        $limited = $this->truncateByWords($joined, (int)$maxChars);

        // Escape HTML, then convert newlines to <br>
        $escaped = htmlspecialchars($limited, ENT_QUOTES, 'UTF-8');
        return nl2br($escaped);
    }

    /**
     * Format full description: clean and preserve paragraphs/line breaks
     */
    private function formatFullDescription($text)
    {
        $clean = $this->sanitizeDescription($text);
        $escaped = htmlspecialchars($clean, ENT_QUOTES, 'UTF-8');
        return nl2br($escaped);
    }

    /**
     * Escape HTML output for security
     */
    private function escapeHtml($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format date safely
     */
    private function formatDate($date)
    {
        try {
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                return 'Invalid date';
            }
            return date('Y/m/d H:i', $timestamp);
        } catch (Exception $e) {
            return 'Invalid date';
        }
    }

    /**
     * Validate file data structure
     */
    private function validateFileData($file)
    {
        $required_fields = ['id', 'filename'];
        foreach ($required_fields as $field) {
            if (!isset($file[$field]) || empty($file[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }
        return true;
    }

    /**
     * Get safe file ID
     */
    private function getSafeFileId($file)
    {
        $id = $file['id'] ?? 0;
        return is_numeric($id) ? (int)$id : 0;
    }

    /**
     * Cache frequently used data to improve performance
     */
    private static $cache = [];

    /**
     * Get cached data or compute and cache it
     */
    private function getCachedData($key, $callback)
    {
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = $callback();
        }
        return self::$cache[$key];
    }

    /**
     * Clear cache (useful for testing or memory management)
     */
    public static function clearCache()
    {
        self::$cache = [];
    }

    /**
     * Get file type category for better organization
     */
    private function getFileTypeCategory($extension)
    {
        $categories = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico', 'tiff'],
            'document' => ['pdf', 'doc', 'docx', 'rtf', 'odt', 'txt', 'md'],
            'spreadsheet' => ['xls', 'xlsx', 'csv', 'ods'],
            'presentation' => ['ppt', 'pptx', 'odp'],
            'code' => ['php', 'js', 'html', 'css', 'py', 'java', 'cpp', 'c', 'cs', 'rb', 'go', 'rs'],
            'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'],
            'audio' => ['mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a'],
            'video' => ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm'],
            'application' => ['exe', 'msi', 'deb', 'rpm', 'dmg', 'pkg', 'apk', 'ipa'],
        ];

        foreach ($categories as $category => $extensions) {
            if (in_array($extension, $extensions)) {
                return $category;
            }
        }

        return 'unknown';
    }

    /**
     * Get file priority for sorting (higher priority = more important)
     */
    private function getFilePriority($file)
    {
        $extension = strtolower(pathinfo($file['filename'] ?? '', PATHINFO_EXTENSION));
        $category = $this->getFileTypeCategory($extension);

        $priorities = [
            'document' => 100,
            'spreadsheet' => 90,
            'presentation' => 80,
            'code' => 70,
            'image' => 60,
            'video' => 50,
            'audio' => 40,
            'archive' => 30,
            'application' => 20,
            'unknown' => 10,
        ];

        return $priorities[$category] ?? 10;
    }
}
?>