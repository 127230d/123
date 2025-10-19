<?php
require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once 'dssssssssb.php';
require_once 'file_icon_helper.php';
require_once 'components/smart_file_card.php';
require_once 'includes/translation.php';

// Initialize translation system
$translation = Translation::getInstance();

// Set language (you can get this from user settings or session)
$language = isset($_SESSION['language']) ? $_SESSION['language'] : 'en';
$requestedLang = isset($_GET['lang']) ? strtolower($_GET['lang']) : null;
if ($requestedLang && in_array($requestedLang, ['en','ar'])) {
    $_SESSION['language'] = $requestedLang;
    $language = $requestedLang;
    // Redirect to clean URL without query param
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $redirectUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $redirectUrl);
    exit;
}
$translation->setLanguage($language);

// Helper function for translations
function t($key, $replacements = []) {
    return Translation::trans($key, $replacements);
}

// Function to get user avatar initials
function getUserAvatar($username)
{
    $initials = strtoupper(substr($username, 0, 2));
    return $initials;
}

// Function to move formatFileSize to file_icon_helper.php

// File icon functions moved to file_icon_helper.php

// timeAgo function moved to file_icon_helper.php

// Set a test user for demonstration
$_SESSION["username"] = "admin";
$username = "admin";

// Get user data including points
$user_query = mysqli_prepare($con, "SELECT * FROM login WHERE username = ?");
mysqli_stmt_bind_param($user_query, "s", $username);
mysqli_stmt_execute($user_query);
$user_result = mysqli_stmt_get_result($user_query);

$user_data = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($user_query); // Close the prepared statement

// Check if user exists, if not create a default user
if (!$user_data) {
    // Create default user data
    $user_data = [
        'id' => 1,
        'username' => $username,
        'points' => 1000,
        'subscription' => null
    ];
    $is_admin = false;
} else {
    $is_admin = isset($user_data['subscription']) && $user_data['subscription'] === 'admin';
}
?>

<!DOCTYPE html>
<?php $htmlDir = ($language === 'ar') ? 'rtl' : 'ltr'; ?>
<html lang="<?php echo htmlspecialchars($language); ?>" dir="<?php echo $htmlDir; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة وتداول الملفات | File Exchange System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="advanced-styles.css">
    <link rel="stylesheet" href="css/smart-card.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/terminal-enhancements.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/transaction-cards.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/theme-legacy-green.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/reduced-effects.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/preview-upload.css?v=<?php echo time(); ?>">
  
</head>

