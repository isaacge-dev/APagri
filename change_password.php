<?php
/**
 * change_password.php — Voluntary Password Update
 */
require_once 'auth.php';
require_once 'db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        try {
            $check = $pdo->prepare('SELECT password_hash FROM public.users WHERE user_id = :id');
            $check->execute([':id' => $_SESSION['user_id']]);
            $account = $check->fetch(PDO::FETCH_ASSOC);
            if (!$account || !password_verify($current, $account['password_hash'])) {
                throw new RuntimeException('Current password is incorrect.');
            }
            $stmt = $pdo->prepare('
                UPDATE public.users
                SET password_hash = :hash,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = :id
            ');
            $stmt->execute([
                ':hash' => password_hash($password, PASSWORD_DEFAULT),
                ':id'   => $_SESSION['user_id']
            ]);
            logActivity($pdo, 'PASSWORD_CHANGE', 'User Accounts', $_SESSION['user_id'], 'User changed password.');

            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Failed to update password: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Change Password';
require_once 'header.php';
require_once 'sidebar.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card border-0 shadow-sm p-4">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 56px; height: 56px; background: var(--green-pale); color: var(--green-dark);">
                        <i class="bi bi-key-fill fs-3"></i>
                    </div>
                    <h4 class="fw-bold" style="color: var(--green-darkest);">Change Password</h4>
                    <p class="text-muted small">Update your account password to keep your session secure.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-3">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                        <small><?= htmlspecialchars($error) ?></small>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Current Password</label>
                        <input class="form-control" type="password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">New Password</label>
                        <input class="form-control" type="password" name="password" minlength="8" placeholder="At least 8 characters" required autocomplete="new-password">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Confirm New Password</label>
                        <input class="form-control" type="password" name="confirm_password" minlength="8" placeholder="Re-type new password" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary-green w-100 py-2">
                        <i class="bi bi-check2-circle me-1"></i>Save New Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
