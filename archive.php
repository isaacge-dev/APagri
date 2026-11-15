<?php
require_once 'auth.php';
require_once 'db.php';

$modules = [
    'farmers' => [
        'title' => 'Farmers', 'table' => 'farmer', 'pk' => 'farmer_id',
        'select' => "r.farmer_id AS id, CONCAT_WS(' ', r.first_name, r.last_name) AS summary, r.contact_number AS detail, r.deleted_by, r.deleted_at",
        'parents' => [], 'children' => [['farm', 'farmer_id']],
    ],
    'farms' => [
        'title' => 'Farms', 'table' => 'farm', 'pk' => 'farm_id',
        'select' => "r.farm_id AS id, r.farm_name AS summary, CONCAT_WS(' • ', f.first_name || ' ' || f.last_name, r.location, s.soil_name) AS detail, r.deleted_by, r.deleted_at",
        'joins' => 'LEFT JOIN public.farmer f ON f.farmer_id = r.farmer_id LEFT JOIN public.soil_type_lookup s ON s.soil_type_id = r.soil_type_id',
        'parents' => [['farmer', 'farmer_id']], 'children' => [['planting', 'farm_id']],
    ],
    'crops' => [
        'title' => 'Crops', 'table' => 'crop', 'pk' => 'crop_id',
        'select' => "r.crop_id AS id, r.crop_name AS summary, CONCAT_WS(' • ', r.variety, cat.category_name) AS detail, r.deleted_by, r.deleted_at",
        'joins' => 'LEFT JOIN public.crop_category_lookup cat ON cat.category_id = r.category_id',
        'parents' => [], 'children' => [['planting', 'crop_id']],
    ],
    'plantings' => [
        'title' => 'Planting Records', 'table' => 'planting', 'pk' => 'planting_id',
        'select' => "r.planting_id AS id, CONCAT_WS(' • ', f.farm_name, c.crop_name) AS summary, CONCAT_WS(' • ', r.planting_date::text, st.status_label) AS detail, r.deleted_by, r.deleted_at",
        'joins' => 'LEFT JOIN public.farm f ON f.farm_id = r.farm_id LEFT JOIN public.crop c ON c.crop_id = r.crop_id LEFT JOIN public.planting_status_lookup st ON st.status_id = r.status_id',
        'parents' => [['farm', 'farm_id'], ['crop', 'crop_id']], 'children' => [['harvest', 'planting_id']],
    ],
    'harvests' => [
        'title' => 'Harvest Records', 'table' => 'harvest', 'pk' => 'harvest_id',
        'select' => "r.harvest_id AS id, CONCAT_WS(' • ', f.farm_name, c.crop_name) AS summary, CONCAT_WS(' • ', r.quantity_kg::text || ' kg', r.harvest_date::text, q.grade_label) AS detail, r.deleted_by, r.deleted_at",
        'joins' => 'LEFT JOIN public.planting p ON p.planting_id = r.planting_id LEFT JOIN public.farm f ON f.farm_id = p.farm_id LEFT JOIN public.crop c ON c.crop_id = p.crop_id LEFT JOIN public.quality_grade_lookup q ON q.grade_code = r.quality_grade',
        'parents' => [['planting', 'planting_id']], 'children' => [],
    ],
];
$moduleKey = (string)($_GET['module'] ?? 'farmers');
if (!isset($modules[$moduleKey])) $moduleKey = 'farmers';
$config = $modules[$moduleKey];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $postModule = (string)($_POST['module'] ?? '');
    if (!$id || !isset($modules[$postModule])) {
        $message = 'Invalid archive record.';
    } else {
        $item = $modules[$postModule];
        try {
            if ($action === 'restore') {
                foreach ($item['parents'] as [$parentTable, $fk]) {
                    $sql = "SELECT deleted_at FROM public.{$parentTable} WHERE {$fk} = (SELECT {$fk} FROM public.{$item['table']} WHERE {$item['pk']} = :id)";
                    $check = $pdo->prepare($sql);
                    $check->execute([':id' => $id]);
                    $parentDeletedAt = $check->fetchColumn();
                    if ($parentDeletedAt === false || $parentDeletedAt !== null) {
                        throw new RuntimeException('Restore the archived parent record first.');
                    }
                }
                $stmt = $pdo->prepare("UPDATE public.{$item['table']} SET deleted_at = NULL, deleted_by = NULL WHERE {$item['pk']} = :id AND deleted_at IS NOT NULL");
                $stmt->execute([':id' => $id]);
                if ($stmt->rowCount()) logActivity($pdo, 'RESTORE', $item['title'], $id, 'Restored archived record #' . $id . ' (' . $item['title'] . ').');
                $message = $stmt->rowCount() ? 'Record restored.' : 'This record is no longer archived.';
            } elseif ($action === 'permanent_delete') {
                requireAdministrator();
                $exists = $pdo->prepare("SELECT 1 FROM public.{$item['table']} WHERE {$item['pk']} = :id AND deleted_at IS NOT NULL");
                $exists->execute([':id' => $id]);
                if (!$exists->fetchColumn()) throw new RuntimeException('Only archived records can be permanently deleted.');
                foreach ($item['children'] as [$childTable, $fk]) {
                    $child = $pdo->prepare("SELECT COUNT(*) FROM public.{$childTable} WHERE {$fk} = :id");
                    $child->execute([':id' => $id]);
                    if ((int)$child->fetchColumn() > 0) throw new RuntimeException('This record still has related records. Permanently delete or reassign those records first.');
                }
                $stmt = $pdo->prepare("DELETE FROM public.{$item['table']} WHERE {$item['pk']} = :id AND deleted_at IS NOT NULL");
                $stmt->execute([':id' => $id]);
                logActivity($pdo, 'PERMANENT_DELETE', $item['title'], $id, 'Permanently deleted archived record #' . $id . '.');
                $message = 'Archived record permanently deleted.';
            }
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
        } catch (PDOException $e) {
            error_log('APAgri archive action failed: ' . $e->getMessage());
            $message = 'The archive action failed because this record has a database relationship that prevents the change.';
        }
    }
    $moduleKey = $postModule;
    $config = $modules[$moduleKey];
}