<body>
    <!-- Matrix Background Canvas -->
    <canvas class="matrix-bg" id="matrixCanvas"></canvas>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="container-fluid">
            <!-- Page Title -->
            <h1 class="page-title">
                <i class="fas fa-exchange-alt"></i>
                <?php echo t('system_name'); ?>
            </h1>

            <!-- Main Navigation Container - Flexible and centered -->
            <div class="main-nav-container">
                <!-- Navigation Menu -->
                <div class="nav-menu">
                    <a href="../index.php" class="nav-menu-item">
                        <i class="fas fa-home"></i>
                        <span><?php echo t('home'); ?></span>
                    </a>
                    <a href="#" class="nav-menu-item active" data-tab="browse" id="nav-browse">
                        <i class="fas fa-user"></i>
                        <span><?php echo t('profile'); ?></span>
                    </a>
                    <a href="#" class="nav-menu-item" data-tab="upload" id="nav-upload">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span><?php echo t('upload_new_file'); ?></span>
                    </a>
                    <a href="#" class="nav-menu-item" data-tab="my-files" id="nav-my-files">
                        <i class="fas fa-folder"></i>
                        <span><?php echo t('my_files'); ?></span>
                    </a>
                    <a href="#" class="nav-menu-item" data-tab="purchases" id="nav-purchases">
                        <i class="fas fa-shopping-bag"></i>
                        <span>My Purchases</span>
                    </a>
                    <a href="#" class="nav-menu-item" data-tab="transactions" id="nav-transactions">
                        <i class="fas fa-exchange-alt"></i>
                        <span><?php echo t('transactions'); ?></span>
                    </a>
                </div>


                <!-- Separator -->
                <div class="nav-separator"></div>

                <!-- Statistics Bar -->
                <div class="nav-stats">
                    <div class="nav-stat-item" title="My Published Files">
                        <i class="fas fa-folder-open"></i>
                        <span class="nav-stat-value">
                            <?php
                            $count_query = "SELECT COUNT(*) as count FROM shared_files WHERE original_owner_id = ? AND is_available = 1";
                            $count_stmt = mysqli_prepare($con, $count_query);
                            mysqli_stmt_bind_param($count_stmt, "s", $username);
                            mysqli_stmt_execute($count_stmt);
                            $count_result = mysqli_stmt_get_result($count_stmt);
                            $count_data = mysqli_fetch_assoc($count_result);
                            echo $count_data['count'];
                            ?>
                        </span>
                        <span class="nav-stat-label">Published</span>
                    </div>

                    <div class="nav-stat-item" title="My Purchases">
                        <i class="fas fa-shopping-bag"></i>
                        <span class="nav-stat-value">
                            <?php
                            $purchases_count_query = mysqli_prepare($con, "SELECT COUNT(*) as count FROM file_purchases WHERE buyer_username = ?");
                            mysqli_stmt_bind_param($purchases_count_query, "s", $username);
                            mysqli_stmt_execute($purchases_count_query);
                            $purchases_count_result = mysqli_stmt_get_result($purchases_count_query);
                            $purchases_count_data = mysqli_fetch_assoc($purchases_count_result);
                            echo $purchases_count_data['count'];
                            ?>
                        </span>
                        <span class="nav-stat-label">Purchases</span>
                    </div>

                    <div class="nav-stat-item" title="My Sales">
                        <i class="fas fa-chart-line"></i>
                        <span class="nav-stat-value">
                            <?php
                            $sales_count_query = mysqli_prepare($con, "SELECT COUNT(*) as count FROM file_purchases WHERE seller_username = ?");
                            mysqli_stmt_bind_param($sales_count_query, "s", $username);
                            mysqli_stmt_execute($sales_count_query);
                            $sales_count_result = mysqli_stmt_get_result($sales_count_query);
                            $sales_count_data = mysqli_fetch_assoc($sales_count_result);
                            echo $sales_count_data['count'];
                            ?>
                        </span>
                        <span class="nav-stat-label">Sales</span>
                    </div>
                </div>

                <!-- Separator -->
                <div class="nav-separator"></div>

                <!-- User Info Inline -->
                <div class="user-info-inline">
                    <div class="user-avatar-inline"><?php echo getUserAvatar($username); ?></div>
                    <div class="user-details-inline">
                        <span class="username-inline"><?php echo htmlspecialchars($username); ?></span>
                        <div class="points-inline">
                            <i class="fas fa-coins"></i>
                            <span class="points-count-inline"><?php echo number_format($user_data['points'] ?? 0); ?></span>

                            <!-- Mini Admin Controls next to points -->
                            <div class="mini-admin-controls">
                                <?php if ($is_admin): ?>
                                    <a href="admin.php" class="mini-btn" title="<?php echo t('admin_panel'); ?>">
                                        <i class="fas fa-cog"></i>
                                    </a>
                                    <a href="setup_system.php" class="mini-btn" title="<?php echo t('system_setup'); ?>">
                                        <i class="fas fa-tools"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="../logout.php" class="mini-btn danger" title="<?php echo t('logout'); ?>">
                                    <i class="fas fa-sign-out-alt"></i>
                                </a>
                                <span class="nav-sep"></span>
                                <a class="mini-btn" href="?lang=en" title="<?php echo t('english'); ?>">EN</a>
                                <a class="mini-btn" href="?lang=ar" title="<?php echo t('arabic'); ?>">AR</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Statistics bar has been merged with the top bar -->

        <!-- Navigation is now integrated in the top bar -->

        <!-- Tab Content -->
        <div class="tab-content" id="mainTabContent">
            <!-- Browse Available Files Tab -->
            <div class="tab-pane fade show active" id="browse" role="tabpanel">
                <!-- Enhanced Search and Filter Section -->
                <div class="filter-section">
                    <div class="search-bar-horizontal">
                        <!-- Search Input -->
                        <div class="search-input-group">
                            <input type="text" class="search-input" placeholder="<?php echo t('search_placeholder'); ?>"
                                id="searchFiles" onkeyup="filterFiles()" autocomplete="off"
                                aria-label="Search files"
                                aria-describedby="search-help"
                                role="searchbox"
                                aria-expanded="false"
                                aria-autocomplete="list">
                            <i class="fas fa-search search-icon"></i>
                            <button class="search-clear-btn" onclick="clearSearch()" style="display: none;">
                                <i class="fas fa-times"></i>
                            </button>
                            <!-- Search Suggestions Dropdown -->
                            <div class="search-suggestions" id="searchSuggestions" style="display: none;" 
                                 role="listbox" aria-label="Search suggestions">
                                <div class="suggestions-header">
                                    <i class="fas fa-lightbulb"></i>
                                    Search Suggestions
                                </div>
                                <div class="suggestions-list" id="suggestionsList" role="list"></div>
                                <div class="suggestions-footer">
                                    <small id="search-help">Use ↑↓ to navigate, Enter to select, / for search, F3 for repeat search</small>
                                </div>
                        </div>
                        
                            <!-- Search History Dropdown -->
                            <div class="search-history" id="searchHistory" style="display: none;" 
                                 role="listbox" aria-label="Search History">
                                <div class="history-header">
                                    <i class="fas fa-history"></i>
                                    Search History
                                    <button class="btn-clear-history" onclick="clearSearchHistory()" 
                                            aria-label="Clear Search History">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <div class="history-list" id="historyList" role="list"></div>
                            </div>
                        </div>
                        
                        <!-- Filters Row -->
                        <div class="filters-row">
                        <select class="filter-select" id="sortFiles" onchange="filterFiles()">
                            <option value="newest">Newest</option>
                            <option value="oldest">Oldest</option>
                            <option value="price-low">Price: Low to High</option>
                            <option value="price-high">Price: High to Low</option>
                            <option value="popular">Most Popular</option>
                                <option value="rating">Highest Rated</option>
                                <option value="name">Name: A-Z</option>
                        </select>
                            
                        <select class="filter-select" id="filterType" onchange="filterFiles()">
                            <option value=""><?php echo t('all_categories'); ?></option>
                            <option value="pdf"><?php echo t('pdf'); ?></option>
                            <option value="image"><?php echo t('image'); ?></option>
                            <option value="document"><?php echo t('document'); ?></option>
                            <option value="archive"><?php echo t('archive'); ?></option>
                                <option value="code"><?php echo t('code'); ?></option>
                            <option value="other"><?php echo t('other'); ?></option>
                        </select>
                            
                            <select class="filter-select" id="filterPrice" onchange="filterFiles()">
                                <option value=""><?php echo t('all_prices'); ?></option>
                                <option value="0-50"><?php echo t('price_0_50'); ?></option>
                                <option value="50-100"><?php echo t('price_50_100'); ?></option>
                                <option value="100-200"><?php echo t('price_100_200'); ?></option>
                                <option value="200+"><?php echo t('price_200'); ?></option>
                                <option value="0-50">0 - 50 Points</option>
                                <option value="50-100">50 - 100 Points</option>
                                <option value="100-200">100 - 200 Points</option>
                                <option value="200+">200+ Points</option>
                            </select>
                            
                            <!-- Advanced Filters (Initially Hidden) -->
                            <div class="advanced-filters" id="advancedFilters" style="display: none;">
                                <select class="filter-select" id="filterDate" onchange="filterFiles()">
                                    <option value="">All Dates</option>
                                    <option value="today">Today</option>
                                    <option value="week">This Week</option>
                                    <option value="month">This Month</option>
                                    <option value="year">This Year</option>
                                </select>
                                
                                <select class="filter-select" id="filterSize" onchange="filterFiles()">
                                    <option value="">All Sizes</option>
                                    <option value="small">Small (&lt; 1MB)</option>
                                    <option value="medium">Medium (1-10MB)</option>
                                    <option value="large">Large (10-100MB)</option>
                                    <option value="xlarge">Extra Large (&gt; 100MB)</option>
                                </select>
                                
                                <input type="text" class="filter-select" id="filterTags" placeholder="Search by tags..." 
                                       onkeyup="filterFiles()">
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="filter-actions">
                                <button class="btn-filter-toggle" onclick="toggleAdvancedFilters()">
                                    <i class="fas fa-sliders-h"></i>
                                    Filters
                                </button>
                                <button class="btn-filter-clear" onclick="clearAllFilters()">
                                    <i class="fas fa-times-circle"></i>
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Available Files Grid -->
                <?php
                // Pagination defaults and calculation
                $files_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 35;
                if ($files_per_page <= 0) $files_per_page = 35;
                $current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

                // Count total available files for pagination
                $count_files_stmt = mysqli_prepare($con, "SELECT COUNT(*) as cnt FROM shared_files WHERE is_available = 1");
                if ($count_files_stmt) {
                    mysqli_stmt_execute($count_files_stmt);
                    $count_files_res = mysqli_stmt_get_result($count_files_stmt);
                    $count_row = mysqli_fetch_assoc($count_files_res);
                    $total_files = isset($count_row['cnt']) ? (int)$count_row['cnt'] : 0;
                    mysqli_stmt_close($count_files_stmt);
                } else {
                    // Fallback if prepare fails
                    $total_files = 0;
                }

                $total_pages = max(1, (int)ceil($total_files / $files_per_page));
                $current_page = min($current_page, $total_pages);
                $offset = ($current_page - 1) * $files_per_page;

                // Fetch available files for browsing (limited by pagination)
                $available_files_query = "SELECT f.*, u.username AS owner_name, 
                    (SELECT COUNT(*) FROM file_purchases WHERE file_id = f.id AND buyer_username = ?) AS user_purchased
                    FROM shared_files f
                    JOIN login u ON u.id = f.original_owner_id
                    WHERE f.is_available = 1
                    ORDER BY f.created_at DESC
                    LIMIT " . intval($offset) . ", " . intval($files_per_page);
                $available_files_stmt = mysqli_prepare($con, $available_files_query);
                if ($available_files_stmt) {
                    mysqli_stmt_bind_param($available_files_stmt, "s", $username);
                    mysqli_stmt_execute($available_files_stmt);
                    $available_files_result = mysqli_stmt_get_result($available_files_stmt);
                } else {
                    $available_files_result = null;
                }

                // Load current user's files for "My Published Files" tab
                $user_files_query = "SELECT * FROM shared_files WHERE original_owner_id = ? ORDER BY created_at DESC";
                $user_files_stmt = mysqli_prepare($con, $user_files_query);
                if ($user_files_stmt) {
                    mysqli_stmt_bind_param($user_files_stmt, "s", $username);
                    mysqli_stmt_execute($user_files_stmt);
                    $user_files_result = mysqli_stmt_get_result($user_files_stmt);
                } else {
                    $user_files_result = null;
                }

                // Load user's purchases for "My Purchases" tab
                $purchases_query = "SELECT fp.*, sf.filename, sf.price as file_price, sf.original_owner_id, u.username AS seller_username
                    FROM file_purchases fp
                    LEFT JOIN shared_files sf ON sf.id = fp.file_id
                    LEFT JOIN login u ON u.id = sf.original_owner_id
                    WHERE fp.buyer_username = ?
                    ORDER BY fp.purchase_date DESC";
                $purchases_stmt = mysqli_prepare($con, $purchases_query);
                if ($purchases_stmt) {
                    mysqli_stmt_bind_param($purchases_stmt, "s", $username);
                    mysqli_stmt_execute($purchases_stmt);
                    $purchases_result = mysqli_stmt_get_result($purchases_stmt);
                } else {
                    $purchases_result = null;
                }

                // Load transaction history (both purchases and sales) for the current user
                $transactions_query = "SELECT fp.*, sf.filename,
                    CASE WHEN fp.buyer_username = ? THEN 'purchase' WHEN u.username = ? THEN 'sale' ELSE 'other' END as type,
                    CASE WHEN fp.buyer_username = ? THEN (SELECT username FROM login WHERE id = sf.original_owner_id) ELSE fp.buyer_username END as other_party
                    FROM file_purchases fp
                    LEFT JOIN shared_files sf ON sf.id = fp.file_id
                    LEFT JOIN login u ON u.username = fp.seller_username
                    WHERE fp.buyer_username = ? OR fp.seller_username = ?
                    ORDER BY fp.purchase_date DESC";
                $transactions_stmt = mysqli_prepare($con, $transactions_query);
                if ($transactions_stmt) {
                    // Bind the username to the multiple placeholders
                    mysqli_stmt_bind_param($transactions_stmt, "sssss", $username, $username, $username, $username, $username);
                    mysqli_stmt_execute($transactions_stmt);
                    $transactions_result = mysqli_stmt_get_result($transactions_stmt);
                } else {
                    $transactions_result = null;
                }
                ?>
                <div class="files-grid" id="availableFilesGrid">
                    <?php while ($file = mysqli_fetch_assoc($available_files_result)): ?>
                        <?php
                        // Prepare file data for smart card
                        $file['username'] = $file['owner_name'];
                        $file['upload_date'] = $file['created_at'];
                        $file['views'] = $file['views'] ?? 0;
                        $file['downloads'] = $file['downloads'] ?? 0;
                        $file['rating'] = $file['rating'] ?? 0;
                        $file['sales'] = $file['sales_count'];
                        $file['status'] = 'active';
                        
                        // Check if user purchased this file
                        $is_purchased = ($file['user_purchased'] > 0);
                        
                        // Create and render smart card
                        $smart_card = new SmartFileCard($file, 'browse', $user_data['id'], $is_purchased);
                        echo $smart_card->render();
                        ?>
                    <?php endwhile; ?>
                </div>

                <!-- Enhanced Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-header">
                            <div class="pagination-info">
                                <span class="page-info-text">
                                    <i class="fas fa-info-circle"></i>
                                    Showing page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                                    (<?php echo $total_files; ?> files total)
                                </span>
                            </div>
                            
                            <div class="pagination-controls">
                                <select class="page-size-select" onchange="changePageSize(this.value)">
                                    <option value="35" <?php echo ($files_per_page == 35) ? 'selected' : ''; ?>>35 files per page</option>
                                    <option value="50" <?php echo ($files_per_page == 50) ? 'selected' : ''; ?>>50 files per page</option>
                                    <option value="100" <?php echo ($files_per_page == 100) ? 'selected' : ''; ?>>100 files per page</option>
                                </select>
                            </div>
                        </div>
                        
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <!-- First Page Button -->
                                <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1" aria-label="First page" title="First page">
                                            <i class="fas fa-angle-double-right"></i>
                                            <span class="page-text">الأولى</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <!-- Previous Page Button -->
                                <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($current_page - 1); ?>" aria-label="الصفحة السابقة" title="الصفحة السابقة">
                                            <i class="fas fa-angle-right"></i>
                                            <span class="page-text">السابقة</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);

                                // Ensure we show at least 5 pages when possible
                                if ($end_page - $start_page < 4) {
                                    if ($start_page == 1) {
                                        $end_page = min($total_pages, $start_page + 4);
                                    } else {
                                        $start_page = max(1, $end_page - 4);
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                        <?php if ($i == $current_page): ?>
                                            <span class="page-link current">
                                                <i class="fas fa-circle"></i>
                                                <span class="page-number"><?php echo $i; ?></span>
                                            </span>
                                        <?php else: ?>
                                            <a class="page-link" href="?page=<?php echo $i; ?>" title="الصفحة <?php echo $i; ?>">
                                                <span class="page-number"><?php echo $i; ?></span>
                                            </a>
                                        <?php endif; ?>
                                    </li>
                                <?php endfor; ?>

                                <!-- Next Page Button -->
                                <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($current_page + 1); ?>" aria-label="الصفحة التالية" title="الصفحة التالية">
                                            <span class="page-text">التالية</span>
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <!-- Last Page Button -->
                                <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $total_pages; ?>" aria-label="الصفحة الأخيرة" title="الصفحة الأخيرة">
                                            <span class="page-text">الأخيرة</span>
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>

                <?php if (mysqli_num_rows($available_files_result) == 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-store-slash"></i>
                        <h3>لا توجد ملفات متاحة للشراء حالياً</h3>
                        <p>كن أول من ينشر ملف للبيع!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upload New File Tab -->
            <div class="tab-pane fade" id="upload" role="tabpanel">
                <div class="tab-header">
                    <h2><i class="fas fa-cloud-upload-alt"></i> رفع ملف جديد</h2>
                    <p>شارك ملفاتك مع المجتمع واربح النقاط</p>
                </div>

                <form class="upload-form" id="uploadForm" enctype="multipart/form-data" method="post" action="upload_file.php">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-file"></i> اختيار الملف
                        </label>
                        <div class="file-drop-zone" onclick="document.getElementById('fileUpload').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div class="drop-text">اضغط هنا لاختيار الملف</div>
                            <div class="drop-subtext">أو قم بسحب الملف هنا</div>
                            <input type="file" id="fileUpload" name="fileUpload" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif,.zip,.rar,.7z,.sql,.json,.csv,.xls,.xlsx,.ppt,.pptx,.mp3,.mp4" style="display: none;" onchange="displaySelectedFile(this)">
                        </div>
                        <div id="selectedFileDisplay" class="mt-3" style="display: none;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tag"></i> اسم الملف
                        </label>
                        <input type="text" class="form-control" name="filename" placeholder="أدخل اسم الملف..." required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-align-left"></i> وصف الملف
                        </label>
                        <textarea class="form-control" name="description" rows="4" placeholder="اكتب وصفاً مفصلاً للملف..."></textarea>
                    </div>

                    <!-- Preview Section - Mandatory -->
                    <div class="form-group preview-section">
                        <label class="form-label required">
                            <i class="fas fa-eye"></i> معاينة الملف (إجباري)
                            <span class="required-indicator">*</span>
                        </label>
                        
                        <!-- Preview Type Selection -->
                        <div class="preview-type-selector">
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="preview_type" value="text" id="previewTypeText" checked>
                                    <span class="radio-custom"></span>
                                    <i class="fas fa-file-alt"></i>
                                    نص المعاينة
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="preview_type" value="image" id="previewTypeImage">
                                    <span class="radio-custom"></span>
                                    <i class="fas fa-image"></i>
                                    صورة المعاينة
                                </label>
                            </div>
                        </div>

                        <!-- Text Preview Section -->
                        <div class="preview-content-section" id="textPreviewSection">
                            <div class="preview-input-container">
                                <textarea class="form-control" name="preview_text" id="previewText" rows="6"
                                    placeholder="اكتب نص المعاينة أو استخرج عينة تلقائية من الملف..." required></textarea>
                                <div class="preview-actions">
                                    <button type="button" class="preview-btn" id="extractPreviewBtn" onclick="extractPreviewFromFile()" disabled>
                                        <i class="fas fa-magic"></i> استخراج عينة تلقائية
                                    </button>
                                    <button type="button" class="preview-btn secondary" onclick="clearTextPreview()">
                                        <i class="fas fa-eraser"></i> مسح
                                    </button>
                                </div>
                            </div>
                            <div class="char-counter">
                                <span id="textCharCount">0</span> / 500 حرف
                            </div>
                        </div>

                        <!-- Image Preview Section -->
                        <div class="preview-content-section" id="imagePreviewSection" style="display: none;">
                            <div class="image-upload-container">
                                <div class="image-drop-zone" onclick="document.getElementById('previewImageUpload').click()">
                                    <i class="fas fa-image"></i>
                                    <div class="drop-text">اضغط لاختيار صورة المعاينة</div>
                                    <div class="drop-subtext">JPG, PNG, GIF - حد أقصى 2MB</div>
                                    <input type="file" id="previewImageUpload" name="preview_image" 
                                           accept=".jpg,.jpeg,.png,.gif" style="display: none;" 
                                           onchange="displayPreviewImage(this)" required>
                                </div>
                                <div id="previewImageDisplay" class="preview-image-display" style="display: none;">
                                    <img id="previewImagePreview" src="" alt="معاينة الصورة">
                                    <div class="image-actions">
                                        <button type="button" class="preview-btn secondary" onclick="removePreviewImage()">
                                            <i class="fas fa-trash"></i> إزالة الصورة
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Live Preview Display -->
                        <div class="live-preview-container">
                            <div class="live-preview-header">
                                <i class="fas fa-eye"></i>
                                معاينة مباشرة
                            </div>
                            <div class="live-preview-content" id="livePreviewContent">
                                <div class="preview-placeholder">
                                    <i class="fas fa-eye-slash"></i>
                                    لا توجد معاينة متاحة
                                </div>
                            </div>
                        </div>

                        <div class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            المعاينة إجبارية وستظهر للمستخدمين قبل الشراء
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-dollar-sign"></i> سعر البيع (بالنقاط)
                        </label>
                        <div class="price-input-group">
                            <input type="number" class="form-control" name="price" min="1" max="10000"
                                placeholder="أدخل السعر..." required style="border-radius: 10px 0 0 10px;">
                            <div class="price-currency">نقطة</div>
                        </div>
                    </div>

                    <button type="submit" class="action-btn primary">
                        <i class="fas fa-upload"></i>
                        رفع الملف للمراجعة
                    </button>
                </form>
            </div>


            <!-- My Published Files Tab -->
            <div class="tab-pane fade" id="my-files" role="tabpanel">
                <div class="tab-header">
                    <h2><i class="fas fa-folder-open"></i> ملفاتي المنشورة</h2>
                    <p>إدارة ومتابعة الملفات التي نشرتها</p>
                </div>

                <div class="files-grid" id="myFilesGrid">
                    <?php if ($user_files_result && mysqli_num_rows($user_files_result) > 0): ?>
                        <?php mysqli_data_seek($user_files_result, 0); ?>
                        <?php while ($file = mysqli_fetch_assoc($user_files_result)): ?>
                        <?php
                        // Prepare file data for smart card
                        $file['username'] = $username; // Current user
                        $file['upload_date'] = $file['created_at'];
                        $file['views'] = $file['views'] ?? 0;
                        $file['downloads'] = $file['downloads'] ?? 0;
                        $file['rating'] = $file['rating'] ?? 0;
                        $file['sales'] = $file['sales_count'];
                        
                        
                        // Create and render smart card
                        $smart_card = new SmartFileCard($file, 'my_files', $user_data['id'], false);
                        echo $smart_card->render();
                        ?>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <?php if (!($user_files_result && mysqli_num_rows($user_files_result) > 0)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-plus"></i>
                        <h3>لم تقم برفع أي ملفات بعد</h3>
                        <p>ابدأ برفع ملفك الأول وشاركه مع المجتمع!</p>
                        <button class="action-btn primary mt-3" onclick="showTab('upload')">
                            <i class="fas fa-plus"></i>
                            <span class="btn-text">رفع ملف جديد</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Purchases Tab -->
            <div class="tab-pane fade" id="purchases" role="tabpanel">
                <div class="tab-header">
                    <h2><i class="fas fa-shopping-bag"></i> مشترياتي</h2>
                    <p>الملفات التي اشتريتها ومتاحة للتحميل</p>
                </div>

                <div class="files-grid" id="purchasesGrid">
                    <?php if ($purchases_result && mysqli_num_rows($purchases_result) > 0): ?>
                        <?php while ($purchase = mysqli_fetch_assoc($purchases_result)): ?>
                        <?php
                        // Prepare file data for smart card
                        $purchase['username'] = $purchase['seller_username'];
                        $purchase['upload_date'] = $purchase['purchase_date'];
                        $purchase['views'] = $purchase['views'] ?? 0;
                        $purchase['downloads'] = $purchase['downloads'] ?? 0;
                        $purchase['rating'] = $purchase['rating'] ?? 0;
                        $purchase['sales'] = 0; // Not relevant for purchases
                        $purchase['purchase_price'] = $purchase['paid_price'];
                        
                        // Create and render smart card
                        $smart_card = new SmartFileCard($purchase, 'purchases', $user_data['id'], true);
                        echo $smart_card->render();
                        ?>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <?php if (!($purchases_result && mysqli_num_rows($purchases_result) > 0)): ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>لم تشتري أي ملفات بعد</h3>
                        <p>تصفح المتجر واشتري ملفك الأول!</p>
                        <button class="action-btn primary mt-3" onclick="showTab('browse')">
                            <i class="fas fa-store"></i>
                            <span class="btn-text">تصفح المتجر</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Transaction History Tab -->
            <div class="tab-pane fade" id="transactions" role="tabpanel">
                <div class="tab-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                        <div>
                            <h2><i class="fas fa-history"></i> سجل المعاملات</h2>
                            <p>تاريخ جميع عمليات الشراء والبيع</p>
                        </div>
                        <?php
                        // عدّ المعاملات للمستخدم (مع حماية من نتائج فارغة)
                        $transaction_count = 0;
                        if ($transactions_result && mysqli_num_rows($transactions_result) > 0) {
                            mysqli_data_seek($transactions_result, 0);
                            while (mysqli_fetch_assoc($transactions_result)) {
                                $transaction_count++;
                            }
                            mysqli_data_seek($transactions_result, 0);
                        }

                        if ($transaction_count > 0):
                        ?>
                            <button class="clear-history-btn" onclick="confirmClearHistory()" style="margin-top: 10px;" title="مسح جميع سجلات المعاملات نهائياً">
                                <i class="fas fa-fire"></i>
                                مسح السجل نهائياً
                                <span style="font-size: 0.8em; opacity: 0.8; margin-left: 5px; background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 10px;"><?php echo $transaction_count; ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                // حساب الإحصائيات (مع حماية من نتائج فارغة)
                $total_income = 0;
                $total_expense = 0;
                $sale_count = 0;
                $purchase_count = 0;

                if ($transactions_result && mysqli_num_rows($transactions_result) > 0) {
                    mysqli_data_seek($transactions_result, 0);
                    while ($transaction = mysqli_fetch_assoc($transactions_result)) {
                        if ($transaction['type'] == 'sale') {
                            $total_income += $transaction['price'];
                            $sale_count++;
                        } else {
                            $total_expense += $transaction['price'];
                            $purchase_count++;
                        }
                    }
                    mysqli_data_seek($transactions_result, 0);
                }
                ?>

                <!-- إحصائيات المعاملات -->
                <div class="transaction-stats">
                    <div class="stat-card">
                        <div class="stat-value income">+<?php echo number_format($total_income); ?></div>
                        <div class="stat-label">إجمالي الدخل</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value expense">-<?php echo number_format($total_expense); ?></div>
                        <div class="stat-label">إجمالي المصروف</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value income"><?php echo $sale_count; ?></div>
                        <div class="stat-label">عمليات البيع</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value expense"><?php echo $purchase_count; ?></div>
                        <div class="stat-label">عمليات الشراء</div>
                    </div>
                </div>

                <!-- فلاتر المعاملات -->
                <div class="transaction-filters">
                    <button class="filter-btn active" onclick="filterTransactions('all')">
                        <i class="fas fa-list"></i> جميع المعاملات
                    </button>
                    <button class="filter-btn" onclick="filterTransactions('sale')">
                        <i class="fas fa-arrow-up"></i> البيع فقط
                    </button>
                    <button class="filter-btn" onclick="filterTransactions('purchase')">
                        <i class="fas fa-arrow-down"></i> الشراء فقط
                    </button>
                </div>

                <!-- شبكة بطاقات المعاملات -->
                <div class="transactions-grid" id="transactionsGrid">
                            <?php while ($transaction = mysqli_fetch_assoc($transactions_result)): ?>
                        <div class="transaction-card <?php echo $transaction['type']; ?>" data-type="<?php echo $transaction['type']; ?>">
                            <!-- رأس البطاقة -->
                            <div class="transaction-header">
                                <div class="transaction-type <?php echo $transaction['type']; ?>">
                                            <i class="fas fa-<?php echo $transaction['type'] == 'sale' ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                            <?php echo $transaction['type'] == 'sale' ? 'بيع' : 'شراء'; ?>
                                </div>
                                <div class="transaction-amount <?php echo $transaction['type'] == 'sale' ? 'positive' : 'negative'; ?>">
                                            <?php echo $transaction['type'] == 'sale' ? '+' : '-'; ?><?php echo number_format($transaction['price']); ?> نقطة
                                </div>
                            </div>

                            <!-- معلومات الملف -->
                            <div class="transaction-main-info">
                                <div class="transaction-file-info">
                                    <div class="transaction-file-icon">
                                        <i class="fas fa-file"></i>
                                    </div>
                                    <div class="transaction-file-details">
                                        <div class="transaction-file-name">
                                            <?php echo htmlspecialchars($transaction['filename']); ?>
                                        </div>
                                        <div class="transaction-file-size">
                                            <i class="fas fa-info-circle"></i>
                                            <?php echo $transaction['type'] == 'sale' ? 'تم بيعه بنجاح' : 'تم شراؤه بنجاح'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- تفاصيل المعاملة -->
                            <div class="transaction-details">
                                <div class="transaction-detail-item">
                                    <div class="transaction-detail-label">
                                        <i class="fas fa-user"></i>
                                        <?php echo $transaction['type'] == 'sale' ? 'المشتري' : 'البائع'; ?>
                                    </div>
                                    <div class="transaction-detail-value"><?php echo htmlspecialchars($transaction['other_party']); ?></div>
                                </div>
                                <div class="transaction-detail-item">
                                    <div class="transaction-detail-label">
                                        <i class="fas fa-tag"></i>
                                        نوع العملية
                                    </div>
                                    <div class="transaction-detail-value"><?php echo $transaction['type'] == 'sale' ? 'دخل' : 'مصروف'; ?></div>
                                </div>
                                <div class="transaction-detail-item">
                                    <div class="transaction-detail-label">
                                        <i class="fas fa-coins"></i>
                                        المبلغ
                                    </div>
                                    <div class="transaction-detail-value"><?php echo number_format($transaction['price']); ?> نقطة</div>
                                </div>
                                <div class="transaction-detail-item">
                                    <div class="transaction-detail-label">
                                        <i class="fas fa-calendar"></i>
                                        التاريخ الكامل
                                    </div>
                                    <div class="transaction-detail-value"><?php echo date('Y-m-d H:i', strtotime($transaction['date'])); ?></div>
                                </div>
                            </div>

                            <!-- التاريخ النسبي -->
                            <div class="transaction-date">
                                <i class="fas fa-clock"></i>
                                <?php echo timeAgo($transaction['date']); ?>
                            </div>
                        </div>
                            <?php endwhile; ?>
                </div>

                <?php mysqli_data_seek($transactions_result, 0); ?>
                <?php if (mysqli_num_rows($transactions_result) == 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt" style="font-size: 4rem; color: var(--text-gray); margin-bottom: 20px;"></i>
                        <h3 style="color: var(--text-light); margin-bottom: 15px;">لا توجد معاملات بعد</h3>
                        <p style="color: var(--text-gray); margin-bottom: 20px; line-height: 1.6;">
                            سيظهر هنا تاريخ جميع عمليات الشراء والبيع الخاصة بك.<br>
                            ابدأ بشراء أو بيع الملفات لرؤية سجل المعاملات.
                        </p>
                        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                            <button class="action-btn primary" onclick="showTab('browse')" style="margin-top: 10px;">
                                <i class="fas fa-store"></i>
                                <span class="btn-text">تصفح المتجر</span>
                            </button>
                            <button class="action-btn" onclick="showTab('upload')" style="margin-top: 10px;">
                                <i class="fas fa-upload"></i>
                                <span class="btn-text">رفع ملف جديد</span>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notification Area -->
    <div id="notificationArea"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="file-system.js"></script>
    <script>
        // Preview Text Extraction Functions
        // Legacy delegators for earlier calls: they forward to the unified preview handlers below.
        function extractPreviewFromFile() {
            if (typeof extractPreviewFromFileMain === 'function') return extractPreviewFromFileMain();
            showNotification('ميزة استخراج المعاينة غير متاحة حالياً', 'info');
        }

        function clearPreview() {
            if (typeof clearTextPreview === 'function') return clearTextPreview();
            const el = document.getElementById('previewText');
            if (el) el.value = '';
            showNotification('تم مسح نص المعاينة', 'info');
        }

        // Enable/disable extract button when file is selected
        function displaySelectedFile(input) {
            const extractBtn = document.getElementById('extractPreviewBtn');
            const selectedDisplay = document.getElementById('selectedFileDisplay');

            if (input.files && input.files.length > 0) {
                const file = input.files[0];
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                const fileName = file.name;

                selectedDisplay.innerHTML = `
                    <div class="selected-file-info">
                        <div class="file-icon">
                            <i class="fas fa-file" style="color: var(--primary-green);"></i>
                        </div>
                        <div class="file-details">
                            <div class="file-name">${fileName}</div>
                            <div class="file-size">${fileSize} MB</div>
                        </div>
                    </div>
                `;
                selectedDisplay.style.display = 'block';
                extractBtn.disabled = false;
            } else {
                selectedDisplay.style.display = 'none';
                extractBtn.disabled = true;
            }
        }

        // Enhanced Browse Files Functions
        let currentViewMode = 'grid';
        let allFiles = [];
        let filteredFiles = [];

        // Initialize files data
        function initializeFiles() {
            const fileCards = document.querySelectorAll('#availableFilesGrid .smart-file-card');
            allFiles = Array.from(fileCards).map(card => ({
                element: card,
                name: card.querySelector('.file-title')?.textContent?.toLowerCase() || '',
                type: getFileType(card),
                price: getFilePrice(card),
                rating: getFileRating(card),
                sales: getFileSales(card),
                date: getFileDate(card)
            }));
            filteredFiles = [...allFiles];
            updateResultsInfo();
        }

        function getFileType(card) {
            const icon = card.querySelector('.file-icon-container i');
            if (icon) {
                const classList = Array.from(icon.classList);
                if (classList.some(c => c.includes('pdf'))) return 'pdf';
                if (classList.some(c => c.includes('image'))) return 'image';
                if (classList.some(c => c.includes('document'))) return 'document';
                if (classList.some(c => c.includes('archive'))) return 'archive';
                if (classList.some(c => c.includes('code'))) return 'code';
            }
            return 'other';
        }

        function getFilePrice(card) {
            const priceElement = card.querySelector('.file-price');
            if (priceElement) {
                const priceText = priceElement.textContent.replace(/[^\d]/g, '');
                return parseInt(priceText) || 0;
            }
            return 0;
        }

        function getFileRating(card) {
            const ratingElement = card.querySelector('.file-rating');
            if (ratingElement) {
                const ratingText = ratingElement.textContent.replace(/[^\d.]/g, '');
                return parseFloat(ratingText) || 0;
            }
            return 0;
        }

        function getFileSales(card) {
            const salesElement = card.querySelector('.file-sales');
            if (salesElement) {
                const salesText = salesElement.textContent.replace(/[^\d]/g, '');
                return parseInt(salesText) || 0;
            }
            return 0;
        }

        function getFileDate(card) {
            const dateElement = card.querySelector('.file-date');
            if (dateElement) {
                return new Date(dateElement.textContent);
            }
            return new Date();
        }

        // Enhanced filter function with debouncing
        let searchTimeout;
        function filterFiles() {
            const searchTerm = document.getElementById('searchFiles').value.toLowerCase();
            const sortBy = document.getElementById('sortFiles').value;
            const filterType = document.getElementById('filterType').value;
            const filterPrice = document.getElementById('filterPrice').value;
            const filterDate = document.getElementById('filterDate').value;
            const filterSize = document.getElementById('filterSize').value;
            const filterTags = document.getElementById('filterTags').value.toLowerCase();

            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            // Show suggestions if search term is not empty
            if (searchTerm.length > 0) {
                showSearchSuggestions(searchTerm);
            } else {
                hideSearchSuggestions();
            }

            // Show loading
            showLoading();

            // Debounce search for better performance
            searchTimeout = setTimeout(() => {
                // Save search to history
                if (searchTerm.trim()) {
                    saveSearchHistory(searchTerm);
                }
                
                // Check cache first
                const filters = { filterType, filterPrice, filterDate, filterSize, filterTags, sortBy };
                const cachedResults = getCachedSearchResults(searchTerm, filters);
                
                if (cachedResults) {
                    filteredFiles = cachedResults;
                    updateFileDisplay();
                    updateResultsInfo();
                    hideLoading();
                    return;
                }
                
                // Filter files with improved search algorithm
                const startTime = performance.now();
                filteredFiles = allFiles.filter(file => {
                    const matchesSearch = advancedSearch(file, searchTerm);
                    const matchesType = !filterType || file.type === filterType;
                    const matchesPrice = !filterPrice || checkPriceRange(file.price, filterPrice);
                    const matchesDate = !filterDate || checkDateRange(file.date, filterDate);
                    const matchesSize = !filterSize || checkSizeRange(file.size, filterSize);
                    const matchesTags = !filterTags || checkTagsMatch(file.tags, filterTags);
                    
                    return matchesSearch && matchesType && matchesPrice && matchesDate && matchesSize && matchesTags;
                });

                // Sort files
                sortFiles(sortBy);

                // Cache results
                setCachedSearchResults(searchTerm, filters, [...filteredFiles]);
                
                // Log performance metrics
                const endTime = performance.now();
                console.log(`Search completed in ${(endTime - startTime).toFixed(2)}ms`);
                
                // Track search analytics
                trackSearch(searchTerm, filteredFiles.length);

                // Update display with highlighting
                updateFileDisplay();
                updateResultsInfo();
                hideLoading();
            }, 300);
        }

        // Advanced search function with fuzzy matching and performance optimization
        function advancedSearch(file, searchTerm) {
            if (!searchTerm) return true;
            
            const searchFields = [
                file.name,
                file.description || '',
                file.owner || '',
                file.tags ? file.tags.join(' ') : ''
            ];
            
            const searchText = searchFields.join(' ').toLowerCase();
            const searchLower = searchTerm.toLowerCase();
            
            // Quick exact match check
            if (searchText.includes(searchLower)) return true;
            
            // Performance optimization: limit fuzzy matching for very long search terms
            if (searchLower.length > 50) {
                return searchText.includes(searchLower);
            }
            
            // Fuzzy match for better results with word-based matching
            const words = searchLower.split(/\s+/).filter(word => word.length > 0);
            if (words.length === 0) return true;
            
            // Use more efficient matching algorithm
            return words.every(word => {
                if (word.length < 2) return true; // Skip very short words
                return searchFields.some(field => 
                    field.toLowerCase().includes(word)
                );
            });
        }
        
        // Debounce utility for better performance
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
        
        // Optimized search with caching
        const searchCache = new Map();
        const CACHE_SIZE = 100;
        
        function getCachedSearchResults(searchTerm, filters) {
            const cacheKey = `${searchTerm}_${JSON.stringify(filters)}`;
            return searchCache.get(cacheKey);
        }
        
        function setCachedSearchResults(searchTerm, filters, results) {
            const cacheKey = `${searchTerm}_${JSON.stringify(filters)}`;
            
            // Limit cache size
            if (searchCache.size >= CACHE_SIZE) {
                const firstKey = searchCache.keys().next().value;
                searchCache.delete(firstKey);
            }
            
            searchCache.set(cacheKey, results);
        }

        function checkPriceRange(price, range) {
            switch (range) {
                case '0-50': return price >= 0 && price <= 50;
                case '50-100': return price >= 50 && price <= 100;
                case '100-200': return price >= 100 && price <= 200;
                case '200+': return price >= 200;
                default: return true;
            }
        }

        function checkDateRange(fileDate, range) {
            const now = new Date();
            const fileDateObj = new Date(fileDate);
            const diffTime = now - fileDateObj;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            switch (range) {
                case 'today': return diffDays <= 1;
                case 'week': return diffDays <= 7;
                case 'month': return diffDays <= 30;
                case 'year': return diffDays <= 365;
                default: return true;
            }
        }

        function checkSizeRange(fileSize, range) {
            // Convert file size to MB for comparison
            const sizeInMB = parseFileSize(fileSize);
            
            switch (range) {
                case 'small': return sizeInMB < 1;
                case 'medium': return sizeInMB >= 1 && sizeInMB < 10;
                case 'large': return sizeInMB >= 10 && sizeInMB < 100;
                case 'xlarge': return sizeInMB >= 100;
                default: return true;
            }
        }

        function parseFileSize(sizeString) {
            if (!sizeString) return 0;
            
            const size = parseFloat(sizeString);
            const unit = sizeString.toLowerCase().replace(/[0-9.]/g, '');
            
            switch (unit) {
                case 'kb': return size / 1024;
                case 'mb': return size;
                case 'gb': return size * 1024;
                default: return size / (1024 * 1024); // Assume bytes
            }
        }

        function checkTagsMatch(fileTags, searchTags) {
            if (!fileTags || !searchTags) return true;
            
            const tags = Array.isArray(fileTags) ? fileTags : fileTags.split(',').map(tag => tag.trim());
            const searchTerms = searchTags.split(',').map(term => term.trim().toLowerCase());
            
            return searchTerms.every(term => 
                tags.some(tag => tag.toLowerCase().includes(term))
            );
        }

        function sortFiles(sortBy) {
            filteredFiles.sort((a, b) => {
                switch (sortBy) {
                    case 'newest':
                        return b.date - a.date;
                    case 'oldest':
                        return a.date - b.date;
                    case 'price-low':
                        return a.price - b.price;
                    case 'price-high':
                        return b.price - a.price;
                    case 'popular':
                        return b.sales - a.sales;
                    case 'rating':
                        return b.rating - a.rating;
                    case 'name':
                        return a.name.localeCompare(b.name);
                    default:
                        return 0;
                }
            });
        }

        function updateFileDisplay() {
            const grid = document.getElementById('availableFilesGrid');
            
            // Hide all files
            allFiles.forEach(file => {
                file.element.style.display = 'none';
            });

            // Show filtered files with animation and highlighting
            filteredFiles.forEach((file, index) => {
                file.element.style.display = 'block';
                file.element.style.animation = 'fadeInUp 0.5s ease forwards';
                file.element.style.animationDelay = `${index * 0.1}s`;
                
                // Add search highlighting
                highlightSearchTerms(file.element);
            });
        }

        // Search suggestions functionality
        function showSearchSuggestions(searchTerm) {
            const suggestionsContainer = document.getElementById('searchSuggestions');
            const suggestionsList = document.getElementById('suggestionsList');
            
            if (!suggestionsContainer || !suggestionsList) return;
            
            // Generate suggestions based on file names and types
            const suggestions = generateSuggestions(searchTerm);
            
            if (suggestions.length === 0) {
                hideSearchSuggestions();
                return;
            }
            
            // Clear previous suggestions
            suggestionsList.innerHTML = '';
            
            // Add suggestions to the list
            suggestions.forEach((suggestion, index) => {
                const suggestionItem = document.createElement('div');
                suggestionItem.className = 'suggestion-item';
                suggestionItem.innerHTML = `
                    <i class="fas ${suggestion.icon} suggestion-icon"></i>
                    <span class="suggestion-text">${highlightText(suggestion.text, searchTerm)}</span>
                    <span class="suggestion-type">${suggestion.type}</span>
                `;
                
                suggestionItem.addEventListener('click', () => {
                    selectSuggestion(suggestion.text);
                });
                
                suggestionsList.appendChild(suggestionItem);
            });
            
            suggestionsContainer.style.display = 'block';
        }

        function hideSearchSuggestions() {
            const suggestionsContainer = document.getElementById('searchSuggestions');
            if (suggestionsContainer) {
                suggestionsContainer.style.display = 'none';
            }
        }

        function generateSuggestions(searchTerm) {
            const suggestions = [];
            const searchLower = searchTerm.toLowerCase();
            
            // Get unique file names that match
            const matchingNames = [...new Set(
                allFiles
                    .filter(file => file.name.toLowerCase().includes(searchLower))
                    .map(file => file.name)
            )].slice(0, 5);
            
            matchingNames.forEach(name => {
                suggestions.push({
                    text: name,
                    type: 'اسم ملف',
                    icon: 'fa-file'
                });
            });
            
            // Get file types that match
            const matchingTypes = [...new Set(
                allFiles
                    .filter(file => file.type && file.type.toLowerCase().includes(searchLower))
                    .map(file => file.type)
            )].slice(0, 3);
            
            matchingTypes.forEach(type => {
                suggestions.push({
                    text: type,
                    type: 'نوع ملف',
                    icon: 'fa-tag'
                });
            });
            
            // Get popular search terms from analytics
            const popularTerms = getPopularSearchTerms();
            popularTerms
                .filter(term => term.toLowerCase().includes(searchLower))
                .slice(0, 2)
                .forEach(term => {
                    suggestions.push({
                        text: term,
                        type: 'شائع',
                        icon: 'fa-fire'
                    });
                });
            
            return suggestions.slice(0, 8); // Limit to 8 suggestions
        }
        
        function getPopularSearchTerms() {
            const searchAnalytics = JSON.parse(localStorage.getItem('searchAnalytics') || '{}');
            const termCounts = {};
            
            // Aggregate search counts across all days
            Object.values(searchAnalytics).forEach(dayData => {
                Object.entries(dayData).forEach(([term, data]) => {
                    if (!termCounts[term]) {
                        termCounts[term] = 0;
                    }
                    termCounts[term] += data.count;
                });
            });
            
            // Sort by popularity and return top terms
            return Object.entries(termCounts)
                .sort(([,a], [,b]) => b - a)
                .slice(0, 10)
                .map(([term]) => term);
        }
        
        function getSearchAnalytics() {
            const searchAnalytics = JSON.parse(localStorage.getItem('searchAnalytics') || '{}');
            const totalSearches = Object.values(searchAnalytics)
                .reduce((total, dayData) => {
                    return total + Object.values(dayData).reduce((dayTotal, termData) => dayTotal + termData.count, 0);
                }, 0);
            
            const popularTerms = getPopularSearchTerms();
            
            return {
                totalSearches,
                popularTerms,
                analytics: searchAnalytics
            };
        }

        function highlightText(text, searchTerm) {
            if (!searchTerm) return text;
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            return text.replace(regex, '<span class="search-highlight">$1</span>');
        }

        function selectSuggestion(suggestionText) {
            document.getElementById('searchFiles').value = suggestionText;
            hideSearchSuggestions();
            filterFiles();
        }

        function highlightSearchTerms(element) {
            const searchTerm = document.getElementById('searchFiles').value;
            if (!searchTerm) return;
            
            const titleElement = element.querySelector('.file-title');
            const descElement = element.querySelector('.file-description');
            
            if (titleElement) {
                titleElement.innerHTML = highlightText(titleElement.textContent, searchTerm);
            }
            if (descElement) {
                descElement.innerHTML = highlightText(descElement.textContent, searchTerm);
            }
        }

        function updateResultsInfo() {
            const countElement = document.getElementById('resultsCount');
            const statusElement = document.getElementById('filterStatus');
            
            if (countElement) {
                countElement.textContent = filteredFiles.length;
            }
            
            if (statusElement) {
                const searchTerm = document.getElementById('searchFiles').value;
                const filterType = document.getElementById('filterType').value;
                const filterPrice = document.getElementById('filterPrice').value;
                
                let status = 'جميع الملفات';
                if (searchTerm) status = `البحث: "${searchTerm}"`;
                if (filterType) status += ` | النوع: ${filterType}`;
                if (filterPrice) status += ` | السعر: ${filterPrice}`;
                
                statusElement.textContent = status;
            }
        }

        function setViewMode(mode) {
            currentViewMode = mode;
            const grid = document.getElementById('availableFilesGrid');
            const buttons = document.querySelectorAll('.view-btn');
            
            // Update button states
            buttons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.view === mode) {
                    btn.classList.add('active');
                }
            });
            
            // Update grid class
            grid.className = `files-grid ${mode}-view`;
        }

        function clearSearch() {
            document.getElementById('searchFiles').value = '';
            document.getElementById('searchFiles').focus();
            filterFiles();
            updateClearButton();
        }

        function updateClearButton() {
            const searchInput = document.getElementById('searchFiles');
            const clearBtn = document.querySelector('.search-clear-btn');
            
            if (searchInput.value.length > 0) {
                clearBtn.style.display = 'block';
            } else {
                clearBtn.style.display = 'none';
            }
        }

        function showLoading() {
            const grid = document.getElementById('availableFilesGrid');
            const loading = document.createElement('div');
            loading.className = 'loading-files';
            loading.innerHTML = '<div class="loading-spinner"></div>جاري التحميل...';
            loading.id = 'loadingIndicator';
            grid.appendChild(loading);
        }

        function hideLoading() {
            const loading = document.getElementById('loadingIndicator');
            if (loading) {
                loading.remove();
            }
        }

        // Change page size function
        function changePageSize(newSize) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', newSize);
            url.searchParams.set('page', '1'); // Reset to first page
            window.location.href = url.toString();
        }

        // Keyboard navigation for search suggestions
        let selectedSuggestionIndex = -1;
        
        function handleSearchKeydown(event) {
            const suggestionsContainer = document.getElementById('searchSuggestions');
            const historyContainer = document.getElementById('searchHistory');
            const suggestions = suggestionsContainer.querySelectorAll('.suggestion-item');
            const historyItems = historyContainer.querySelectorAll('.history-item');
            
            // Handle suggestions navigation
            if (suggestionsContainer.style.display !== 'none' && suggestions.length > 0) {
                switch (event.key) {
                    case 'ArrowDown':
                        event.preventDefault();
                        selectedSuggestionIndex = Math.min(selectedSuggestionIndex + 1, suggestions.length - 1);
                        updateSuggestionSelection(suggestions);
                        break;
                    case 'ArrowUp':
                        event.preventDefault();
                        selectedSuggestionIndex = Math.max(selectedSuggestionIndex - 1, -1);
                        updateSuggestionSelection(suggestions);
                        break;
                    case 'Enter':
                        event.preventDefault();
                        if (selectedSuggestionIndex >= 0 && selectedSuggestionIndex < suggestions.length) {
                            suggestions[selectedSuggestionIndex].click();
                        }
                        break;
                    case 'Escape':
                        hideSearchSuggestions();
                        selectedSuggestionIndex = -1;
                        break;
                }
                return;
            }
            
            // Handle history navigation
            if (historyContainer.style.display !== 'none' && historyItems.length > 0) {
                switch (event.key) {
                    case 'ArrowDown':
                        event.preventDefault();
                        selectedSuggestionIndex = Math.min(selectedSuggestionIndex + 1, historyItems.length - 1);
                        updateHistorySelection(historyItems);
                        break;
                    case 'ArrowUp':
                        event.preventDefault();
                        selectedSuggestionIndex = Math.max(selectedSuggestionIndex - 1, -1);
                        updateHistorySelection(historyItems);
                        break;
                    case 'Enter':
                        event.preventDefault();
                        if (selectedSuggestionIndex >= 0 && selectedSuggestionIndex < historyItems.length) {
                            historyItems[selectedSuggestionIndex].click();
                        }
                        break;
                    case 'Escape':
                        hideSearchHistory();
                        selectedSuggestionIndex = -1;
                        break;
                }
                return;
            }
            
            // Global keyboard shortcuts
            switch (event.key) {
                case '/':
                    if (!event.ctrlKey && !event.metaKey) {
                        event.preventDefault();
                        document.getElementById('searchFiles').focus();
                    }
                    break;
                case 'Escape':
                    if (document.activeElement === document.getElementById('searchFiles')) {
                        clearSearch();
                    }
                    break;
                case 'F3':
                    event.preventDefault();
                    const searchInput = document.getElementById('searchFiles');
                    if (searchInput.value.trim()) {
                        filterFiles();
                    }
                    break;
            }
        }
        
        function updateSuggestionSelection(suggestions) {
            suggestions.forEach((suggestion, index) => {
                if (index === selectedSuggestionIndex) {
                    suggestion.classList.add('active');
                    suggestion.setAttribute('aria-selected', 'true');
                } else {
                    suggestion.classList.remove('active');
                    suggestion.setAttribute('aria-selected', 'false');
                }
            });
        }
        
        function updateHistorySelection(historyItems) {
            historyItems.forEach((item, index) => {
                if (index === selectedSuggestionIndex) {
                    item.classList.add('active');
                    item.setAttribute('aria-selected', 'true');
                } else {
                    item.classList.remove('active');
                    item.setAttribute('aria-selected', 'false');
                }
            });
        }
        
        // Search analytics and tracking
        function trackSearch(searchTerm, resultCount) {
            if (!searchTerm.trim()) return;
            
            const searchAnalytics = JSON.parse(localStorage.getItem('searchAnalytics') || '{}');
            const today = new Date().toISOString().split('T')[0];
            
            if (!searchAnalytics[today]) {
                searchAnalytics[today] = {};
            }
            
            if (!searchAnalytics[today][searchTerm]) {
                searchAnalytics[today][searchTerm] = {
                    count: 0,
                    totalResults: 0,
                    lastSearched: new Date().toISOString()
                };
            }
            
            searchAnalytics[today][searchTerm].count++;
            searchAnalytics[today][searchTerm].totalResults += resultCount;
            searchAnalytics[today][searchTerm].lastSearched = new Date().toISOString();
            
            // Keep only last 30 days of analytics
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
            
            Object.keys(searchAnalytics).forEach(date => {
                if (new Date(date) < thirtyDaysAgo) {
                    delete searchAnalytics[date];
                }
            });
            
            localStorage.setItem('searchAnalytics', JSON.stringify(searchAnalytics));
        }
        
        // Search history functionality
        function saveSearchHistory(searchTerm) {
            if (!searchTerm.trim()) return;
            
            let searchHistory = JSON.parse(localStorage.getItem('searchHistory') || '[]');
            const timestamp = new Date().toISOString();
            
            // Remove existing entry if it exists
            searchHistory = searchHistory.filter(item => item.term !== searchTerm);
            
            // Add new entry at the beginning
            searchHistory.unshift({
                term: searchTerm,
                timestamp: timestamp,
                count: 1
            });
            
            // Keep only last 15 searches
            searchHistory = searchHistory.slice(0, 15);
            
            localStorage.setItem('searchHistory', JSON.stringify(searchHistory));
        }
        
        function getSearchHistory() {
            return JSON.parse(localStorage.getItem('searchHistory') || '[]');
        }
        
        function showSearchHistory() {
            const historyContainer = document.getElementById('searchHistory');
            const historyList = document.getElementById('historyList');
            
            if (!historyContainer || !historyList) return;
            
            const history = getSearchHistory();
            
            if (history.length === 0) {
                hideSearchHistory();
                return;
            }
            
            // Clear previous history
            historyList.innerHTML = '';
            
            // Add history items
            history.forEach((item, index) => {
                const historyItem = document.createElement('div');
                historyItem.className = 'history-item';
                historyItem.innerHTML = `
                    <span class="history-text">${item.term}</span>
                    <span class="history-time">${formatTimeAgo(item.timestamp)}</span>
                    <button class="history-remove" onclick="removeFromHistory('${item.term}', event)">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                historyItem.addEventListener('click', (e) => {
                    if (!e.target.closest('.history-remove')) {
                        selectHistoryItem(item.term);
                    }
                });
                
                historyList.appendChild(historyItem);
            });
            
            historyContainer.style.display = 'block';
        }
        
        function hideSearchHistory() {
            const historyContainer = document.getElementById('searchHistory');
            if (historyContainer) {
                historyContainer.style.display = 'none';
            }
        }
        
        function selectHistoryItem(term) {
            document.getElementById('searchFiles').value = term;
            hideSearchHistory();
            filterFiles();
        }
        
        function removeFromHistory(term, event) {
            event.stopPropagation();
            
            let searchHistory = getSearchHistory();
            searchHistory = searchHistory.filter(item => item.term !== term);
            localStorage.setItem('searchHistory', JSON.stringify(searchHistory));
            
            showSearchHistory();
        }
        
        function clearSearchHistory() {
            localStorage.removeItem('searchHistory');
            hideSearchHistory();
        }
        
        function formatTimeAgo(timestamp) {
            const now = new Date();
            const time = new Date(timestamp);
            const diffInMinutes = Math.floor((now - time) / (1000 * 60));
            
            if (diffInMinutes < 1) return 'الآن';
            if (diffInMinutes < 60) return `منذ ${diffInMinutes} دقيقة`;
            
            const diffInHours = Math.floor(diffInMinutes / 60);
            if (diffInHours < 24) return `منذ ${diffInHours} ساعة`;
            
            const diffInDays = Math.floor(diffInHours / 24);
            if (diffInDays < 0) return `منذ ${diffInDays} يوم`;
            
            return time.toLocaleDateString('ar-SA');
        }
        
        // Enhanced clear search function
        function clearSearch() {
            document.getElementById('searchFiles').value = '';
            document.getElementById('searchFiles').focus();
            hideSearchSuggestions();
            selectedSuggestionIndex = -1;
            filterFiles();
            updateClearButton();
        }

        // Clear all filters function
        function clearAllFilters() {
            document.getElementById('searchFiles').value = '';
            document.getElementById('sortFiles').value = 'newest';
            document.getElementById('filterType').value = '';
            document.getElementById('filterPrice').value = '';
            document.getElementById('filterDate').value = '';
            document.getElementById('filterSize').value = '';
            document.getElementById('filterTags').value = '';
            
            hideSearchSuggestions();
            selectedSuggestionIndex = -1;
            filterFiles();
            updateClearButton();
        }

        // Toggle advanced filters visibility
        function toggleAdvancedFilters() {
            const advancedFiltersContainer = document.getElementById('advancedFilters');
            const toggleBtn = document.querySelector('.btn-filter-toggle');
            const isVisible = advancedFiltersContainer.style.display !== 'none';
            
            if (isVisible) {
                advancedFiltersContainer.style.display = 'none';
                toggleBtn.innerHTML = '<i class="fas fa-sliders-h"></i> فلاتر';
            } else {
                advancedFiltersContainer.style.display = 'flex';
                toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i> إخفاء';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeFiles();
            
            // Add event listeners
            const searchInput = document.getElementById('searchFiles');
            if (searchInput) {
                searchInput.addEventListener('input', updateClearButton);
                searchInput.addEventListener('keydown', handleSearchKeydown);
                searchInput.addEventListener('blur', () => {
                    // Hide suggestions and history after a short delay to allow clicking
                    setTimeout(() => {
                        hideSearchSuggestions();
                        hideSearchHistory();
                    }, 200);
                });
                searchInput.addEventListener('focus', () => {
                    const searchTerm = searchInput.value;
                    if (searchTerm.length > 0) {
                        showSearchSuggestions(searchTerm);
                    } else {
                        showSearchHistory();
                    }
                });
            }
            
            // Close suggestions and history when clicking outside
            document.addEventListener('click', (event) => {
                const searchContainer = document.querySelector('.search-input-group');
                if (searchContainer && !searchContainer.contains(event.target)) {
                    hideSearchSuggestions();
                    hideSearchHistory();
                }
            });
            
            // Initialize view mode
            setViewMode('grid');
        });

        // Transaction Filtering Functions
        function filterTransactions(type) {
            const cards = document.querySelectorAll('.transaction-card');
            const filterBtns = document.querySelectorAll('.filter-btn');
            const grid = document.getElementById('transactionsGrid');
            
            // Update active filter button
            filterBtns.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Add loading effect
            grid.style.opacity = '0.7';
            grid.style.transform = 'scale(0.98)';
            
            setTimeout(() => {
                // Filter cards with animation
                cards.forEach((card, index) => {
                    if (type === 'all' || card.dataset.type === type) {
                        card.style.display = 'block';
                        card.style.animation = 'fadeInUp 0.6s ease forwards';
                        card.style.animationDelay = `${index * 0.1}s`;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // Reset grid
                grid.style.opacity = '1';
                grid.style.transform = 'scale(1)';
            }, 200);
        }

        // Sort transactions by date
        function sortTransactions(sortBy) {
            const grid = document.getElementById('transactionsGrid');
            const cards = Array.from(grid.querySelectorAll('.transaction-card'));
            
            cards.sort((a, b) => {
                const dateA = new Date(a.querySelector('.transaction-date').textContent);
                const dateB = new Date(b.querySelector('.transaction-date').textContent);
                
                if (sortBy === 'newest') {
                    return dateB - dateA;
                } else {
                    return dateA - dateB;
                }
            });
            
            // Re-append sorted cards
            cards.forEach(card => grid.appendChild(card));
        }

        // Matrix Rain Background Effect
        function initMatrixBackground() {
            const canvas = document.getElementById('matrixCanvas');
            const ctx = canvas.getContext('2d');

            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;

            const matrix = "ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789@#$%^&*()*&^%+-/~{[|`]}";
            const matrixArray = matrix.split("");

            const font_size = 10;
            const columns = canvas.width / font_size;
            const drops = [];

            for (let x = 0; x < columns; x++) {
                drops[x] = 1;
            }

            function draw() {
                ctx.fillStyle = 'rgba(5, 5, 5, 0.04)';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                ctx.fillStyle = '#00ff0020';
                ctx.font = font_size + 'px monospace';

                for (let i = 0; i < drops.length; i++) {
                    const text = matrixArray[Math.floor(Math.random() * matrixArray.length)];
                    ctx.fillText(text, i * font_size, drops[i] * font_size);

                    if (drops[i] * font_size > canvas.height && Math.random() > 0.975) {
                        drops[i] = 0;
                    }
                    drops[i]++;
                }
            }

            setInterval(draw, 35);
        }

        // Initialize on load
        window.addEventListener('load', initMatrixBackground);
        window.addEventListener('resize', initMatrixBackground);

        // Show notification function
        function showNotification(message, type = 'info', duration = 5000) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;

            document.getElementById('notificationArea').appendChild(notification);

            setTimeout(() => notification.classList.add('show'), 100);

            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 400);
            }, duration);
        }

        // Tab switching function - Updated for new navigation
        function showTab(tabName) {
            // Remove active class from all navigation menu items
            document.querySelectorAll('.nav-menu-item').forEach(item => {
                item.classList.remove('active');
            });

            // Remove active class from old tab system
            document.querySelectorAll('.nav-link').forEach(tab => {
                tab.classList.remove('active');
            });

            // Hide all tab panes
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('show', 'active');
            });

            // Activate the corresponding navigation item
            const navItem = document.getElementById('nav-' + tabName);
            if (navItem) {
                navItem.classList.add('active');
            }

            // Show selected tab (fallback for old tab system)
            const oldTab = document.getElementById(tabName + '-tab');
            if (oldTab) {
                oldTab.classList.add('active');
            }

            const tabPane = document.getElementById(tabName);
            if (tabPane) {
                tabPane.classList.add('show', 'active');
            }

            // Load tab-specific data if needed
            if (tabName === 'browse') {
                loadAvailableFiles();
            }
        }

        // دالة حذف الملف مع رسالة تأكيد بسيطة
        function confirmDeleteFile(fileId, fileName) {
            // رسالة تأكيد بسيطة بدلاً من النافذة المعقدة
            const confirmed = confirm(`هل أنت متأكد من حذف هذا الملف نهائياً؟\n\nاسم الملف: ${fileName}\n\n⚠️ لن يمكن استرجاع الملف بعد حذفه!`);

            if (confirmed) {
                deleteFileConfirmed(fileId);
            }
        }

        // تنفيذ حذف الملف
        function deleteFileConfirmed(fileId) {
            showNotification('جاري حذف الملف...', 'info');

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'delete_file');
            formData.append('file_id', fileId);

            console.log('Sending delete request for file ID:', fileId); // Debug log

            fetch('delete_file.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! ${response.status}`);
                    }
                    return response.text(); // تغيير لـ text() أولاً لفحص الاستجابة
                })
                .then(text => {
                    console.log('Response text:', text); // Debug log
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e, 'Response text:', text);
                        throw new Error('استجابة غير صحيحة من الخادم');
                    }

                    console.log('Parsed data:', data); // Debug log

                    if (data.success) {
                        showNotification('✅ تم حذف الملف بنجاح', 'success');
                        setTimeout(() => {
                            showTab('my-files'); // إعادة تحميل تبويب الملفات
                            location.reload(); // إعادة تحميل الصفحة
                        }, 1500);
                    } else {
                        showNotification('❌ ' + (data.message || 'فشل في حذف الملف'), 'error', 7000);
                    }
                })
                .catch(error => {
                    console.error('Delete file error:', error);
                    showNotification('☪️ حدث خطأ أثناء حذف الملف: ' + error.message, 'error', 7000);
                });
        }

        // دوال المعاينة الداخلية (مطابقة لقسم الملفات المتاحة)
        function showInlinePreview(button, fileId) {
            // استخدام النظام المودال الموحد بدلاً من النظام الداخلي
            previewFile(fileId);
        }

        // دالة المعاينة الموحدة
        function previewFile(fileId) {
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
            // إزالة أي مودال سابق
            const existingModal = document.querySelector('.preview-modal');
            if (existingModal) {
                existingModal.remove();
            }
            
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
            
            // إضافة event listener لإغلاق المودال عند الضغط على Escape
            document.addEventListener('keydown', function handleEscape(e) {
                if (e.key === 'Escape') {
                    closePreviewModal();
                    document.removeEventListener('keydown', handleEscape);
                }
            });
            
            setTimeout(() => modal.classList.add('show'), 10);
        }

        function closePreviewModal() {
            const modal = document.querySelector('.preview-modal');
            if (modal) {
                modal.classList.remove('show');
                setTimeout(() => modal.remove(), 300);
            }
        }

        function formatFileSize(bytes) {
            if (bytes == 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        function closeInlinePreview(fileId) {
            const overlay = document.querySelector(`#preview-${fileId}`);
            if (overlay) {
                overlay.classList.remove('active');
            }
        }

        // إعادة رفع ملف مرفوض
        function resubmitFile(fileId) {
            const confirmed = confirm('هل تريد إعادة رفع هذا الملف بعد تعديله؟\n\nسيتم إعادة إرساله للمراجعة مرة أخرى.');

            if (confirmed) {
                showNotification('جاري إعادة رفع الملف...', 'info');

                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('action', 'resubmit_file');
                formData.append('file_id', fileId);

                fetch('resubmit_file.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('✅ تم إعادة رفع الملف بنجاح! سيتم مراجعته قريباً.', 'success');
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showNotification('❌ ' + (data.message || 'فشل في إعادة رفع الملف'), 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Resubmit error:', error);
                        showNotification('☠️ حدث خطأ أثناء إعادة رفع الملف', 'error');
                    });
            }
        }

        // معاينة ملف المستخدم (النسخة القديمة - للتوافق مع الأماكن الأخرى)
        function previewMyFile(fileId) {
            // استخدام النظام الجديد بدلاً من فتح نافذة جديدة
            const fileCard = document.querySelector(`[data-file-id="${fileId}"]`) ||
                document.querySelector(`.file-card`); // fallback
            const previewButton = fileCard?.querySelector(`button[onclick*="${fileId}"]`);

            if (previewButton) {
                showInlinePreview(previewButton, fileId);
            } else {
                // Fallback للنظام القديم
                showNotification('جاري فتح معاينة الملف...', 'info');
                window.open(`preview_file.php?ajax=1&file_id=${fileId}`, '_blank');
            }
        }

        // File purchase function
        function purchaseFile(fileId, price) {
            if (!confirm(`هل أنت متأكد من شراء هذا الملف بـ ${price} نقطة؟`)) {
                return;
            }

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'purchase_file');
            formData.append('file_id', fileId);

            fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    showNotification('حدث خطأ أثناء الشراء', 'error');
                    console.error('Error:', error);
                });
        }

        // File filtering function
        function filterFiles() {
            const searchTerm = document.getElementById('searchFiles').value.toLowerCase();
            const sortBy = document.getElementById('sortFiles').value;
            const filterType = document.getElementById('filterType').value;

            const fileCards = Array.from(document.querySelectorAll('#availableFilesGrid .file-card'));

            // Filter by search term and type
            fileCards.forEach(card => {
                const title = card.querySelector('.file-title').textContent.toLowerCase();
                const description = card.querySelector('.file-description')?.textContent.toLowerCase() || '';
                const fileType = card.dataset.fileType;

                const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
                const matchesType = !filterType || fileType === filterType;

                card.style.display = matchesSearch && matchesType ? 'block' : 'none';
            });

            // Sort visible cards
            const visibleCards = fileCards.filter(card => card.style.display !== 'none');
            const parent = document.getElementById('availableFilesGrid');

            visibleCards.sort((a, b) => {
                switch (sortBy) {
                    case 'oldest':
                        return new Date(a.dataset.date) - new Date(b.dataset.date);
                    case 'price-low':
                        return parseInt(a.dataset.price) - parseInt(b.dataset.price);
                    case 'price-high':
                        return parseInt(b.dataset.price) - parseInt(a.dataset.price);
                    case 'popular':
                        return parseInt(b.dataset.sales) - parseInt(a.dataset.sales);
                    default: // newest
                        return new Date(b.dataset.date) - new Date(a.dataset.date);
                }
            });

            // Re-append sorted cards
            visibleCards.forEach(card => parent.appendChild(card));
        }

        // Display selected file info with text preview
        function displaySelectedFile(input) {
            const display = document.getElementById('selectedFileDisplay');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                const sizeFormatted = formatFileSize(file.size);
                const ext = file.name.split('.').pop().toLowerCase();
                const textExtensions = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'html', 'css', 'js', 'php', 'py', 'java', 'cpp', 'c', 'h'];

                let previewHtml = '';

                // معاينة الملفات النصية
                if (textExtensions.includes(ext) && file.size < 1024 * 1024) { // أقل من 1MB
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const content = e.target.result;
                        const preview = content.substring(0, 100); // أول 100 حرف
                        const hasMore = content.length > 100;

                        const previewElement = document.getElementById('textPreview');
                        if (previewElement) {
                            previewElement.innerHTML = `
                                <div class="text-preview">
                                    <h6 style="color: var(--primary-green); margin-bottom: 8px;">
                                        <i class="fas fa-eye"></i> معاينة المحتوى:
                                    </h6>
                                    <div class="preview-content">
                                        <code style="background: rgba(0, 255, 0, 0.05); padding: 10px; border-radius: 5px; display: block; color: var(--text-light); font-size: 0.9rem; line-height: 1.4; white-space: pre-wrap; max-height: 120px; overflow-y: auto;">${preview.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</code>
                                        ${hasMore ? '<p style="color: var(--text-gray); font-size: 0.8rem; margin-top: 5px;">... والمزيد</p>' : ''}
                                    </div>
                                </div>
                            `;
                        }
                    };
                    reader.readAsText(file);

                    previewHtml = '<div id="textPreview" style="margin-top: 15px;"></div>';
                }

                display.innerHTML = `
                    <div class="file-card" style="margin: 0;">
                        <div class="file-header">
                            <div class="file-icon-large">
                                <i class="${getFileIconJS(file.name)}" style="font-size: 2rem;"></i>
                            </div>
                            <div style="flex: 1;">
                                <h4 class="file-title" style="margin-bottom: 8px;">${file.name}</h4>
                                <div class="file-meta-item">
                                    <span style="color: var(--text-gray);">الحجم: </span>
                                    <span style="color: var(--primary-green); font-weight: 500;">${sizeFormatted}</span>
                                </div>
                                <div class="file-meta-item">
                                    <span style="color: var(--text-gray);">النوع: </span>
                                    <span style="color: var(--text-light);">${ext.toUpperCase()}</span>
                                </div>
                            </div>
                        </div>
                        ${previewHtml}
                    </div>
                `;
                display.style.display = 'block';

                // تحديث اسم الملف في حقل الإدخال إذا كان فارغاً
                const filenameInput = document.querySelector('input[name="filename"]');
                if (filenameInput && !filenameInput.value) {
                    const nameWithoutExt = file.name.replace(/\.[^/.]+$/, "");
                    filenameInput.value = nameWithoutExt;
                }
            }
        }
        // Get file icon in JavaScript
        function getFileIconJS(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const icons = {
                // Documents
                'pdf': 'fas fa-file-pdf',
                'doc': 'fas fa-file-word',
                'docx': 'fas fa-file-word',
                'txt': 'fas fa-file-alt',
                'rtf': 'fas fa-file-alt',
                'md': 'fas fa-file-alt',

                // Images
                'jpg': 'fas fa-file-image',
                'jpeg': 'fas fa-file-image',
                'png': 'fas fa-file-image',
                'gif': 'fas fa-file-image',
                'bmp': 'fas fa-file-image',
                'webp': 'fas fa-file-image',

                // Archives
                'zip': 'fas fa-file-archive',
                'rar': 'fas fa-file-archive',
                '7z': 'fas fa-file-archive',
                'tar': 'fas fa-file-archive',
                'gz': 'fas fa-file-archive',

                // Spreadsheets
                'xls': 'fas fa-file-excel',
                'xlsx': 'fas fa-file-excel',
                'csv': 'fas fa-file-csv',

                // Presentations
                'ppt': 'fas fa-file-powerpoint',
                'pptx': 'fas fa-file-powerpoint',

                // Audio/Video
                'mp3': 'fas fa-file-audio',
                'wav': 'fas fa-file-audio',
                'mp4': 'fas fa-file-video',
                'avi': 'fas fa-file-video',
                'mov': 'fas fa-file-video',

                // Code/Data
                'json': 'fas fa-file-code',
                'xml': 'fas fa-file-code',
                'html': 'fas fa-file-code',
                'css': 'fas fa-file-code',
                'js': 'fas fa-file-code',
                'php': 'fas fa-file-code',
                'py': 'fas fa-file-code',
                'java': 'fas fa-file-code',
                'cpp': 'fas fa-file-code',
                'c': 'fas fa-file-code',
                'h': 'fas fa-file-code',

                // Executables
                'exe': 'fas fa-cog',
                'msi': 'fas fa-cog',
                'apk': 'fas fa-mobile-alt',
                'deb': 'fas fa-box',
                'rpm': 'fas fa-box'
            };
            return icons[ext] || 'fas fa-file';
        }

        // Format file size in JavaScript
        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(1) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(1) + ' KB';
            } else {
                return bytes + ' bytes';
            }
        }

        // File management functions
        function editFile(fileId) {
            showNotification('جاري فتح نافذة التعديل...', 'info');
            // Implementation for edit file modal
        }

        function deleteFile(fileId) {
            if (confirm('هل أنت متأكد من حذف هذا الملف؟')) {
                showNotification('جاري حذف الملف...', 'info');
                // Implementation for delete file
            }
        }

        function toggleAvailability(fileId, currentStatus) {
            const action = currentStatus ? 'إيقاف' : 'إعادة';
            if (confirm(`هل أنت متأكد من ${action} نشر هذا الملف؟`)) {
                showNotification(`جاري ${action} النشر...`, 'info');

                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('action', 'toggle_availability');
                formData.append('file_id', fileId);

                fetch('manage_file.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(data.message, 'success');
                            // تحديث واجهة المستخدم
                            const fileCard = document.querySelector(`[data-file-id="${fileId}"]`);
                            if (fileCard) {
                                const availabilityButton = fileCard.querySelector('.action-btn[onclick*="toggleAvailability"]');
                                const newStatus = !currentStatus;
                                availabilityButton.setAttribute('onclick', `toggleAvailability(${fileId}, ${newStatus})`);

                                if (!currentStatus) {
                                    // إذا كان إعادة نشر، نغير الزر ليظهر حالة المراجعة
                                    availabilityButton.innerHTML = `<i class="fas fa-clock"></i> قيد المراجعة`;
                                    availabilityButton.disabled = true;
                                    availabilityButton.classList.add('pending');
                                } else {
                                    // إذا كان إيقاف نشر
                                    availabilityButton.innerHTML = `<i class="fas fa-eye"></i> إعادة النشر`;
                                    availabilityButton.disabled = false;
                                    availabilityButton.classList.remove('pending');
                                }
                            }
                            // تحديث قائمة الملفات
                            loadTabContent('my-files');
                        } else {
                            showNotification(data.message || 'حدث خطأ أثناء تغيير حالة النشر', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Toggle availability error:', error);
                        showNotification('حدث خطأ أثناء تغيير حالة النشر', 'error');
                    });
            }
        }

        function viewSalesStats(fileId) {
            showNotification('جاري تحميل الإحصائيات...', 'info');
            // Implementation for sales statistics
        }

        function previewFile(fileId) {
            showNotification('جاري فتح معاينة الملف...', 'info');
            // Implementation for file preview
        }

        function shareFile(fileId) {
            showNotification('جاري إنشاء رابط المشاركة...', 'info');
            // Implementation for file sharing
        }

        // متغير عام للنافذة المنبثقة
        let currentModal = null;

        // تأكيد مسح سجل المعاملات
        function confirmClearHistory() {
            console.log('confirmClearHistory() called'); // Debug

            // إزالة نافذة موجودة إن وجدت
            if (currentModal) {
                currentModal.remove();
            }

            // إنشاء نافذة تأكيد مخصصة مع تحذيرات قوية
            const modal = document.createElement('div');
            currentModal = modal;
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.3s ease;
            `;

            const dialog = document.createElement('div');
            dialog.style.cssText = `
                background: var(--gradient-dark);
                border: 2px solid #ff4444;
                border-radius: 15px;
                padding: 30px;
                max-width: 500px;
                text-align: center;
                box-shadow: 0 20px 60px rgba(255, 0, 0, 0.4);
                animation: shake 0.5s ease-in-out;
            `;

            dialog.innerHTML = `
                <div>
                    <div style="color: #ff4444; font-size: 4rem; margin-bottom: 20px; animation: pulse 2s infinite;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 style="color: #ff6464; margin-bottom: 15px; font-size: 1.3rem;">⚠️ تحذير: مسح نهائي! ⚠️</h3>
                    <p style="color: var(--text-light); margin-bottom: 15px; font-size: 1.1rem; line-height: 1.5;">
                        هل أنت متأكد من حذف جميع سجلات المعاملات الخاصة بك؟
                    </p>
                    <div style="background: rgba(255, 100, 100, 0.1); border: 1px solid rgba(255, 100, 100, 0.3); border-radius: 8px; padding: 15px; margin: 20px 0;">
                        <p style="color: #ff6464; font-weight: bold; margin-bottom: 10px; font-size: 1rem;">
                            <i class="fas fa-fire"></i> هذه العملية غير قابلة للتراجع!
                        </p>
                        <ul style="text-align: right; color: #ffa500; font-size: 0.9rem; margin: 0; padding-right: 20px;">
                            <li>سيتم حذف جميع سجلات مبيعاتك</li>
                            <li>سيتم حذف جميع سجلات مشترياتك</li>
                            <li>لن تتمكن من استعادة هذه البيانات فيما بعد</li>
                        </ul>
                    </div>
                    <div style="margin-bottom: 25px;">
                        <p style="color: var(--primary-green); font-size: 1rem; margin-bottom: 10px;">
                            اكتب "حذف نهائي" للتأكيد:
                        </p>
                        <input type="text" id="confirmInput" placeholder="حذف نهائي" 
                               style="background: var(--card-black); border: 2px solid #ff4444; color: var(--text-light); 
                                      padding: 10px; border-radius: 5px; text-align: center; width: 200px; 
                                      font-size: 1rem;" 
                               onkeyup="toggleDeleteButton(this.value)"
                               onkeydown="if (event.key === 'Enter' && this.value === 'حذف نهائي') { executeHistoryClear(); closeModal(); }">
                    </div>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <button id="confirmDeleteBtn" onclick="executeHistoryClear(); closeModal();" 
                                disabled
                                style="background: #dc3545; color: white; border: none; padding: 12px 25px; border-radius: 8px; 
                                       font-weight: 600; cursor: not-allowed; transition: all 0.2s ease; opacity: 0.5;"
                                onmouseover="if (!this.disabled) { this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 15px rgba(220, 53, 69, 0.4)'; }"
                                onmouseout="if (!this.disabled) { this.style.transform='scale(1)'; this.style.boxShadow='none'; }">
                            <i class="fas fa-fire"></i> حذف نهائياً
                        </button>
                        <button onclick="closeModal();" 
                                style="background: var(--card-black); color: var(--text-light); border: 1px solid var(--border-glow); 
                                       padding: 12px 25px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease;"
                                onmouseover="this.style.borderColor='var(--primary-green)'; this.style.color='var(--primary-green)'" 
                                onmouseout="this.style.borderColor='var(--border-glow)'; this.style.color='var(--text-light)'">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                    </div>
                </div>
            `;
            
            // إضافة CSS animations
            const style = document.createElement('style');
            style.textContent = `
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(-5px); }
                    75% { transform: translateX(5px); }
                }
                @keyframes pulse {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.7; }
                }
            `;
            document.head.appendChild(style);
            
            modal.appendChild(dialog);
            document.body.appendChild(modal);
            
            setTimeout(() => modal.style.opacity = '1', 10);
            
            // تركيز على حقل الإدخال
            setTimeout(() => document.getElementById('confirmInput').focus(), 500);
        }
        
        // تفعيل/إلغاء زر الحذف بناءً على النص المدخل
        function toggleDeleteButton(inputValue) {
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            if (inputValue === 'حذف نهائي') {
                deleteBtn.disabled = false;
                deleteBtn.style.cursor = 'pointer';
                deleteBtn.style.opacity = '1';
                deleteBtn.style.background = '#dc3545';
            } else {
                deleteBtn.disabled = true;
                deleteBtn.style.cursor = 'not-allowed';
                deleteBtn.style.opacity = '0.5';
                deleteBtn.style.background = '#666';
            }
        }
        
        // إغلاق النافذة المنبثقة
        function closeModal() {
            console.log('closeModal() called');
            if (currentModal) {
                currentModal.style.opacity = '0';
                setTimeout(() => {
                    if (currentModal) {
                        currentModal.remove();
                        currentModal = null;
                    }
                }, 300);
            }
        }
        
        // تنفيذ مسح السجل
        function executeHistoryClear() {
            console.log('executeHistoryClear() called');
            clearHistoryConfirmed();
        }
        
        // تنفيذ مسح السجل
        function clearHistoryConfirmed() {
            console.log('clearHistoryConfirmed() called'); // Debug
            showNotification('جاري مسح سجل المعاملات...', 'info');
            
            // تعطيل زر الحذف لمنع النقر المتعدد
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            if (deleteBtn) {
                deleteBtn.disabled = true;
                deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحذف...';
            }
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'clear_transaction_history');

            fetch('clear_history.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('خطأ في الشبكة: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    let message = '✅ تم مسح سجل المعاملات بنجاح';
                    if (data.deleted_count) {
                        message += ` (${data.deleted_count} سجل)`;
                    }
                    showNotification(message, 'success');
                    
                    // إضافة تأثير بصري للنجاح
                    if (currentModal) {
                        currentModal.style.background = 'rgba(0, 255, 0, 0.1)';
                        setTimeout(() => {
                            if (currentModal) {
                                currentModal.remove();
                                currentModal = null;
                            }
                        }, 1000);
                    }
                    
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification('❌ ' + (data.message || 'فشل في مسح السجل'), 'error');
                    
                    // إعادة تفعيل الزر في حالة الفشل
                    if (deleteBtn) {
                        deleteBtn.disabled = false;
                        deleteBtn.innerHTML = '<i class="fas fa-fire"></i> حذف نهائياً';
                    }
                }
            })
            .catch(error => {
                console.error('Clear history error:', error);
                showNotification('☢️ حدث خطأ أثناء مسح السجل: ' + error.message, 'error');
                
                // إعادة تفعيل الزر في حالة الخطأ
                if (deleteBtn) {
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = '<i class="fas fa-fire"></i> حذف نهائياً';
                }
            });
        }

        function loadAvailableFiles() {
            // Refresh available files data
            console.log('Loading available files...');
        }
        
        // دالة اختبار مبسطة لمسح السجل
        function testClearHistory() {
            console.log('testClearHistory() called');
            
            if (!confirm('هل أنت متأكد من مسح سجل المعاملات؟')) {
                return;
            }
            
            showNotification('جاري اختبار مسح السجل...', 'info');
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'clear_transaction_history');

            console.log('Sending clear history request...');

            fetch('clear_history.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response received:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed response:', data);
                    
                    if (data.success) {
                        showNotification('✅ ' + data.message, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showNotification('❌ ' + data.message, 'error');
                    }
                } catch (e) {
                    console.error('JSON parse error:', e);
                    showNotification('❌ خطأ في الاستجابة', 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showNotification('❌ حدث خطأ أثناء الطلب', 'error');
            });
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animations to file cards
            const fileCards = document.querySelectorAll('.file-card');
            fileCards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
            });

            // Auto-resize textareas
            document.querySelectorAll('textarea').forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            });

            // Initialize drag and drop for file upload
            const dropZone = document.querySelector('.file-drop-zone');
            if (dropZone) {
                dropZone.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.style.background = 'var(--hover-green)';
                    this.style.borderColor = 'var(--primary-green)';
                });

                dropZone.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.style.background = 'var(--gradient-dark)';
                    this.style.borderColor = 'var(--border-glow)';
                });

                dropZone.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.style.background = 'var(--gradient-dark)';
                    this.style.borderColor = 'var(--border-glow)';
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        document.getElementById('fileUpload').files = files;
                        displaySelectedFile(document.getElementById('fileUpload'));
                    }
                });
            }
        });
    </script>
    <script>
        // Navigation Menu - Tab switching functionality
        document.querySelectorAll('.nav-menu-item[data-tab]').forEach(item => {
            item.addEventListener('click', e => {
                e.preventDefault();
                
                // Get the tab name from data-tab attribute
                const tabName = item.dataset.tab;
                
                // Remove active class from all navigation items
                document.querySelectorAll('.nav-menu-item').forEach(navItem => {
                    navItem.classList.remove('active');
                });
                
                // Add active class to clicked item
                item.classList.add('active');
                
                // Show the corresponding tab
                showTab(tabName);
            });
        });

        function showTab(tabName) {
            // Hide all tab panes
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('show', 'active');
            });
            
            // Show the selected tab pane
            const targetPane = document.getElementById(tabName);
            if (targetPane) {
                targetPane.classList.add('show', 'active');
            }
            
            // Load tab-specific data if needed
            if (tabName === 'browse') {
                loadAvailableFiles();
            }
        }

        // Initialize the default tab view when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Find the initially active navigation item from the HTML
            const activeNavItem = document.querySelector('.nav-menu-item.active');
            if (activeNavItem) {
                const activeTabName = activeNavItem.dataset.tab || 'browse';
                showTab(activeTabName);
            } else {
                // Default to browse tab if no active item found
                showTab('browse');
            }
        });
    </script>
    <script src="js/smart-card.js"></script>
    <script src="js/terminal-system.js"></script>
    
    <!-- Enhanced System Integration -->
    <script>
        // Initialize enhanced system when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Add terminal integration to existing functions
            if (typeof terminalSystem !== 'undefined' && terminalSystem) {
                // Override existing functions to include terminal logging
                const originalPurchaseFile = window.purchaseFile;
                if (originalPurchaseFile) {
                    window.purchaseFile = function(fileId, price) {
                        if (terminalSystem.isOpen) {
                            terminalSystem.printLine(`Executing purchase for file ID: ${fileId}, Price: ${price}`, 'info');
                        }
                        return originalPurchaseFile(fileId, price);
                    };
                }
                
                const originalShowNotification = window.showNotification;
                if (originalShowNotification) {
                    window.showNotification = function(message, type, duration) {
                        if (terminalSystem.isOpen) {
                            terminalSystem.printLine(`Notification: ${message}`, type);
                        }
                        return originalShowNotification(message, type, duration);
                    };
                }
            }
            
            // Add system status monitoring
            setInterval(function() {
                updateSystemStatus();
            }, 30000);
        });
        
        // System status monitoring
        function updateSystemStatus() {
            const statusElement = document.getElementById('systemStatusIndicator');
            if (statusElement) {
                const now = new Date();
                const statusText = statusElement.querySelector('.status-text');
                if (statusText) {
                    statusText.textContent = `System Online - ${now.toLocaleTimeString()}`;
                }
            }
        }
        
        // Enhanced error handling
        window.addEventListener('error', function(e) {
            if (typeof terminalSystem !== 'undefined' && terminalSystem && terminalSystem.isOpen) {
                terminalSystem.printLine(`JavaScript Error: ${e.message} at ${e.filename}:${e.lineno}`, 'error');
            }
        });
        
        // Enhanced console logging
        const originalConsoleLog = console.log;
        console.log = function(...args) {
            if (typeof terminalSystem !== 'undefined' && terminalSystem && terminalSystem.isOpen) {
                terminalSystem.printLine(`Console: ${args.join(' ')}`, 'output');
            }
            return originalConsoleLog.apply(console, args);
        };
        
        const originalConsoleError = console.error;
        console.error = function(...args) {
            if (typeof terminalSystem !== 'undefined' && terminalSystem && terminalSystem.isOpen) {
                terminalSystem.printLine(`Error: ${args.join(' ')}`, 'error');
            }
            return originalConsoleError.apply(console, args);
        };
    </script>

    <!-- Preview Upload JavaScript -->
    <script>
        // Preview type switching
        document.addEventListener('DOMContentLoaded', function() {
            const textRadio = document.getElementById('previewTypeText');
            const imageRadio = document.getElementById('previewTypeImage');
            const textSection = document.getElementById('textPreviewSection');
            const imageSection = document.getElementById('imagePreviewSection');
            const textInput = document.getElementById('previewText');
            const imageInput = document.getElementById('previewImageUpload');

            function switchPreviewType() {
                if (textRadio.checked) {
                    textSection.style.display = 'block';
                    imageSection.style.display = 'none';
                    textSection.classList.add('active');
                    imageSection.classList.remove('active');
                    textInput.required = true;
                    imageInput.required = false;
                } else {
                    textSection.style.display = 'none';
                    imageSection.style.display = 'block';
                    textSection.classList.remove('active');
                    imageSection.classList.add('active');
                    textInput.required = false;
                    imageInput.required = true;
                }
                updateLivePreview();
            }

            textRadio.addEventListener('change', switchPreviewType);
            imageRadio.addEventListener('change', switchPreviewType);

            // Character counter for text preview
            textInput.addEventListener('input', function() {
                const charCount = this.value.length;
                const counter = document.getElementById('textCharCount');
                const counterElement = counter.parentElement;
                
                counter.textContent = charCount;
                
                if (charCount > 450) {
                    counterElement.classList.add('error');
                    counterElement.classList.remove('warning');
                } else if (charCount > 400) {
                    counterElement.classList.add('warning');
                    counterElement.classList.remove('error');
                } else {
                    counterElement.classList.remove('warning', 'error');
                }
                
                updateLivePreview();
            });

            // Initialize
            switchPreviewType();
        });

        // Extract preview from file
        function extractPreviewFromFile() {
            const fileInput = document.getElementById('fileUpload');
            const textArea = document.getElementById('previewText');
            
            if (!fileInput.files || !fileInput.files[0]) {
                showNotification('يرجى اختيار ملف أولاً', 'warning');
                return;
            }

            const file = fileInput.files[0];
            const fileName = file.name.toLowerCase();
            
            // Check if it's a text file
            if (fileName.endsWith('.txt') || fileName.endsWith('.md') || fileName.endsWith('.json') || fileName.endsWith('.csv')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const content = e.target.result;
                    const preview = content.substring(0, 500);
                    textArea.value = preview + (content.length > 500 ? '...' : '');
                    textArea.dispatchEvent(new Event('input'));
                    showNotification('تم استخراج المعاينة بنجاح', 'success');
                };
                reader.readAsText(file);
            } else {
                showNotification('لا يمكن استخراج معاينة نصية من هذا النوع من الملفات', 'warning');
            }
        }

        // Clear text preview
        function clearTextPreview() {
            const textArea = document.getElementById('previewText');
            textArea.value = '';
            textArea.dispatchEvent(new Event('input'));
        }

        // Display preview image
        function displayPreviewImage(input) {
            const file = input.files[0];
            if (!file) return;

            // Validate file size (2MB max)
            if (file.size > 2 * 1024 * 1024) {
                showNotification('حجم الصورة يجب أن يكون أقل من 2 ميجابايت', 'error');
                input.value = '';
                return;
            }

            // Validate file type
            if (!file.type.startsWith('image/')) {
                showNotification('يرجى اختيار ملف صورة صحيح', 'error');
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('previewImagePreview');
                const display = document.getElementById('previewImageDisplay');
                
                preview.src = e.target.result;
                preview.onload = function() {
                    display.style.display = 'block';
                    updateLivePreview();
                    showNotification('تم تحميل صورة المعاينة بنجاح', 'success');
                };
            };
            reader.readAsDataURL(file);
        }

        // Remove preview image
        function removePreviewImage() {
            const input = document.getElementById('previewImageUpload');
            const display = document.getElementById('previewImageDisplay');
            const preview = document.getElementById('previewImagePreview');
            
            input.value = '';
            preview.src = '';
            display.style.display = 'none';
            
            updateLivePreview();
        }

        // Update live preview
        function updateLivePreview() {
            const livePreview = document.getElementById('livePreviewContent');
            const textRadio = document.getElementById('previewTypeText');
            
            if (textRadio.checked) {
                const textContent = document.getElementById('previewText').value;
                if (textContent.trim()) {
                    livePreview.innerHTML = `<div class="live-preview-text">${textContent}</div>`;
                } else {
                    livePreview.innerHTML = `
                        <div class="preview-placeholder">
                            <i class="fas fa-eye-slash"></i>
                            اكتب نص المعاينة لرؤيته هنا
                        </div>
                    `;
                }
            } else {
                const imagePreview = document.getElementById('previewImagePreview');
                if (imagePreview && imagePreview.src && imagePreview.src !== '' && !imagePreview.src.endsWith('index.php')) {
                    livePreview.innerHTML = `<img src="${imagePreview.src}" class="live-preview-image" alt="معاينة الصورة">`;
                } else {
                    livePreview.innerHTML = `
                        <div class="preview-placeholder">
                            <i class="fas fa-image"></i>
                            اختر صورة المعاينة لرؤيتها هنا
                        </div>
                    `;
                }
            }
        }

        // Enable extract button when file is selected
        function displaySelectedFile(input) {
            const extractBtn = document.getElementById('extractPreviewBtn');
            if (input.files && input.files[0]) {
                extractBtn.disabled = false;
                
                // Show file info
                const file = input.files[0];
                const display = document.getElementById('selectedFileDisplay');
                display.innerHTML = `
                    <div class="selected-file-info">
                        <i class="fas fa-file"></i>
                        <span>${file.name}</span>
                        <small>(${formatFileSize(file.size)})</small>
                    </div>
                `;
                display.style.display = 'block';
            } else {
                extractBtn.disabled = true;
            }
        }

        // Format file size helper
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Form validation before submit
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const textRadio = document.getElementById('previewTypeText');
            const textInput = document.getElementById('previewText');
            const imageInput = document.getElementById('previewImageUpload');
            
            if (textRadio.checked) {
                if (!textInput.value.trim()) {
                    e.preventDefault();
                    showNotification('يرجى إدخال نص المعاينة', 'error');
                    textInput.focus();
                    return false;
                }
            } else {
                if (!imageInput.files || !imageInput.files[0]) {
                    e.preventDefault();
                    showNotification('يرجى اختيار صورة المعاينة', 'error');
                    return false;
                }
            }
        });
        // Smart Card Functions - Missing functions that are called from smart_file_card.php

        // Toggle card expansion/collapse (Details button functionality)
        function toggleCard(fileId) {
            console.log('Toggle function called with fileId:', fileId);
            
            // Find the card element
            const card = document.querySelector(`[data-file-id="${fileId}"]`);
            if (!card) {
                console.error('Card not found for fileId:', fileId);
                return;
            }
            
            // Find the drawer for this card
            const drawer = card.querySelector(`#drawer-${fileId}`);
            if (!drawer) {
                console.error('Drawer not found in card:', card);
                return;
            }
            
            // Toggle the 'expanded' class on the card
            const isExpanded = card.classList.toggle('expanded');
            
            // Toggle the drawer's display
            if (isExpanded) {
                // Collapse all other expanded cards
                document.querySelectorAll('.smart-file-card.expanded').forEach(expandedCard => {
                    if (expandedCard !== card) {
                        expandedCard.classList.remove('expanded');
                        const otherDrawer = expandedCard.querySelector('.smart-card-drawer');
                        if (otherDrawer) {
                            otherDrawer.classList.remove('expanded');
                            otherDrawer.style.maxHeight = '0';
                            otherDrawer.style.opacity = '0';
                        }
                    }
                });
                // Expand this card's drawer
                drawer.classList.add('expanded');
                drawer.style.maxHeight = drawer.scrollHeight + 'px';
                drawer.style.opacity = '1';
                // Update indicator
                const indicator = card.querySelector('.expand-indicator i');
                if (indicator) {
                    indicator.className = 'fas fa-chevron-up';
                }
                showNotification('تم فتح تفاصيل الملف', 'success');
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                // Collapse this card's drawer
                drawer.classList.remove('expanded');
                drawer.style.maxHeight = '0';
                drawer.style.opacity = '0';
                // Update indicator
                const indicator = card.querySelector('.expand-indicator i');
                if (indicator) {
                    indicator.className = 'fas fa-chevron-down';
                }
                showNotification('تم إغلاق تفاصيل الملف', 'info');
            }
        }

        // Enhanced preview file function with fallback
        function previewFile(fileId, previewType = 'text', previewText = '', previewImage = '') {
            console.log('Preview function called with fileId:', fileId); // Debug log
            showLoadingState('جاري تحميل معاينة الملف...');

            // Use the enhanced preview system if available
            if (typeof window.previewFileEnhanced === 'function') {
                window.previewFileEnhanced(fileId, previewType, previewText, previewImage);
                return;
            }

            // Use the existing preview system from the main file-system.js
            if (typeof window.previewFile === 'function') {
                window.previewFile(fileId);
            } else {
                // Fallback implementation
                fetch(`preview_file.php?ajax=1&file_id=${fileId}`)
                    .then(response => {
                        console.log('Fetch response status:', response.status); // Debug log
                        return response.json();
                    })
                    .then(data => {
                        console.log('Fetch response data:', data); // Debug log
                        hideLoadingState();

                        if (data.success) {
                            createPreviewModal(data.file, data.preview);
                        } else {
                            showNotification(data.message || 'فشل في تحميل المعاينة', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Preview error:', error); // Debug log
                        hideLoadingState();
                        showNotification('حدث خطأ أثناء تحميل المعاينة', 'error');
                    });
            }
        }

        // Create preview modal (reused from existing code)
        function createPreviewModal(file, preview) {
            // Remove any existing modal
            const existingModal = document.querySelector('.preview-modal');
            if (existingModal) {
                existingModal.remove();
            }

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
                                <span>${file.owner_name || file.username}</span>
                            </div>
                        </div>
                        <div class="preview-content">
                            ${preview}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button onclick="closePreviewModal()" class="action-btn">إغلاق</button>
                        ${file.price > 0 ? `<button onclick="purchaseFile(${file.id}, ${file.price}, '${file.filename}')" class="action-btn primary">
                            <i class="fas fa-shopping-cart"></i>
                            شراء بـ ${file.price} نقطة
                        </button>` : ''}
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // Add event listener for closing modal on Escape key
            document.addEventListener('keydown', function handleEscape(e) {
                if (e.key === 'Escape') {
                    closePreviewModal();
                    document.removeEventListener('keydown', handleEscape);
                }
            });

            setTimeout(() => modal.classList.add('show'), 10);
        }

        function closePreviewModal() {
            const modal = document.querySelector('.preview-modal');
            if (modal) {
                modal.classList.remove('show');
                setTimeout(() => modal.remove(), 300);
            }
        }

        // Loading state functions (if not already defined)
        function showLoadingState(message = 'جاري التحميل...') {
            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'loadingOverlay';
            loadingDiv.className = 'loading-overlay';
            loadingDiv.innerHTML = `
                <div class="loading-content">
                    <div class="loading-spinner"></div>
                    <p>${message}</p>
                </div>
            `;
            document.body.appendChild(loadingDiv);
        }

        function hideLoadingState() {
            const loadingDiv = document.getElementById('loadingOverlay');
            if (loadingDiv) {
                loadingDiv.remove();
            }
        }

        // Make functions globally accessible
        window.toggleCard = toggleCard;
        window.previewFile = previewFile;
        window.createPreviewModal = createPreviewModal;
        window.closePreviewModal = closePreviewModal;
        window.showLoadingState = showLoadingState;
        window.hideLoadingState = hideLoadingState;
        window.formatFileSize = formatFileSize;

        // Debug function to test if functions are accessible
        window.testFunctions = function() {
            console.log('Testing JavaScript functions:');
            console.log('toggleCard function:', typeof window.toggleCard);
            console.log('previewFile function:', typeof window.previewFile);
            console.log('Available cards:', document.querySelectorAll('.smart-file-card').length);
            console.log('Available drawers:', document.querySelectorAll('.smart-card-drawer').length);

            // Test for potential issues
            const cards = document.querySelectorAll('.smart-file-card');
            let issues = [];

            cards.forEach(card => {
                if (!card.hasAttribute('data-file-id')) {
                    issues.push('Card missing data-file-id: ' + card.className);
                }
                if (!card.querySelector('.smart-card-drawer')) {
                    issues.push('Card missing drawer: ' + card.className);
                }
            });

            if (issues.length > 0) {
                console.warn('Potential issues found:', issues);
            } else {
                console.log('✓ No issues found');
            }
        };

        // اختبار نظام إدارة النوافذ الجديد
        window.testWindowManager = function() {
            if (window.enhancedUI && window.enhancedUI.testWindowManager) {
                return window.enhancedUI.testWindowManager();
            } else {
                console.log('Window manager not available');
            }
        };

        // تعليمات الاستخدام للنظام الجديد
        window.showWindowManagerHelp = function() {
            console.log(`
🪟 نظام إدارة النوافذ الجديد:

المشكلة التي تم حلها:
- عند فتح نافذة جديدة، النافذة السابقة تُغلق تلقائياً
- لا توجد نوافذ متعددة مفتوحة في نفس الوقت

طرق إغلاق النوافذ:
1. النقر خارج منطقة النافذة
2. الضغط على مفتاح Escape
3. فتح نافذة أخرى (تغلق السابقة تلقائياً)

دوال الاختبار:
- testWindowManager() - اختبار النظام
- testFunctions() - اختبار عام
- testPreview() - اختبار المعاينة
- testToggleCard() - اختبار زر التفاصيل
- testCompleteSystem() - اختبار شامل للنظام

أمثلة الاستخدام:
- انقر على زر "التفاصيل" في كارت
- انقر خارج الدرج لإغلاقه
- اضغط Escape لإغلاق الدرج
- انقر على زر "معاينة" في كارت آخر (سيغلق الدرج الأول)

اختبار زر التفاصيل:
- في Console: testToggleCard(1) - اختبار زر التفاصيل للكارت برقم 1
- في Console: testCompleteSystem() - اختبار شامل للنظام
- انقر على زر "التفاصيل" في أي ملف لاختبار الوظيفة الحقيقية
            `);
        };

        // اختبار زر التفاصيل
        window.testToggleCard = function(fileId = 1) {
            console.log('Testing toggle card for fileId:', fileId);

            // التحقق من وجود العناصر المطلوبة
            const card = document.querySelector(`[data-file-id="${fileId}"]`);
            const drawer = document.querySelector(`#drawer-${fileId}`);

            if (card && drawer) {
                console.log('✅ Found card and drawer elements');
                console.log('Testing toggleCard function...');

                // استدعاء دالة toggleCard
                if (typeof window.toggleCard === 'function') {
                    window.toggleCard(fileId);
                    console.log('✅ toggleCard function executed successfully');
                } else {
                    console.error('❌ toggleCard function not found');
                }
            } else {
                console.error('❌ Card or drawer not found for fileId:', fileId);
                console.log('Available cards:', document.querySelectorAll('.smart-file-card').length);
                console.log('Available drawers:', document.querySelectorAll('.smart-card-drawer').length);

                // عرض قائمة بالكروت المتاحة
                const allCards = document.querySelectorAll('.smart-file-card');
                console.log('Available card IDs:');
                allCards.forEach(card => {
                    const cardId = card.getAttribute('data-file-id');
                    console.log('  - Card ID:', cardId);
                });
            }
        };

        // اختبار شامل للنظام
        window.testCompleteSystem = function() {
            console.log('🔧 Running complete system test...\n');

            // اختبار دالة showNotification
            console.log('Testing showNotification...');
            if (typeof window.showNotification === 'function') {
                window.showNotification('اختبار النظام - مرحباً بك!', 'info', 2000);
                console.log('✅ showNotification works');
            } else {
                console.error('❌ showNotification not found');
            }

            // اختبار دالة toggleCard
            console.log('\nTesting toggleCard...');
            if (typeof window.toggleCard === 'function') {
                console.log('✅ toggleCard function found');
            } else {
                console.error('❌ toggleCard function not found');
            }

            // اختبار العناصر المطلوبة
            const cards = document.querySelectorAll('.smart-file-card');
            const drawers = document.querySelectorAll('.smart-card-drawer');

            console.log('\n📊 System Status:');
            console.log('Cards found:', cards.length);
            console.log('Drawers found:', drawers.length);

            if (cards.length > 0) {
                const firstCard = cards[0];
                const cardId = firstCard.getAttribute('data-file-id');
                console.log('First card ID:', cardId);

                if (cardId) {
                    console.log('Testing with first card ID:', cardId);
                    setTimeout(() => {
                        window.testToggleCard(parseInt(cardId));
                    }, 1000);
                }
            }

            console.log('\n✅ Complete system test finished!');
        };

    </script>

    <!-- External JavaScript Files - Load in correct order -->
    <script src="js/modules/notifications.js"></script>
    <script src="file-system.js"></script>
    <script src="js/enhanced-ui.js"></script>
    <script src="js/index-functions.js"></script>

    <!-- Debug script - Remove in production -->
    <script>
        // Auto-run diagnostics after page load
        window.addEventListener('load', function() {
            setTimeout(() => {
                console.log('Page loaded successfully');
                if (window.testFunctions) {
                    window.testFunctions();
                }
            }, 1000);
        });

        // Global error handler
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.error);
        });
    </script>

</body>
</html>
