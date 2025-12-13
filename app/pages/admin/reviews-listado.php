<?php
/**
 * Admin - Reviews Management
 */


require_admin();

$site_config = read_json(APP_PATH . '/config/site.json');
$page_title = 'Gestión de Reviews';
$message = '';
$error = '';

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $review_id = $_GET['id'];
    $reviews_data = read_json(APP_PATH . '/data/reviews.json');

    foreach ($reviews_data['reviews'] as &$review) {
        if ($review['id'] === $review_id) {
            if ($_GET['action'] === 'approve') {
                $review['status'] = 'approved';
                $review['approved_at'] = gmdate('Y-m-d\TH:i:s\Z');
                $review['approved_by'] = $_SESSION['username'];
                $message = 'Review aprobado';
            } elseif ($_GET['action'] === 'reject') {
                $review['status'] = 'rejected';
                $message = 'Review rechazado';
            } elseif ($_GET['action'] === 'delete') {
                $reviews_data['reviews'] = array_filter($reviews_data['reviews'], fn($r) => $r['id'] !== $review_id);
                $reviews_data['reviews'] = array_values($reviews_data['reviews']);
                $message = 'Review eliminado';
                write_json(APP_PATH . '/data/reviews.json', $reviews_data);
                log_admin_action('review_deleted', $_SESSION['username'], ['review_id' => $review_id]);
                break;
            }
            break;
        }
    }

    if ($_GET['action'] !== 'delete') {
        write_json(APP_PATH . '/data/reviews.json', $reviews_data);
        log_admin_action('review_' . $_GET['action'], $_SESSION['username'], ['review_id' => $review_id]);
    }
}

// Get reviews
$reviews_data = read_json(APP_PATH . '/data/reviews.json');
$all_reviews = $reviews_data['reviews'] ?? [];

// Filter out reviews from deleted products
$all_reviews = array_filter($all_reviews, function($review) {
    $product = get_product_by_id($review['product_id']);
    return $product !== null;
});
$all_reviews = array_values($all_reviews); // Re-index array

// Filter by status
$filter = $_GET['filter'] ?? 'all';
if ($filter !== 'all') {
    $reviews = array_filter($all_reviews, fn($r) => $r['status'] === $filter);
} else {
    $reviews = $all_reviews;
}

