<?php
require_once 'auth.php';
require_once 'db.php';

// Dynamic Remarks Lookup from database
$remarksMap = [
    1 => 'High quality harvest',
    2 => 'Good harvest',
    3 => 'Acceptable quality',
    4 => 'Low Yield',
    5 => 'Poor Quality',
    6 => 'Damaged Harvest',
];

try {
    $stmtR = $pdo->query("SELECT remark_id, remark_description FROM harvest_remark ORDER BY remark_id");
    while ($r = $stmtR->fetch(PDO::FETCH_ASSOC)) {
        $remarksMap[(int)$r['remark_id']] = $r['remark_description'];
    }
} catch (PDOException $e) {
    // Fallback default map used if table doesn't exist
}

$remarksBadge = [
    1 => 'bg-success bg-opacity-10 text-success',
    2 => 'bg-info bg-opacity-10 text-info',
    3 => 'bg-primary bg-opacity-10 text-primary',
    4 => 'bg-warning bg-opacity-10 text-warning',
    5 => 'bg-danger bg-opacity-10 text-danger',
    6 => 'bg-danger bg-opacity-25 text-danger',
];

$gradeBadge = [
    'A' => 'bg-success text-white',
    'B' => 'bg-teal text-white',
    'C' => 'bg-warning text-dark',
    'D' => 'bg-danger text-white',
];

$gradesList = [];
try {
    $gradesList = $pdo->query("SELECT grade_code, grade_label FROM public.quality_grade_lookup ORDER BY grade_code")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $gradesList = [
        ['grade_code' => 'A', 'grade_label' => 'A — Excellent'],
        ['grade_code' => 'B', 'grade_label' => 'B — Good'],
        ['grade_code' => 'C', 'grade_label' => 'C — Fair'],
        ['grade_code' => 'D', 'grade_label' => 'D — Poor'],
    ];
}

