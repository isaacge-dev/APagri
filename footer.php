    <!-- End .content-area -->
    </div>
</main><!-- End #mainContent -->

<!-- ========== TOAST CONTAINER ========== -->
<div class="toast-container" id="toastContainer"></div>

<script>
/* ========== MODAL STACKING FIX ========== */
// Move all modals directly to <body> on DOM ready and when shown so they are never trapped in parent stacking contexts
$(function () {
    $('.modal').appendTo('body');
});
$(document).on('show.bs.modal', '.modal', function () {
    if ($(this).parent()[0] !== document.body) {
        $(this).appendTo('body');
    }
});
/* ========== SIDEBAR TOGGLE ========== */
const sidebar     = document.getElementById('sidebar');
const topbar      = document.getElementById('topbar');
const mainContent = document.getElementById('mainContent');
const overlay     = document.getElementById('sidebarOverlay');

function toggleSidebar() {
    const isMobile = window.innerWidth < 992;
    if (isMobile) {
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('show');
    } else {
        sidebar.classList.toggle('collapsed');
        topbar.classList.toggle('expanded');
        mainContent.classList.toggle('expanded');
    }
}

function closeSidebarMobile() {
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('show');
}

/* ========== LIVE CLOCK ========== */
function updateClock() {
    const now = new Date();
    document.getElementById('liveClock').textContent =
        now.toLocaleTimeString('en-PH', { hour12: true, hour: '2-digit', minute: '2-digit' });
}
updateClock();
setInterval(updateClock, 1000);

/* ========== TOAST HELPER ========== */
/**
 * showToast('Message', 'success'|'danger'|'warning'|'info')
 */
function showToast(message, type = 'success') {
    const icons = { success: 'bi-check-circle-fill', danger: 'bi-x-circle-fill',
                    warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    const id = 'toast_' + Date.now();
    const html = `
        <div id="${id}" class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="bi ${icons[type] ?? 'bi-info-circle-fill'}"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`;
    document.getElementById('toastContainer').insertAdjacentHTML('beforeend', html);
    const toastEl = document.getElementById(id);
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

/* ========== PRINT / SAVE AS PDF ========== */
let printTableInstance = null;
let printOriginalPageLength = null;
function printDataReport(moduleTitle, tableSelector) {
    const title = document.getElementById('printModuleTitle');
    const exported = document.getElementById('printExportedAt');
    const summary = document.getElementById('printFilterSummary');
    title.textContent = moduleTitle;
    exported.textContent = new Date().toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });

    const filters = [];
    document.querySelectorAll('.report-filters [data-print-filter-label]').forEach((control) => {
        if (control.value) {
            const value = control.tagName === 'SELECT' ? control.options[control.selectedIndex].textContent.trim() : control.value;
            filters.push(control.dataset.printFilterLabel + ': ' + value);
        }
    });
    const search = document.querySelector('.dataTables_filter input');
    if (search && search.value.trim()) filters.push('Search: ' + search.value.trim());
    summary.textContent = filters.length ? 'Filters: ' + filters.join(' • ') : 'Filters: None';
    summary.hidden = false;

    printTableInstance = null;
    printOriginalPageLength = null;
    if (tableSelector && window.jQuery && $.fn.dataTable && $.fn.dataTable.isDataTable(tableSelector)) {
        printTableInstance = $(tableSelector).DataTable();
        printOriginalPageLength = printTableInstance.page.len();
        printTableInstance.page.len(-1).draw();
    }
    window.setTimeout(() => window.print(), 80);
}

window.addEventListener('afterprint', () => {
    const summary = document.getElementById('printFilterSummary');
    if (summary) { summary.hidden = true; summary.textContent = ''; }
    if (printTableInstance && printOriginalPageLength !== null) {
        printTableInstance.page.len(printOriginalPageLength).draw();
    }
    printTableInstance = null;
    printOriginalPageLength = null;
});

/* ========== DELETE CONFIRM HELPER ========== */
function confirmDelete(formId, itemName) {
    if (confirm(`⚠️ Are you sure you want to delete "${itemName}"?\n\nThis action cannot be undone.`)) {
        document.getElementById(formId).submit();
    }
}
</script>

<?php
// Flash message → toast bridge
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['flash'])):
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    showToast(<?= json_encode(htmlspecialchars($flash['msg'])) ?>, <?= json_encode($flash['type']) ?>);
});
</script>
<?php endif; ?>

</body>
</html>