// Sort by date (newest first)
usort($reviews, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

// Stats
$total = count($all_reviews);
$pending = count(array_filter($all_reviews, fn($r) => $r['status'] === 'pending'));
$approved = count(array_filter($all_reviews, fn($r) => $r['status'] === 'approved'));
$rejected = count(array_filter($all_reviews, fn($r) => $r['status'] === 'rejected'));

$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Reviews - Admin</title>
    <style nonce="<?= csp_nonce() ?>">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 15px 20px; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .btn { padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block; cursor: pointer; border: none; transition: all 0.3s; }
        .btn-primary { background: #4CAF50; color: white; }
        .btn-primary:hover { background: #45a049; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        .card { background: white; border-radius: 8px; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 15px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 15px; }
        .stat-card { background: white; padding: 12px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .stat-value { font-size: 24px; font-weight: bold; color: #2c3e50; margin-bottom: 2px; }
        .stat-label { color: #666; font-size: 12px; }
        .filters { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-btn { padding: 8px 16px; border-radius: 6px; background: white; border: 2px solid #e0e0e0; color: #333; text-decoration: none; font-size: 14px; transition: all 0.3s; }
        .filter-btn:hover, .filter-btn.active { background: #4CAF50; border-color: #4CAF50; color: white; }
        .review-card { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 10px; border-left: 4px solid #ccc; }
        .review-card.pending { border-left-color: #FFA726; }
        .review-card.approved { border-left-color: #4CAF50; }
        .review-card.rejected { border-left-color: #dc3545; }
        .review-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .review-user { font-weight: 600; color: #2c3e50; }
        .review-date { color: #666; font-size: 13px; }
        .stars { color: #FFA726; font-size: 18px; }
        .review-comment { color: #555; line-height: 1.6; margin: 15px 0; }
        .review-product { background: white; padding: 10px; border-radius: 6px; font-size: 13px; color: #666; margin-bottom: 15px; }
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.approved { background: #d4edda; color: #155724; }
        .badge.rejected { background: #f8d7da; color: #721c24; }
        .badge.verified { background: #d1ecf1; color: #0c5460; }
        .actions { display: flex; gap: 8px; margin-top: 15px; }
        @media (max-width: 1024px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include APP_PATH . '/includes/admin/sidebar.php'; ?>
    <div class="main-content">
        <?php include APP_PATH . '/includes/admin/header.php'; ?>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo $total; ?></div><div class="stat-label">Total Reviews</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $pending; ?></div><div class="stat-label">Pendientes</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $approved; ?></div><div class="stat-label">Aprobados</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $rejected; ?></div><div class="stat-label">Rechazados</div></div>
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="filters">
                <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">Todos</a>
                <a href="?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pendientes</a>
                <a href="?filter=approved" class="filter-btn <?php echo $filter === 'approved' ? 'active' : ''; ?>">Aprobados</a>
                <a href="?filter=rejected" class="filter-btn <?php echo $filter === 'rejected' ? 'active' : ''; ?>">Rechazados</a>
            </div>

            <?php if (empty($reviews)): ?>
                <p style="text-align: center; padding: 40px; color: #999;">No hay reviews<?php echo $filter !== 'all' ? ' con este estado' : ''; ?>.</p>
            <?php else: ?>
                <?php foreach ($reviews as $review):
                    $product = get_product_by_id($review['product_id']);
                ?>
                    <div class="review-card <?php echo $review['status']; ?>">
                        <div class="review-header">
                            <div>
                                <div class="review-user"><?php echo htmlspecialchars($review['user_name']); ?></div>
                                <div class="review-date"><?php echo date('d/m/Y H:i', strtotime($review['created_at'])); ?></div>
                            </div>
                            <div style="text-align: right;">
                                <div class="stars">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <?php echo $i < $review['rating'] ? '★' : '☆'; ?>
                                    <?php endfor; ?>
                                </div>
                                <span class="badge <?php echo $review['status']; ?>">
                                    <?php echo ucfirst($review['status']); ?>
                                </span>
                                <?php if ($review['verified_purchase']): ?>
                                    <span class="badge verified">Compra verificada</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="review-product">
                            <strong>Producto:</strong> <?php echo $product ? htmlspecialchars($product['name']) : 'Producto no encontrado'; ?>
                        </div>

                        <div class="review-comment">"<?php echo htmlspecialchars($review['comment']); ?>"</div>

                        <div class="actions">
                            <?php if ($review['status'] === 'pending'): ?>
                                <a href="?action=approve&id=<?php echo urlencode($review['id']); ?>&filter=<?php echo $filter; ?>"
                                   class="btn btn-primary btn-sm">✅ Aprobar</a>
                                <a href="?action=reject&id=<?php echo urlencode($review['id']); ?>&filter=<?php echo $filter; ?>"
                                   class="btn btn-secondary btn-sm">❌ Rechazar</a>
                            <?php endif; ?>
                            <a href="?action=delete&id=<?php echo urlencode($review['id']); ?>&filter=<?php echo $filter; ?>"
                               class="btn btn-danger btn-sm"
                               data-action="confirmDeleteReview"
                               data-review-id="<?php echo urlencode($review['id']); ?>">🗑️ Eliminar</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script nonce="<?= csp_nonce() ?>">
        function confirmDeleteReview(event, element, params) {
            if (event && event.preventDefault) event.preventDefault();
            const reviewId = params?.reviewId;
            const url = element?.getAttribute('href');

            if (reviewId && url) {
                showModal({
                    title: 'Eliminar Review',
                    message: '¿Estás seguro de que deseas eliminar este review?',
                    icon: '🗑️',
                    confirmText: 'Eliminar',
                    confirmType: 'danger',
                    onConfirm: function() {
                        window.location.href = url;
                    }
                });
            }
        }

        window.confirmDeleteReview = confirmDeleteReview;
    </script>

    <!-- Modal Component -->
    <?php include APP_PATH . '/includes/admin/modal.php'; ?>

    <!-- Event Delegation System for CSP -->
    <script nonce="<?= csp_nonce() ?>" src="<?php echo url('/assets/js/event-handlers.js'); ?>"></script>
</body>
</html>
