<style nonce="<?= csp_nonce() ?>">
    /* Common Responsive Styles for Admin Backoffice */

    /* Main Content Layout */
    .main-content {
        margin-left: 260px;
        padding: 15px 20px;
        transition: margin-left 0.3s ease;
    }

    /* Responsive Main Content */
    @media (max-width: 1024px) {
        .main-content {
            margin-left: 0;
            padding: 15px;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 10px;
        }
    }

    /* Responsive Tables */
    @media (max-width: 1024px) {
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -15px;
            padding: 0 15px;
        }

        table {
            min-width: 800px;
        }
    }

    @media (max-width: 768px) {
        .table-container {
            margin: 0 -10px;
            padding: 0 10px;
        }

        table {
            font-size: 13px;
        }

        table th,
        table td {
            padding: 8px 6px !important;
        }

        /* Hide less important columns on mobile */
        .hide-mobile {
            display: none !important;
        }
    }

    /* Responsive Filters */
    @media (max-width: 768px) {
        .filters-row {
            grid-template-columns: 1fr !important;
            gap: 10px !important;
        }

        .filter-group {
            margin-bottom: 0;
        }
    }

    /* Responsive Buttons */
    @media (max-width: 768px) {
        .header-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .header-actions .btn {
            width: 100%;
            text-align: center;
        }

        .actions {
            flex-direction: column !important;
            gap: 5px !important;
        }

        .actions .btn {
            width: 100%;
            padding: 8px 12px;
        }
    }

    /* Responsive Stats Grid */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 10px !important;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr !important;
        }
    }

    /* Responsive Forms */
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr !important;
        }

        .form-row {
            flex-direction: column;
        }

        .form-group {
            margin-bottom: 15px;
        }
    }

    /* Responsive Cards */
    @media (max-width: 768px) {
        .card {
            padding: 12px !important;
            margin-bottom: 12px !important;
        }

        .card-header {
            font-size: 14px !important;
        }
    }

    /* Responsive Bulk Actions */
    @media (max-width: 768px) {
        .bulk-actions-bar {
            flex-direction: column;
            align-items: stretch;
        }

        .bulk-actions-bar select,
        .bulk-actions-bar .btn {
            width: 100%;
        }
    }

    /* Utility classes for responsive behavior */
    .mobile-only {
        display: none;
    }

    @media (max-width: 768px) {
        .mobile-only {
            display: block;
        }

        .desktop-only {
            display: none;
        }
    }

    /* Improved touch targets for mobile */
    @media (max-width: 768px) {
        .btn,
        button,
        a.btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    }

    /* Better spacing for mobile forms */
    @media (max-width: 768px) {
        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="password"],
        select,
        textarea {
            font-size: 16px; /* Prevents zoom on iOS */
            padding: 10px 12px;
        }
    }
</style>