// Fetch plantings for dropdown
$plantings = [];
try {
    $stmtP = $pdo->query("
        SELECT pl.planting_id, fm.farm_name, cr.crop_name, pl.planting_date
        FROM public.planting pl
        JOIN public.farm fm ON pl.farm_id = fm.farm_id AND fm.deleted_at IS NULL
        JOIN public.crop cr ON pl.crop_id = cr.crop_id AND cr.deleted_at IS NULL
        WHERE pl.deleted_at IS NULL
        ORDER BY pl.planting_id DESC
    ");
    $plantings = $stmtP->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // silently fail — table might not exist yet
}

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $planting_id          = trim($_POST['planting_id'] ?? '');
        $harvest_date         = trim($_POST['harvest_date'] ?? '');
        $quantity_kg          = trim($_POST['quantity_kg'] ?? '');
        $selling_price_per_kg = trim($_POST['selling_price_per_kg'] ?? '');
        $quality_grade        = trim($_POST['quality_grade'] ?? '');
        $remarks              = trim($_POST['remarks'] ?? '');

        if ($planting_id === '' || $harvest_date === '' || $quantity_kg === '') {
            $_SESSION['flash'] = ['msg' => 'Planting, harvest date, and quantity are required.', 'type' => 'danger'];
            header('Location: harvests.php');
            exit;
        }
        if ((float)$quantity_kg <= 0) {
            $_SESSION['flash'] = ['msg' => 'Quantity must be greater than 0.', 'type' => 'danger'];
            header('Location: harvests.php');
            exit;
        }
        if ($selling_price_per_kg !== '' && (float)$selling_price_per_kg < 0) {
            $_SESSION['flash'] = ['msg' => 'Selling price per kg cannot be negative.', 'type' => 'danger'];
            header('Location: harvests.php');
            exit;
        }

        try {
            $parentCheck = $pdo->prepare("SELECT COUNT(*) FROM public.planting pl JOIN public.farm fm ON fm.farm_id = pl.farm_id AND fm.deleted_at IS NULL JOIN public.crop cr ON cr.crop_id = pl.crop_id AND cr.deleted_at IS NULL WHERE pl.planting_id = :id AND pl.deleted_at IS NULL");
            $parentCheck->execute([':id' => $planting_id]);
            if ((int)$parentCheck->fetchColumn() === 0) throw new RuntimeException('Select an active planting record. Archived parents cannot receive harvests.');

            $plDateStmt = $pdo->prepare('SELECT planting_date FROM public.planting WHERE planting_id = :id');
            $plDateStmt->execute([':id' => $planting_id]);
            $plDate = $plDateStmt->fetchColumn();
            if ($plDate && $harvest_date < $plDate) {
                throw new RuntimeException("Harvest date ({$harvest_date}) cannot be earlier than planting date ({$plDate}).");
            }
            $stmt = $pdo->prepare("
                INSERT INTO harvest (planting_id, harvest_date, quantity_kg, selling_price_per_kg, quality_grade, remark_id)
                VALUES (:planting_id, :harvest_date, :quantity_kg, :selling_price_per_kg, :quality_grade, :remark_id)
                RETURNING harvest_id
            ");
            $stmt->execute([
                ':planting_id'          => $planting_id,
                ':harvest_date'         => $harvest_date,
                ':quantity_kg'          => $quantity_kg,
                ':selling_price_per_kg' => $selling_price_per_kg !== '' ? $selling_price_per_kg : null,
                ':quality_grade'        => $quality_grade !== '' ? $quality_grade : null,
                ':remark_id'            => $remarks !== '' ? (int)$remarks : null,
            ]);
            $newId = $stmt->fetchColumn();
            logRecordChange($pdo, 'CREATE', 'Harvest Records', $newId, "harvest #{$newId}", [], [
                'planting_id' => $planting_id, 'harvest_date' => $harvest_date, 'quantity_kg' => $quantity_kg,
                'selling_price_per_kg' => $selling_price_per_kg !== '' ? $selling_price_per_kg : null,
                'quality_grade' => $quality_grade ?: null, 'remark_id' => $remarks !== '' ? (int)$remarks : null,
            ]);
            $_SESSION['flash'] = ['msg' => 'Harvest record added successfully.', 'type' => 'success'];
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['msg' => $e->getMessage(), 'type' => 'danger'];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['msg' => 'Error adding harvest: ' . $e->getMessage(), 'type' => 'danger'];
        }
        header('Location: harvests.php');
        exit;
    }

    if ($action === 'edit') {
        $harvest_id           = (int)($_POST['harvest_id'] ?? 0);
        $planting_id          = trim($_POST['planting_id'] ?? '');
        $harvest_date         = trim($_POST['harvest_date'] ?? '');
        $quantity_kg          = trim($_POST['quantity_kg'] ?? '');
        $selling_price_per_kg = trim($_POST['selling_price_per_kg'] ?? '');
        $quality_grade        = trim($_POST['quality_grade'] ?? '');
        $remarks              = trim($_POST['remarks'] ?? '');

        if ($planting_id === '' || $harvest_date === '' || $quantity_kg === '') {
            $_SESSION['flash'] = ['msg' => 'Planting, harvest date, and quantity are required.', 'type' => 'danger'];
            header('Location: harvests.php');
            exit;
        }
        if ((float)$quantity_kg <= 0) {
            $_SESSION['flash'] = ['msg' => 'Quantity must be greater than 0.', 'type' => 'danger'];
            header('Location: harvests.php');
            exit;
        }
        if ($selling_price_per_kg !== '' && (float)$selling_price_per_kg < 0) {
            $_SESSION['flash'] = ['msg' => 'Selling price per kg cannot be negative.', 'type' => 'danger'];
            header('Location: harvests.php');
            exit;
        }

        try {
            $parentCheck = $pdo->prepare("SELECT COUNT(*) FROM public.planting pl JOIN public.farm fm ON fm.farm_id = pl.farm_id AND fm.deleted_at IS NULL JOIN public.crop cr ON cr.crop_id = pl.crop_id AND cr.deleted_at IS NULL WHERE pl.planting_id = :id AND pl.deleted_at IS NULL");
            $parentCheck->execute([':id' => $planting_id]);
            if ((int)$parentCheck->fetchColumn() === 0) throw new RuntimeException('Select an active planting record. Archived parents cannot receive harvests.');

            $plDateStmt = $pdo->prepare('SELECT planting_date FROM public.planting WHERE planting_id = :id');
            $plDateStmt->execute([':id' => $planting_id]);
            $plDate = $plDateStmt->fetchColumn();
            if ($plDate && $harvest_date < $plDate) {
                throw new RuntimeException("Harvest date ({$harvest_date}) cannot be earlier than planting date ({$plDate}).");
            }
            $beforeStmt = $pdo->prepare('SELECT planting_id, harvest_date, quantity_kg, selling_price_per_kg, quality_grade, remark_id FROM public.harvest WHERE harvest_id = :id AND deleted_at IS NULL');
            $beforeStmt->execute([':id' => $harvest_id]);
            $before = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stmt = $pdo->prepare("
                UPDATE harvest SET
                    planting_id          = :planting_id,
                    harvest_date         = :harvest_date,
                    quantity_kg          = :quantity_kg,
                    selling_price_per_kg = :selling_price_per_kg,
                    quality_grade        = :quality_grade,
                    remark_id            = :remark_id
                WHERE harvest_id = :harvest_id AND deleted_at IS NULL
            ");
            $stmt->execute([
                ':planting_id'          => $planting_id,
                ':harvest_date'         => $harvest_date,
                ':quantity_kg'          => $quantity_kg,
                ':selling_price_per_kg' => $selling_price_per_kg !== '' ? $selling_price_per_kg : null,
                ':quality_grade'        => $quality_grade !== '' ? $quality_grade : null,
                ':remark_id'            => $remarks !== '' ? (int)$remarks : null,
                ':harvest_id'           => $harvest_id,
            ]);
            logRecordChange($pdo, 'UPDATE', 'Harvest Records', $harvest_id, "harvest #{$harvest_id}", $before, [
                'planting_id' => $planting_id, 'harvest_date' => $harvest_date, 'quantity_kg' => $quantity_kg,
                'selling_price_per_kg' => $selling_price_per_kg !== '' ? $selling_price_per_kg : null,
                'quality_grade' => $quality_grade ?: null, 'remark_id' => $remarks !== '' ? (int)$remarks : null,
            ]);
            $_SESSION['flash'] = ['msg' => 'Harvest record updated successfully.', 'type' => 'success'];
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['msg' => $e->getMessage(), 'type' => 'danger'];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['msg' => 'Error updating harvest: ' . $e->getMessage(), 'type' => 'danger'];
        }
        header('Location: harvests.php');
        exit;
    }

    if ($action === 'delete') {
        $harvest_id = (int)($_POST['harvest_id'] ?? 0);
        try {
            $beforeStmt = $pdo->prepare('SELECT planting_id, harvest_date, quantity_kg, selling_price_per_kg, quality_grade, remark_id FROM public.harvest WHERE harvest_id = :id AND deleted_at IS NULL');
            $beforeStmt->execute([':id' => $harvest_id]);
            $before = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!$before) {
                throw new RuntimeException('Harvest record is already archived or could not be found.');
            }
            $stmt = $pdo->prepare('UPDATE public.harvest SET deleted_at = CURRENT_TIMESTAMP, deleted_by = :deleted_by WHERE harvest_id = :id AND deleted_at IS NULL');
            $stmt->execute([':deleted_by' => $_SESSION['username'] ?? $_SESSION['full_name'], ':id' => $harvest_id]);
            logActivity($pdo, 'ARCHIVE', 'Harvest Records', $harvest_id, "Archived harvest record #{$harvest_id} (" . number_format((float)$before['quantity_kg'], 2) . ' kg).');
            $_SESSION['flash'] = ['msg' => 'Harvest record archived successfully.', 'type' => 'success'];
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['msg' => $e->getMessage(), 'type' => 'danger'];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['msg' => 'Error deleting harvest: ' . $e->getMessage(), 'type' => 'danger'];
        }
        header('Location: harvests.php');
        exit;
    }
}

