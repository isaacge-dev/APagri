<?php
require_once 'auth.php';
require_once 'db.php';

// --- Action Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $first_name      = trim($_POST['first_name'] ?? '');
        $last_name       = trim($_POST['last_name'] ?? '');
        $contact_number  = trim($_POST['contact_number'] ?? '');
        $address         = trim($_POST['address'] ?? '');
        $date_registered = trim($_POST['date_registered'] ?? '');

        if ($first_name === '' || $last_name === '') {
            $_SESSION['flash'] = ['msg' => 'First name and last name are required.', 'type' => 'danger'];
            header('Location: farmers.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO farmer (first_name, last_name, contact_number, address, date_registered)
                 VALUES (:first_name, :last_name, :contact_number, :address, :date_registered)
                 RETURNING farmer_id"
            );
            $stmt->execute([
                ':first_name'      => $first_name,
                ':last_name'       => $last_name,
                ':contact_number'  => $contact_number !== '' ? $contact_number : null,
                ':address'         => $address !== '' ? $address : null,
                ':date_registered' => $date_registered !== '' ? $date_registered : null,
            ]);
            $newId = $stmt->fetchColumn();
            logRecordChange($pdo, 'CREATE', 'Farmers', $newId, "farmer {$first_name} {$last_name}", [], [
                'first_name' => $first_name, 'last_name' => $last_name, 'contact_number' => $contact_number ?: null,
                'address' => $address ?: null, 'date_registered' => $date_registered ?: null,
            ]);
            $_SESSION['flash'] = ['msg' => 'Farmer added successfully.', 'type' => 'success'];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['msg' => 'Error adding farmer: ' . $e->getMessage(), 'type' => 'danger'];
        }
        header('Location: farmers.php');
        exit;
    }

    if ($action === 'edit') {
        $farmer_id       = (int)($_POST['farmer_id'] ?? 0);
        $first_name      = trim($_POST['first_name'] ?? '');
        $last_name       = trim($_POST['last_name'] ?? '');
        $contact_number  = trim($_POST['contact_number'] ?? '');
        $address         = trim($_POST['address'] ?? '');
        $date_registered = trim($_POST['date_registered'] ?? '');

        if ($first_name === '' || $last_name === '') {
            $_SESSION['flash'] = ['msg' => 'First name and last name are required.', 'type' => 'danger'];
            header('Location: farmers.php');
            exit;
        }

        try {
            $beforeStmt = $pdo->prepare('SELECT first_name, last_name, contact_number, address, date_registered FROM public.farmer WHERE farmer_id = :id AND deleted_at IS NULL');
            $beforeStmt->execute([':id' => $farmer_id]);
            $before = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stmt = $pdo->prepare(
                "UPDATE farmer
                    SET first_name      = :first_name,
                        last_name       = :last_name,
                        contact_number  = :contact_number,
                        address         = :address,
                        date_registered = :date_registered
                 WHERE farmer_id = :farmer_id AND deleted_at IS NULL"
            );
            $stmt->execute([
                ':first_name'      => $first_name,
                ':last_name'       => $last_name,
                ':contact_number'  => $contact_number !== '' ? $contact_number : null,
                ':address'         => $address !== '' ? $address : null,
                ':date_registered' => $date_registered !== '' ? $date_registered : null,
                ':farmer_id'       => $farmer_id,
            ]);
            logRecordChange($pdo, 'UPDATE', 'Farmers', $farmer_id, "farmer {$first_name} {$last_name}", $before, [
                'first_name' => $first_name, 'last_name' => $last_name, 'contact_number' => $contact_number ?: null,
                'address' => $address ?: null, 'date_registered' => $date_registered ?: null,
            ]);
            $_SESSION['flash'] = ['msg' => 'Farmer updated successfully.', 'type' => 'success'];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['msg' => 'Error updating farmer: ' . $e->getMessage(), 'type' => 'danger'];
        }
        header('Location: farmers.php');
        exit;
    }

    if ($action === 'delete') {
        $farmer_id = (int)($_POST['farmer_id'] ?? 0);
        try {
            $beforeStmt = $pdo->prepare('SELECT first_name, last_name, contact_number, address, date_registered FROM public.farmer WHERE farmer_id = :id AND deleted_at IS NULL');
            $beforeStmt->execute([':id' => $farmer_id]);
            $before = $beforeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!$before) {
                throw new RuntimeException('Farmer is already archived or could not be found.');
            }
            $childCheck = $pdo->prepare('SELECT COUNT(*) FROM public.farm WHERE farmer_id = :id AND deleted_at IS NULL');
            $childCheck->execute([':id' => $farmer_id]);
            if ((int)$childCheck->fetchColumn() > 0) {
                $_SESSION['flash'] = ['msg' => 'Archive this farmer\'s active farms first.', 'type' => 'warning'];
            } else {
                $stmt = $pdo->prepare('UPDATE public.farmer SET deleted_at = CURRENT_TIMESTAMP, deleted_by = :deleted_by WHERE farmer_id = :id AND deleted_at IS NULL');
                $stmt->execute([':deleted_by' => $_SESSION['username'] ?? $_SESSION['full_name'], ':id' => $farmer_id]);
                $label = trim(($before['first_name'] ?? '') . ' ' . ($before['last_name'] ?? ''));
                logActivity($pdo, 'ARCHIVE', 'Farmers', $farmer_id, "Archived farmer {$label}.");
                $_SESSION['flash'] = ['msg' => 'Farmer archived successfully.', 'type' => 'success'];
            }
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['msg' => $e->getMessage(), 'type' => 'danger'];
        } catch (PDOException $e) {
            $sqlstate = $e->getCode();
            if ($sqlstate === '23000' || $sqlstate === '23503') {
                $_SESSION['flash'] = [
                    'msg'  => 'Cannot delete this farmer because they are linked to existing records.',
                    'type' => 'danger',
                ];
            } else {
                $_SESSION['flash'] = ['msg' => 'Error deleting farmer: ' . $e->getMessage(), 'type' => 'danger'];
            }
        }
        header('Location: farmers.php');
        exit;
    }
}

