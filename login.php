<?php
/**
 * login.php — APagri Authentication Page
 * Role-based login: Administrator & Staff
 * Connected to Supabase PostgreSQL (public.users)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id']) && ctype_digit((string)$_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$_SESSION = [];
session_regenerate_id(true);

$error   = '';
$selRole = trim($_POST['role'] ?? 'Administrator');
if (!in_array($selRole, ['Administrator', 'Staff'], true)) {
    $selRole = 'Administrator';
}
$selUser = trim($_POST['username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username      = trim($_POST['username'] ?? '');
    $password      = $_POST['password'] ?? '';
    $submittedRole = trim((string)($_POST['role'] ?? 'Administrator'));

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        require_once 'db.php';
        try {
            // Case-insensitive username match
            $stmt = $pdo->prepare('
                SELECT user_id, full_name, username, password_hash, role, permissions, is_active
                FROM public.users
                WHERE LOWER(username) = LOWER(:username)
                LIMIT 1
            ');
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                error_log('APagri login: no account matched the submitted username.');
                $error = 'Invalid username or password.';
            } elseif (!($user['is_active'] === true || $user['is_active'] === 't' || $user['is_active'] == 1 || $user['is_active'] === 'true')) {
                $error = 'This account is disabled. Please contact an administrator.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                error_log('APagri login: password verification failed for the matched account.');
                $error = 'Invalid username or password.';
            } elseif (strcasecmp(trim($submittedRole), trim((string)$user['role'])) !== 0) {
                // Clear and helpful error when the user selects the wrong tab
                $error = "Role mismatch: This account is registered as '{$user['role']}'. Please select the {$user['role']} tab to sign in.";
            } else {
                // Successful authentication
                session_regenerate_id(true);
                $_SESSION['user_id']   = (int)$user['user_id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = trim($user['role']);
                $_SESSION['user_name'] = $_SESSION['full_name'];
                $_SESSION['user_role'] = $_SESSION['role'];

                // Permissions array normalization
                $perms = $user['permissions'] ?? [];
                if (is_string($perms)) {
                    $perms = json_decode($perms, true) ?: [];
                }
                $_SESSION['permissions'] = is_array($perms) ? $perms : [];
                require_once 'auth.php';
                logActivity($pdo, 'LOGIN', 'Authentication', $user['user_id'], 'User signed in.');
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('APagri login failed: ' . $e->getMessage());
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — APagri Agriculture Information System</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --green-darkest: #1b4332;
            --green-dark:    #2d6a4f;
            --green-mid:     #40916c;
            --green-light:   #74c69d;
            --green-xlight:  #b7e4c7;
            --green-pale:    #d8f3dc;
        }

        /* ── Background ── */
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #1b4332 url('login-bg.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient overlay to enhance card focus and text contrast */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at center, rgba(15, 45, 30, 0.20) 0%, rgba(10, 30, 20, 0.50) 100%);
            backdrop-filter: blur(1px);
            -webkit-backdrop-filter: blur(1px);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Card ── */
        .login-wrap {
            position: relative;
            z-index: 1;
            width: 100%;
            padding: 1.5rem 1rem;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.75);
            border-radius: 24px;
            box-shadow: 0 24px 64px rgba(10, 35, 20, 0.35), 0 2px 8px rgba(0, 0, 0, 0.08);
            padding: 2.4rem 2.4rem 2rem;
            width: 100%;
            max-width: 430px;
            margin: 0 auto;
            animation: cardIn .45s cubic-bezier(.34,1.56,.64,1) both;
        }
        @keyframes cardIn {
            from { opacity:0; transform: translateY(28px) scale(.96); }
            to   { opacity:1; transform: translateY(0)    scale(1);   }
        }

        /* ── SVG Logo ── */
        .logo-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 1.4rem;
        }
        .logo-tile {
            width: 72px; height: 72px;
            background: linear-gradient(145deg, var(--green-pale), #ffffff);
            border: 2px solid var(--green-xlight);
            border-radius: 20px;
            box-shadow: 0 4px 18px rgba(45,106,79,.20);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: .85rem;
            transition: transform .3s cubic-bezier(.34,1.56,.64,1);
        }
        .logo-tile:hover { transform: rotate(-6deg) scale(1.08); }
        .logo-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--green-darkest);
            letter-spacing: -.5px;
            margin: 0;
        }
        .logo-sub {
            font-size: .82rem;
            color: #6c757d;
            margin: 2px 0 0;
            text-align: center;
        }

        /* ── Role Tabs ── */
        .role-tabs {
            display: flex;
            border-radius: 12px;
            overflow: hidden;
            border: 1.5px solid var(--green-pale);
            background: #f8faf8;
            margin-bottom: 1.4rem;
            padding: 3px;
            gap: 4px;
        }
        .role-tab-btn {
            flex: 1;
            border: none;
            background: transparent;
            padding: .6rem .5rem;
            font-size: .88rem;
            font-weight: 600;
            color: var(--green-dark);
            cursor: pointer;
            border-radius: 9px;
            transition: all .2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
        }
        .role-tab-btn.active {
            background: var(--green-mid);
            color: #fff;
            box-shadow: 0 2px 8px rgba(64,145,108,.35);
        }
        .role-tab-btn:not(.active):hover {
            background: var(--green-pale);
        }

        /* ── Inputs ── */
        .form-control {
            border-radius: 10px;
            padding: .65rem .85rem;
            font-size: .95rem;
        }
        .form-control:focus {
            border-color: var(--green-light);
            box-shadow: 0 0 0 .22rem rgba(116,198,157,.30);
        }
        .input-group-text {
            background: #f8faf8;
            border-color: #dee2e6;
            color: var(--green-mid);
            border-radius: 10px 0 0 10px;
        }
        .input-group .form-control {
            border-radius: 0 10px 10px 0;
        }
        .input-group .btn-outline-secondary {
            border-color: #dee2e6;
            border-radius: 0 10px 10px 0;
        }
        .input-group .form-control.with-toggle {
            border-radius: 0;
        }

        /* ── Submit Button ── */
        .btn-login {
            background: var(--green-mid);
            border: none;
            color: #fff;
            width: 100%;
            padding: .78rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: .02em;
            transition: background .2s, transform .15s cubic-bezier(.34,1.56,.64,1), box-shadow .2s;
        }
        .btn-login:hover {
            background: var(--green-dark);
            color: #fff;
            box-shadow: 0 6px 18px rgba(45,106,79,.35);
        }
        .btn-login:active { transform: scale(.97); }

        /* ── Error alert ── */
        .alert-danger {
            border-radius: 10px;
            font-size: .88rem;
            border: none;
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
<div class="login-wrap">
<div class="login-card">

    <!-- ── Logo ── -->
    <div class="logo-wrap">
        <div class="logo-tile">
            <!-- Agriculture SVG Logo — leaf & plant icon -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"
                 width="44" height="44" fill="none" aria-hidden="true">
                <!-- Main stem -->
                <line x1="20" y1="34" x2="20" y2="10"
                      stroke="#1b4332" stroke-width="2.4" stroke-linecap="round"/>
                <!-- Left leaves -->
                <path d="M20 24 C14 20 10 14 13 8 C16 14 20 18 20 24Z"
                      fill="#2d6a4f" opacity=".9"/>
                <path d="M20 30 C13 27 9 21 11 15 C14 20 18 25 20 30Z"
                      fill="#40916c" opacity=".75"/>
                <!-- Right leaves -->
                <path d="M20 24 C26 20 30 14 27 8 C24 14 20 18 20 24Z"
                      fill="#40916c" opacity=".9"/>
                <path d="M20 30 C27 27 31 21 29 15 C26 20 22 25 20 30Z"
                      fill="#74c69d" opacity=".75"/>
                <!-- Ground -->
                <ellipse cx="20" cy="34.5" rx="5" ry="1.5"
                         fill="#1b4332" opacity=".35"/>
            </svg>
        </div>
        <h1 class="logo-title">APagri</h1>
        <p class="logo-sub">Agriculture Information System</p>
    </div>

    <!-- ── Error Alert ── -->
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <!-- ── Role Tabs (Administrator / Staff) ── -->
    <div class="role-tabs" role="group" aria-label="Login role">
        <button type="button" class="role-tab-btn <?= $selRole === 'Administrator' ? 'active' : '' ?>"
                id="tabAdmin" onclick="setRole('Administrator')">
            <i class="bi bi-shield-lock-fill"></i> Administrator
        </button>
        <button type="button" class="role-tab-btn <?= $selRole === 'Staff' ? 'active' : '' ?>"
                id="tabStaff" onclick="setRole('Staff')">
            <i class="bi bi-person-badge-fill"></i> Staff
        </button>
    </div>

    <!-- ── Form ── -->
    <form method="POST" action="login.php" novalidate id="loginForm">
        <!-- Hidden role field populated by JS / synced with selected tab -->
        <input type="hidden" name="role" id="roleField"
               value="<?= htmlspecialchars($selRole) ?>">

        <div class="mb-3">
            <label class="form-label fw-semibold small text-secondary">Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                <input type="text" name="username" class="form-control"
                       placeholder="Enter username"
                       value="<?= htmlspecialchars($selUser) ?>"
                       required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold small text-secondary">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                <input type="password" name="password" id="passwordInput"
                       class="form-control with-toggle" placeholder="Enter password" required>
                <button type="button" class="btn btn-outline-secondary"
                        onclick="togglePassword()" tabindex="-1" title="Show/hide password">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
    </form>

</div><!-- /.login-card -->
</div><!-- /.login-wrap -->

<script>
/* ── Role tab switcher ── */
function setRole(role) {
    document.getElementById('roleField').value = role;

    const adminBtn = document.getElementById('tabAdmin');
    const staffBtn = document.getElementById('tabStaff');

    if (role === 'Administrator') {
        adminBtn.classList.add('active');
        staffBtn.classList.remove('active');
    } else {
        staffBtn.classList.add('active');
        adminBtn.classList.remove('active');
    }
}

/* ── Password visibility toggle ── */
function togglePassword() {
    const inp = document.getElementById('passwordInput');
    const ico = document.getElementById('eyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        ico.className = 'bi bi-eye';
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
