<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Status</title>

    <?php include "../Admin Section/includes/head.php"; ?>

    <link rel="stylesheet" href="../Admin Section/assets/css/admin-productRegisterNew.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../Admin Section/assets/css/admin-reservationStatus.css?v=<?php echo time(); ?>">
</head>

<body>

    <div class="body-container">
        <?php include "../Admin Section/includes/sidebar.php"; ?>

        <div class="main-content-container">
            <div class="navbar">
                <h5 class="title-page">Reservation Status</h5>
            </div>

            <div class="main-content">
                <div class="content-container">
                    <!-- Header with refresh controls -->
                    <div class="page-header">
                        <div class="last-updated">
                            Last updated: <span id="lastUpdatedTime">-</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div class="auto-refresh-toggle">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="autoRefreshToggle" checked>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span>Auto-refresh (30s)</span>
                            </div>
                            <button class="refresh-btn" id="refreshBtn" onclick="fetchData()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M23 4v6h-6M1 20v-6h6"/>
                                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                                </svg>
                                Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="stats-cards">
                        <div class="stat-card pending">
                            <div class="icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12,6 12,12 16,14"/>
                                </svg>
                            </div>
                            <div class="info">
                                <h3 id="pendingCount">0</h3>
                                <p>Pending Reservations</p>
                            </div>
                        </div>

                        <div class="stat-card pending-update">
                            <div class="icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </div>
                            <div class="info">
                                <h3 id="pendingUpdateCount">0</h3>
                                <p>Pending Update</p>
                            </div>
                        </div>
                    </div>

                    <!-- Tables -->
                    <div class="tables-container">
                        <!-- Pending List -->
                        <div class="table-section">
                            <div class="table-header pending">
                                <h4>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12,6 12,12 16,14"/>
                                    </svg>
                                    Pending Reservations
                                    <span class="badge" id="pendingBadge">0</span>
                                </h4>
                            </div>
                            <div class="table-wrapper">
                                <table class="reservation-table">
                                    <thead>
                                        <tr>
                                            <th>Booking ID</th>
                                            <th>Package</th>
                                            <th>Departure</th>
                                            <th>Amount</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pendingTableBody">
                                        <tr>
                                            <td colspan="5">
                                                <div class="empty-state">
                                                    <p>Loading...</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pending Update List -->
                        <div class="table-section">
                            <div class="table-header pending-update">
                                <h4>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                    Pending Update
                                    <span class="badge" id="pendingUpdateBadge">0</span>
                                </h4>
                            </div>
                            <div class="table-wrapper">
                                <table class="reservation-table">
                                    <thead>
                                        <tr>
                                            <th>Booking ID</th>
                                            <th>Package</th>
                                            <th>Departure</th>
                                            <th>Amount</th>
                                            <th>Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pendingUpdateTableBody">
                                        <tr>
                                            <td colspan="5">
                                                <div class="empty-state">
                                                    <p>Loading...</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require "../Agent Section/includes/scripts.php"; ?>

    <script>
        // Configuration
        const REFRESH_INTERVAL = 30000; // 30 seconds
        let autoRefreshInterval = null;
        let isRefreshing = false;

        // Format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount);
        }

        // Format date
        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        }

        // Format datetime
        function formatDateTime(dateTimeStr) {
            if (!dateTimeStr) return '-';
            const date = new Date(dateTimeStr);
            return date.toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Render table rows
        function renderTableRows(data, type) {
            if (!data || data.length === 0) {
                return `
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                                    <rect x="9" y="3" width="6" height="4" rx="1"/>
                                </svg>
                                <p>No ${type === 'pending' ? 'pending' : 'pending update'} reservations</p>
                            </div>
                        </td>
                    </tr>
                `;
            }

            return data.map(item => {
                const timeField = type === 'pending' ? item.createdAt : item.updatedAt;
                return `
                    <tr onclick="viewBooking('${item.bookingId}')">
                        <td class="booking-id">${item.bookingId}</td>
                        <td class="package-name" title="${item.packageName || '-'}">${item.packageName || '-'}</td>
                        <td class="date">${formatDate(item.departureDate)}</td>
                        <td class="amount">${formatCurrency(item.totalAmount)}</td>
                        <td class="date">${formatDateTime(timeField)}</td>
                    </tr>
                `;
            }).join('');
        }

        // Fetch data from API
        async function fetchData() {
            if (isRefreshing) return;

            const refreshBtn = document.getElementById('refreshBtn');
            isRefreshing = true;
            refreshBtn.classList.add('spinning');

            try {
                const response = await fetch('api/reservation-status-api.php');
                const result = await response.json();

                if (result.success) {
                    // Update counts
                    document.getElementById('pendingCount').textContent = result.data.pending.count;
                    document.getElementById('pendingUpdateCount').textContent = result.data.pendingUpdate.count;
                    document.getElementById('pendingBadge').textContent = result.data.pending.count;
                    document.getElementById('pendingUpdateBadge').textContent = result.data.pendingUpdate.count;

                    // Update tables
                    document.getElementById('pendingTableBody').innerHTML =
                        renderTableRows(result.data.pending.list, 'pending');
                    document.getElementById('pendingUpdateTableBody').innerHTML =
                        renderTableRows(result.data.pendingUpdate.list, 'pendingUpdate');

                    // Update timestamp
                    document.getElementById('lastUpdatedTime').textContent = result.timestamp;
                } else {
                    console.error('API Error:', result.error);
                }
            } catch (error) {
                console.error('Fetch Error:', error);
            } finally {
                isRefreshing = false;
                refreshBtn.classList.remove('spinning');
            }
        }

        // View booking details (placeholder for future implementation)
        function viewBooking(bookingId) {
            // TODO: Navigate to booking detail page
            console.log('View booking:', bookingId);
            // window.location.href = `admin-bookingDetail.php?id=${bookingId}`;
        }

        // Toggle auto-refresh
        function toggleAutoRefresh(enabled) {
            if (enabled) {
                autoRefreshInterval = setInterval(fetchData, REFRESH_INTERVAL);
            } else {
                if (autoRefreshInterval) {
                    clearInterval(autoRefreshInterval);
                    autoRefreshInterval = null;
                }
            }
        }

        // Handle visibility change (pause when tab is hidden)
        document.addEventListener('visibilitychange', function() {
            const autoRefreshToggle = document.getElementById('autoRefreshToggle');
            if (document.hidden) {
                if (autoRefreshInterval) {
                    clearInterval(autoRefreshInterval);
                    autoRefreshInterval = null;
                }
            } else {
                if (autoRefreshToggle.checked) {
                    fetchData(); // Refresh immediately when tab becomes visible
                    toggleAutoRefresh(true);
                }
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Initial fetch
            fetchData();

            // Setup auto-refresh toggle
            const autoRefreshToggle = document.getElementById('autoRefreshToggle');
            autoRefreshToggle.addEventListener('change', function() {
                toggleAutoRefresh(this.checked);
            });

            // Start auto-refresh
            toggleAutoRefresh(true);
        });
    </script>

</body>

</html>
