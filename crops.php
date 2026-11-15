<?php
require_once 'auth.php';
require_once 'db.php';

// ── Action Handling ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD ──────────────────────────────────────────────────────────────────
    if ($action === 'add') {
        $crop_name           = trim($_POST['crop_name'] ?? '');
        $variety             = trim($_POST['variety'] ?? '');
        $category_id         = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 8; // default to 8 'Other'
        $growing_period_days = ($_POST['growing_period_days'] ?? '') !== '' ? (int)$_POST['growing_period_days'] : null;
        $season_id           = !empty($_POST['season_id']) ? (int)$_POST['season_id'] : null;

        if ($crop_name === '') {
            $_SESSION['flash'] = ['msg' => 'Crop name is required.', 'type' => 'danger'];
            header('Location: crops.php');
            exit;
        }

        if ($growing_period_days !== null && $growing_period_days <= 0) {
            $_SESSION['flash'] = ['msg' => 'Growing period must be greater than 0 days.', 'type' => 'danger'];
            header('Location: crops.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO crop (crop_name, variety, category_id, growing_period_days, season_id)
                VALUES (:crop_name, :variety, :category_id, :growing_period_days, :season_id)
                RETURNING crop_id
            ");
            $stmt->execute([
                ':crop_name'           => $crop_name,
                ':variety'             => $variety ?: null,
                ':category_id'         => $category_id,
                ':growing_period_days' => $growing_period_days,
                ':season_id'           => $season_id,
            ]);
            $newId = $stmt->fetchColumn();
            logRecordChange($pdo, 'CREATE', 'Crops', $newId, "crop {$crop_name}", [], [
                'crop_name' => $crop_name, 'variety' => $variety ?: null, 'category_id' => $category_id,
                'growing_period_days' => $growing_period_days, 'season_id' => $season_id,
            ]);
            $_SESSION['flash'] = ['msg' => 'Crop added successfully.', 'type' => 'success'];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['msg' => 'Error adding crop: ' . $e->getMessage(), 'type' => 'danger'];
        }

        header('Location: crops.php');
        exit;
    }

    // ── EDIT ─────────────────────────────────────────────────────────────────
    if ($action === 'edit') {
        $crop_id             = (int)($_POST['crop_id'] ?? 0);
        $crop_name           = trim($_POST['crop_name'] ?? '');
        $variety             = trim($_POST['variety'] ?? '');
        $category_id         = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : 8;
        $growing_period_days = ($_POST['growing_period_days'] ?? '') !== '' ? (int)$_POST['growing_period_days'] : null;
        $season_id           = !empty($_POST['season_id']) ? (int)$_POST['season_id'] : null;

        if ($crop_name === '') {
            $_SESSION['flash'] = ['msg' => 'Crop name is required.', 'type' => 'danger'];
            header('Location: crops.php');
            exit;
        }

        if ($growing_period_days !== null && $growing_period_days <= 0) {
            $_SESSION['flash'] = ['msg' => 'Growing period must be greater than 0 days.', 'type' => 'danger'];
            header('Location: crops.php');
            exit;
        }

        try {
            $beforeStmt = $pdo->prepare('SELECT crop_name, variety, category_id, growing_period_days, season_id FROM public.crop WHERE crop_id = :id AND deleted_at IS NULL');
            $beforeStmt->execute([':id' => $crop_id]);
            $before = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stmt = $pdo->prepare("
                UPDATE crop
                   SET crop_name           = :crop_name,
                       variety             = :variety,
                       category_id         = :category_id,
                       growing_period_days = :growing_period_days,
                       season_id           = :season_id
                 WHERE crop_id = :crop_id AND deleted_at IS NULL
            ");
            $stmt->execute([
                ':crop_name'           => $crop_name,
                ':variety'             => $variety ?: null,
                ':category_id'         => $category_id,
                ':growing_period_days' => $growing_period_days,
                ':season_id'           => $season_id,
                ':crop_id'             => $crop_id,
            ]);
            logRecordChange($pdo, 'UPDATE', 'Crops', $crop_id, "crop {$crop_name}", $before, [
                'crop_name' => $crop_name, 'variety' => $variety ?: null, 'category_id' => $category_id,
                'growing_period_days' => $growing_period_days, 'season_id' => $season_id,
            ]);
            $_SESSION['flash'] = ['msg' => 'Crop updated successfully.', 'type' => 'success'];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['msg' => 'Error updating crop: ' . $e->getMessage(), 'type' => 'danger'];
        }

        header('Location: crops.php');
        exit;
    }

    // ── DELETE ───────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $crop_id = (int)($_POST['crop_id'] ?? 0);

        try {
            $beforeStmt = $pdo->prepare('SELECT crop_name, variety, category_id, growing_period_days, season_id FROM public.crop WHERE crop_id = :id AND deleted_at IS NULL');
            $beforeStmt->execute([':id' => $crop_id]);
            $before = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!$before) {
                throw new RuntimeException('Crop is already archived or could not be found.');
            }
            $childCheck = $pdo->prepare('SELECT COUNT(*) FROM public.planting WHERE crop_id = :id AND deleted_at IS NULL');
            $childCheck->execute([':id' => $crop_id]);
            if ((int)$childCheck->fetchColumn() > 0) {
                $_SESSION['flash'] = ['msg' => 'Archive this crop\'s active planting records first.', 'type' => 'warning'];
            } else {
                $stmt = $pdo->prepare('UPDATE public.crop SET deleted_at = CURRENT_TIMESTAMP, deleted_by = :deleted_by WHERE crop_id = :id AND deleted_at IS NULL');
                $stmt->execute([':deleted_by' => $_SESSION['username'] ?? $_SESSION['full_name'], ':id' => $crop_id]);
                logActivity($pdo, 'ARCHIVE', 'Crops', $crop_id, "Archived crop {$before['crop_name']}.");
                $_SESSION['flash'] = ['msg' => 'Crop archived successfully.', 'type' => 'success'];
            }
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['msg' => $e->getMessage(), 'type' => 'danger'];
        } catch (PDOException $e) {
            $sqlstate = $e->getCode();
            if ($sqlstate === '23503' || $sqlstate === '23000') {
                $_SESSION['flash'] = ['msg' => 'Cannot delete: crop is used in planting records.', 'type' => 'danger'];
            } else {
                $_SESSION['flash'] = ['msg' => 'Error deleting crop: ' . $e->getMessage(), 'type' => 'danger'];
            }
        }

        header('Location: crops.php');
        exit;
    }
}

