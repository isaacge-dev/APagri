<?php
require_once 'auth.php';
require_once 'db.php';

// ── Fetch all farmers for dropdowns ──────────────────────────────────────────
$farmers = $pdo->query(
    "SELECT farmer_id, first_name, last_name FROM public.farmer WHERE deleted_at IS NULL ORDER BY last_name, first_name"
)->fetchAll(PDO::FETCH_ASSOC);

// ── Fetch soil & irrigation lookup lists ──────────────────────────────────────
$soilTypesList = $pdo->query(
    "SELECT soil_type_id, soil_name, description FROM public.soil_type_lookup ORDER BY soil_type_id"
)->fetchAll(PDO::FETCH_ASSOC);

$irrigationTypesList = $pdo->query(
    "SELECT irrigation_type_id, irrigation_name, description FROM public.irrigation_type_lookup ORDER BY irrigation_type_id"
)->fetchAll(PDO::FETCH_ASSOC);

// ── Action handling ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD ───────────────────────────────────────────────────────────────────
    if ($action === 'add') {
        $farmer_id      = trim($_POST['farmer_id'] ?? '');
        $farm_name      = trim($_POST['farm_name'] ?? '');
        $location       = trim($_POST['location'] ?? '');
        $area_hectares  = ($_POST['area_hectares'] ?? '') !== '' ? $_POST['area_hectares'] : null;
        $soil_type_id       = !empty($_POST['soil_type_id']) ? (int)$_POST['soil_type_id'] : 9;
        $irrigation_type_id = !empty($_POST['irrigation_type_id']) ? (int)$_POST['irrigation_type_id'] : null;

        if ($farmer_id === '' || $farm_name === '') {
            $_SESSION['flash'] = ['msg' => 'Farmer and Farm Name are required.', 'type' => 'danger'];
            header('Location: farms.php');
            exit;
        }

        if ($area_hectares !== null && (float)$area_hectares <= 0) {
            $_SESSION['flash'] = ['msg' => 'Farm area must be greater than 0 hectares.', 'type' => 'danger'];
            header('Location: farms.php');
            exit;
        }

        try {
            $parentCheck = $pdo->prepare('SELECT 1 FROM public.farmer WHERE farmer_id = :id AND deleted_at IS NULL');
            $parentCheck->execute([':id' => $farmer_id]);
            if (!$parentCheck->fetchColumn()) throw new RuntimeException('Select an active farmer. Archived farmers cannot be assigned to a farm.');
            $stmt = $pdo->prepare(
                "INSERT INTO farm (farmer_id, farm_name, location, area_hectares, soil_type_id, irrigation_type_id)
                 VALUES (:farmer_id, :farm_name, :location, :area_hectares, :soil_type_id, :irrigation_type_id)
                 RETURNING farm_id"
            );
            $stmt->execute([
                ':farmer_id'          => $farmer_id,
                ':farm_name'          => $farm_name,
                ':location'           => $location ?: null,
                ':area_hectares'      => $area_hectares,
                ':soil_type_id'       => $soil_type_id,
                ':irrigation_type_id' => $irrigation_type_id,
            ]);
            $newId = $stmt->fetchColumn();
            logRecordChange($pdo, 'CREATE', 'Farms', $newId, "farm {$farm_name}", [], [
                'farmer_id' => $farmer_id, 'farm_name' => $farm_name, 'location' => $location ?: null,
                'area_hectares' => $area_hectares, 'soil_type_id' => $soil_type_id, 'irrigation_type_id' => $irrigation_type_id,
            ]);
            $_SESSION['flash'] = ['msg' => 'Farm added successfully.', 'type' => 'success'];
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['msg' => $e->getMessage(), 'type' => 'danger'];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['msg' => 'Error adding farm: ' . $e->getMessage(), 'type' => 'danger'];
        }
        header('Location: farms.php');
        exit;
    }

    // ── EDIT ──────────────────────────────────────────────────────────────────
    if ($action === 'edit') {
        $farm_id        = trim($_POST['farm_id'] ?? '');
        $farmer_id      = trim($_POST['farmer_id'] ?? '');
        $farm_name      = trim($_POST['farm_name'] ?? '');
        $location       = trim($_POST['location'] ?? '');
        $area_hectares  = ($_POST['area_hectares'] ?? '') !== '' ? $_POST['area_hectares'] : null;
        $soil_type_id       = !empty($_POST['soil_type_id']) ? (int)$_POST['soil_type_id'] : 9;
        $irrigation_type_id = !empty($_POST['irrigation_type_id']) ? (int)$_POST['irrigation_type_id'] : null;

        if ($farmer_id === '' || $farm_name === '') {
            $_SESSION['flash'] = ['msg' => 'Farmer and Farm Name are required.', 'type' => 'danger'];
            header('Location: farms.php');
            exit;
        }

        if ($area_hectares !== null && (float)$area_hectares <= 0) {
            $_SESSION['flash'] = ['msg' => 'Farm area must be greater than 0 hectares.', 'type' => 'danger'];
            header('Location: farms.php');
            exit;
        }

        try {
            $parentCheck = $pdo->prepare('SELECT 1 FROM public.farmer WHERE farmer_id = :id AND deleted_at IS NULL');
            $parentCheck->execute([':id' => $farmer_id]);
            if (!$parentCheck->fetchColumn()) throw new RuntimeException('Select an active farmer. Archived farmers cannot be assigned to a farm.');
            $beforeStmt = $pdo->prepare('SELECT farmer_id, farm_name, location, area_hectares, soil_type_id, irrigation_type_id FROM public.farm WHERE farm_id = :id AND deleted_at IS NULL');
            $beforeStmt->execute([':id' => $farm_id]);
            $before = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stmt = $pdo->prepare(
                "UPDATE farm SET
                    farmer_id          = :farmer_id,
                    farm_name          = :farm_name,
                    location           = :location,
                    area_hectares      = :area_hectares,
                    soil_type_id       = :soil_type_id,
                    irrigation_type_id = :irrigation_type_id
                 WHERE farm_id = :farm_id AND deleted_at IS NULL"
            );
            $stmt->execute([
                ':farmer_id'          => $farmer_id,
                ':farm_name'          => $farm_name,
                ':location'           => $location ?: null,
                ':area_hectares'      => $area_hectares,
                ':soil_type_id'       => $soil_type_id,
                ':irrigation_type_id' => $irrigation_type_id,
                ':farm_id'            => $farm_id,
            ]);
            logRecordChange($pdo, 'UPDATE', 'Farms', $farm_id, "farm {$farm_name}", $before, [
                'farmer_id' => $farmer_id, 'farm_name' => $farm_name, 'location' => $location ?: null,
                'area_hectares' => $area_hectares, 'soil_type_id' => $soil_type_id, 'irrigation_type_id' => $irrigation_type_id,
            ]);
            $_SESSION['flash'] = ['msg' => 'Farm updated successfully.', 'type' => 'success'];
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['msg' => $e->getMessage(), 'type' => 'danger'];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['msg' => 'Error updating farm: ' . $e->getMessage(), 'type' => 'danger'];
        }
        header('Location: farms.php');
        exit;
    }

    // ── DELETE ────────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $farm_id = trim($_POST['farm_id'] ?? '');
        try {
            $beforeStmt = $pdo->prepare('SELECT farmer_id, farm_name, location, area_hectares, soil_type_id, irrigation_type_id FROM public.farm WHERE farm_id = :id AND deleted_at IS NULL');
            $beforeStmt->execute([':id' => $farm_id]);
            $before = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!$before) {
                throw new RuntimeException('Farm is already archived or could not be found.');
            }
            $childCheck = $pdo->prepare('SELECT COUNT(*) FROM public.planting WHERE farm_id = :id AND deleted_at IS NULL');
            $childCheck->execute([':id' => $farm_id]);
            if ((int)$childCheck->fetchColumn() > 0) {
                $_SESSION['flash'] = ['msg' => 'Archive this farm\'s active planting records first.', 'type' => 'warning'];
            } else {
                $stmt = $pdo->prepare('UPDATE public.farm SET deleted_at = CURRENT_TIMESTAMP, deleted_by = :deleted_by WHERE farm_id = :id AND deleted_at IS NULL');
                $stmt->execute([':deleted_by' => $_SESSION['username'] ?? $_SESSION['full_name'], ':id' => $farm_id]);
                logActivity($pdo, 'ARCHIVE', 'Farms', $farm_id, "Archived farm {$before['farm_name']}.");
                $_SESSION['flash'] = ['msg' => 'Farm archived successfully.', 'type' => 'success'];
            }
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['msg' => $e->getMessage(), 'type' => 'danger'];
        } catch (PDOException $e) {
            $sqlstate = $e->getCode();
            if ($sqlstate === '23503' || $sqlstate === '23000') {
                $_SESSION['flash'] = ['msg' => 'Cannot delete this farm — it is referenced by other records.', 'type' => 'danger'];
            } else {
                $_SESSION['flash'] = ['msg' => 'Error deleting farm: ' . $e->getMessage(), 'type' => 'danger'];
            }
        }
        header('Location: farms.php');
        exit;
    }
}

