<?php
/**
 * header.php — Top navbar + HTML boilerplate
 * Expects: $pageTitle (string) set before including this file.
 */
if (!isset($pageTitle)) $pageTitle = 'APagri';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — APagri</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

    <!-- Core Scripts loaded in head so page-level scripts and DataTables work immediately -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

    <style>
        /* ========== DESIGN TOKENS ========== */
        :root {
            --green-darkest: #1b4332;
            --green-dark:    #2d6a4f;
            --green-mid:     #40916c;
            --green-light:   #74c69d;
            --green-xlight:  #b7e4c7;
            --green-pale:    #d8f3dc;
            --green-muted:   #e9f5ec;
            --sidebar-w:     260px;
            --topbar-h:      60px;
            --bg-body:       #f4f7f4;
            --text-dark:     #1a2e1a;
            --text-muted:    #6c757d;
            --border-color:  #dee2e6;
            --card-shadow:   0 2px 12px rgba(45,106,79,.10);
        }

        /* ========== GLOBAL ========== */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg-body);
            color: var(--text-dark);
            margin: 0;
            overflow-x: hidden;
        }

        /* ========== SIDEBAR ========== */
        #sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: linear-gradient(180deg, var(--green-darkest) 0%, var(--green-dark) 100%);
            display: flex;
            flex-direction: column;
            z-index: 1040;
            transition: transform .3s ease;
            overflow-y: auto;
            overflow-x: hidden;
        }
        #sidebar.collapsed { transform: translateX(calc(-1 * var(--sidebar-w))); }

        /* Brand */
        .sidebar-brand {
            padding: 1.2rem 1.4rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            border-bottom: 1px solid rgba(255,255,255,.1);
            text-decoration: none;
        }
        .sidebar-brand .brand-icon {
            width: 44px; height: 44px;
            background: #fff;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,.18);
        }
        .sidebar-brand .brand-icon i { color: var(--green-darkest); font-size: 1.3rem; }
        .sidebar-brand .brand-text   { color: #fff; font-weight: 700; font-size: 1.1rem; line-height: 1.1; }
        .sidebar-brand .brand-sub    { color: var(--green-xlight); font-size: .7rem; font-weight: 400; }

        /* Nav sections */
        .sidebar-section-label {
            padding: .9rem 1.4rem .3rem;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--green-xlight);
            opacity: .7;
        }
        .sidebar-nav { list-style: none; padding: 0 .75rem; margin: 0; }
        .sidebar-nav li { margin-bottom: 2px; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .6rem .85rem;
            border-radius: 8px;
            color: rgba(255,255,255,.8);
            text-decoration: none;
            font-size: .88rem;
            font-weight: 500;
            transition: background .18s, color .18s;
        }
        .sidebar-nav a i { font-size: 1.05rem; flex-shrink: 0; }
        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255,255,255,.12);
            color: #fff;
        }
        .sidebar-nav a.active { background: var(--green-mid); font-weight: 600; }

        /* Sidebar footer */
        .sidebar-footer {
            margin-top: auto;
            padding: 1rem 1.4rem;
            border-top: 1px solid rgba(255,255,255,.1);
        }
        .sidebar-footer .user-pill {
            display: flex; align-items: center; gap: .65rem;
        }
        .user-avatar {
            width: 34px; height: 34px;
            background: var(--green-light);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: var(--green-darkest); font-size: .85rem;
            flex-shrink: 0;
        }
        .user-name  { color: #fff; font-size: .82rem; font-weight: 600; }
        .user-role  { color: var(--green-xlight); font-size: .72rem; }
        .btn-logout {
            margin-top: .6rem;
            display: flex; align-items: center; gap: .5rem;
            color: rgba(255,255,255,.65);
            font-size: .82rem; text-decoration: none;
            padding: .35rem .5rem; border-radius: 6px;
            transition: color .18s, background .18s;
        }
        .btn-logout:hover { color: #fff; background: rgba(255,255,255,.1); }

        /* ========== TOPBAR ========== */
        #topbar {
            position: fixed;
            top: 0;
            left: var(--sidebar-w);
            right: 0;
            height: var(--topbar-h);
            background: #fff;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            gap: 1rem;
            z-index: 1030;
            transition: left .3s ease;
        }
        #topbar.expanded { left: 0; }

        #sidebarToggle {
            border: none; background: none;
            color: var(--text-muted);
            font-size: 1.2rem;
            padding: .3rem .5rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background .15s, color .15s;
        }
        #sidebarToggle:hover { background: var(--green-muted); color: var(--green-dark); }

        .topbar-title {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-dark);
        }
        .topbar-breadcrumb {
            font-size: .8rem;
            color: var(--text-muted);
        }

        /* ========== MAIN CONTENT ========== */
        #mainContent {
            margin-left: var(--sidebar-w);
            padding-top: var(--topbar-h);
            min-height: 100vh;
            transition: margin-left .3s ease;
        }
        #mainContent.expanded { margin-left: 0; }

        .content-area { padding: 1.75rem 2rem; }

        /* ========== CARDS ========== */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        .card-header {
            background: #fff;
            border-bottom: 1px solid var(--green-pale);
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
            color: var(--green-dark);
            padding: .9rem 1.25rem;
        }

        /* ========== STAT CARDS ========== */
        .stat-card {
            border-radius: 14px;
            padding: 1.3rem 1.5rem;
            border: none;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 1.1rem;
            transition: transform .18s, box-shadow .18s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(45,106,79,.16); }
        .stat-icon {
            width: 52px; height: 52px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .stat-value { font-size: 1.75rem; font-weight: 700; line-height: 1; }
        .stat-label { font-size: .8rem; color: var(--text-muted); font-weight: 500; margin-top: 2px; }

        /* Colour variants */
        .stat-green   { background:#fff; }
        .stat-green   .stat-icon { background: var(--green-muted); color: var(--green-dark); }
        .stat-green   .stat-value { color: var(--green-dark); }

        .stat-teal    { background:#fff; }
        .stat-teal    .stat-icon { background: #e0f5ef; color: #0d9e82; }
        .stat-teal    .stat-value { color: #0d9e82; }

        .stat-lime    { background:#fff; }
        .stat-lime    .stat-icon { background: #f0fbea; color: #5a9e2f; }
        .stat-lime    .stat-value { color: #5a9e2f; }

        .stat-olive   { background:#fff; }
        .stat-olive   .stat-icon { background: #f5f5e0; color: #8d8c2b; }
        .stat-olive   .stat-value { color: #8d8c2b; }

        .stat-sage    { background:#fff; }
        .stat-sage    .stat-icon { background: #eaf2ea; color: #4e7c5f; }
        .stat-sage    .stat-value { color: #4e7c5f; }

        /* ========== BUTTONS ========== */
        .btn-primary-green {
            background: var(--green-mid);
            border-color: var(--green-mid);
            color: #fff;
            font-weight: 500;
        }
        .btn-primary-green:hover { background: var(--green-dark); border-color: var(--green-dark); color: #fff; }

        .btn-outline-green {
            border-color: var(--green-mid);
            color: var(--green-mid);
        }
        .btn-outline-green:hover { background: var(--green-mid); color: #fff; }

        /* ========== TABLES ========== */
        .table th {
            background: var(--green-muted);
            color: var(--green-dark);
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            border-top: none;
            border-bottom: 2px solid var(--green-pale);
        }
        .table td { vertical-align: middle; font-size: .88rem; }
        .table-hover tbody tr:hover { background: var(--green-muted); }

        /* Status badges */
        .badge-planned    { background: #e3f2fd; color: #1565c0; }
        .badge-planted    { background: var(--green-muted); color: var(--green-dark); }
        .badge-growing    { background: #f0fbea; color: #3a7d1e; }
        .badge-harvested  { background: #e8f5e9; color: #2e7d32; }
        .badge-failed     { background: #fdecea; color: #c62828; }

        /* ========== PAGE HEADER ========== */
        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .75rem;
            margin-bottom: 1.5rem;
        }
        .page-header-title { font-size: 1.35rem; font-weight: 700; color: var(--text-dark); margin: 0; }
        .page-header-sub   { font-size: .85rem; color: var(--text-muted); margin: 0; }

        /* ========== ALERT TOAST ========== */
        .toast-container { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999; }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 991.98px) {
            #sidebar { transform: translateX(calc(-1 * var(--sidebar-w))); }
            #sidebar.mobile-open { transform: translateX(0); }
            #topbar { left: 0; }
            #mainContent { margin-left: 0; }
            .content-area { padding: 1.25rem 1rem; }
        }

        /* Overlay for mobile sidebar */
        #sidebarOverlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.35);
            z-index: 1035;
        }
        #sidebarOverlay.show { display: block; }

        /* DataTables overrides */
        div.dataTables_wrapper div.dataTables_filter input { border-radius: 8px; border: 1px solid var(--border-color); }
        div.dataTables_wrapper div.dataTables_length select { border-radius: 8px; border: 1px solid var(--border-color); }

        /* Shared operational report printing */
        .print-report-header { display: none; }
        @media print {
            @page { margin: 14mm 12mm; }
            html, body { width: 100% !important; min-height: 0 !important; background: #fff !important; color: #111 !important; }
            #sidebar, #topbar, #sidebarOverlay, .toast-container, .no-print,
            .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate,
            .report-filters, .modal, .btn { display: none !important; }
            #mainContent { width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .content-area, .container-fluid, .main-content { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; }
            .print-report-header { display: block !important; margin: 0 0 14pt !important; padding: 0 0 9pt !important; border-bottom: 2px solid #1b4332 !important; color: #111 !important; }
            .print-report-header h1 { margin: 0 0 3pt !important; font-size: 17pt !important; color: #1b4332 !important; }
            .print-report-header h2 { margin: 0 0 4pt !important; font-size: 13pt !important; color: #222 !important; }
            .print-report-header p { margin: 2pt 0 0 !important; font-size: 9pt !important; color: #333 !important; }
            .card, .card-body, .table-responsive { width: 100% !important; overflow: visible !important; box-shadow: none !important; border: 0 !important; }
            table.print-table { width: 100% !important; border-collapse: collapse !important; color: #111 !important; font-size: 9pt !important; }
            table.print-table th, table.print-table td { padding: 5pt !important; color: #111 !important; background: #fff !important; border: 1px solid #555 !important; }
            table.print-table thead { display: table-header-group; }
            table.print-table tr { break-inside: avoid !important; page-break-inside: avoid !important; }
            table.print-table .print-actions { display: none !important; }
            .badge { color: #111 !important; background: #fff !important; border: 1px solid #888 !important; }
            canvas { max-width: 100% !important; }
        }

        /* ========== PREMIUM STAT-CARD HOVER POLISH ========== */
        .stat-card {
            /* smooth cubic-bezier for a springy feel */
            transition: transform .22s cubic-bezier(.34,1.56,.64,1),
                        box-shadow .22s ease,
                        border-color .22s ease;
            border: 1.5px solid transparent;
            will-change: transform;
            cursor: default;
            position: relative;
            overflow: hidden;
        }

        /* Shimmer sweep pseudo-element */
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: -75%;
            width: 50%; height: 100%;
            background: linear-gradient(
                120deg,
                transparent 0%,
                rgba(255,255,255,.45) 50%,
                transparent 100%
            );
            transform: skewX(-20deg);
            opacity: 0;
            transition: opacity .15s;
            pointer-events: none;
        }

        .stat-card:hover::before {
            opacity: 1;
            animation: shimmer .55s ease forwards;
        }

        @keyframes shimmer {
            from { left: -75%; }
            to   { left: 125%;  }
        }

        /* Lift + coloured border on hover per variant */
        .stat-card:hover {
            transform: translateY(-5px) scale(1.015);
            box-shadow: 0 12px 32px rgba(45,106,79,.20);
        }
        .stat-green:hover  { border-color: var(--green-mid);  }
        .stat-teal:hover   { border-color: #0d9e82;           }
        .stat-lime:hover   { border-color: #5a9e2f;           }
        .stat-olive:hover  { border-color: #8d8c2b;           }
        .stat-sage:hover   { border-color: #4e7c5f;           }

        /* Icon pulse on card hover */
        .stat-card:hover .stat-icon {
            animation: iconPop .3s cubic-bezier(.34,1.56,.64,1) forwards;
        }
        @keyframes iconPop {
            0%   { transform: scale(1);    }
            60%  { transform: scale(1.18); }
            100% { transform: scale(1.08); }
        }

        /* ========== SIDEBAR BRAND LOGO ANIMATION ========== */
        .sidebar-brand .brand-icon svg {
            transition: transform .3s cubic-bezier(.34,1.56,.64,1);
        }
        .sidebar-brand:hover .brand-icon svg {
            transform: rotate(-8deg) scale(1.12);
        }

        /* ========== NAV LINK LEFT INDICATOR ========== */
        .sidebar-nav a {
            position: relative;
        }
        .sidebar-nav a.active::before {
            content: '';
            position: absolute;
            left: 0; top: 20%; bottom: 20%;
            width: 3px;
            border-radius: 0 3px 3px 0;
            background: var(--green-xlight);
            margin-left: -0.85rem;
        }

        /* ========== CONTENT PAGE-ENTER FADE (Pure opacity, no transform so modals never get trapped in a stacking context) ========== */
        .content-area {
            animation: pageFadeIn .2s ease-out;
        }
        @keyframes pageFadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        /* ========== MODAL Z-INDEX FIX ========== */
        .modal {
            z-index: 1060 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }
        .modal-dialog {
            z-index: 1065 !important;
        }

        /* ========== CARD GENERIC HOVER (non-stat cards) ========== */
        .card {
            transition: box-shadow .2s ease, transform .2s ease;
        }
        .card:hover {
            box-shadow: 0 6px 24px rgba(45,106,79,.13);
        }

        /* ========== TABLE ROW TRANSITION ========== */
        .table-hover tbody tr {
            transition: background .15s ease;
        }

        /* ========== BUTTON MICRO-INTERACTIONS ========== */
        .btn-primary-green,
        .btn-outline-green {
            transition: background .18s, border-color .18s, color .18s,
                        transform .15s cubic-bezier(.34,1.56,.64,1),
                        box-shadow .18s;
        }
        .btn-primary-green:active,
        .btn-outline-green:active {
            transform: scale(.95);
        }
        .btn-primary-green:hover {
            box-shadow: 0 4px 14px rgba(64,145,108,.35);
        }
    </style>
</head>
<body>

<div id="printReportHeader" class="print-report-header" aria-hidden="true">
    <h1>APAgri — Agriculture Information System</h1>
    <h2 id="printModuleTitle"></h2>
    <p>Exported: <time id="printExportedAt"></time></p>
    <p id="printFilterSummary" hidden></p>
</div>

<!-- ===== MOBILE OVERLAY ===== -->
<div id="sidebarOverlay" onclick="closeSidebarMobile()"></div>
