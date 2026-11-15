<?php
/**
 * index.php — APagri Dashboard
 */
require_once 'auth.php';
require_once 'db.php';

$pageTitle = 'Dashboard';

/* =====================================================================
   LIVE STATISTICS — PDO queries
   ===================================================================== */

// --- Summary counts ---
function safeCount(PDO $pdo, string $table): int {
    $allowed = ['farmer', 'farm', 'crop', 'planting', 'harvest'];
    if (!in_array($table, $allowed, true)) return 0;
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM public.{$table} WHERE deleted_at IS NULL")->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

$totalFarmers   = safeCount($pdo, 'farmer');
$totalFarms     = safeCount($pdo, 'farm');
$totalCrops     = safeCount($pdo, 'crop');
$totalPlantings = safeCount($pdo, 'planting');
$totalHarvests  = safeCount($pdo, 'harvest');

// --- Total harvested kg ---
try {
    $totalKg = (float) $pdo->query("SELECT COALESCE(SUM(quantity_kg), 0) FROM public.harvest WHERE deleted_at IS NULL")->fetchColumn();
} catch (PDOException $e) {
    $totalKg = 0;
}

// --- Planting status breakdown ---
try {
    $statusRows = $pdo->query("
        SELECT st.status_code AS status, st.status_label, COUNT(*) AS cnt
        FROM public.planting pl
        JOIN public.planting_status_lookup st ON pl.status_id = st.status_id
        WHERE pl.deleted_at IS NULL
        GROUP BY st.status_code, st.status_label
        ORDER BY cnt DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Dashboard status query error: ' . $e->getMessage());
    $statusRows = [];
}

// --- Recent planting records (5) ---
try {
    $recentPlantings = $pdo->query("
        SELECT
            pl.planting_id,
            fm.farm_name,
            cr.crop_name,
            pl.planting_date,
            pl.expected_harvest_date,
            pl.area_planted,
            st.status_code,
            st.status_label
        FROM public.planting pl
        JOIN public.farm fm ON pl.farm_id = fm.farm_id AND fm.deleted_at IS NULL
        JOIN public.crop cr ON pl.crop_id = cr.crop_id AND cr.deleted_at IS NULL
        JOIN public.planting_status_lookup st ON pl.status_id = st.status_id
        WHERE pl.deleted_at IS NULL
        ORDER BY pl.planting_id DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Dashboard recent plantings error: ' . $e->getMessage());
    $recentPlantings = [];
}

// --- Recent harvest records (5) ---
try {
    $recentHarvests = $pdo->query("
        SELECT
            hv.harvest_id,
            fm.farm_name,
            cr.crop_name,
            hv.harvest_date,
            hv.quantity_kg,
            hv.selling_price_per_kg,
            hv.quality_grade,
            hv.remark_id,
            rem.remark_description
        FROM public.harvest hv
        JOIN public.planting pl ON hv.planting_id = pl.planting_id AND pl.deleted_at IS NULL
        JOIN public.farm fm ON pl.farm_id = fm.farm_id AND fm.deleted_at IS NULL
        JOIN public.crop cr ON pl.crop_id = cr.crop_id AND cr.deleted_at IS NULL
        LEFT JOIN public.harvest_remark rem ON hv.remark_id = rem.remark_id
        WHERE hv.deleted_at IS NULL
        ORDER BY hv.harvest_id DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Dashboard recent harvests error: ' . $e->getMessage());
    $recentHarvests = [];
}

// --- Remarks lookup (for display labels) ---
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

/* Status badge helper */
function statusBadge(string $status): string {
    $map = [
        'planned'   => ['badge-planned',   'bi-hourglass',         'Planned'],
        'planted'   => ['badge-planted',   'bi-seed',              'Planted'],
        'growing'   => ['badge-growing',   'bi-tree',              'Growing'],
        'harvested' => ['badge-harvested', 'bi-basket3-fill',      'Harvested'],
        'failed'    => ['badge-failed',    'bi-exclamation-circle','Failed'],
    ];
    $s = strtolower($status);
    [$cls, $ico, $lbl] = $map[$s] ?? ['bg-secondary text-white', 'bi-question', ucfirst($status)];
    return "<span class=\"badge {$cls} d-inline-flex align-items-center gap-1\">
                <i class=\"bi {$ico}\" style=\"font-size:.7rem\"></i>{$lbl}
            </span>";
}

/* Status chart data */
$allStatuses = ['planned','planted','growing','harvested','failed'];
$statusCounts = [];
foreach ($allStatuses as $s) {
    $statusCounts[$s] = 0;
}
foreach ($statusRows as $row) {
    $key = strtolower($row['status']);
    if (isset($statusCounts[$key])) $statusCounts[$key] = (int) $row['cnt'];
}

require_once 'header.php';
require_once 'sidebar.php';
?>

<!-- ========== PAGE HEADER ========== -->
<div class="page-header">
    <div>
        <h1 class="page-header-title"><i class="bi bi-speedometer2 me-2 text-success"></i>Dashboard</h1>
        <p class="page-header-sub">Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'User') ?>. Here's your farm overview.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <span class="badge bg-light border text-dark px-3 py-2">
            <i class="bi bi-calendar3 me-1"></i>
            <?= date('F j, Y') ?>
        </span>
    </div>
</div>

<!-- ========== SUMMARY CARDS ========== -->
<div class="row g-3 mb-4">

    <div class="col-6 col-sm-6 col-md-4 col-xl-2-4">
        <div class="stat-card stat-green h-100">
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalFarmers) ?></div>
                <div class="stat-label">Farmers</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-xl-2-4">
        <div class="stat-card stat-teal h-100">
            <div class="stat-icon"><i class="bi bi-geo-alt-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalFarms) ?></div>
                <div class="stat-label">Farms</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-xl-2-4">
        <div class="stat-card stat-lime h-100">
            <div class="stat-icon"><i class="bi bi-flower2"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalCrops) ?></div>
                <div class="stat-label">Crop Types</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-xl-2-4">
        <div class="stat-card stat-olive h-100">
            <div class="stat-icon"><i class="bi bi-calendar2-week"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalPlantings) ?></div>
                <div class="stat-label">Planting Records</div>
            </div>
        </div>
    </div>

    <div class="col-6 col-sm-6 col-md-4 col-xl-2-4">
        <div class="stat-card stat-sage h-100">
            <div class="stat-icon"><i class="bi bi-basket3-fill"></i></div>
            <div>
                <div class="stat-value"><?= number_format($totalHarvests) ?></div>
                <div class="stat-label">Harvest Records</div>
            </div>
        </div>
    </div>

