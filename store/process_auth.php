<?php
// process_auth.php
require_once 'includes/db.php';

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            if($user['role'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $_SESSION['login_error'] = "Invalid username or password.";
            header("Location: login.php");
            exit();
        }
    } 
    elseif ($action === 'token_login') {
        $login_token = trim($_POST['login_token'] ?? '');
        
        if (empty($login_token)) {
            $_SESSION['token_error'] = "Access Token is required.";
            header("Location: login.php");
            exit();
        }
        
        $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE login_token = ?");
        $stmt->execute([$login_token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $_SESSION['token_error'] = "Invalid Access Token. Please verify and try again.";
            header("Location: login.php");
            exit();
        }
    }
    elseif ($action === 'register') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        // Simple validation
        if (strlen($username) < 3 || strlen($password) < 5) {
            $_SESSION['register_error'] = "Username must be > 3 chars and password > 5 chars.";
            header("Location: login.php");
            exit();
        }

        // Check if exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['register_error'] = "Username already taken.";
            header("Location: login.php");
            exit();
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $login_token = "DARK-TOKEN-" . strtoupper(bin2hex(random_bytes(12)));
        $stmt = $pdo->prepare("INSERT INTO users (username, password, login_token) VALUES (?, ?, ?)");
        
        try {
            $stmt->execute([$username, $hashed_password, $login_token]);
            $_SESSION['register_success'] = "Registration successful! You can now login.";
        } catch(PDOException $e) {
            $_SESSION['register_error'] = "Registration failed. Try again.";
        }
        
        header("Location: login.php");
        exit();
    }
} elseif ($action === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>