// --- Fetch farmers ---
$farmers = $pdo->query(
    "SELECT farmer_id, first_name, last_name, contact_number, address, date_registered
       FROM public.farmer
      WHERE deleted_at IS NULL
      ORDER BY farmer_id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// --- Distinct years for year-filter dropdown ---
$yearRows = $pdo->query(
    "SELECT DISTINCT EXTRACT(YEAR FROM date_registered)::int AS yr
       FROM public.farmer
      WHERE date_registered IS NOT NULL
        AND deleted_at IS NULL
      ORDER BY yr DESC"
)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Farmers';
require_once 'header.php';
require_once 'sidebar.php';
?>

<!-- Page content -->
<div class="container-fluid py-4">

    <!-- Page heading -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0 fw-bold" style="color: var(--green-dark);">
            <i class="bi bi-people-fill me-2"></i>Farmers
        </h4>
        <button class="btn btn-primary-green" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-1"></i> Add Farmer
        </button>
    </div>

    <!-- Card -->
    <div class="card shadow-sm border-0">
        <div class="card-body">

            <div class="d-flex justify-content-start mb-3 no-print">
                <button type="button" class="btn btn-outline-green" onclick="printDataReport('Farmers Directory', '#farmersTable')">
                    <i class="bi bi-printer me-1"></i> Print / Export PDF
                </button>
            </div>

            <!-- Year filter -->
            <div class="d-flex align-items-center gap-2 mb-3 report-filters">
                <label for="yearFilter" class="form-label mb-0 fw-semibold text-muted small">
                    Filter by Registration Year:
                </label>
                <select id="yearFilter" class="form-select form-select-sm w-auto" data-print-filter-label="Registration year">
                    <option value="">All Years</option>
                    <?php foreach ($yearRows as $yr): ?>
                        <option value="<?= htmlspecialchars((string)$yr) ?>"><?= htmlspecialchars((string)$yr) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- DataTable -->
            <div class="table-responsive">
                <table id="farmersTable" class="table table-hover table-bordered align-middle w-100 print-table">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Full Name</th>
                            <th>Contact Number</th>
                            <th>Address</th>
                            <th>Date Registered</th>
                            <th style="width:120px" class="text-center print-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row = 1; foreach ($farmers as $f): ?>
                        <tr>
                            <td><?= $row++ ?></td>
                            <td><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></td>
                            <td><?= htmlspecialchars($f['contact_number'] ?? '—') ?></td>
                            <td><?= nl2br(htmlspecialchars($f['address'] ?? '—')) ?></td>
                            <td data-order="<?= htmlspecialchars($f['date_registered'] ?? '') ?>">
                                <?php
                                    if (!empty($f['date_registered'])) {
                                        echo date('F j, Y', strtotime($f['date_registered']));
                                    } else {
                                        echo '—';
                                    }
                                ?>
                            </td>
                            <td class="text-center print-actions">
                                <!-- Edit -->
                                <button type="button"
                                        class="btn btn-outline-green btn-sm"
                                        onclick="openEditModal(this)"
                                        data-id="<?= $f['farmer_id'] ?>"
                                        data-first="<?= htmlspecialchars($f['first_name']) ?>"
                                        data-last="<?= htmlspecialchars($f['last_name']) ?>"
                                        data-contact="<?= htmlspecialchars($f['contact_number'] ?? '') ?>"
                                        data-address="<?= htmlspecialchars($f['address'] ?? '') ?>"
                                        data-date="<?= htmlspecialchars($f['date_registered'] ?? '') ?>"
                                        title="Edit">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>

                                <!-- Delete form -->
                                <form id="deleteForm_<?= $f['farmer_id'] ?>"
                                      method="POST"
                                      action="farmers.php"
                                      class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="farmer_id" value="<?= $f['farmer_id'] ?>">
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm"
                                            onclick="confirmDelete('deleteForm_<?= $f['farmer_id'] ?>', '<?= htmlspecialchars(addslashes($f['first_name'] . ' ' . $f['last_name'])) ?>')"
                                            title="Delete">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div><!-- /table-responsive -->

        </div>
    </div><!-- /card -->
</div><!-- /container-fluid -->

<!-- ===================== ADD MODAL ===================== -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="farmers.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header" style="background-color: var(--green-pale);">
                    <h5 class="modal-title fw-bold" id="addModalLabel" style="color: var(--green-dark);">
                        <i class="bi bi-person-plus-fill me-2"></i>Add Farmer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required placeholder="e.g. Juan">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required placeholder="e.g. dela Cruz">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" placeholder="e.g. 09171234567">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date Registered</label>
                            <input type="date" name="date_registered" class="form-control"
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" class="form-control" rows="3"
                                      placeholder="Barangay, Municipality, Province"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background-color: var(--green-muted);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-green">
                        <i class="bi bi-save me-1"></i> Save Farmer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===================== EDIT MODAL ===================== -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="farmers.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="farmer_id" id="edit_farmer_id">
                <div class="modal-header" style="background-color: var(--green-pale);">
                    <h5 class="modal-title fw-bold" id="editModalLabel" style="color: var(--green-dark);">
                        <i class="bi bi-pencil-square me-2"></i>Edit Farmer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Number</label>
                            <input type="text" name="contact_number" id="edit_contact_number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date Registered</label>
                            <input type="date" name="date_registered" id="edit_date_registered" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" id="edit_address" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background-color: var(--green-muted);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-green">
                        <i class="bi bi-save me-1"></i> Update Farmer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===================== PAGE SCRIPTS ===================== -->
<script>
// Populate edit modal
function openEditModal(btn) {
    const ds = btn.dataset;
    document.getElementById('edit_farmer_id').value       = ds.id;
    document.getElementById('edit_first_name').value      = ds.first;
    document.getElementById('edit_last_name').value       = ds.last;
    document.getElementById('edit_contact_number').value  = ds.contact;
    document.getElementById('edit_address').value         = ds.address;
    document.getElementById('edit_date_registered').value = ds.date;
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}

// DataTable init + year filter
$(function () {
    const table = $('#farmersTable').DataTable({
        pageLength: 10,
        responsive: true,
        order: [],          // PHP already sorted DESC; keep that order
        columnDefs: [
            { orderable: false, targets: [0, 5] }   // # and Actions columns
        ],
        language: {
            search: 'Search farmers:',
            emptyTable: 'No farmers found.',
            zeroRecords: 'No matching farmers found.'
        }
    });

    // Year filter — column index 4 is "Date Registered"
    $('#yearFilter').on('change', function () {
        const yr = $(this).val();
        table.column(4).search(yr ? yr : '', false, false).draw();
    });
});
</script>

<?php require_once 'footer.php'; ?>