// ── Fetch farms with farmer names & lookup details ───────────────────────────
$farms = $pdo->query(
    "SELECT fm.*, fr.first_name, fr.last_name, s.soil_name, i.irrigation_name
     FROM public.farm fm
     JOIN public.farmer fr ON fm.farmer_id = fr.farmer_id AND fr.deleted_at IS NULL
     LEFT JOIN public.soil_type_lookup s ON fm.soil_type_id = s.soil_type_id
     LEFT JOIN public.irrigation_type_lookup i ON fm.irrigation_type_id = i.irrigation_type_id
     WHERE fm.deleted_at IS NULL
     ORDER BY fm.farm_id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Farms';
require_once 'header.php';
require_once 'sidebar.php';
?>

<!-- ── Main Content ─────────────────────────────────────────────────────────── -->
<div class="container-fluid py-4">

        <!-- Page Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="fw-bold mb-0" style="color:var(--green-dark);">
                    <i class="bi bi-tree-fill me-2"></i>Farms
                </h2>
                <p class="text-muted mb-0 small">Manage all registered farms</p>
            </div>
            <button class="btn btn-primary-green" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg me-1"></i> Add Farm
            </button>
        </div>

        <!-- Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body">

                <div class="d-flex justify-content-start mb-3 no-print">
                    <button type="button" class="btn btn-outline-green" onclick="printDataReport('Farms Directory', '#farmsTable')">
                        <i class="bi bi-printer me-1"></i> Print / Export PDF
                    </button>
                </div>

                <!-- Soil Type Filter -->
                <div class="row mb-3 report-filters">
                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="soilFilter" class="form-label small fw-semibold text-muted">Filter by Soil Type</label>
                        <select id="soilFilter" class="form-select form-select-sm" data-print-filter-label="Soil type">
                            <option value="">All Soil Types</option>
                            <?php foreach ($soilTypesList as $st): ?>
                                <option value="<?= htmlspecialchars($st['soil_name']) ?>">
                                    <?= htmlspecialchars($st['soil_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- DataTable -->
                <div class="table-responsive">
                    <table id="farmsTable" class="table table-hover align-middle print-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Farm Name</th>
                                <th>Farmer</th>
                                <th>Location</th>
                                <th>Area (ha)</th>
                                <th>Soil Type</th>
                                <th>Irrigation Type</th>
                                <th class="text-center print-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($farms as $i => $f): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($f['farm_name']) ?></td>
                                <td><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></td>
                                <td><?= htmlspecialchars($f['location'] ?? '—') ?></td>
                                <td>
                                    <?= $f['area_hectares'] !== null
                                        ? number_format((float)$f['area_hectares'], 2) . ' ha'
                                        : '—' ?>
                                </td>
                                <td><?= htmlspecialchars($f['soil_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($f['irrigation_name'] ?? '—') ?></td>
                                <td class="text-center print-actions">
                                    <button class="btn btn-outline-green btn-sm me-1"
                                        onclick="openEditModal(this)"
                                        data-id="<?= $f['farm_id'] ?>"
                                        data-farmer-id="<?= $f['farmer_id'] ?>"
                                        data-name="<?= htmlspecialchars($f['farm_name'], ENT_QUOTES) ?>"
                                        data-location="<?= htmlspecialchars($f['location'] ?? '', ENT_QUOTES) ?>"
                                        data-area="<?= htmlspecialchars($f['area_hectares'] ?? '', ENT_QUOTES) ?>"
                                        data-soil-id="<?= $f['soil_type_id'] ?>"
                                        data-irrigation-id="<?= $f['irrigation_type_id'] ?? '' ?>">
                                        <i class="bi bi-pencil-fill"></i> Edit
                                    </button>

                                    <!-- Delete form -->
                                    <form id="deleteForm_<?= $f['farm_id'] ?>" method="POST" action="farms.php" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="farm_id" value="<?= $f['farm_id'] ?>">
                                        <button type="button" class="btn btn-danger btn-sm"
                                            onclick="confirmDelete('deleteForm_<?= $f['farm_id'] ?>', '<?= htmlspecialchars($f['farm_name'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-trash-fill"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div><!-- /.table-responsive -->

            </div><!-- /.card-body -->
        </div><!-- /.card -->

    </div><!-- /.container-fluid -->


<!-- ═══════════════════════════════════════════════════════════════════════════
     ADD MODAL
════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="farms.php">
                <input type="hidden" name="action" value="add">

                <div class="modal-header" style="background:var(--green-pale); border-bottom:1px solid var(--green-mid);">
                    <h5 class="modal-title fw-bold" id="addModalLabel" style="color:var(--green-dark);">
                        <i class="bi bi-plus-circle-fill me-2"></i>Add New Farm
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Farmer -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Farmer <span class="text-danger">*</span></label>
                            <select name="farmer_id" class="form-select" required>
                                <option value="">— Select Farmer —</option>
                                <?php foreach ($farmers as $fr): ?>
                                    <option value="<?= $fr['farmer_id'] ?>">
                                        <?= htmlspecialchars($fr['first_name'] . ' ' . $fr['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Farm Name -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Farm Name <span class="text-danger">*</span></label>
                            <input type="text" name="farm_name" class="form-control" placeholder="e.g. Green Valley Farm" required>
                        </div>

                        <!-- Location -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. Barangay Mabini, Davao">
                        </div>

                        <!-- Area -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Area (hectares)</label>
                            <input type="number" name="area_hectares" class="form-control" step="0.01" min="0.01" placeholder="e.g. 2.50">
                        </div>

                        <!-- Soil Type -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Soil Type <span class="text-danger">*</span></label>
                            <select name="soil_type_id" class="form-select" required>
                                <option value="">— Select Soil Type —</option>
                                <?php foreach ($soilTypesList as $st): ?>
                                    <option value="<?= $st['soil_type_id'] ?>">
                                        <?= htmlspecialchars($st['soil_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Irrigation Type -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Irrigation Type</label>
                            <select name="irrigation_type_id" class="form-select">
                                <option value="">— Select Irrigation (Optional) —</option>
                                <?php foreach ($irrigationTypesList as $it): ?>
                                    <option value="<?= $it['irrigation_type_id'] ?>">
                                        <?= htmlspecialchars($it['irrigation_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div><!-- /.row -->
                </div><!-- /.modal-body -->

                <div class="modal-footer" style="background:var(--green-muted);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-green">
                        <i class="bi bi-floppy-fill me-1"></i> Save Farm
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     EDIT MODAL
════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="farms.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="farm_id" id="edit_farm_id">

                <div class="modal-header" style="background:var(--green-pale); border-bottom:1px solid var(--green-mid);">
                    <h5 class="modal-title fw-bold" id="editModalLabel" style="color:var(--green-dark);">
                        <i class="bi bi-pencil-square me-2"></i>Edit Farm
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Farmer -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Farmer <span class="text-danger">*</span></label>
                            <select name="farmer_id" id="edit_farmer_id" class="form-select" required>
                                <option value="">— Select Farmer —</option>
                                <?php foreach ($farmers as $fr): ?>
                                    <option value="<?= $fr['farmer_id'] ?>">
                                        <?= htmlspecialchars($fr['first_name'] . ' ' . $fr['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Farm Name -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Farm Name <span class="text-danger">*</span></label>
                            <input type="text" name="farm_name" id="edit_farm_name" class="form-control" required>
                        </div>

                        <!-- Location -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Location</label>
                            <input type="text" name="location" id="edit_location" class="form-control">
                        </div>

                        <!-- Area -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Area (hectares)</label>
                            <input type="number" name="area_hectares" id="edit_area_hectares" class="form-control" step="0.01" min="0.01">
                        </div>

                        <!-- Soil Type -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Soil Type <span class="text-danger">*</span></label>
                            <select name="soil_type_id" id="edit_soil_type_id" class="form-select" required>
                                <option value="">— Select Soil Type —</option>
                                <?php foreach ($soilTypesList as $st): ?>
                                    <option value="<?= $st['soil_type_id'] ?>">
                                        <?= htmlspecialchars($st['soil_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Irrigation Type -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small">Irrigation Type</label>
                            <select name="irrigation_type_id" id="edit_irrigation_type_id" class="form-select">
                                <option value="">— Select Irrigation (Optional) —</option>
                                <?php foreach ($irrigationTypesList as $it): ?>
                                    <option value="<?= $it['irrigation_type_id'] ?>">
                                        <?= htmlspecialchars($it['irrigation_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div><!-- /.row -->
                </div><!-- /.modal-body -->

                <div class="modal-footer" style="background:var(--green-muted);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-green">
                        <i class="bi bi-floppy-fill me-1"></i> Update Farm
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     PAGE-LEVEL SCRIPTS
════════════════════════════════════════════════════════════════════════════ -->
<script>
// ── DataTable init ────────────────────────────────────────────────────────────
let farmsTable;
$(document).ready(function () {
    farmsTable = $('#farmsTable').DataTable({
        pageLength: 10,
        responsive: true,
        order: [],          // keep PHP ORDER BY DESC
        columnDefs: [
            { orderable: false, targets: 7 }   // Actions column
        ],
        language: {
            search: 'Search farms:',
            lengthMenu: 'Show _MENU_ farms per page',
            info: 'Showing _START_ to _END_ of _TOTAL_ farms',
            emptyTable: 'No farms found.'
        }
    });

    // ── Soil Type filter ──────────────────────────────────────────────────────
    $('#soilFilter').on('change', function () {
        const val = $.fn.dataTable.util.escapeRegex($(this).val());
        farmsTable.column(5)   // Soil Type column index
            .search(val ? '^' + val + '$' : '', true, false)
            .draw();
    });
});

// ── Open Edit Modal ───────────────────────────────────────────────────────────
function openEditModal(btn) {
    const d = btn.dataset;
    document.getElementById('edit_farm_id').value           = d.id;
    document.getElementById('edit_farm_name').value         = d.name;
    document.getElementById('edit_location').value          = d.location;
    document.getElementById('edit_area_hectares').value     = d.area;
    document.getElementById('edit_soil_type_id').value      = d.soilId || '';
    document.getElementById('edit_irrigation_type_id').value = d.irrigationId || '';

    // Set farmer select
    const farmerSel = document.getElementById('edit_farmer_id');
    farmerSel.value = d.farmerId;

    const editModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal'));
    editModal.show();
}
</script>

<?php require_once 'footer.php'; ?>
