<?php
/**
 * auth.php — Session Guard
 * Include this at the top of every protected page.
 * If no valid session exists, redirects to login.php.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Reject the local bypass session created by the previous login-disabled build.
if (empty($_SESSION['user_id']) || !ctype_digit((string)$_SESSION['user_id'])) {
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: login.php');
    exit;
}

// Keep legacy template keys in sync with the canonical auth session keys.
$_SESSION['full_name'] = $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'User';
$_SESSION['username'] = $_SESSION['username'] ?? '';
$_SESSION['role'] = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'Staff';
$_SESSION['permissions'] = $_SESSION['permissions'] ?? [];
$_SESSION['user_name'] = $_SESSION['full_name'];
$_SESSION['user_role'] = $_SESSION['role'];

function isAdministrator(): bool {
    return strcasecmp(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')), 'Administrator') === 0;
}

function userCan(string $module, string $action): bool {
    if (isAdministrator()) return true;
    $permissions = $_SESSION['permissions'] ?? [];
    if (is_string($permissions)) $permissions = json_decode($permissions, true) ?: [];
    return !empty($permissions[$module][$action]);
}

function requirePermission(string $module, string $action): void {
    if (!userCan($module, $action)) {
        http_response_code(403);
        exit('You do not have permission to perform this action.');
    }
}

function requireAdministrator(): void {
    if (!isAdministrator()) {
        http_response_code(403);
        exit('Administrator access is required.');
    }
}

function logActivity(PDO $pdo, string $actionType, string $moduleName, $recordId, string $description): void {
    try {
        $stmt = $pdo->prepare('INSERT INTO public.activity_logs (user_id, user_name, action_type, module_name, record_id, description) VALUES (:user_id, :user_name, :action_type, :module_name, :record_id, :description)');
        $stmt->execute([
            ':user_id' => is_numeric($_SESSION['user_id'] ?? null) ? (int)$_SESSION['user_id'] : null,
            ':user_name' => $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'System',
            ':action_type' => $actionType,
            ':module_name' => $moduleName,
            ':record_id' => $recordId === null ? null : (string)$recordId,
            ':description' => $description,
        ]);
    } catch (PDOException $e) {
        error_log('APagri activity log failed: ' . $e->getMessage());
    }
}

function logRecordChange(PDO $pdo, string $actionType, string $moduleName, $recordId, string $label, array $before = [], array $after = []): void {
    $format = static function ($value): string {
        if ($value === null || $value === '') return '(empty)';
        if (is_bool($value)) return $value ? 'true' : 'false';
        return is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };

    if ($actionType === 'CREATE') {
        $details = [];
        foreach ($after as $field => $value) $details[] = $field . '=' . $format($value);
        $description = 'Added ' . $label . ($details ? ': ' . implode('; ', $details) : '.');
    } elseif ($actionType === 'DELETE') {
        $details = [];
        foreach ($before as $field => $value) $details[] = $field . '=' . $format($value);
        $description = 'Deleted ' . $label . ($details ? '. Previous values: ' . implode('; ', $details) : '.');
    } else {
        $changes = [];
        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;
            if ((string)$oldValue !== (string)$newValue) {
                $changes[] = $field . ': ' . $format($oldValue) . ' → ' . $format($newValue);
            }
        }
        $description = 'Edited ' . $label . ($changes ? ': ' . implode('; ', $changes) : ': no field values changed.');
    }

    logActivity($pdo, $actionType, $moduleName, $recordId, $description);
}
?>
