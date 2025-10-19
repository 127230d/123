<?php
session_start();
require_once 'dssssssssb.php';
require_once 'includes/file_operations.php';

header('Content-Type: application/json; charset=utf-8');

$response = [
    'ok' => false,
    'message' => ''
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('طريقة الطلب غير مسموح بها');
    }

    if (!isset($_SESSION['username'])) {
        http_response_code(401);
        throw new Exception('يجب تسجيل الدخول لإتمام الشراء');
    }

    $fileId = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
    if (!$fileId) {
        http_response_code(400);
        throw new Exception('معرّف ملف غير صالح');
    }

    // جلب بيانات المشتري
    $stmt = mysqli_prepare($con, 'SELECT id, username, points, subscription FROM login WHERE username = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $_SESSION['username']);
    mysqli_stmt_execute($stmt);
    $buyerRes = mysqli_stmt_get_result($stmt);
    $buyer = mysqli_fetch_assoc($buyerRes);
    mysqli_stmt_close($stmt);

    if (!$buyer) {
        http_response_code(401);
        throw new Exception('المستخدم غير موجود');
    }

    // جلب معلومات الملف والبائع الحالي
    $stmt = mysqli_prepare($con, 'SELECT sf.id, sf.price, sf.file_path, sf.is_available, COALESCE(sf.current_owner_id, sf.user_id) AS owner_id, u.username AS owner_username FROM shared_files sf LEFT JOIN login u ON u.id = COALESCE(sf.current_owner_id, sf.user_id) WHERE sf.id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $fileId);
    mysqli_stmt_execute($stmt);
    $fileRes = mysqli_stmt_get_result($stmt);
    $file = mysqli_fetch_assoc($fileRes);
    mysqli_stmt_close($stmt);

    if (!$file) {
        http_response_code(404);
        throw new Exception('الملف غير موجود');
    }

    if ((int)$file['is_available'] !== 1) {
        http_response_code(409);
        throw new Exception('الملف غير متاح للشراء');
    }

    $buyerId = (int)$buyer['id'];
    $sellerId = (int)$file['owner_id'];
    $price = (int)$file['price'];

    if ($buyerId === $sellerId) {
        http_response_code(409);
        throw new Exception('لا يمكنك شراء ملف تمتلكه بالفعل');
    }

    if ($price < 0) {
        http_response_code(400);
        throw new Exception('سعر غير صالح');
    }

    // التحقق من وجود الأعمدة الاختيارية
    $hasCurrentOwner = false; $hasIsTransferred = false; $hasPurchasedOwner = false; $hasPurchasedFile = false; $hasOwnershipDate = false;

    $colRes = mysqli_query($con, "SHOW COLUMNS FROM shared_files LIKE 'current_owner_id'");
    if ($colRes && mysqli_num_rows($colRes) > 0) { $hasCurrentOwner = true; }

    $colRes = mysqli_query($con, "SHOW COLUMNS FROM shared_files LIKE 'is_transferred'");
    if ($colRes && mysqli_num_rows($colRes) > 0) { $hasIsTransferred = true; }

    $colRes = mysqli_query($con, "SHOW COLUMNS FROM purchased_files LIKE 'owner_id'");
    if ($colRes && mysqli_num_rows($colRes) > 0) { $hasPurchasedOwner = true; }

    $colRes = mysqli_query($con, "SHOW COLUMNS FROM purchased_files LIKE 'file_id'");
    if ($colRes && mysqli_num_rows($colRes) > 0) { $hasPurchasedFile = true; }

    $colRes = mysqli_query($con, "SHOW COLUMNS FROM purchased_files LIKE 'ownership_date'");
    if ($colRes && mysqli_num_rows($colRes) > 0) { $hasOwnershipDate = true; }

    mysqli_begin_transaction($con);

    // خصم من المشتري
    $stmt = mysqli_prepare($con, 'UPDATE login SET points = points - ? WHERE id = ? AND points >= ?');
    mysqli_stmt_bind_param($stmt, 'iii', $price, $buyerId, $price);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_affected_rows($stmt) === 0) {
        throw new Exception('رصيد غير كافٍ');
    }
    mysqli_stmt_close($stmt);

    // إضافة للبائع
    $stmt = mysqli_prepare($con, 'UPDATE login SET points = points + ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $price, $sellerId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // نقل الملكية الفعلية للملف
    try {
        $newFilePath = transferFileOwnership($file['file_path'], $buyer['username']);
        
        // تحديث مسار الملف ونقل الملكية + إيقاف الإتاحة للبيع
        if ($hasCurrentOwner && $hasIsTransferred) {
            $stmt = mysqli_prepare($con, 'UPDATE shared_files SET current_owner_id = ?, file_path = ?, is_transferred = 1, is_available = 0 WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'isi', $buyerId, $newFilePath, $fileId);
        } elseif ($hasCurrentOwner) {
            $stmt = mysqli_prepare($con, 'UPDATE shared_files SET current_owner_id = ?, file_path = ?, is_available = 0 WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'isi', $buyerId, $newFilePath, $fileId);
        } else {
            $stmt = mysqli_prepare($con, 'UPDATE shared_files SET file_path = ?, is_available = 0 WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'si', $newFilePath, $fileId);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        mysqli_rollback($con);
        throw new Exception('فشل في نقل الملف: ' . $e->getMessage());
    }

    // سجل في file_purchases
    $stmt = mysqli_prepare($con, 'INSERT INTO file_purchases (buyer_id, file_id, seller_id, price_paid) VALUES (?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'iiii', $buyerId, $fileId, $sellerId, $price);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // تحديث purchased_files (ملكية حالية)
    if ($hasPurchasedOwner || $hasPurchasedFile) {
        // إدراج بالحقلين الجديدين إن وُجدا
        if ($hasPurchasedOwner && $hasPurchasedFile) {
            $stmt = mysqli_prepare($con, 'INSERT INTO purchased_files (owner_id, file_id) VALUES (?, ?)');
            mysqli_stmt_bind_param($stmt, 'ii', $buyerId, $fileId);
        } elseif ($hasPurchasedOwner) {
            $stmt = mysqli_prepare($con, 'INSERT INTO purchased_files (owner_id) VALUES (?)');
            mysqli_stmt_bind_param($stmt, 'i', $buyerId);
        } elseif ($hasPurchasedFile) {
            $stmt = mysqli_prepare($con, 'INSERT INTO purchased_files (file_id) VALUES (?)');
            mysqli_stmt_bind_param($stmt, 'i', $fileId);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        // توافق خلفي مع البنية القديمة (user_id نصّي + file_path)
        $buyerUsername = $buyer['username'];
        $stmt = mysqli_prepare($con, 'INSERT INTO purchased_files (user_id, file_path, price) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'ssi', $buyerUsername, $file['file_path'], $price);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // سجل المعاملة العامة
    $stmt = mysqli_prepare($con, 'INSERT INTO transactions (buyer_id, seller_id, file_id, amount) VALUES (?, ?, ?, ?)');
    $buyerIdStr = (string)$buyerId; $sellerIdStr = (string)$sellerId; $amount = $price;
    mysqli_stmt_bind_param($stmt, 'ssii', $buyerIdStr, $sellerIdStr, $fileId, $amount);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // تاريخ الحركات المالية: خصم للمشتري، إيداع للبائع
    $descBuyer = 'خصم لشراء ملف #' . $fileId;
    $stmt = mysqli_prepare($con, "INSERT INTO transaction_history (user_id, transaction_type, amount, description) VALUES (?, 'debit', ?, ?)");
    $amountDec = number_format($price, 2, '.', '');
    mysqli_stmt_bind_param($stmt, 'sds', $buyerIdStr, $amountDec, $descBuyer);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $descSeller = 'إيداع من بيع ملف #' . $fileId;
    $stmt = mysqli_prepare($con, "INSERT INTO transaction_history (user_id, transaction_type, amount, description) VALUES (?, 'credit', ?, ?)");
    mysqli_stmt_bind_param($stmt, 'sds', $sellerIdStr, $amountDec, $descSeller);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($con);

    $response['ok'] = true;
    $response['message'] = 'تمت عملية الشراء ونقل الملكية بنجاح';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($con) && mysqli_errno($con) === 0) {
        // no-op
    }
    if (isset($con)) { @mysqli_rollback($con); }
    $response['ok'] = false;
    $response['message'] = $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

