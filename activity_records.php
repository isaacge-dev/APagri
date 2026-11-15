<?php
/** Admin-only activity history and agricultural edit explorer. */
require_once 'auth.php';
require_once 'db.php';
requireAdministrator();

$pageTitle = 'Activity Records';
$stmt = $pdo->prepare("SELECT log_id, created_at, user_name, action_type, module_name, record_id, description
    FROM public.activity_logs
    WHERE UPPER(action_type) IN ('CREATE', 'UPDATE', 'DELETE')
      AND module_name IN ('Farmers', 'Farms', 'Crops', 'Planting Records', 'Harvest Records', 'Planting', 'Harvest')
    ORDER BY created_at DESC, log_id DESC");
$stmt->execute();
$edits = $stmt->fetchAll(PDO::FETCH_ASSOC);
$previewEdits = array_slice($edits, 0, 8);
$logs = $pdo->query('SELECT log_id, created_at, user_name, action_type, module_name, record_id, description FROM public.activity_logs ORDER BY created_at DESC, log_id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
$moduleLabels = ['Farmers'=>'Farmers', 'Farms'=>'Farms', 'Crops'=>'Crops', 'Planting Records'=>'Planting Records', 'Planting'=>'Planting Records', 'Harvest Records'=>'Harvest Records', 'Harvest'=>'Harvest Records'];
$users = array_values(array_unique(array_map(static fn($row) => (string)$row['user_name'], $edits)));
$dateFormat = static function ($value): string {
    $timestamp = strtotime((string)$value);
    return $timestamp === false ? (string)$value : date('M d, Y • h:i A', $timestamp);
};
$actionLabel = static function ($action): string {
    return match (strtoupper((string)$action)) { 'CREATE' => 'Added', 'UPDATE' => 'Edited', 'DELETE' => 'Deleted', default => (string)$action };
};
$actionClass = static function ($action): string {
    return match (strtoupper((string)$action)) { 'CREATE' => 'bg-success', 'UPDATE' => 'bg-warning text-dark', 'DELETE' => 'bg-danger', default => 'bg-secondary' };
};
require_once 'header.php';
require_once 'sidebar.php';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><h4 class="fw-bold mb-1" style="color:var(--green-dark)"><i class="bi bi-journal-text me-2"></i>Activity Records</h4><p class="text-muted mb-0">Recent system activity and agricultural data changes.</p></div>
    </div>

    <section class="card border-0 shadow-sm mb-4" aria-labelledby="editsTitle">
        <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
            <div><h5 id="editsTitle" class="h6 fw-bold mb-0" style="color:var(--green-darkest)"><i class="bi bi-pencil-square me-2"></i>Edits</h5><small class="text-muted">Latest changes across the five agricultural modules</small></div>
            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#editsModal" title="Full View" aria-label="Full View of all edits"><i class="bi bi-arrows-fullscreen me-1"></i>Full View</button>
        </div>
        <div class="list-group list-group-flush">
            <?php if (!$previewEdits): ?><div class="list-group-item text-muted py-4 text-center">No agricultural edits have been logged yet.</div><?php endif; ?>
            <?php foreach ($previewEdits as $edit):
                $module = $moduleLabels[$edit['module_name']] ?? $edit['module_name'];
                $action = strtoupper((string)$edit['action_type']);
                $created = (string)$edit['created_at'];
            ?>
                <div class="list-group-item py-3">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <span class="badge rounded-pill text-bg-success-subtle border border-success-subtle text-success"><?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge <?= htmlspecialchars($actionClass($action), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($actionLabel($action), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="fw-semibold"><?= htmlspecialchars($edit['description'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="small text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($edit['user_name'], ENT_QUOTES, 'UTF-8') ?><span class="mx-2">•</span><time datetime="<?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dateFormat($created), ENT_QUOTES, 'UTF-8') ?></time></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3"><h5 class="h6 fw-bold mb-0">Recent System Activity</h5></div>
        <div class="card-body"><div class="table-responsive"><table id="activityTable" class="table table-hover align-middle w-100"><thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Module</th><th>Record ID</th><th>Description</th></tr></thead><tbody>
        <?php foreach ($logs as $log): $created=(string)$log['created_at']; ?>
            <tr><td data-order="<?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dateFormat($created), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($log['user_name'], ENT_QUOTES, 'UTF-8') ?></td><td><span class="badge <?= htmlspecialchars($actionClass($log['action_type']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($actionLabel($log['action_type']), ENT_QUOTES, 'UTF-8') ?></span></td><td><?= htmlspecialchars($moduleLabels[$log['module_name']] ?? $log['module_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($log['record_id'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($log['description'], ENT_QUOTES, 'UTF-8') ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
    </section>
</div>

<div class="modal fade" id="editsModal" tabindex="-1" aria-labelledby="editsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title fw-bold" id="editsModalTitle">Full View — Edits</h5><small class="text-muted">Complete create, update, and delete history</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="row g-2 mb-3">
                <div class="col-sm-6 col-lg-2"><label class="form-label small" for="filterModule">Module</label><select id="filterModule" class="form-select form-select-sm"><option value="">All Modules</option><?php foreach (['Farmers','Farms','Crops','Planting Records','Harvest Records'] as $m): ?><option><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                <div class="col-sm-6 col-lg-2"><label class="form-label small" for="filterAction">Action</label><select id="filterAction" class="form-select form-select-sm"><option value="">All Actions</option><option>Added</option><option>Edited</option><option>Deleted</option></select></div>
                <div class="col-sm-6 col-lg-2"><label class="form-label small" for="filterUser">User</label><select id="filterUser" class="form-select form-select-sm"><option value="">All Users</option><?php foreach ($users as $name): ?><option><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                <div class="col-sm-6 col-lg-2"><label class="form-label small" for="filterFrom">From</label><input id="filterFrom" type="date" class="form-control form-control-sm"></div>
                <div class="col-sm-6 col-lg-2"><label class="form-label small" for="filterTo">To</label><input id="filterTo" type="date" class="form-control form-control-sm"></div>
                <div class="col-sm-6 col-lg-2 d-flex align-items-end"><button id="clearEditFilters" class="btn btn-sm btn-outline-secondary w-100" type="button">Clear filters</button></div>
            </div>
            <div class="table-responsive"><table id="allEditsTable" class="table table-hover align-middle w-100"><thead><tr><th>Date &amp; Time</th><th>Module</th><th>Action</th><th>Record</th><th>Edited By</th><th>Change Preview</th><th>Inspect</th></tr></thead><tbody>
            <?php foreach ($edits as $edit):
                $module = $moduleLabels[$edit['module_name']] ?? $edit['module_name'];
                $action = strtoupper((string)$edit['action_type']);
                $created = (string)$edit['created_at'];
                $timestamp = strtotime($created);
                $isoDate = $timestamp === false ? '' : date('Y-m-d', $timestamp);
            ?>
                <tr data-created="<?= htmlspecialchars($isoDate, ENT_QUOTES, 'UTF-8') ?>">
                    <td data-order="<?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dateFormat($created), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge <?= htmlspecialchars($actionClass($action), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($actionLabel($action), ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars($edit['record_id'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($edit['user_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($edit['description'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><button type="button" class="btn btn-sm btn-outline-success inspect-edit" data-module="<?= htmlspecialchars($module, ENT_QUOTES, 'UTF-8') ?>" data-action="<?= htmlspecialchars($actionLabel($action), ENT_QUOTES, 'UTF-8') ?>" data-record="<?= htmlspecialchars($edit['record_id'] ?? '—', ENT_QUOTES, 'UTF-8') ?>" data-user="<?= htmlspecialchars($edit['user_name'], ENT_QUOTES, 'UTF-8') ?>" data-created="<?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?>" data-description="<?= htmlspecialchars($edit['description'], ENT_QUOTES, 'UTF-8') ?>" title="Inspect edit" aria-label="Inspect edit"><i class="bi bi-eye"></i></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div></div>
</div>

<div class="modal fade" id="editDetailModal" tabindex="-1" aria-labelledby="editDetailTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="editDetailTitle">Edit details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-3">Module</dt><dd class="col-sm-9" id="detailModule"></dd><dt class="col-sm-3">Action</dt><dd class="col-sm-9" id="detailAction"></dd><dt class="col-sm-3">Record ID</dt><dd class="col-sm-9" id="detailRecord"></dd><dt class="col-sm-3">Edited by</dt><dd class="col-sm-9" id="detailUser"></dd><dt class="col-sm-3">Timestamp</dt><dd class="col-sm-9" id="detailCreated"></dd><dt class="col-sm-3">Field details</dt><dd class="col-sm-9 text-break" id="detailDescription"></dd></dl></div></div></div></div>

<script>
$(function () {
    $('#activityTable').DataTable({ pageLength: 20, order: [[0, 'desc']], responsive: true });
    let editsTable = null;
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (!settings.nTable || settings.nTable.id !== 'allEditsTable') return true;
        const row = settings.aoData[dataIndex].nTr;
        const date = row.dataset.created || '';
        const from = $('#filterFrom').val();
        const to = $('#filterTo').val();
        return (!from || date >= from) && (!to || date <= to);
    });
    $('#editsModal').on('shown.bs.modal', function () {
        if (!editsTable) editsTable = $('#allEditsTable').DataTable({ pageLength: 25, order: [[0, 'desc']], autoWidth: false, columnDefs: [{targets: 6, orderable: false, searchable: false}] });
        editsTable.columns.adjust().draw(false);
    });
    $('#filterModule').on('change', function () { if (editsTable) editsTable.column(1).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw(); });
    $('#filterAction').on('change', function () { if (editsTable) editsTable.column(2).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw(); });
    $('#filterUser').on('change', function () { if (editsTable) editsTable.column(4).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw(); });
    $('#filterFrom, #filterTo').on('change', function () { if (editsTable) editsTable.draw(); });
    $('#clearEditFilters').on('click', function () { $('#filterModule, #filterAction, #filterUser, #filterFrom, #filterTo').val(''); if (editsTable) editsTable.search('').columns().search('').draw(); });
    $(document).on('click', '.inspect-edit', function () {
        const button = this;
        $('#detailModule').text(button.dataset.module || ''); $('#detailAction').text(button.dataset.action || '');
        $('#detailRecord').text(button.dataset.record || ''); $('#detailUser').text(button.dataset.user || '');
        $('#detailCreated').text(button.dataset.created || ''); $('#detailDescription').text(button.dataset.description || '');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editDetailModal')).show();
    });
});
</script>
<?php require_once 'footer.php'; ?>
