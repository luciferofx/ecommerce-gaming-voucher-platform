<?php
// shop.php
require_once 'includes/db.php';
require_once 'includes/currency.php';
require_once 'includes/header.php';

// Fetch all available cards
$stmt = $pdo->query("SELECT * FROM cards WHERE status = 'available' ORDER BY created_at DESC");
$available_cards = $stmt->fetchAll();
?>

<div style="margin-top: 20px; margin-bottom: 50px;">
    <h1
        style="margin-bottom: 10px; font-weight: 800; background: linear-gradient(90deg, var(--primary-color), var(--secondary-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 15px rgba(187, 134, 252, 0.2);">
        Game Cards Shop</h1>
    <p style="color: #aaa; margin-bottom: 40px; font-size: 1.1rem;">Select your desired pre-paid gaming cards and
        checkout. Our system is fully simulated.</p>

    <?php
    if (isset($_SESSION['buy_error'])) {
        echo "<div class='alert alert-error'>" . htmlspecialchars($_SESSION['buy_error']) . "</div>";
        unset($_SESSION['buy_error']);
    }
    ?>

    <?php if (empty($available_cards)): ?>
        <div
            style="background-color: var(--card-bg); border: 1px dashed #444; border-radius: 12px; padding: 40px; text-align: center;">
            <p style="font-size: 1.2rem; color: #888; margin-bottom: 20px;">No Card are currently available in the
                inventory.</p>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin.php" class="btn">Go to Admin to Add Cards</a>
            <?php else: ?>
                <p style="color: #666;">Check back later as our administrators regularly replenish stock!</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($available_cards as $card): ?>
                <div class="game-card">
                    <span
                        style="font-size: 0.8rem; background-color: rgba(3, 218, 198, 0.1); color: var(--secondary-color); padding: 3px 8px; border-radius: 4px; font-weight: 600; display: inline-block; margin-bottom: 15px; border: 1px solid rgba(3, 218, 198, 0.2);">INSTANT
                        SIMULATION</span>
                    <h3 style="font-size: 1.4rem; color: #fff; margin-bottom: 10px;">
                        <?php echo htmlspecialchars($card['game_name']); ?></h3>
                    <p style="color: #aaa; margin-bottom: 10px;">Value: <strong
                            style="color: #fff;"><?php echo htmlspecialchars($card['card_value']); ?></strong></p>
                    <div class="price"><?php echo htmlspecialchars(currencyFormat($pdo, $card['price'])); ?></div>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="checkout.php?card_id=<?php echo $card['id']; ?>" class="btn btn-secondary"
                            style="width: 100%; display: block; text-align: center;">Proceed
                            to Checkout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn"
                            style="width: 100%; display: block; text-align: center;">Login
                            to Purchase</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
