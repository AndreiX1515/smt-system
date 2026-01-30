<aside class="admin-sidebar">
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <!-- Dashboard (서브메뉴) -->
            <li class="nav-item">
                <a href="#" class="nav-link has-submenu" onclick="toggleSubmenu(event, 'dashboard-submenu')">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="2" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                        <rect x="11" y="2" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                        <rect x="2" y="11" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                        <rect x="11" y="11" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    <span>Dashboard</span>
                    <svg class="chevron" width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </a>
                <ul class="submenu open" id="dashboard-submenu">
                    <li class="submenu-item">
                        <a href="admin-dashboard.php" class="submenu-link">Overview</a>
                    </li>
                    <li class="submenu-item">
                        <a href="admin-reservationStatus.php" class="submenu-link">Reservation Status</a>
                    </li>
                </ul>
            </li>

            <!--  -->
            <li class="nav-item">
                <a href="admin-manageUsers.php" class="nav-link">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="10" cy="6" r="3" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M4 17C4 14.2386 6.68629 12 10 12C13.3137 12 16 14.2386 16 17" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    <span></span>
                </a>
            </li>

            <!--   -->
            <li class="nav-item">
                <a href="admin-matching.php" class="nav-link">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 10H17M10 3V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span> </span>
                </a>
            </li>

            <!--   -->
            <li class="nav-item">
                <a href="admin-delivery.php" class="nav-link">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="5" width="12" height="10" rx="1" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M14 9H16L18 11V15H14V9Z" stroke="currentColor" stroke-width="1.5"/>
                        <circle cx="5.5" cy="15" r="1.5" stroke="currentColor" stroke-width="1.5"/>
                        <circle cx="15.5" cy="15" r="1.5" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    <span> </span>
                </a>
            </li>

            <!--   ( ) -->
            <li class="nav-item">
                <a href="#" class="nav-link has-submenu active" onclick="toggleSubmenu(event, 'product-submenu')">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="3" width="14" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M7 3V17M13 3V17M3 10H17" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    <span> </span>
                    <svg class="chevron" width="12" height="12" viewBox="0 0 12 12" fill="none">
                        <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </a>
                <ul class="submenu open" id="product-submenu">
                    <li class="submenu-item">
                        <a href="admin-manageProducts.php" class="submenu-link"> </a>
                    </li>
                    <li class="submenu-item active">
                        <a href="admin-productRegisterNew.php" class="submenu-link"> </a>
                    </li>
                    <li class="submenu-item">
                        <a href="admin-productCategories.php" class="submenu-link"> </a>
                    </li>
                </ul>
            </li>

            <!--    -->
            <li class="nav-item">
                <a href="admin-analytics.php" class="nav-link">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 15V10M10 15V5M17 15V8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span>  </span>
                </a>
            </li>

            <!--   -->
            <li class="nav-item">
                <a href="admin-inquiries.php" class="nav-link">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 5C3 3.89543 3.89543 3 5 3H15C16.1046 3 17 3.89543 17 5V12C17 13.1046 16.1046 14 15 14H7L3 17V5Z" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    <span> </span>
                </a>
            </li>

            <!--    -->
            <li class="nav-item">
                <a href="admin-visa.php" class="nav-link">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="2" y="4" width="16" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M6 8H14M6 12H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span>  </span>
                </a>
            </li>

            <!--   -->
            <li class="nav-item">
                <a href="admin-settings.php" class="nav-link">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="10" cy="10" r="3" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M10 2V4M10 16V18M18 10H16M4 10H2M15.5 4.5L14 6M6 14L4.5 15.5M15.5 15.5L14 14M6 6L4.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span> </span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<script>
function toggleSubmenu(event, submenuId) {
    event.preventDefault();
    const submenu = document.getElementById(submenuId);
    const link = event.currentTarget;
    const chevron = link.querySelector('.chevron');

    if (submenu.classList.contains('open')) {
        submenu.classList.remove('open');
        chevron.style.transform = 'rotate(0deg)';
    } else {
        submenu.classList.add('open');
        chevron.style.transform = 'rotate(180deg)';
    }
}

// Initialize: Set active submenu chevron
document.addEventListener('DOMContentLoaded', function() {
    const activeLink = document.querySelector('.nav-link.active');
    if (activeLink && activeLink.classList.contains('has-submenu')) {
        const chevron = activeLink.querySelector('.chevron');
        if (chevron) {
            chevron.style.transform = 'rotate(180deg)';
        }
    }
});
</script>
