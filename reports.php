<?php
/**
 * reports.php — Reports & Agricultural Analytics
 */
require_once 'auth.php';
require_once 'db.php';

// KPI queries
$kpi = $pdo->query("
    SELECT
        COALESCE(SUM(quantity_kg), 0) AS qty,
        COALESCE(SUM(quantity_kg * selling_price_per_kg), 0) AS revenue,
        COALESCE(AVG(selling_price_per_kg), 0) AS avg_price
    FROM public.harvest
    WHERE deleted_at IS NULL
")->fetch(PDO::FETCH_ASSOC);

$area = (float)$pdo->query("
    SELECT COALESCE(SUM(p.area_planted), 0)
    FROM public.planting p
    JOIN public.planting_status_lookup st ON p.status_id = st.status_id
    WHERE p.deleted_at IS NULL AND st.status_code IN ('growing', 'planted')
")->fetchColumn();

$crops = $pdo->query("
    SELECT
        ccat.category_name AS crop_category,
        COALESCE(SUM(h.quantity_kg), 0) AS qty,
        COALESCE(SUM(h.quantity_kg * h.selling_price_per_kg), 0) AS revenue
    FROM public.crop_category_lookup ccat
    LEFT JOIN public.crop c ON c.category_id = ccat.category_id AND c.deleted_at IS NULL
    LEFT JOIN public.planting p ON p.crop_id = c.crop_id AND p.deleted_at IS NULL
    LEFT JOIN public.harvest h ON h.planting_id = p.planting_id AND h.deleted_at IS NULL
    GROUP BY ccat.category_id, ccat.category_name
    ORDER BY ccat.category_id
")->fetchAll(PDO::FETCH_ASSOC);

$monthly = $pdo->query("
    SELECT
        TO_CHAR(date_trunc('month', harvest_date), 'YYYY-MM') AS harvest_month,
        SUM(quantity_kg) AS qty,
        SUM(quantity_kg * selling_price_per_kg) AS revenue
    FROM public.harvest
    WHERE deleted_at IS NULL
    GROUP BY 1
    ORDER BY 1
")->fetchAll(PDO::FETCH_ASSOC);

$statuses = $pdo->query("
    SELECT st.status_label AS status, COUNT(p.planting_id) AS total
    FROM public.planting_status_lookup st
    LEFT JOIN public.planting p ON p.status_id = st.status_id AND p.deleted_at IS NULL
    GROUP BY st.status_id, st.status_label
    ORDER BY st.status_id
")->fetchAll(PDO::FETCH_ASSOC);

$scatter = $pdo->query("
    SELECT p.area_planted, h.quantity_kg
    FROM public.harvest h
    JOIN public.planting p ON p.planting_id = h.planting_id
    WHERE h.deleted_at IS NULL AND p.deleted_at IS NULL
")->fetchAll(PDO::FETCH_ASSOC);

$pipeline = [];
foreach (['farmer', 'farm', 'crop', 'planting', 'harvest'] as $t) {
    $pipeline[$t] = (int)$pdo->query("SELECT COUNT(*) FROM public.{$t} WHERE deleted_at IS NULL")->fetchColumn();
}

$pageTitle = 'Reports & Analytics';
require_once 'header.php';
require_once 'sidebar.php';
?>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-bold" style="color: var(--green-dark);">
                <i class="bi bi-bar-chart-line-fill me-2"></i>Reports & Analytics
            </h4>
            <div class="text-muted small">Comprehensive metrics, yields, and harvest valuation</div>
        </div>
    </div>

    <div class="d-flex justify-content-start mb-3 no-print">
        <button type="button" class="btn btn-outline-green" onclick="printDataReport('Reports & Analytics Report', null)">
            <i class="bi bi-printer me-1"></i> Print / Export PDF
        </button>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3" style="background: var(--green-pale); color: var(--green-dark);">
                        <i class="bi bi-basket3-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Total Harvested</div>
                        <div class="fs-4 fw-bold" style="color: var(--green-darkest);"><?= number_format((float)$kpi['qty'], 1) ?> kg</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3" style="background: var(--green-pale); color: var(--green-dark);">
                        <i class="bi bi-cash-stack fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Est. Harvest Value</div>
                        <div class="fs-4 fw-bold" style="color: var(--green-darkest);">₱<?= number_format((float)$kpi['revenue'], 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3" style="background: var(--green-pale); color: var(--green-dark);">
                        <i class="bi bi-bounding-box-circles fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Active Growing Area</div>
                        <div class="fs-4 fw-bold" style="color: var(--green-darkest);"><?= number_format($area, 2) ?> ha</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3" style="background: var(--green-pale); color: var(--green-dark);">
                        <i class="bi bi-tag-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Avg. Price / kg</div>
                        <div class="fs-4 fw-bold" style="color: var(--green-darkest);">₱<?= number_format((float)$kpi['avg_price'], 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-3 h-100">
                <h6 class="fw-bold mb-3" style="color: var(--green-darkest);">
                    <i class="bi bi-bar-chart-fill me-2"></i>Yield & Revenue by Crop Category
                </h6>
                <div style="position: relative; height: 280px;">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm p-3 h-100">
                <h6 class="fw-bold mb-3" style="color: var(--green-darkest);">
                    <i class="bi bi-graph-up me-2"></i>Monthly Harvest Trend
                </h6>
                <div style="position: relative; height: 280px;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm p-3 h-100">
                <h6 class="fw-bold mb-3" style="color: var(--green-darkest);">
                    <i class="bi bi-pie-chart-fill me-2"></i>Plantings by Status
                </h6>
                <div style="position: relative; height: 260px;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm p-3 h-100">
                <h6 class="fw-bold mb-3" style="color: var(--green-darkest);">
                    <i class="bi bi-distribute-vertical me-2"></i>Planted Area vs Harvested Quantity
                </h6>
                <div style="position: relative; height: 260px;">
                    <canvas id="scatterChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Pipeline Diagram -->
    <div class="card border-0 shadow-sm p-3">
        <h6 class="fw-bold mb-2" style="color: var(--green-darkest);">
            <i class="bi bi-diagram-3-fill me-2"></i>Agricultural Data Pipeline
        </h6>
        <div class="mermaid text-center py-2">
flowchart LR
    Farmers["Farmers: <?= $pipeline['farmer'] ?>"] --> Farms["Farms: <?= $pipeline['farm'] ?>"]
    Farms --> Planting["Planting: <?= $pipeline['planting'] ?>"]
    Crops["Crops: <?= $pipeline['crop'] ?>"] --> Planting
    Planting --> Harvest["Harvest: <?= $pipeline['harvest'] ?>"]
    Harvest --> Value["Market Valuation"]
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
<script>
mermaid.initialize({ startOnLoad: true, theme: 'default' });

const cats = <?= json_encode($crops) ?>;
const months = <?= json_encode($monthly) ?>;
const statuses = <?= json_encode($statuses) ?>;
const points = <?= json_encode($scatter) ?>;

// Category Chart
new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: cats.map(x => x.crop_category || 'Uncategorized'),
        datasets: [
            { label: 'Yield (kg)', data: cats.map(x => x.qty), backgroundColor: '#40916c' },
            { label: 'Revenue (₱)', data: cats.map(x => x.revenue), backgroundColor: '#95d5b2' }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// Trend Chart
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: months.map(x => x.harvest_month),
        datasets: [
            { label: 'Quantity (kg)', data: months.map(x => x.qty), borderColor: '#2d6a4f', tension: 0.3, fill: false },
            { label: 'Revenue (₱)', data: months.map(x => x.revenue), borderColor: '#74c69d', tension: 0.3, fill: false }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// Status Chart
new Chart(document.getElementById('statusChart'), {
    type: 'pie',
    data: {
        labels: statuses.map(x => x.status),
        datasets: [{
            data: statuses.map(x => x.total),
            backgroundColor: ['#40916c', '#74c69d', '#b7e4c7', '#1b4332', '#d8f3dc']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// Scatter Chart
new Chart(document.getElementById('scatterChart'), {
    type: 'scatter',
    data: {
        datasets: [{
            label: 'Planting Area (ha) vs Yield (kg)',
            data: points.map(x => ({ x: Number(x.area_planted), y: Number(x.quantity_kg) })),
            backgroundColor: '#40916c'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { title: { display: true, text: 'Area Planted (ha)' } },
            y: { title: { display: true, text: 'Harvested Qty (kg)' } }
        }
    }
});
</script>

<?php require_once 'footer.php'; ?>