// ── Fetch Data ───────────────────────────────────────────────────────────────
$crops = $pdo->query(
    "SELECT c.*, cat.category_name, sea.season_name
     FROM public.crop c
     LEFT JOIN public.crop_category_lookup cat ON c.category_id = cat.category_id
     LEFT JOIN public.season_lookup sea ON c.season_id = sea.season_id
     WHERE c.deleted_at IS NULL
     ORDER BY c.crop_id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT category_id, category_name FROM public.crop_category_lookup ORDER BY category_id")->fetchAll(PDO::FETCH_ASSOC);

$seasons = $pdo->query("SELECT season_id, season_name FROM public.season_lookup ORDER BY season_id")->fetchAll(PDO::FETCH_ASSOC);

// ── Layout ───────────────────────────────────────────────────────────────────
$pageTitle = 'Crops';
require_once 'header.php';
require_once 'sidebar.php';
?>

<!-- ── Main Content ──────────────────────────────────────────────────────── -->
    <div class="container-fluid py-4">

        <!-- Page Header -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h2 class="fw-bold mb-0" style="color: var(--green-dark, #2d6a4f);">
                <i class="bi bi-flower1 me-2"></i>Crops
            </h2>
            <button class="btn btn-primary-green" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg me-1"></i>Add Crop
            </button>
        </div>

        <!-- Filters -->
        <div class="row g-3 mb-3 report-filters">
            <div class="col-sm-6 col-md-4 col-lg-3">
                <select id="filterCategory" class="form-select form-select-sm" data-print-filter-label="Category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <select id="filterSeason" class="form-select form-select-sm" data-print-filter-label="Season">
                    <option value="">All Seasons</option>
                    <?php foreach ($seasons as $sea): ?>
                        <option value="<?= htmlspecialchars($sea['season_name']) ?>"><?= htmlspecialchars($sea['season_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="d-flex justify-content-start p-3 pb-0 no-print">
                    <button type="button" class="btn btn-outline-green" onclick="printDataReport('Crops Directory', '#cropsTable')">
                        <i class="bi bi-printer me-1"></i> Print / Export PDF
                    </button>
                </div>
                <div class="table-responsive">
                    <table id="cropsTable" class="table table-hover table-striped align-middle mb-0 w-100 print-table">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Crop Name</th>
                                <th>Variety</th>
                                <th>Category</th>
                                <th>Growing Period</th>
                                <th>Season</th>
                                <th style="width:120px;" class="print-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rowNum = 1; foreach ($crops as $crop): ?>
                            <tr>
                                <td><?= $rowNum++ ?></td>
                                <td><?= htmlspecialchars($crop['crop_name']) ?></td>
                                <td><?= htmlspecialchars($crop['variety'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($crop['category_name'] ?? '—') ?></td>
                                <td>
                                    <?= $crop['growing_period_days'] !== null
                                        ? htmlspecialchars($crop['growing_period_days']) . ' days'
                                        : '—' ?>
                                </td>
                                <td><?= htmlspecialchars($crop['season_name'] ?? '—') ?></td>
                                <td class="print-actions">
                                    <!-- Edit -->
                                    <button type="button"
                                            class="btn btn-outline-green btn-sm"
                                            onclick="openEditModal(this)"
                                            data-id="<?= $crop['crop_id'] ?>"
                                            data-name="<?= htmlspecialchars($crop['crop_name'], ENT_QUOTES) ?>"
                                            data-variety="<?= htmlspecialchars($crop['variety'] ?? '', ENT_QUOTES) ?>"
                                            data-category-id="<?= $crop['category_id'] ?>"
                                            data-period="<?= htmlspecialchars($crop['growing_period_days'] ?? '', ENT_QUOTES) ?>"
                                            data-season-id="<?= $crop['season_id'] ?? '' ?>">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>

                                    <!-- Delete -->
                                    <form id="deleteForm_<?= $crop['crop_id'] ?>" method="POST" action="crops.php" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="crop_id" value="<?= $crop['crop_id'] ?>">
                                        <button type="button"
                                                class="btn btn-outline-danger btn-sm"
                                                onclick="confirmDelete('deleteForm_<?= $crop['crop_id'] ?>', '<?= htmlspecialchars($crop['crop_name'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div><!-- /container-fluid -->


<!-- ── ADD Modal ─────────────────────────────────────────────────────────── -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="crops.php" novalidate>
                <input type="hidden" name="action" value="add">

                <div class="modal-header" style="background-color: var(--green-mid, #40916c);">
                    <h5 class="modal-title text-white fw-bold" id="addModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>Add New Crop
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Crop Name -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Crop Name <span class="text-danger">*</span></label>
                            <input type="text" name="crop_name" class="form-control" placeholder="e.g. Rice" required>
                        </div>
                        <!-- Variety -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Variety</label>
                            <input type="text" name="variety" class="form-control" placeholder="e.g. NSIC Rc222">
                        </div>
                        <!-- Category -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">— Select Category —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Growing Period -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Growing Period (days)</label>
                            <input type="number" name="growing_period_days" class="form-control" min="1" step="1" placeholder="e.g. 120">
                        </div>
                        <!-- Recommended Season -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Recommended Season</label>
                            <select name="season_id" class="form-select">
                                <option value="">— Select Season (Optional) —</option>
                                <?php foreach ($seasons as $sea): ?>
                                    <option value="<?= $sea['season_id'] ?>"><?= htmlspecialchars($sea['season_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-green">
                        <i class="bi bi-check-lg me-1"></i>Save Crop
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── EDIT Modal ─────────────────────────────────────────────────────────── -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="crops.php" novalidate>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="crop_id" id="editCropId">

                <div class="modal-header" style="background-color: var(--green-mid, #40916c);">
                    <h5 class="modal-title text-white fw-bold" id="editModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>Edit Crop
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Crop Name -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Crop Name <span class="text-danger">*</span></label>
                            <input type="text" name="crop_name" id="editCropName" class="form-control" required>
                        </div>
                        <!-- Variety -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Variety</label>
                            <input type="text" name="variety" id="editVariety" class="form-control">
                        </div>
                        <!-- Category -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category_id" id="editCategoryId" class="form-select" required>
                                <option value="">— Select Category —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Growing Period -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Growing Period (days)</label>
                            <input type="number" name="growing_period_days" id="editPeriod" class="form-control" min="1" step="1">
                        </div>
                        <!-- Recommended Season -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Recommended Season</label>
                            <select name="season_id" id="editSeasonId" class="form-select">
                                <option value="">— Select Season (Optional) —</option>
                                <?php foreach ($seasons as $sea): ?>
                                    <option value="<?= $sea['season_id'] ?>"><?= htmlspecialchars($sea['season_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-green">
                        <i class="bi bi-check-lg me-1"></i>Update Crop
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Page Scripts ───────────────────────────────────────────────────────── -->
<script>
// DataTable init
const cropsTable = $('#cropsTable').DataTable({
    pageLength: 10,
    responsive: true,
    order: [],   // PHP already sorted DESC
    columnDefs: [
        { orderable: false, targets: 6 }  // Actions column
    ],
    language: {
        search: 'Search:',
        lengthMenu: 'Show _MENU_ entries',
        info: 'Showing _START_ to _END_ of _TOTAL_ crops',
        emptyTable: 'No crops found.'
    }
});

// ── Category filter ───────────────────────────────────────────────────────
$('#filterCategory').on('change', function () {
    // Column index 3 = Category
    cropsTable.column(3).search(this.value, false, false).draw();
});

// ── Season filter ─────────────────────────────────────────────────────────
$('#filterSeason').on('change', function () {
    // Column index 5 = Season
    cropsTable.column(5).search(this.value, false, false).draw();
});

// ── Populate Edit Modal ───────────────────────────────────────────────────
function openEditModal(btn) {
    const d = btn.dataset;

    document.getElementById('editCropId').value     = d.id;
    document.getElementById('editCropName').value   = d.name;
    document.getElementById('editVariety').value    = d.variety;
    document.getElementById('editPeriod').value     = d.period;
    document.getElementById('editCategoryId').value = d.categoryId || '';
    document.getElementById('editSeasonId').value   = d.seasonId || '';

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal'));
    modal.show();
}
</script>

<?php require_once 'footer.php'; ?>