</div>

<!-- ========== AGRICULTURAL STATS ========== -->
<div class="row g-3 mb-4">

    <!-- Total Harvest KG Card -->
    <div class="col-12 col-md-5 col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-box-seam-fill text-success"></i>
                Total Harvested Quantity
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                <div style="font-size:3rem; font-weight:800; color:var(--green-dark); line-height:1;">
                    <?= number_format($totalKg, 1) ?>
                </div>
                <div class="text-muted mt-1">kilograms harvested</div>
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        Avg per harvest: <strong>
                            <?= $totalHarvests > 0 ? number_format($totalKg / $totalHarvests, 2) : '0.00' ?> kg
                        </strong>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Planting Status Breakdown -->
    <div class="col-12 col-md-7 col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-bar-chart-fill text-success"></i>
                Planting Status Breakdown
            </div>
            <div class="card-body">
                <?php if ($totalPlantings === 0): ?>
                    <p class="text-muted text-center py-3">No planting records yet.</p>
                <?php else: ?>
                    <?php
                    $statusColors = [
                        'planned'   => '#5b8def',
                        'planted'   => '#40916c',
                        'growing'   => '#5a9e2f',
                        'harvested' => '#2e7d32',
                        'failed'    => '#c62828',
                    ];
                    $statusLabels = [
                        'planned'   => 'Planned',
                        'planted'   => 'Planted',
                        'growing'   => 'Growing',
                        'harvested' => 'Harvested',
                        'failed'    => 'Failed',
                    ];
                    foreach ($allStatuses as $s):
                        $count = $statusCounts[$s];
                        $pct   = $totalPlantings > 0 ? round($count / $totalPlantings * 100) : 0;
                        $color = $statusColors[$s];
                        $label = $statusLabels[$s];
                    ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold" style="color:<?= $color ?>"><?= $label ?></span>
                            <span class="small text-muted"><?= $count ?> <span class="text-secondary">(<?= $pct ?>%)</span></span>
                        </div>
                        <div class="progress" style="height:9px; border-radius:99px;">
                            <div class="progress-bar" role="progressbar"
                                 style="width:<?= $pct ?>%; background:<?= $color ?>; border-radius:99px;"
                                 aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Mini chart using Chart.js -->
                    <div class="mt-3 d-flex justify-content-center">
                        <canvas id="statusDoughnut" width="130" height="130"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- ========== MODULE QUICK LINKS ========== -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-grid-3x3-gap-fill text-success"></i>
                Quick Actions
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a href="farmers.php" class="btn btn-sm btn-primary-green">
                        <i class="bi bi-person-plus me-1"></i>Manage Farmers
                    </a>
                    <a href="farms.php" class="btn btn-sm btn-primary-green">
                        <i class="bi bi-map me-1"></i>Manage Farms
                    </a>
                    <a href="crops.php" class="btn btn-sm btn-primary-green">
                        <i class="bi bi-flower2 me-1"></i>Manage Crops
                    </a>
                    <a href="plantings.php" class="btn btn-sm btn-primary-green">
                        <i class="bi bi-calendar2-plus me-1"></i>Log Planting
                    </a>
                    <a href="harvests.php" class="btn btn-sm btn-primary-green">
                        <i class="bi bi-basket3 me-1"></i>Log Harvest
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========== RECENT ACTIVITY TABLES ========== -->
<div class="row g-3">

    <!-- Recent Plantings -->
    <div class="col-12 col-xl-6">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-calendar2-week text-success"></i>
                    Recent Planting Records
                </span>
                <a href="plantings.php" class="btn btn-sm btn-outline-green">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentPlantings)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox" style="font-size:2rem; opacity:.4;"></i>
                        <p class="mt-2 mb-0 small">No planting records found.</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Farm</th>
                                <th>Crop</th>
                                <th>Date Planted</th>
                                <th>Expected Harvest</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPlantings as $row): ?>
                            <tr>
                                <td>
                                    <span class="fw-semibold"><?= htmlspecialchars($row['farm_name']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($row['crop_name']) ?></td>
                                <td>
                                    <?php
                                    $d = $row['planting_date'];
                                    echo $d ? date('M j, Y', strtotime($d)) : '—';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $eh = $row['expected_harvest_date'];
                                    echo $eh ? date('M j, Y', strtotime($eh)) : '—';
                                    ?>
                                </td>
                                <td><?= statusBadge($row['status_code'] ?? ($row['status'] ?? '')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Harvests -->
    <div class="col-12 col-xl-6">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-basket3-fill text-success"></i>
                    Recent Harvest Records
                </span>
                <a href="harvests.php" class="btn btn-sm btn-outline-green">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentHarvests)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox" style="font-size:2rem; opacity:.4;"></i>
                        <p class="mt-2 mb-0 small">No harvest records found.</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Farm</th>
                                <th>Crop</th>
                                <th>Harvest Date</th>
                                <th>Qty (kg)</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentHarvests as $row): ?>
                            <tr>
                                <td>
                                    <span class="fw-semibold"><?= htmlspecialchars($row['farm_name']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($row['crop_name']) ?></td>
                                <td>
                                    <?php
                                    $hd = $row['harvest_date'];
                                    echo $hd ? date('M j, Y', strtotime($hd)) : '—';
                                    ?>
                                </td>
                                <td>
                                    <span class="fw-semibold text-success">
                                        <?= number_format((float)$row['quantity_kg'], 1) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $rKey   = (int)($row['remark_id'] ?? $row['remarks'] ?? 0);
                                    $rLabel = $remarksMap[$rKey] ?? ($row['remark_description'] ?? '—');
                                    $rColors = [
                                        1 => 'bg-success bg-opacity-10 text-success',
                                        2 => 'bg-info bg-opacity-10 text-info',
                                        3 => 'bg-primary bg-opacity-10 text-primary',
                                        4 => 'bg-warning bg-opacity-10 text-warning',
                                        5 => 'bg-danger bg-opacity-10 text-danger',
                                        6 => 'bg-danger bg-opacity-25 text-danger',
                                    ];
                                    $rColor = $rColors[$rKey] ?? 'bg-secondary bg-opacity-10 text-secondary';
                                    ?>
                                    <span class="badge <?= $rColor ?> small"><?= htmlspecialchars($rLabel) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /row -->

<?php require_once 'footer.php'; ?>

<!-- Chart.js for the doughnut -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
<?php if ($totalPlantings > 0): ?>
(function() {
    const ctx = document.getElementById('statusDoughnut');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Planned','Planted','Growing','Harvested','Failed'],
            datasets: [{
                data: [
                    <?= $statusCounts['planned'] ?>,
                    <?= $statusCounts['planted'] ?>,
                    <?= $statusCounts['growing'] ?>,
                    <?= $statusCounts['harvested'] ?>,
                    <?= $statusCounts['failed'] ?>
                ],
                backgroundColor: ['#5b8def','#40916c','#5a9e2f','#2e7d32','#c62828'],
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: { font: { size: 11 }, boxWidth: 12, padding: 8 }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.raw} (${Math.round(ctx.raw/<?= $totalPlantings ?>*100)}%)`
                    }
                }
            }
        }
    });
})();
<?php endif; ?>
</script>

<style>
/* Flexbox 5-col trick for the stat cards */
@media (min-width: 1200px) {
    .col-xl-2-4 { flex: 0 0 20%; max-width: 20%; }
}
</style>
