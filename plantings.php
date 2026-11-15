<?php
require_once 'auth.php';
require_once 'db.php';

// ── Helper ────────────────────────────────────────────────────────────────────
function statusBadge(string $statusCode, string $statusLabel = ''): string {
    $map = [
        'planned'   => 'badge-planned',
        'planted'   => 'badge-planted',
        'growing'   => 'badge-growing',
        'harvested' => 'badge-harvested',
        'failed'    => 'badge-failed',
    ];
    $s = strtolower(trim($statusCode));
    $cls = $map[$s] ?? 'bg-secondary';
    $label = $statusLabel ?: ucfirst($s);
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($label) . '</span>';
}

// ── Dropdown data ─────────────────────────────────────────────────────────────
$farms = $pdo->query("SELECT farm_id, farm_name, location FROM public.farm WHERE deleted_at IS NULL ORDER BY farm_name")->fetchAll(PDO::FETCH_ASSOC);
$crops = $pdo->query("SELECT crop_id, crop_name, variety FROM public.crop WHERE deleted_at IS NULL ORDER BY crop_name")->fetchAll(PDO::FETCH_ASSOC);
$plantingStatuses = $pdo->query("SELECT status_id, status_code, status_label FROM public.planting_status_lookup ORDER BY status_id")->fetchAll(PDO::FETCH_ASSOC);

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD ───────────────────────────────────────────────────────────────────
    if ($action === 'add') {
        $farm_id              = trim($_POST['farm_id'] ?? '');
        $crop_id              = trim($_POST['crop_id'] ?? '');
        $planting_date        = trim($_POST['planting_date'] ?? '');
        $area_planted         = ($_POST['area_planted'] ?? '') !== '' ? $_POST['area_planted'] : null;
        $seed_quantity        = ($_POST['seed_quantity'] ?? '') !== '' ? $_POST['seed_quantity'] : null;
        $expected_harvest_date = trim($_POST['expected_harvest_date'] ?? '') ?: null;
        $status_id            = !empty($_POST['status_id']) ? (int)$_POST['status_id'] : 1;

        if (!$farm_id || !$crop_id || !$planting_date || !$status_id) {
            $_SESSION['flash'] = ['msg' => 'Farm, Crop, Planting Date, and Status are required.', 'type' => 'danger'];
            header('Location: plantings.php');
            exit;
        }

        if ($expected_harvest_date && $expected_harvest_date < $planting_date) {
            $_SESSION['flash'] = ['msg' => 'Expected harvest date must be on or after planting date.', 'type' => 'danger'];
            header('Location: plantings.php');
            exit;
        }

        if ($area_planted !== null && (float)$area_planted <= 0) {
            $_SESSION['flash'] = ['msg' => 'Area planted must be greater than 0.', 'type' => 'danger'];
            header('Location: plantings.php');
            exit;
        }

        if ($seed_quantity !== null && (float)$seed_quantity < 0) {
            $_SESSION['flash'] = ['msg' => 'Seed quantity cannot be negative.', 'type' => 'danger'];
            header('Location: plantings.php');
            exit;
        }

        try {
            $parentCheck = $pdo->prepare('SELECT (SELECT COUNT(*) FROM public.farm WHERE farm_id = :farm_id AND deleted_at IS NULL) > 0 AND (SELECT COUNT(*) FROM public.crop WHERE crop_id = :crop_id AND deleted_at IS NULL) > 0');
            $parentCheck->execute([':farm_id' => $farm_id, ':crop_id' => $crop_id]);
            if (!$parentCheck->fetchColumn()) throw new RuntimeException('Select an active farm and crop. Archived parents cannot be used for planting records.');

            $farmAreaStmt = $pdo->prepare('SELECT area_hectares, farm_name FROM public.farm WHERE farm_id = :id');
            $farmAreaStmt->execute([':id' => $farm_id]);
            $farmInfo = $farmAreaStmt->fetch(PDO::FETCH_ASSOC);
            if ($farmInfo && $farmInfo['area_hectares'] !== null && $area_planted !== null && (float)$area_planted > (float)$farmInfo['area_hectares']) {
                throw new RuntimeException("Area planted ({$area_planted} ha) cannot exceed total farm area (" . number_format((float)$farmInfo['area_hectares'], 2) . " ha) of {$farmInfo['farm_name']}.");
            }
            $stmt = $pdo->prepare("
                INSERT INTO planting (farm_id, crop_id, planting_date, area_planted, seed_quantity, expected_harvest_date, status_id)
                VALUES (:farm_id, :crop_id, :planting_date, :area_planted, :seed_quantity, :expected_harvest_date, :status_id)
                RETURNING planting_id
            ");
            $stmt->execute([
                ':farm_id'               => $farm_id,
                ':crop_id'               => $crop_id,
                ':planting_date'         => $planting_date,
                ':area_planted'          => $area_planted,
                ':seed_quantity'         => $seed_quantity,
                ':expected_harvest_date' => $expected_harvest_date,
                ':status_id'             => $status_id,
            ]);
            $newId = $stmt->fetchColumn();
            logRecordChange($pdo, 'CREATE', 'Planting Records', $newId, "planting #{$newId}", [], [
                'farm_id' => $farm_id, 'crop_id' => $crop_id, 'planting_date' => $planting_date,
                'area_planted' => $area_planted, 'seed_quantity' => $seed_quantity,
                'expected_harvest_date' => $expected_harvest_date, 'status_id' => $status_id,
            ]);
            $_SESSION['flash'] = ['msg' => 'Planting record added successfully.', 'type' => 'success'];
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['msg' => $e->getMessage(), 'type' => 'danger'];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['msg' => 'Error adding planting: ' . $e->getMessage(), 'type' => 'danger'];
        }
        header('Location: plantings.php');
        exit;
    }

    // ── EDIT ──────────────────────────────────────────────────────────────────
    if ($action === 'edit') {
        $planting_id           = (int) ($_POST['planting_id'] ?? 0);
        $farm_id               = trim($_POST['farm_id'] ?? '');
        $crop_id               = trim($_POST['crop_id'] ?? '');
        $planting_date         = trim($_POST['planting_date'] ?? '');
        $area_planted          = ($_POST['area_planted'] ?? '') !== '' ? $_POST['area_planted'] : null;
        $seed_quantity         = ($_POST['seed_quantity'] ?? '') !== '' ? $_POST['seed_quantity'] : null;
        $expected_harvest_date = trim($_POST['expected_harvest_date'] ?? '') ?: null;
        $status_id             = !empty($_POST['status_id']) ? (int)$_POST['status_id'] : 1;

        if (!$planting_id || !$farm_id || !$crop_id || !$planting_date || !$status_id) {
            $_SESSION['flash'] = ['msg' => 'Farm, Crop, Planting Date, and Status are required.', 'type' => 'danger'];
            header('Location: plantings.php');
            exit;
        }

        if ($expected_harvest_date && $expected_harvest_date < $planting_date) {
            $_SESSION['flash'] = ['msg' => 'Expected harvest date must be on or after planting date.', 'type' => 'danger'];
            header('Location: plantings.php');
            exit;
        }

        if ($area_planted !== null && (float)$area_planted <= 0) {
            $_SESSION['flash'] = ['msg' => 'Area planted must be greater than 0.', 'type' => 'danger'];
            header('Location: plantings.php');
            exit;
        }

        if ($seed_quantity !== null && (float)$seed_quantity < 0) {
            $_SESSION['flash'] = ['msg' => 'Seed quantity cannot be negative.', 'type' => 'danger'];
            header('Location: plantings.php');
            exit;
        }

        try {
            $parentCheck = $pdo->prepare('SELECT (SELECT COUNT(*) FROM public.farm WHERE farm_id = :farm_id AND deleted_at IS NULL) > 0 AND (SELECT COUNT(*) FROM public.crop WHERE crop_id = :crop_id AND deleted_at IS NULL) > 0');
            $parentCheck->execute([':farm_id' => $farm_id, ':crop_id' => $crop_id]);
            if (!$parentCheck->fetchColumn()) throw new RuntimeException('Select an active farm and crop. Archived parents cannot be used for planting records.');

            $farmAreaStmt = $pdo->prepare('SELECT area_hectares, farm_name FROM public.farm WHERE farm_id = :id');
            $farmAreaStmt->execute([':id' => $farm_id]);
            $farmInfo = $farmAreaStmt->fetch(PDO::FETCH_ASSOC);
            if ($farmInfo && $farmInfo['area_hectares'] !== null && $area_planted !== null && (float)$area_planted > (float)$farmInfo['area_hectares']) {
                throw new RuntimeException("Area planted ({$area_planted} ha) cannot exceed total farm area (" . number_format((float)$farmInfo['area_hectares'], 2) . " ha) of {$farmInfo['farm_name']}.");
            }
            $beforeStmt = $pdo->prepare('SELECT farm_id, crop_id, planting_date, area_planted, seed_quantity, expected_harvest_date, status_id FROM public.planting WHERE planting_id = :id AND deleted_at IS NULL');
            $beforeStmt->execute([':id' => $planting_id]);
            $before = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stmt = $pdo->prepare("
                UPDATE planting
                SET farm_id = :farm_id,
                    crop_id = :crop_id,
                    planting_date = :planting_date,
                    area_planted = :area_planted,
                    seed_quantity = :seed_quantity,
                    expected_harvest_date = :expected_harvest_date,
                    status_id = :status_id
                WHERE planting_id = :planting_id AND deleted_at IS NULL
            ");
            $stmt->execute([
                ':farm_id'               => $farm_id,
                ':crop_id'               => $crop_id,
                ':planting_date'         => $planting_date,
                ':area_planted'          => $area_planted,
                ':seed_quantity'         => $seed_quantity,
                ':expected_harvest_date' => $expected_harvest_date,
                ':status_id'             => $status_id,
                ':planting_id'           => $planting_id,
            ]);
            logRecordChange($pdo, 'UPDATE', 'Planting Records', $planting_id, "planting #{$planting_id}", $before, [
                'farm_id' => $farm_id, 'crop_id' => $crop_id, 'planting_date' => $planting_date,
                'area_planted' => $area_planted, 'seed_quantity' => $seed_quantity,
                'expected_harvest_date' => $expected_harvest_date, 'status_id' => $status_id,
            ]);
            $_SESSION['flash'] = ['msg' => 'Planting record updated successfully.', 'type' => 'success'];
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['msg' => $e->getMessage(), 'type' => 'danger'];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['msg' => 'Error updating planting: ' . $e->getMessage(), 'type' => 'danger'];
        }
        header('Location: plantings.php');
        exit;
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $planting_id = (int) ($_POST['planting_id'] ?? 0);
        try {
            $beforeStmt = $pdo->prepare('SELECT farm_id, crop_id, planting_date, area_planted, seed_quantity, expected_harvest_date, status_id FROM public.planting WHERE planting_id = :id AND deleted_at IS NULL');
            $beforeStmt->execute([':id' => $planting_id]);
            $before = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!$before) {
                throw new RuntimeException('Planting record is already archived or could not be found.');
            }
            $childCheck = $pdo->prepare('SELECT COUNT(*) FROM public.harvest WHERE planting_id = :id AND deleted_at IS NULL');
            $childCheck->execute([':id' => $planting_id]);
            if ((int)$childCheck->fetchColumn() > 0) {
                $_SESSION['flash'] = ['msg' => 'Archive this planting record\'s active harvests first.', 'type' => 'warning'];
            } else {
                $stmt = $pdo->prepare('UPDATE public.planting SET deleted_at = CURRENT_TIMESTAMP, deleted_by = :deleted_by WHERE planting_id = :id AND deleted_at IS NULL');
                $stmt->execute([':deleted_by' => $_SESSION['username'] ?? $_SESSION['full_name'], ':id' => $planting_id]);
                logActivity($pdo, 'ARCHIVE', 'Planting Records', $planting_id, "Archived planting record #{$planting_id}.");
                $_SESSION['flash'] = ['msg' => 'Planting record archived successfully.', 'type' => 'success'];
            }
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['msg' => $e->getMessage(), 'type' => 'danger'];
        } catch (PDOException $e) {
            $code = $e->getCode();
            if ($code === '23503' || $code === '23000') {
                $_SESSION['flash'] = ['msg' => 'Cannot delete: this planting has linked harvest records.', 'type' => 'danger'];
            } else {
                $_SESSION['flash'] = ['msg' => 'Error deleting planting: ' . $e->getMessage(), 'type' => 'danger'];
            }
        }
        header('Location: plantings.php');
        exit;
    }
}

