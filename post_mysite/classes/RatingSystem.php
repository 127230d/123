<?php
/**
 * Enhanced File Sharing System - Rating System
 * Handles file ratings and comments with comprehensive functionality
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/models/File.php';
require_once __DIR__ . '/models/User.php';

class RatingSystem {
    private $db;
    private $file_model;
    private $user_model;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->file_model = new File();
        $this->user_model = new User();
    }

    public function addRating($file_id, $user_id, $rating, $comment = null) {
        // Validate input
        if (!$this->validateRatingInput($file_id, $user_id, $rating, $comment)) {
            return [
                'success' => false,
                'message' => 'Invalid rating data'
            ];
        }

        // Check if user can rate this file
        if (!$this->canUserRate($file_id, $user_id)) {
            return [
                'success' => false,
                'message' => 'You cannot rate this file'
            ];
        }

        try {
            $this->db->beginTransaction();

            // Check if user already rated this file
            $existing_rating = $this->getUserRating($file_id, $user_id);

            if ($existing_rating) {
                // Update existing rating
                $this->updateExistingRating($existing_rating['id'], $rating, $comment);
                $message = 'Rating updated successfully';
            } else {
                // Insert new rating
                $this->insertNewRating($file_id, $user_id, $rating, $comment);
                $message = 'Rating added successfully';
            }

            // Update file's average rating
            $this->updateFileAverageRating($file_id);

            $this->db->commit();

            return [
                'success' => true,
                'message' => $message,
                'rating' => $this->getFileRatingSummary($file_id)
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'Failed to save rating: ' . $e->getMessage()
            ];
        }
    }

    public function addComment($file_id, $user_id, $comment, $parent_id = null) {
        // Validate input
        if (!$this->validateCommentInput($file_id, $user_id, $comment)) {
            return [
                'success' => false,
                'message' => 'Invalid comment data'
            ];
        }

        // Check if comments are enabled
        if (!config('limits.enable_comments')) {
            return [
                'success' => false,
                'message' => 'Comments are disabled'
            ];
        }

        try {
            $comment_id = $this->db->insert('file_comments', [
                'file_id' => $file_id,
                'user_id' => $user_id,
                'parent_id' => $parent_id,
                'comment' => $comment,
                'is_approved' => 1, // Auto-approve for now
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return [
                'success' => true,
                'message' => 'Comment added successfully',
                'comment_id' => $comment_id
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to save comment: ' . $e->getMessage()
            ];
        }
    }

    public function getFileRatingSummary($file_id) {
        $stats = $this->db->selectOne("
            SELECT
                COUNT(*) as total_ratings,
                AVG(rating) as average_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_stars,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_stars,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_stars,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_stars,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_stars
            FROM file_ratings
            WHERE file_id = ?
        ", [$file_id]);

        return $stats ?: [
            'total_ratings' => 0,
            'average_rating' => 0,
            'five_stars' => 0,
            'four_stars' => 0,
            'three_stars' => 0,
            'two_stars' => 0,
            'one_stars' => 0
        ];
    }

    public function getFileComments($file_id, $page = 1, $per_page = 20) {
        $offset = ($page - 1) * $per_page;

        $comments = $this->db->select("
            SELECT
                c.*,
                u.username,
                u.first_name,
                u.last_name,
                u.avatar,
                (SELECT COUNT(*) FROM file_comments WHERE parent_id = c.id) as reply_count
            FROM file_comments c
            JOIN login u ON c.user_id = u.username
            WHERE c.file_id = ? AND c.is_approved = 1
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ", [$file_id, $per_page, $offset]);

        $total = $this->db->rowCount("
            SELECT COUNT(*) FROM file_comments
            WHERE file_id = ? AND is_approved = 1
        ", [$file_id]);

        return [
            'comments' => $comments,
            'pagination' => [
                'total' => $total,
                'per_page' => $per_page,
                'current_page' => $page,
                'last_page' => ceil($total / $per_page)
            ]
        ];
    }

    public function getUserRating($file_id, $user_id) {
        return $this->db->selectOne("
            SELECT * FROM file_ratings
            WHERE file_id = ? AND user_id = ?
        ", [$file_id, $user_id]);
    }

    public function deleteRating($file_id, $user_id) {
        $rating = $this->getUserRating($file_id, $user_id);

        if (!$rating) {
            return [
                'success' => false,
                'message' => 'Rating not found'
            ];
        }

        try {
            $this->db->beginTransaction();

            $this->db->delete('file_ratings', 'file_id = ? AND user_id = ?', [$file_id, $user_id]);

            // Update file's average rating
            $this->updateFileAverageRating($file_id);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Rating deleted successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'Failed to delete rating: ' . $e->getMessage()
            ];
        }
    }

    public function markCommentHelpful($comment_id, $user_id) {
        // Check if user already marked this comment as helpful
        $existing = $this->db->exists(
            'file_comment_helpful',
            'comment_id = ? AND user_id = ?',
            [$comment_id, $user_id]
        );

        if ($existing) {
            return [
                'success' => false,
                'message' => 'You have already marked this comment as helpful'
            ];
        }

        $this->db->insert('file_comment_helpful', [
            'comment_id' => $comment_id,
            'user_id' => $user_id
        ]);

        // Update helpful count in comments table
        $this->db->query(
            "UPDATE file_comments SET helpful_count = helpful_count + 1 WHERE id = ?",
            [$comment_id]
        );

        return [
            'success' => true,
            'message' => 'Comment marked as helpful'
        ];
    }

    public function reportComment($comment_id, $user_id, $reason) {
        // Check if user already reported this comment
        $existing = $this->db->exists(
            'file_comment_reports',
            'comment_id = ? AND user_id = ?',
            [$comment_id, $user_id]
        );

        if ($existing) {
            return [
                'success' => false,
                'message' => 'You have already reported this comment'
            ];
        }

        $this->db->insert('file_comment_reports', [
            'comment_id' => $comment_id,
            'user_id' => $user_id,
            'reason' => $reason
        ]);

        // Update report count in comments table
        $this->db->query(
            "UPDATE file_comments SET report_count = report_count + 1 WHERE id = ?",
            [$comment_id]
        );

        return [
            'success' => true,
            'message' => 'Comment reported successfully'
        ];
    }

    public function getRatingDistribution($file_id) {
        return $this->db->select("
            SELECT rating, COUNT(*) as count
            FROM file_ratings
            WHERE file_id = ?
            GROUP BY rating
            ORDER BY rating DESC
        ", [$file_id]);
    }

    public function getTopRatedFiles($limit = 10) {
        return $this->db->select("
            SELECT
                f.*,
                u.username as owner_username,
                c.name as category_name,
                AVG(r.rating) as avg_rating,
                COUNT(r.id) as rating_count
            FROM shared_files f
            LEFT JOIN login u ON f.original_owner_id = u.username
            LEFT JOIN file_categories c ON f.category_id = c.id
            LEFT JOIN file_ratings r ON f.id = r.file_id
            WHERE f.is_available = 1 AND f.rating_count > 0
            GROUP BY f.id
            HAVING avg_rating >= 4.0
            ORDER BY avg_rating DESC, rating_count DESC
            LIMIT ?
        ", [$limit]);
    }

    public function getRecentRatings($limit = 20) {
        return $this->db->select("
            SELECT
                r.*,
                f.filename,
                f.id as file_id,
                u.username as rater_username
            FROM file_ratings r
            JOIN shared_files f ON r.file_id = f.id
            JOIN login u ON r.user_id = u.username
            WHERE f.is_available = 1
            ORDER BY r.created_at DESC
            LIMIT ?
        ", [$limit]);
    }

    private function validateRatingInput($file_id, $user_id, $rating, $comment) {
        if (!$file_id || !$user_id) {
            return false;
        }

        if (!$rating || $rating < 1 || $rating > 5) {
            return false;
        }

        if ($comment && strlen($comment) > config('limits.max_comment_length')) {
            return false;
        }

        return true;
    }

    private function validateCommentInput($file_id, $user_id, $comment) {
        if (!$file_id || !$user_id || !$comment) {
            return false;
        }

        if (strlen($comment) > config('limits.max_comment_length')) {
            return false;
        }

        return true;
    }

    private function canUserRate($file_id, $user_id) {
        $file = $this->file_model->find($file_id);

        if (!$file || !$file['is_available']) {
            return false;
        }

        // Users cannot rate their own files
        if ($file['original_owner_id'] === $user_id) {
            return false;
        }

        // Check if user has purchased the file (optional - can be configured)
        if (config('limits.require_purchase_for_rating')) {
            $has_purchased = $this->db->exists(
                'file_purchases',
                'file_id = ? AND buyer_username = ?',
                [$file_id, $user_id]
            );

            if (!$has_purchased) {
                return false;
            }
        }

        return true;
    }

    private function insertNewRating($file_id, $user_id, $rating, $comment) {
        $this->db->insert('file_ratings', [
            'file_id' => $file_id,
            'user_id' => $user_id,
            'rating' => $rating,
            'comment' => $comment,
            'is_verified_purchase' => $this->isVerifiedPurchase($file_id, $user_id),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function updateExistingRating($rating_id, $rating, $comment) {
        $this->db->update(
            'file_ratings',
            [
                'rating' => $rating,
                'comment' => $comment,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$rating_id]
        );
    }

    private function updateFileAverageRating($file_id) {
        $stats = $this->db->selectOne("
            SELECT
                COUNT(*) as total_ratings,
                AVG(rating) as avg_rating
            FROM file_ratings
            WHERE file_id = ?
        ", [$file_id]);

        if ($stats && $stats['total_ratings'] > 0) {
            $this->file_model->update($file_id, [
                'average_rating' => round($stats['avg_rating'], 2),
                'rating_count' => $stats['total_ratings']
            ]);
        }
    }

    private function isVerifiedPurchase($file_id, $user_id) {
        return $this->db->exists(
            'file_purchases',
            'file_id = ? AND buyer_username = ?',
            [$file_id, $user_id]
        );
    }

    public function getRatingStats($file_id) {
        $summary = $this->getFileRatingSummary($file_id);
        $distribution = $this->getRatingDistribution($file_id);

        return [
            'summary' => $summary,
            'distribution' => $distribution,
            'percentage' => $this->calculateRatingPercentages($summary)
        ];
    }

    private function calculateRatingPercentages($summary) {
        $total = $summary['total_ratings'];
        if ($total === 0) {
            return [
                '5' => 0,
                '4' => 0,
                '3' => 0,
                '2' => 0,
                '1' => 0
            ];
        }

        return [
            '5' => round(($summary['five_stars'] / $total) * 100, 1),
            '4' => round(($summary['four_stars'] / $total) * 100, 1),
            '3' => round(($summary['three_stars'] / $total) * 100, 1),
            '2' => round(($summary['two_stars'] / $total) * 100, 1),
            '1' => round(($summary['one_stars'] / $total) * 100, 1)
        ];
    }
}
