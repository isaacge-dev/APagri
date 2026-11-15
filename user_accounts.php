<?php
/**
 * user_accounts.php — Manage System Users
 * Administrator only
 */
require_once 'auth.php';
require_once 'db.php';
requireAdministrator();

$notice = '';
$noticeType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['user_id'] ?? 0);

    if ($action === 'create') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = ($_POST['role'] ?? '') === 'Administrator' ? 'Administrator' : 'Staff';

        if (strlen($password) < 8) {
            $notice = 'Password must be at least 8 characters.';
            $noticeType = 'danger';
        } elseif ($fullName === '' || $username === '' || $email === '') {
            $notice = 'All fields are required.';
            $noticeType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO public.users (full_name, username, email, password_hash, role, permissions, is_active)
                    VALUES (:name, :username, :email, :hash, :role, :permissions, TRUE)
                    RETURNING user_id
                ');
                $stmt->execute([
                    ':name'        => $fullName,
                    ':username'    => $username,
                    ':email'       => $email,
                    ':hash'        => password_hash($password, PASSWORD_DEFAULT),
                    ':role'        => $role,
                    ':permissions' => json_encode($_POST['permissions'] ?? [])
                ]);
                $newId = $stmt->fetchColumn();
                logActivity($pdo, 'ACCOUNT_CREATED', 'User Accounts', $newId, "Created account for {$username}.");
                $notice = "Account for '{$username}' created successfully.";
                $noticeType = 'success';
            } catch (PDOException $e) {
                $notice = 'Failed to create account: ' . $e->getMessage();
                $noticeType = 'danger';
            }
        }
    } elseif ($id > 0 && $action === 'reset_password') {
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 8) {
            $notice = 'Password must be at least 8 characters.';
            $noticeType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE public.users SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP WHERE user_id = :id');
                $stmt->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id]);
                logActivity($pdo, 'PASSWORD_RESET', 'User Accounts', $id, 'Administrator set a new fixed password.');
                $notice = 'Password updated.';
            } catch (PDOException $e) {
                $notice = 'Failed to update password: ' . $e->getMessage();
                $noticeType = 'danger';
            }
        }
    } elseif ($id > 0 && $action === 'toggle') {
        try {
            $stmt = $pdo->prepare('UPDATE public.users SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP WHERE user_id = :id RETURNING is_active');
            $stmt->execute([':id' => $id]);
            logActivity($pdo, 'ACCOUNT_STATUS', 'User Accounts', $id, 'Account active status toggled.');
            $notice = 'Account status updated.';
            $noticeType = 'success';
        } catch (PDOException $e) {
            $notice = 'Failed to update status: ' . $e->getMessage();
            $noticeType = 'danger';
        }
    } elseif ($id > 0 && $action === 'role') {
        $role = ($_POST['role'] ?? '') === 'Administrator' ? 'Administrator' : 'Staff';
        try {
            $stmt = $pdo->prepare('UPDATE public.users SET role = :role, updated_at = CURRENT_TIMESTAMP WHERE user_id = :id');
            $stmt->execute([':role' => $role, ':id' => $id]);
            logActivity($pdo, 'ROLE_CHANGED', 'User Accounts', $id, "Role changed to {$role}.");
            $notice = 'Role updated.';
        } catch (PDOException $e) {
            $notice = 'Failed to update role: ' . $e->getMessage();
            $noticeType = 'danger';
        }
    } elseif ($id > 0 && $action === 'delete' && $id !== (int)$_SESSION['user_id']) {
        try {
            $stmt = $pdo->prepare('DELETE FROM public.users WHERE user_id = :id');
            $stmt->execute([':id' => $id]);
            logActivity($pdo, 'DELETE', 'User Accounts', $id, 'Account deleted.');
            $notice = 'Account deleted.';
            $noticeType = 'success';
        } catch (PDOException $e) {
            $notice = 'Failed to delete account: ' . $e->getMessage();
            $noticeType = 'danger';
        }
    }
}

$users = $pdo->query('SELECT user_id, full_name, username, email, role, is_active, created_at FROM public.users ORDER BY user_id')->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'User Accounts';
require_once 'header.php';
require_once 'sidebar.php';
?>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold" style="color: var(--green-dark);">
                <i class="bi bi-person-gear me-2"></i>User Accounts
            </h4>
            <div class="text-muted small">Manage Administrator and Staff credentials and permissions</div>
        </div>
    </div>

    <?php if ($notice): ?>
        <div class="alert alert-<?= $noticeType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($notice) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Create User Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold" style="color: var(--green-darkest);">
                <i class="bi bi-person-plus-fill me-2"></i>Create New Account
            </h6>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="create">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Full Name *</label>
                    <input class="form-control" name="full_name" placeholder="e.g. Maria Santos" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Username *</label>
                    <input class="form-control" name="username" placeholder="e.g. msantos" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Email *</label>
                    <input class="form-control" type="email" name="email" placeholder="e.g. msantos@apagri.local" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Password *</label>
                    <input class="form-control" type="password" name="password" placeholder="Min. 8 characters" minlength="8" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Role *</label>
                    <select class="form-select" name="role">
                        <option value="Staff">Staff</option>
                        <option value="Administrator">Administrator</option>
                    </select>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary-green">
                        <i class="bi bi-check-lg me-1"></i>Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="mb-0 fw-bold" style="color: var(--green-darkest);">
                <i class="bi bi-people-fill me-2"></i>Registered Users
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="usersTable" class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['user_id'] ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <?php if ($u['role'] === 'Administrator'): ?>
                                        <span class="badge bg-success"><i class="bi bi-shield-lock-fill me-1"></i>Administrator</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-person-fill me-1"></i>Staff</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars(substr($u['created_at'] ?? '', 0, 10)) ?></td>
                                <td class="text-end">
                                    <?php if ((int)$u['user_id'] !== (int)$_SESSION['user_id']): ?>
                                        <form method="post" class="d-inline-flex gap-1 align-items-center">
                                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                            <input type="hidden" name="action" value="role">
                                            <select class="form-select form-select-sm" name="role" aria-label="Role for <?= htmlspecialchars($u['username']) ?>">
                                                <option value="Staff" <?= $u['role'] === 'Staff' ? 'selected' : '' ?>>Staff</option>
                                                <option value="Administrator" <?= $u['role'] === 'Administrator' ? 'selected' : '' ?>>Administrator</option>
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary">Save role</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" class="d-inline-flex gap-1 align-items-center">
                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input class="form-control form-control-sm" type="password" name="password" minlength="8" placeholder="Set password" aria-label="Set password for <?= htmlspecialchars($u['username']) ?>" required style="max-width: 130px">
                                        <button class="btn btn-sm btn-outline-primary" title="Set password">Set</button>
                                    </form>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <button class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="Toggle status">
                                            <?= $u['is_active'] ? '<i class="bi bi-pause-fill"></i> Disable' : '<i class="bi bi-play-fill"></i> Enable' ?>
                                        </button>
                                    </form>
                                    <?php if ((int)$u['user_id'] !== (int)$_SESSION['user_id']): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete user account <?= htmlspecialchars($u['username']) ?>?')">
                                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button class="btn btn-sm btn-outline-danger" title="Delete account">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        pageLength: 10,
        responsive: true
    });
});
</script>

<?php require_once 'footer.php'; ?>