$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$joins = $config['joins'] ?? '';
$params = [];
$baseSql = "SELECT {$config['select']} FROM public.{$config['table']} r {$joins}";
$countSql = "SELECT COUNT(*) FROM ({$baseSql} WHERE r.deleted_at IS NOT NULL) archived";
if ($search !== '') {
    $baseSql = "SELECT * FROM ({$baseSql} WHERE r.deleted_at IS NOT NULL) archived WHERE CAST(id AS TEXT) ILIKE :q1 OR COALESCE(summary, '') ILIKE :q2 OR COALESCE(detail, '') ILIKE :q3 OR COALESCE(deleted_by, '') ILIKE :q4";
    $countSql = "SELECT COUNT(*) FROM ({$baseSql}) filtered";
    $needle = '%' . $search . '%';
    $params = [':q1' => $needle, ':q2' => $needle, ':q3' => $needle, ':q4' => $needle];
} else {
    $baseSql .= ' WHERE r.deleted_at IS NOT NULL';
}
$count = $pdo->prepare($countSql);
$count->execute($params);
$totalRows = (int)$count->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$list = $pdo->prepare($baseSql . ' ORDER BY deleted_at DESC LIMIT :limit OFFSET :offset');
foreach ($params as $key => $value) $list->bindValue($key, $value, PDO::PARAM_STR);
$list->bindValue(':limit', $perPage, PDO::PARAM_INT);
$list->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
$list->execute();
$rows = $list->fetchAll(PDO::FETCH_ASSOC);