// ── Fetch table data ──────────────────────────────────────────────────────────
$plantings = $pdo->query("
    SELECT pl.*, fm.farm_name, cr.crop_name, cr.variety, st.status_code, st.status_label
    FROM public.planting pl
    JOIN public.farm fm ON pl.farm_id = fm.farm_id AND fm.deleted_at IS NULL
    JOIN public.crop cr ON pl.crop_id = cr.crop_id AND cr.deleted_at IS NULL
    JOIN public.planting_status_lookup st ON pl.status_id = st.status_id
    WHERE pl.deleted_at IS NULL
    ORDER BY pl.planting_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Planting Records';
require_once 'header.php';
require_once 'sidebar.php';
?>

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold mb-0" style="color: var(--green-dark, #2d6a4f);">
        <i class="bi bi-calendar2-week me-2"></i>Planting Records
      </h2>
      <button class="btn btn-primary-green" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-1"></i>Add Planting
      </button>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm border-0 mb-3">
      <div class="card-body py-2">
        <div class="row g-2 align-items-center report-filters">
          <div class="col-auto">
            <label class="col-form-label fw-semibold">Filter:</label>
          </div>
          <div class="col-sm-3">
            <select id="farmFilter" class="form-select form-select-sm" data-print-filter-label="Farm">
              <option value="">All Farms</option>
              <?php foreach ($farms as $f): ?>
                <option value="<?= htmlspecialchars($f['farm_name']) ?>">
                  <?= htmlspecialchars($f['farm_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-3">
            <select id="statusFilter" class="form-select form-select-sm" data-print-filter-label="Status">
              <option value="">All Statuses</option>
              <?php foreach ($plantingStatuses as $ps): ?>
                <option value="<?= htmlspecialchars($ps['status_label']) ?>">
                  <?= htmlspecialchars($ps['status_label']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Card -->
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="d-flex justify-content-start mb-3 no-print">
          <button type="button" class="btn btn-outline-green" onclick="printDataReport('Planting Records Report', '#plantingsTable')">
            <i class="bi bi-printer me-1"></i> Print / Export PDF
          </button>
        </div>
        <div class="table-responsive">
          <table id="plantingsTable" class="table table-hover align-middle print-table" style="width:100%">
            <thead>
              <tr>
                <th>#</th>
                <th>Farm</th>
                <th>Crop</th>
                <th>Planting Date</th>
                <th>Area (ha)</th>
                <th>Seed Qty</th>
                <th>Exp. Harvest Date</th>
                <th>Status</th>
                <th class="print-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $row = 1; foreach ($plantings as $pl): ?>
              <?php
                $plantingDateFmt = $pl['planting_date']        ? date('M j, Y', strtotime($pl['planting_date']))         : '—';
                $harvestDateFmt  = $pl['expected_harvest_date'] ? date('M j, Y', strtotime($pl['expected_harvest_date'])) : '—';
                $farmCropLabel   = htmlspecialchars($pl['farm_name'] . ' - ' . $pl['crop_name']);
                $variety         = $pl['variety'] ? '<br><small class="text-muted">' . htmlspecialchars($pl['variety']) . '</small>' : '';
                $area            = $pl['area_planted']  !== null ? htmlspecialchars($pl['area_planted'])  : '—';
                $seed            = $pl['seed_quantity'] !== null ? htmlspecialchars($pl['seed_quantity']) : '—';
              ?>
              <tr>
                <td><?= $row++ ?></td>
                <td><?= htmlspecialchars($pl['farm_name']) ?></td>
                <td><?= htmlspecialchars($pl['crop_name']) . $variety ?></td>
                <td><?= $plantingDateFmt ?></td>
                <td><?= $area ?></td>
                <td><?= $seed ?></td>
                <td><?= $harvestDateFmt ?></td>
                <td class="status-cell"><?= statusBadge($pl['status_code'], $pl['status_label']) ?></td>
                <td class="print-actions">
                  <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-green"
                      onclick="openEditModal(this)"
                      data-id="<?= $pl['planting_id'] ?>"
                      data-farm-id="<?= $pl['farm_id'] ?>"
                      data-crop-id="<?= $pl['crop_id'] ?>"
                      data-date="<?= htmlspecialchars($pl['planting_date'] ?? '') ?>"
                      data-area="<?= htmlspecialchars($pl['area_planted'] ?? '') ?>"
                      data-seed="<?= htmlspecialchars($pl['seed_quantity'] ?? '') ?>"
                      data-harvest-date="<?= htmlspecialchars($pl['expected_harvest_date'] ?? '') ?>"
                      data-status-id="<?= $pl['status_id'] ?>"
                      title="Edit">
                      <i class="bi bi-pencil-fill"></i>
                    </button>
                    <!-- Delete form -->
                    <form id="deleteForm_<?= $pl['planting_id'] ?>" method="POST" action="plantings.php" class="d-inline">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="planting_id" value="<?= $pl['planting_id'] ?>">
                      <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="confirmDelete('deleteForm_<?= $pl['planting_id'] ?>', '<?= addslashes($farmCropLabel) ?>')"
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

  </div><!-- /container-fluid -->


<!-- ═══════════════════════════ ADD MODAL ═══════════════════════════════════ -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="plantings.php" id="addForm">
        <input type="hidden" name="action" value="add">
        <div class="modal-header" style="background: var(--green-mid, #40916c); color: #fff;">
          <h5 class="modal-title" id="addModalLabel">
            <i class="bi bi-plus-circle me-2"></i>Add Planting Record
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">

            <!-- Farm -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Farm <span class="text-danger">*</span></label>
              <select name="farm_id" class="form-select" required>
                <option value="">— Select Farm —</option>
                <?php foreach ($farms as $f): ?>
                  <option value="<?= $f['farm_id'] ?>">
                    <?= htmlspecialchars($f['farm_name']) ?> (<?= htmlspecialchars($f['location'] ?? '') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Crop -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Crop <span class="text-danger">*</span></label>
              <select name="crop_id" class="form-select" required>
                <option value="">— Select Crop —</option>
                <?php foreach ($crops as $c): ?>
                  <option value="<?= $c['crop_id'] ?>">
                    <?= htmlspecialchars($c['crop_name']) ?><?= $c['variety'] ? ' - ' . htmlspecialchars($c['variety']) : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Planting Date -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Planting Date <span class="text-danger">*</span></label>
              <input type="date" name="planting_date" id="add_planting_date" class="form-control"
                     value="<?= date('Y-m-d') ?>" required>
            </div>

            <!-- Status -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
              <select name="status_id" class="form-select" required>
                <option value="">— Select Status —</option>
                <?php foreach ($plantingStatuses as $ps): ?>
                  <option value="<?= $ps['status_id'] ?>"><?= htmlspecialchars($ps['status_label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Area Planted -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Area Planted (ha)</label>
              <input type="number" name="area_planted" class="form-control" step="0.01" min="0.01" placeholder="e.g. 1.50">
            </div>

            <!-- Seed Quantity -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Seed Quantity</label>
              <input type="number" name="seed_quantity" class="form-control" step="0.01" min="0" placeholder="e.g. 10.00">
            </div>

            <!-- Expected Harvest Date -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Expected Harvest Date</label>
              <input type="date" name="expected_harvest_date" id="add_harvest_date" class="form-control">
            </div>

          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-green">
            <i class="bi bi-save me-1"></i>Save Planting
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- ═══════════════════════════ EDIT MODAL ══════════════════════════════════ -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="plantings.php" id="editForm">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="planting_id" id="edit_planting_id">
        <div class="modal-header" style="background: var(--green-mid, #40916c); color: #fff;">
          <h5 class="modal-title" id="editModalLabel">
            <i class="bi bi-pencil-square me-2"></i>Edit Planting Record
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">

            <!-- Farm -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Farm <span class="text-danger">*</span></label>
              <select name="farm_id" id="edit_farm_id" class="form-select" required>
                <option value="">— Select Farm —</option>
                <?php foreach ($farms as $f): ?>
                  <option value="<?= $f['farm_id'] ?>">
                    <?= htmlspecialchars($f['farm_name']) ?> (<?= htmlspecialchars($f['location'] ?? '') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Crop -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Crop <span class="text-danger">*</span></label>
              <select name="crop_id" id="edit_crop_id" class="form-select" required>
                <option value="">— Select Crop —</option>
                <?php foreach ($crops as $c): ?>
                  <option value="<?= $c['crop_id'] ?>">
                    <?= htmlspecialchars($c['crop_name']) ?><?= $c['variety'] ? ' - ' . htmlspecialchars($c['variety']) : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Planting Date -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Planting Date <span class="text-danger">*</span></label>
              <input type="date" name="planting_date" id="edit_planting_date" class="form-control" required>
            </div>

            <!-- Status -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
              <select name="status_id" id="edit_status_id" class="form-select" required>
                <option value="">— Select Status —</option>
                <?php foreach ($plantingStatuses as $ps): ?>
                  <option value="<?= $ps['status_id'] ?>"><?= htmlspecialchars($ps['status_label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Area Planted -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Area Planted (ha)</label>
              <input type="number" name="area_planted" id="edit_area" class="form-control" step="0.01" min="0.01">
            </div>

            <!-- Seed Quantity -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Seed Quantity</label>
              <input type="number" name="seed_quantity" id="edit_seed" class="form-control" step="0.01" min="0">
            </div>

            <!-- Expected Harvest Date -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Expected Harvest Date</label>
              <input type="date" name="expected_harvest_date" id="edit_harvest_date" class="form-control">
            </div>

          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary-green">
            <i class="bi bi-save me-1"></i>Update Planting
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- ═══════════════════════════ PAGE SCRIPTS ════════════════════════════════ -->
<script>
// ── DataTable ────────────────────────────────────────────────────────────────
let plantingsTable;
$(document).ready(function () {
    plantingsTable = $('#plantingsTable').DataTable({
        pageLength: 10,
        responsive: true,
        order: [],  // PHP already ordered DESC
        columnDefs: [
            { orderable: false, targets: 8 }  // Actions
        ]
    });

    function applyPlantingFilters() {
        var farmVal = $('#farmFilter').val();
        var statusVal = $('#statusFilter').val();

        if (farmVal) {
            plantingsTable.column(1).search('^' + $.fn.dataTable.util.escapeRegex(farmVal) + '$', true, false);
        } else {
            plantingsTable.column(1).search('');
        }

        if (statusVal) {
            plantingsTable.column(7).search($.fn.dataTable.util.escapeRegex(statusVal), true, false);
        } else {
            plantingsTable.column(7).search('');
        }

        plantingsTable.draw();
    }

    // Attach filter listeners and apply initial state
    $('#farmFilter, #statusFilter').on('change', applyPlantingFilters);
    if ($('#farmFilter').val() || $('#statusFilter').val()) {
        applyPlantingFilters();
    }
});

// ── Edit Modal ───────────────────────────────────────────────────────────────
function openEditModal(btn) {
    var d = btn.dataset;
    document.getElementById('edit_planting_id').value   = d.id;
    document.getElementById('edit_planting_date').value = d.date       || '';
    document.getElementById('edit_area').value          = d.area        || '';
    document.getElementById('edit_seed').value          = d.seed        || '';
    document.getElementById('edit_harvest_date').value  = d.harvestDate || '';

    // Set <select> values
    document.getElementById('edit_farm_id').value   = d.farmId || '';
    document.getElementById('edit_crop_id').value   = d.cropId || '';
    document.getElementById('edit_status_id').value = d.statusId || '';

    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal'));
    modal.show();
}

// ── Client-side harvest date validation ──────────────────────────────────────
function validateHarvestDate(plantingDateId, harvestDateId) {
    var pd = document.getElementById(plantingDateId).value;
    var hd = document.getElementById(harvestDateId).value;
    if (pd && hd && hd < pd) {
        alert('Expected Harvest Date must be on or after the Planting Date.');
        document.getElementById(harvestDateId).value = '';
        return false;
    }
    return true;
}

document.getElementById('addForm').addEventListener('submit', function (e) {
    if (!validateHarvestDate('add_planting_date', 'add_harvest_date')) {
        e.preventDefault();
    }
});

document.getElementById('editForm').addEventListener('submit', function (e) {
    if (!validateHarvestDate('edit_planting_date', 'edit_harvest_date')) {
        e.preventDefault();
    }
});
</script>

<?php require_once 'footer.php'; ?>
