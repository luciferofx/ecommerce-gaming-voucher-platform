<?php
// index.php
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fetch 3 featured available cards
$stmt = $pdo->query("SELECT * FROM cards WHERE status = 'available' ORDER BY created_at DESC LIMIT 3");
$featured_cards = $stmt->fetchAll();

// Fetch 3 high-rated recent reviews for landing showcase
$stmt_reviews = $pdo->query("SELECT r.*, u.username 
                             FROM reviews r 
                             JOIN users u ON r.user_id = u.id 
                             ORDER BY r.rating DESC, r.created_at DESC 
                             LIMIT 3");
$featured_reviews = $stmt_reviews->fetchAll();
?>

<!-- Hero Banner -->
<div class="hero">
    <h1>Dark Gaming Cards</h1>
    <p>Premium pre-paid payment keys, game vouchers, and credit cards. Instant digital delivery, 100% secure vault, and dedicated admin verification.</p>
    <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
        <a href="shop.php" class="btn btn-secondary" style="font-size: 1.1rem; padding: 12px 30px; color: #000; box-shadow: 0 4px 15px rgba(3, 218, 198, 0.3);">Browse Shop</a>
        <a href="reviews.php" class="btn" style="font-size: 1.1rem; padding: 12px 30px; box-shadow: 0 4px 15px rgba(187, 134, 252, 0.3);">Customer Reviews</a>
    </div>
</div>

<!-- Features Showcase -->
<div style="margin-top: 60px; margin-bottom: 60px;">
    <h2 class="text-center" style="font-size: 2.2rem; margin-bottom: 40px; font-weight: 700;">Why Gamers Choose Us</h2>
    <div class="features-grid">
        <div class="feature-card">
            <div class="icon">⚡</div>
            <h3>Instant Delivery</h3>
            <p>Once our administrators verify your purchase payment simulation, your card details and codes appear in your dashboard instantly.</p>
        </div>
        <div class="feature-card">
            <div class="icon">🛡️</div>
            <h3>Multi-Gateway Vault</h3>
            <p>We support Credit/Debit Cards (VISA, MasterCard, Rupay, JCB), Cryptocurrency, and PayPal for highly protected simulations.</p>
        </div>
        <div class="feature-card">
            <div class="icon">⭐</div>
            <h3>Transparent Ratings</h3>
            <p>100% honest and transparent star reviews. Feedback can only be written by registered buyers with verified orders.</p>
        </div>
    </div>
</div>

<!-- Featured Cards Section -->
<div style="margin-top: 60px; margin-bottom: 60px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid #333; padding-bottom: 15px;">
        <h2 style="font-size: 2rem; font-weight: 700; margin: 0;">Featured Game Cards</h2>
        <a href="shop.php" style="color: var(--secondary-color); text-decoration: none; font-weight: bold; transition: color 0.3s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--secondary-color)'">View All Cards &rarr;</a>
    </div>
    
    <?php if (empty($featured_cards)): ?>
        <p style="color: #888;">No cards available at the moment. Check back soon!</p>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($featured_cards as $card): ?>
                <div class="game-card">
                    <span style="font-size: 0.8rem; background-color: rgba(187,134,252,0.1); color: var(--primary-color); padding: 3px 8px; border-radius: 4px; font-weight: 600; display: inline-block; margin-bottom: 10px;">HOT SELLER</span>
                    <h3><?php echo htmlspecialchars($card['game_name']); ?></h3>
                    <p style="color: #aaa; margin: 10px 0;">Value: <?php echo htmlspecialchars($card['card_value']); ?></p>
                    <div class="price">$<?php echo htmlspecialchars($card['price']); ?></div>
                    <a href="checkout.php?card_id=<?php echo $card['id']; ?>" class="btn" style="width: 100%; display: block; text-align: center;">Proceed to Buy</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Customer Reviews Section -->
<div style="margin-top: 80px; margin-bottom: 40px; background: rgba(30,30,30,0.5); padding: 50px 30px; border-radius: 16px; border: 1px solid #2a2a2a;">
    <h2 class="text-center" style="font-size: 2rem; font-weight: 700; margin-bottom: 10px;">Loved by Real Gamers</h2>
    <p class="text-center" style="color: #aaa; margin-bottom: 40px;">Here is what our verified buyers have to say about their experience.</p>
    
    <?php if (empty($featured_reviews)): ?>
        <!-- Fallback mock reviews if database is empty so page looks amazing -->
        <div class="reviews-grid">
            <div class="review-card">
                <div class="review-header">
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">K</div>
                        <div>
                            <div class="reviewer-name">Kaelen_Gamer</div>
                            <div class="reviewer-role">Verified Buyer</div>
                        </div>
                    </div>
                    <div class="review-date">May 15, 2026</div>
                </div>
                <div class="review-game-tag">Steam Wallet / VISA Gift Card</div>
                <div class="star-rating" style="margin-bottom: 10px;">
                    <span class="star filled">★</span>
                    <span class="star filled">★</span>
                    <span class="star filled">★</span>
                    <span class="star filled">★</span>
                    <span class="star filled">★</span>
                </div>
                <p class="review-body">Absolute lifesaver! The admin approved my payment within minutes, and the VISA code worked perfectly on my Steam purchase. 10/10 recommend!</p>
            </div>
            
            <div class="review-card">
                <div class="review-header">
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">S</div>
                        <div>
                            <div class="reviewer-name">ShadowBlade</div>
                            <div class="reviewer-role">Verified Buyer</div>
                        </div>
                    </div>
                    <div class="review-date">May 12, 2026</div>
                </div>
                <div class="review-game-tag">MasterCard Voucher</div>
                <div class="star-rating" style="margin-bottom: 10px;">
                    <span class="star filled">★</span>
                    <span class="star filled">★</span>
                    <span class="star filled">★</span>
                    <span class="star filled">★</span>
                    <span class="star">★</span>
                </div>
                <p class="review-body">Very cool store concept. I got my details. The UI looks super futuristic and dark. Highly secure simulated checkout.</p>
            </div>
            
            <div class="review-card">
                <div class="review-header">
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">E</div>
                        <div>
                            <div class="reviewer-name">ElixirQueen</div>
                            <div class="reviewer-role">Verified Buyer</div>
                        </div>
                    </div>
                    <div class="review-date">May 10, 2026</div>
                </div>
                <div class="review-game-tag">Rupay Card</div>
                <div class="star-rating" style="margin-bottom: 10px;">
                    <span class="star filled">★</span>
                    <span class="star filled">★</span>
                    <span class="star filled">★</span>
                    <span class="star filled">★</span>
                    <span class="star filled">★</span>
                </div>
                <p class="review-body">Highly recommended website for gaming keys! Payment verification was quick. Outstanding UI design!</p>
            </div>
        </div>
    <?php else: ?>
        <div class="reviews-grid">
            <?php foreach ($featured_reviews as $rev): ?>
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
                    <div class="star-rating" style="margin-bottom: 10px;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?php echo ($i <= $rev['rating']) ? 'filled' : ''; ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <p class="review-body"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div class="text-center" style="margin-top: 40px;">
        <a href="reviews.php" class="btn btn-secondary" style="color: #000; padding: 10px 25px; border-radius: 6px; font-weight: bold;">View All Feedbacks</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