$archiveTotal = 0;
foreach ($modules as $m) $archiveTotal += (int)$pdo->query("SELECT COUNT(*) FROM public.{$m['table']} WHERE deleted_at IS NOT NULL")->fetchColumn();
$pageTitle = 'Archive';
require_once 'header.php';
require_once 'sidebar.php';
function archiveUrl(string $module, string $search, int $page): string {
    return 'archive.php?' . http_build_query(['module' => $module, 'q' => $search, 'page' => $page]);
}
?>
<div class="container-fluid py-4">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div><h1 class="h4 fw-bold mb-1" style="color:var(--green-dark)"><i class="bi bi-archive me-2"></i>Archive</h1><div class="text-muted small">Archived operational records: <?= number_format($archiveTotal) ?></div></div>
  </div>
  <?php if ($message !== ''): ?><div class="alert alert-info alert-dismissible fade show" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom-0 pt-3">
      <div class="d-flex flex-wrap gap-2 mb-3" role="tablist">
        <?php foreach ($modules as $key => $m): ?><a class="btn btn-sm <?= $key === $moduleKey ? 'btn-primary-green' : 'btn-outline-green' ?>" href="<?= htmlspecialchars(archiveUrl($key, '', 1), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($m['title'], ENT_QUOTES, 'UTF-8') ?></a><?php endforeach; ?>
      </div>
      <form class="d-flex gap-2" method="get"><input type="hidden" name="module" value="<?= htmlspecialchars($moduleKey, ENT_QUOTES, 'UTF-8') ?>"><input class="form-control" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search archived records"><button class="btn btn-outline-green"><i class="bi bi-search"></i> Search</button><?php if ($search !== ''): ?><a class="btn btn-light" href="<?= htmlspecialchars(archiveUrl($moduleKey, '', 1), ENT_QUOTES, 'UTF-8') ?>">Clear</a><?php endif; ?></form>
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>ID</th><th><?= htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8') ?> / Details</th><th>Archived By</th><th>Date Archived</th><th>Actions</th></tr></thead><tbody>
      <?php foreach ($rows as $row): ?><tr><td><?= (int)$row['id'] ?></td><td><div class="fw-semibold"><?= htmlspecialchars((string)($row['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div><small class="text-muted"><?= htmlspecialchars((string)($row['detail'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></td><td><?= htmlspecialchars((string)($row['deleted_by'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(date('M j, Y g:i A', strtotime((string)$row['deleted_at'])), ENT_QUOTES, 'UTF-8') ?></td><td><div class="d-flex gap-1"><form method="post" onsubmit="return confirm('Restore this record?')"><input type="hidden" name="action" value="restore"><input type="hidden" name="module" value="<?= htmlspecialchars($moduleKey, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm btn-outline-success" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button></form><?php if (isAdministrator()): ?><form method="post" onsubmit="return confirm('PERMANENTLY DELETE this archived record? This cannot be undone.')"><input type="hidden" name="action" value="permanent_delete"><input type="hidden" name="module" value="<?= htmlspecialchars($moduleKey, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm btn-outline-danger" title="Permanently delete"><i class="bi bi-trash"></i></button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-archive fs-3 d-block mb-2"></i>No archived <?= htmlspecialchars(strtolower($config['title']), ENT_QUOTES, 'UTF-8') ?> found.</td></tr><?php endif; ?>
    </tbody></table></div>
    <div class="card-footer bg-white d-flex align-items-center justify-content-between flex-wrap gap-2"><small class="text-muted">Page <?= $page ?> of <?= $totalPages ?> · <?= $totalRows ?> records</small><div class="btn-group btn-group-sm"><a class="btn btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= htmlspecialchars(archiveUrl($moduleKey, $search, max(1, $page - 1)), ENT_QUOTES, 'UTF-8') ?>">Previous</a><a class="btn btn-outline-secondary <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= htmlspecialchars(archiveUrl($moduleKey, $search, min($totalPages, $page + 1)), ENT_QUOTES, 'UTF-8') ?>">Next</a></div></div>
  </div>
</div>
<?php require_once 'footer.php'; ?>