// Monthly stats
$currentMonth = date('n');
$currentYear  = date('Y');
$monthlyKg      = 0;
$monthlyRevenue = 0;
try {
    $stmtStats = $pdo->prepare("
        SELECT
            COALESCE(SUM(quantity_kg), 0) AS total_kg,
            COALESCE(SUM(quantity_kg * selling_price_per_kg), 0) AS total_revenue
        FROM harvest
        WHERE deleted_at IS NULL
          AND EXTRACT(MONTH FROM harvest_date) = :month
          AND EXTRACT(YEAR  FROM harvest_date) = :year
    ");
    $stmtStats->execute([':month' => $currentMonth, ':year' => $currentYear]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
    $monthlyKg      = $stats['total_kg']      ?? 0;
    $monthlyRevenue = $stats['total_revenue'] ?? 0;
} catch (PDOException $e) { /* silent */ }

// Fetch harvests using the schema's normalized remark_id foreign key.
$harvests = [];
try {
    $stmtH = $pdo->query("
        SELECT
            hv.harvest_id,
            hv.planting_id,
            hv.harvest_date,
            hv.quantity_kg,
            hv.selling_price_per_kg,
            hv.quality_grade,
            hv.remark_id,
            pl.planting_date,
            fm.farm_name,
            cr.crop_name
        FROM public.harvest hv
        JOIN public.planting pl ON hv.planting_id = pl.planting_id AND pl.deleted_at IS NULL
        JOIN public.farm fm ON pl.farm_id = fm.farm_id AND fm.deleted_at IS NULL
        JOIN public.crop cr ON pl.crop_id = cr.crop_id AND cr.deleted_at IS NULL
        WHERE hv.deleted_at IS NULL
        ORDER BY hv.harvest_id DESC
    ");
    $harvests = $stmtH->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('APagri harvest query failed: ' . $e->getMessage());
    $harvests = [];
}

$pageTitle = 'Harvest Records';
require_once 'header.php';
require_once 'sidebar.php';
?>

<div class="container-fluid px-4 py-3">

    <!-- Page Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1 fw-bold" style="color:var(--green-dark,#2d6a4f);">
                <i class="bi bi-basket2-fill me-2"></i>Harvest Records
            </h4>
            <!-- Monthly stat pills -->
            <div class="d-flex flex-wrap gap-2 mt-1">
                <span class="badge rounded-pill px-3 py-2 fs-6"
                      style="background:var(--green-pale,#d8f3dc);color:var(--green-dark,#2d6a4f);">
                    <i class="bi bi-box-seam me-1"></i>
                    This Month: <strong><?= number_format((float)$monthlyKg, 2) ?> kg</strong>
                </span>
                <span class="badge rounded-pill px-3 py-2 fs-6"
                      style="background:#e6f4ea;color:#1a7a40;">
                    <i class="bi bi-currency-exchange me-1"></i>
                    Revenue: <strong>₱<?= number_format((float)$monthlyRevenue, 2) ?></strong>
                </span>
            </div>
        </div>
        <button class="btn btn-primary-green" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-1"></i> Add Harvest
        </button>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2 px-3">
            <div class="row g-2 align-items-center report-filters">
                <div class="col-auto">
                    <label class="col-form-label fw-semibold small">Filter by Remarks:</label>
                </div>
                <div class="col-auto">
                    <select id="filterRemarks" class="form-select form-select-sm" style="min-width:160px;" data-print-filter-label="Remarks">
                        <option value="">All Remarks</option>
                        <?php foreach ($remarksMap as $k => $v): ?>
                            <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="col-form-label fw-semibold small">Quality Grade:</label>
                </div>
                <div class="col-auto">
                    <select id="filterGrade" class="form-select form-select-sm" style="min-width:140px;" data-print-filter-label="Quality grade">
                        <option value="">All Grades</option>
                        <option value="A">A — Excellent</option>
                        <option value="B">B — Good</option>
                        <option value="C">C — Fair</option>
                        <option value="D">D — Poor</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- DataTable Card -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="d-flex justify-content-start p-3 pb-0 no-print">
                <button type="button" class="btn btn-outline-green" onclick="printDataReport('Harvest Records Report', '#harvestTable')">
                    <i class="bi bi-printer me-1"></i> Print / Export PDF
                </button>
            </div>
            <div class="table-responsive">
                <table id="harvestTable" class="table table-hover align-middle mb-0 w-100 print-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Farm</th>
                            <th>Crop</th>
                            <th>Harvest Date</th>
                            <th>Qty (kg)</th>
                            <th>Price/kg (₱)</th>
                            <th>Total Value (₱)</th>
                            <th>Quality Grade</th>
                            <th>Remarks</th>
                            <th class="text-center print-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                            <?php $rowNum = 1; foreach ($harvests as $hv): ?>
                                <?php
                                    // Use aliased 'remark_id' column (never undefined)
                                    $remarkRaw    = $hv['remark_id'] ?? null;
                                    $remarksKey   = ($remarkRaw !== null) ? (int)$remarkRaw : 0;
                                    $remarksLabel = $remarksMap[$remarksKey]  ?? '—';
                                    $remarksCls   = $remarksBadge[$remarksKey] ?? 'bg-secondary bg-opacity-10 text-secondary';

                                    $gradeRaw = trim($hv['quality_grade'] ?? '');
                                    $gradeVal = strtoupper($gradeRaw);
                                    $letter   = preg_replace('/[^A-D]/', '', $gradeVal);
                                    $gradeMap = [
                                        'A' => 'bg-success text-white',
                                        'B' => 'bg-info text-dark',
                                        'C' => 'bg-warning text-dark',
                                        'D' => 'bg-danger text-white'
                                    ];
                                    $gradeCls = $gradeMap[$letter] ?? ($gradeMap[$gradeVal] ?? 'bg-secondary text-white');

                                    $qty   = (float)($hv['quantity_kg'] ?? 0);
                                    $price = (isset($hv['selling_price_per_kg']) && $hv['selling_price_per_kg'] !== null)
                                             ? (float)$hv['selling_price_per_kg'] : null;
                                    $total = ($price !== null) ? $qty * $price : null;

                                    $harvestDateFmt = !empty($hv['harvest_date'])
                                        ? date('M j, Y', strtotime($hv['harvest_date']))
                                        : '—';

                                    $deleteLabel = htmlspecialchars(
                                        ($hv['farm_name'] ?? '?') . ' / ' . ($hv['crop_name'] ?? '?') . ' — ' . ($hv['harvest_date'] ?? '?')
                                    );
                                ?>
                                <tr>
                                    <td><?= $rowNum++ ?></td>
                                    <td><?= htmlspecialchars($hv['farm_name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($hv['crop_name'] ?? '—') ?></td>
                                    <td data-order="<?= htmlspecialchars($hv['harvest_date'] ?? '') ?>">
                                        <?= $harvestDateFmt ?>
                                    </td>
                                    <td><?= number_format($qty, 2) ?></td>
                                    <td>
                                        <?= $price !== null ? '₱' . number_format($price, 2) : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td>
                                        <?= $total !== null ? '₱' . number_format($total, 2) : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td>
                                        <?php if ($letter !== ''): ?>
                                            <span class="badge <?= $gradeCls ?> px-2 py-1"
                                                  <?= $letter === 'B' ? 'style="background-color:#0d9488!important;"' : '' ?>>
                                                Grade <?= htmlspecialchars($letter) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($remarksKey > 0): ?>
                                            <span class="badge <?= $remarksCls ?> px-2 py-1 small">
                                                <?= htmlspecialchars($remarksLabel) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center print-actions">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <button class="btn btn-outline-green btn-sm"
                                                onclick="openEditModal(this)"
                                                data-id="<?= (int)$hv['harvest_id'] ?>"
                                                data-planting-id="<?= (int)$hv['planting_id'] ?>"
                                                data-date="<?= htmlspecialchars($hv['harvest_date'] ?? '') ?>"
                                                data-qty="<?= htmlspecialchars($hv['quantity_kg'] ?? '') ?>"
                                                data-price="<?= htmlspecialchars($hv['selling_price_per_kg'] ?? '') ?>"
                                                data-grade="<?= htmlspecialchars($letter) ?>"
                                                data-remarks="<?= $remarksKey ?>"
                                                title="Edit">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>

                                            <!-- Delete form -->
                                            <form id="deleteForm_<?= (int)$hv['harvest_id'] ?>"
                                                  method="POST" action="harvests.php" class="d-inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="harvest_id" value="<?= (int)$hv['harvest_id'] ?>">
                                                <button type="button" class="btn btn-outline-danger btn-sm"
                                                    onclick="confirmDelete('deleteForm_<?= (int)$hv['harvest_id'] ?>', '<?= $deleteLabel ?>')"
                                                    title="Delete">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ===== ADD MODAL ===== -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background:var(--green-mid,#40916c);">
                <h5 class="modal-title text-white" id="addModalLabel">
                    <i class="bi bi-basket2-fill me-2"></i>Add Harvest Record
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="harvests.php" id="addHarvestForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-body row g-3">

                    <!-- Planting -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Planting <span class="text-danger">*</span></label>
                        <select name="planting_id" class="form-select" required>
                            <option value="">— Select Planting —</option>
                            <?php foreach ($plantings as $pl): ?>
                                <option value="<?= (int)$pl['planting_id'] ?>">
                                    #<?= (int)$pl['planting_id'] ?> — <?= htmlspecialchars($pl['farm_name']) ?> / <?= htmlspecialchars($pl['crop_name']) ?> (<?= htmlspecialchars($pl['planting_date']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Harvest Date -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Harvest Date <span class="text-danger">*</span></label>
                        <input type="date" name="harvest_date" class="form-control"
                               value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <!-- Quantity -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Quantity (kg) <span class="text-danger">*</span></label>
                        <input type="number" name="quantity_kg" id="addQty" class="form-control"
                               step="0.01" min="0.01" placeholder="e.g. 250.00" required>
                    </div>

                    <!-- Price per kg -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Selling Price / kg (₱)</label>
                        <input type="number" name="selling_price_per_kg" id="addPrice" class="form-control"
                               step="0.01" min="0" placeholder="e.g. 45.00">
                        <div id="addTotalPreview" class="form-text fw-semibold text-success mt-1"></div>
                    </div>

                    <!-- Quality Grade -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Quality Grade</label>
                        <select name="quality_grade" class="form-select">
                            <option value="">— Select Grade (Optional) —</option>
                            <?php foreach ($gradesList as $g): ?>
                                <option value="<?= htmlspecialchars($g['grade_code']) ?>">
                                    <?= htmlspecialchars($g['grade_label'] ?? $g['grade_code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Remarks -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Remarks <span class="text-danger">*</span></label>
                        <select name="remarks" class="form-select" required>
                            <option value="">— Select Remarks —</option>
                            <?php foreach ($remarksMap as $k => $v): ?>
                                <option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-green">
                        <i class="bi bi-check-lg me-1"></i>Save Harvest
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== EDIT MODAL ===== -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background:var(--green-mid,#40916c);">
                <h5 class="modal-title text-white" id="editModalLabel">
                    <i class="bi bi-pencil-fill me-2"></i>Edit Harvest Record
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="harvests.php" id="editHarvestForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="harvest_id" id="editHarvestId">
                <div class="modal-body row g-3">

                    <!-- Planting -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Planting <span class="text-danger">*</span></label>
                        <select name="planting_id" id="editPlantingId" class="form-select" required>
                            <option value="">— Select Planting —</option>
                            <?php foreach ($plantings as $pl): ?>
                                <option value="<?= (int)$pl['planting_id'] ?>">
                                    #<?= (int)$pl['planting_id'] ?> — <?= htmlspecialchars($pl['farm_name']) ?> / <?= htmlspecialchars($pl['crop_name']) ?> (<?= htmlspecialchars($pl['planting_date']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Harvest Date -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Harvest Date <span class="text-danger">*</span></label>
                        <input type="date" name="harvest_date" id="editHarvestDate" class="form-control" required>
                    </div>

                    <!-- Quantity -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Quantity (kg) <span class="text-danger">*</span></label>
                        <input type="number" name="quantity_kg" id="editQty" class="form-control"
                               step="0.01" min="0.01" required>
                    </div>

                    <!-- Price per kg -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Selling Price / kg (₱)</label>
                        <input type="number" name="selling_price_per_kg" id="editPrice" class="form-control"
                               step="0.01" min="0">
                        <div id="editTotalPreview" class="form-text fw-semibold text-success mt-1"></div>
                    </div>

                    <!-- Quality Grade -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Quality Grade</label>
                        <select name="quality_grade" id="editGrade" class="form-select">
                            <option value="">— Select Grade (Optional) —</option>
                            <?php foreach ($gradesList as $g): ?>
                                <option value="<?= htmlspecialchars($g['grade_code']) ?>">
                                    <?= htmlspecialchars($g['grade_label'] ?? $g['grade_code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Remarks -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Remarks <span class="text-danger">*</span></label>
                        <select name="remarks" id="editRemarks" class="form-select" required>
                            <option value="">— Select Remarks —</option>
                            <?php foreach ($remarksMap as $k => $v): ?>
                                <option value="<?= $k ?>"><?= htmlspecialchars($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-green">
                        <i class="bi bi-check-lg me-1"></i>Update Harvest
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<script>
// ── DataTable init ──────────────────────────────────────────────────────────
$(document).ready(function () {
    var table = $('#harvestTable').DataTable({
        pageLength: 10,
        responsive: true,
        order: [],   // PHP already ordered DESC
        columnDefs: [
            { orderable: false, targets: 9 }  // Actions column
        ],
        language: {
            search: '<i class="bi bi-search"></i>',
            searchPlaceholder: 'Search records…',
            lengthMenu: 'Show _MENU_ entries',
            emptyTable: 'No harvest records found.',
            info: 'Showing _START_–_END_ of _TOTAL_ records',
        }
    });

    // Filter: Remarks (column index 8)
    $('#filterRemarks').on('change', function () {
        table.column(8).search(this.value, false, false).draw();
    });

    // Filter: Quality Grade (column index 7)
    $('#filterGrade').on('change', function () {
        table.column(7).search(this.value ? this.value : '', false, false).draw();
    });
});

// ── Live total preview ──────────────────────────────────────────────────────
function calcTotal(qtyId, priceId, previewId) {
    var qty   = parseFloat(document.getElementById(qtyId).value)   || 0;
    var price = parseFloat(document.getElementById(priceId).value) || 0;
    var el    = document.getElementById(previewId);
    if (qty > 0 && price > 0) {
        var total = qty * price;
        el.textContent = 'Est. Total: ₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    } else {
        el.textContent = '';
    }
}

document.getElementById('addQty').addEventListener('input',   function(){ calcTotal('addQty','addPrice','addTotalPreview'); });
document.getElementById('addPrice').addEventListener('input', function(){ calcTotal('addQty','addPrice','addTotalPreview'); });
document.getElementById('editQty').addEventListener('input',   function(){ calcTotal('editQty','editPrice','editTotalPreview'); });
document.getElementById('editPrice').addEventListener('input', function(){ calcTotal('editQty','editPrice','editTotalPreview'); });

// ── Open Edit Modal ─────────────────────────────────────────────────────────
function openEditModal(btn) {
    var d = btn.dataset;
    document.getElementById('editHarvestId').value  = d.id;
    document.getElementById('editPlantingId').value = d.plantingId;
    document.getElementById('editHarvestDate').value = d.date;
    document.getElementById('editQty').value        = d.qty;
    document.getElementById('editPrice').value      = d.price;
    document.getElementById('editGrade').value      = d.grade;
    document.getElementById('editRemarks').value    = d.remarks;

    // Trigger total preview
    calcTotal('editQty', 'editPrice', 'editTotalPreview');

    var editModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal'));
    editModal.show();
}
</script>
