<?php
// reviews.php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fetch all reviews
$stmt = $pdo->query("SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC");
$reviews = $stmt->fetchAll();

// Fetch rating statistics
$stmt_stats = $pdo->query("SELECT COUNT(*) as total, AVG(rating) as average FROM reviews");
$stats = $stmt_stats->fetch();
$total_reviews = intval($stats['total'] ?? 0);
$avg_rating = floatval($stats['average'] ?? 0);

// Initialize distribution counts
$distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
if ($total_reviews > 0) {
    $stmt_dist = $pdo->query("SELECT rating, COUNT(*) as count FROM reviews GROUP BY rating");
    while ($row = $stmt_dist->fetch()) {
        $distribution[intval($row['rating'])] = intval($row['count']);
    }
}
?>

<div style="margin-top: 20px; margin-bottom: 50px;">
    <h1 style="margin-bottom: 10px; font-weight: 800; background: linear-gradient(90deg, var(--primary-color), var(--secondary-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 15px rgba(187, 134, 252, 0.2);">Customer Reviews</h1>
    <p style="color: #aaa; margin-bottom: 40px; font-size: 1.1rem;">See what our community has to say about their payment simulations and card purchases.</p>

    <!-- Reviews Statistics Dashboard -->
    <div class="reviews-dashboard">
        <!-- Left Side: Average Score -->
        <div class="avg-rating-box">
            <span style="font-size: 0.95rem; color: #888; font-weight: 600; margin-bottom: 5px; text-transform: uppercase;">Average Rating</span>
            <span class="avg-rating-num"><?php echo $total_reviews > 0 ? number_format($avg_rating, 1) : "0.0"; ?></span>
            <div class="star-rating large" style="margin: 10px 0;">
                <?php 
                $rounded_avg = round($avg_rating);
                for ($i = 1; $i <= 5; $i++): 
                ?>
                    <span class="star <?php echo ($total_reviews > 0 && $i <= $rounded_avg) ? 'filled' : ''; ?>">★</span>
                <?php endfor; ?>
            </div>
            <span style="color: #aaa; font-size: 0.9rem;">Based on <strong><?php echo $total_reviews; ?></strong> reviews</span>
        </div>

        <!-- Right Side: Star Distribution Bars -->
        <div class="rating-distribution">
            <?php 
            for ($star = 5; $star >= 1; $star--): 
                $count = $distribution[$star];
                $percentage = $total_reviews > 0 ? ($count / $total_reviews) * 100 : 0;
            ?>
                <div class="distribution-row">
                    <span class="dist-label"><?php echo $star; ?> Star</span>
                    <div class="dist-bar-container">
                        <div class="dist-bar-fill" style="width: <?php echo $percentage; ?>%;"></div>
                    </div>
                    <span class="dist-count"><?php echo $count; ?></span>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Write Feedback Section -->
    <div style="background-color: rgba(187, 134, 252, 0.05); border: 1px solid rgba(187, 134, 252, 0.2); border-radius: 12px; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 45px;">
        <div>
            <h3 style="color: #fff; margin-bottom: 5px;">Bought a card from us?</h3>
            <p style="color: #aaa; font-size: 0.95rem;">You can share your own review and give stars inside your personal user dashboard for any verified purchase!</p>
        </div>
        <a href="dashboard.php" class="btn" style="border-radius: 6px; padding: 10px 25px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(187, 134, 252, 0.2);">Go to Dashboard</a>
    </div>

    <!-- Reviews List Grid -->
    <h2 style="font-size: 1.8rem; font-weight: 700; border-bottom: 1px solid #333; padding-bottom: 15px; margin-bottom: 30px; color: #fff;">Review Feed</h2>

    <?php if (empty($reviews)): ?>
        <div style="background-color: var(--card-bg); border: 1px dashed #444; border-radius: 12px; padding: 55px; text-align: center;">
            <p style="font-size: 1.2rem; color: #888; margin-bottom: 15px;">No customer reviews have been submitted yet.</p>
            <p style="color: #666; max-width: 500px; margin: 0 auto 25px auto;">Be the first to submit a star review! Purchase an available game card, wait for verification, and share your thoughts.</p>
            <a href="shop.php" class="btn btn-secondary" style="color: #000; border-radius: 6px; padding: 10px 25px;">Browse Card Inventory</a>
        </div>
    <?php else: ?>
        <div class="reviews-grid" style="margin-top: 0;">
            <?php foreach ($reviews as $rev): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <div class="reviewer-avatar"><?php echo strtoupper(substr($rev['username'], 0, 1)); ?></div>
                            <div>
                                <div class="reviewer-name"><?php echo htmlspecialchars($rev['username']); ?></div>
                                <span class="reviewer-role">Verified Buyer</span>
                            </div>
                        </div>
                        <div class="review-date"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></div>
                    </div>
                    <div class="review-game-tag"><?php echo htmlspecialchars($rev['game_name']); ?></div>
                    <div class="star-rating" style="margin-bottom: 15px; display: block;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?php echo ($i <= $rev['rating']) ? 'filled' : ''; ?>" style="font-size: 1.2rem;">★</span>
                        <?php endfor; ?>
                    </div>
                    <p class="review-body"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
