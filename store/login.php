<?php
// login.php
require_once 'includes/db.php';
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$active_tab = 'login'; // default
if (isset($_SESSION['register_error']) || isset($_SESSION['register_success'])) {
    $active_tab = 'register';
} elseif (isset($_SESSION['token_error'])) {
    $active_tab = 'token';
}

require_once 'includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card glass-container">
        <div class="auth-header">
            <h1>Access Portal</h1>
            <p>Sign in, register, or use your secure access token</p>
        </div>
        
        <!-- Sliding Tab Selector -->
        <div class="auth-tabs-container">
            <button type="button" class="auth-tab" data-target="login">Sign In</button>
            <button type="button" class="auth-tab" data-target="register">Register</button>
            <button type="button" class="auth-tab" data-target="token">Token</button>
            <div class="auth-tab-slider" id="tabSlider"></div>
        </div>

        <div class="auth-form-container">
            <!-- Login Form -->
            <div class="auth-form" id="form-login">
                <?php
                if(isset($_SESSION['login_error'])) {
                    echo "<div class='alert alert-error' style='font-size: 0.9rem; padding: 10px 15px; margin-bottom: 20px;'>" . htmlspecialchars($_SESSION['login_error']) . "</div>";
                    unset($_SESSION['login_error']);
                }
                ?>
                <form action="process_auth.php?action=login" method="POST">
                    <div class="auth-input-group">
                        <label>Username</label>
                        <input type="text" name="username" placeholder="Enter your username" required>
                    </div>
                    <div class="auth-input-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="auth-submit-btn btn-login">
                        Sign In &rarr;
                    </button>
                </form>
            </div>

            <!-- Register Form -->
            <div class="auth-form" id="form-register">
                <?php
                if(isset($_SESSION['register_error'])) {
                    echo "<div class='alert alert-error' style='font-size: 0.9rem; padding: 10px 15px; margin-bottom: 20px;'> " . htmlspecialchars($_SESSION['register_error']) . "</div>";
                    unset($_SESSION['register_error']);
                }
                if(isset($_SESSION['register_success'])) {
                    echo "<div class='alert alert-success' style='font-size: 0.9rem; padding: 10px 15px; margin-bottom: 20px;'>" . htmlspecialchars($_SESSION['register_success']) . "</div>";
                    unset($_SESSION['register_success']);
                }
                ?>
                <form action="process_auth.php?action=register" method="POST">
                    <div class="auth-input-group register-input">
                        <label>Choose Username</label>
                        <input type="text" name="username" placeholder="Min. 3 characters" required>
                    </div>
                    <div class="auth-input-group register-input">
                        <label>Choose Password</label>
                        <input type="password" name="password" placeholder="Min. 5 characters" required>
                    </div>
                    <button type="submit" class="auth-submit-btn btn-register">
                        Register Now &rarr;
                    </button>
                </form>
            </div>

            <!-- Token Login Form -->
            <div class="auth-form" id="form-token">
                <?php
                if(isset($_SESSION['token_error'])) {
                    echo "<div class='alert alert-error' style='font-size: 0.9rem; padding: 10px 15px; margin-bottom: 20px;'>" . htmlspecialchars($_SESSION['token_error']) . "</div>";
                    unset($_SESSION['token_error']);
                }
                ?>
                <form action="process_auth.php?action=token_login" method="POST">
                    <div class="auth-input-group token-input">
                        <label>Secure Login Token</label>
                        <input type="text" name="login_token" placeholder="DARK-TOKEN-XXXX..." style="font-family: monospace;" required>
                    </div>
                    <div style="margin-top: 30px;">
                        <button type="submit" class="auth-submit-btn btn-token">
                            Authenticate &rarr;
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.auth-tab');
    const slider = document.getElementById('tabSlider');
    const forms = document.querySelectorAll('.auth-form');

    // Function to set slider position and active styles
    const setTab = (targetName) => {
        tabs.forEach((tab, index) => {
            if (tab.getAttribute('data-target') === targetName) {
                tab.classList.add('active');
                slider.style.transform = `translateX(${index * 100}%)`;
                
                // Customize slider color theme dynamically for a premium effect
                if (targetName === 'register') {
                    slider.style.background = 'linear-gradient(135deg, rgba(3, 218, 198, 0.85), rgba(3, 218, 198, 0.55))';
                    slider.style.boxShadow = '0 2px 12px rgba(3, 218, 198, 0.4)';
                } else if (targetName === 'token') {
                    slider.style.background = 'linear-gradient(135deg, rgba(255, 140, 0, 0.85), rgba(255, 69, 0, 0.55))';
                    slider.style.boxShadow = '0 2px 12px rgba(255, 140, 0, 0.4)';
                } else {
                    slider.style.background = 'linear-gradient(135deg, rgba(187, 134, 252, 0.85), rgba(187, 134, 252, 0.55))';
                    slider.style.boxShadow = '0 2px 12px rgba(187, 134, 252, 0.4)';
                }
            } else {
                tab.classList.remove('active');
            }
        });

        forms.forEach(form => {
            if (form.id === `form-${targetName}`) {
                form.classList.add('active');
            } else {
                form.classList.remove('active');
            }
        });
    };

    // Initial position on load (based on active tab calculated by PHP)
    const initialActiveTab = "<?php echo $active_tab; ?>";
    setTab(initialActiveTab);

    // Event listeners
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-target');
            setTab(target);
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
