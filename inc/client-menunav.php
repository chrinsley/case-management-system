<?php
// inc/client-menunav.php — Client portal sidebar (match admin legalpro-nav icon style)
// Usage from pages/: include __DIR__ . '/../inc/client-menunav.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$clientMenuItems = [
    ['title' => 'Dashboard', 'url' => 'client-dashboard.php', 'icon' => 'ni ni-tv-2', 'id' => 'client-dashboard'],
    ['title' => 'My Cases', 'url' => 'client-cases.php', 'icon' => 'ni ni-folder-17', 'id' => 'client-cases'],
    ['title' => 'Appointments', 'url' => 'client-appointments.php', 'icon' => 'ni ni-calendar-grid-58', 'id' => 'client-appointments'],
    ['title' => 'Court Tracking', 'url' => 'client-court-tracking.php', 'icon' => 'ni ni-collection', 'id' => 'client-court-tracking'],
    ['title' => 'Payments', 'url' => 'client-payments.php', 'icon' => 'ni ni-credit-card', 'id' => 'client-payments'],
];

function clientNavIsActive($itemId, $currentPage)
{
    if ($itemId === 'client-cases' && in_array($currentPage, ['client-cases', 'client-case-view'], true)) {
        return true;
    }
    return $itemId === $currentPage;
}

$clientDisplayName = isset($_SESSION['client_name']) ? (string) $_SESSION['client_name'] : 'Client';
?>

<link href="../assets/css/legalpro-client-portal.css?v=3" rel="stylesheet" />
<style>
    .legalpro-client-nav .nav-link {
        display: flex;
        align-items: center;
        padding: 0.75rem 1.25rem;
        margin: 0.15rem 0.75rem;
        border-radius: 12px;
        font-size: 0.95rem;
        font-weight: 600;
        color: #344767;
        transition: all 0.15s ease;
    }
    .legalpro-client-nav .nav-link .legalpro-client-nav-icon {
        min-width: 36px;
        width: 36px;
        height: 36px;
        background-color: rgba(94, 114, 228, 0.08);
        color: #5e72e4;
        transition: all 0.15s ease;
    }
    .legalpro-client-nav .nav-link .legalpro-client-nav-icon i {
        color: inherit !important;
        opacity: 0.9;
    }
    .legalpro-client-nav .nav-link.active,
    .legalpro-client-nav .nav-link:hover {
        background: linear-gradient(135deg, #5e72e4, #825ee4);
        color: #fff;
        box-shadow: 0 10px 20px rgba(94, 114, 228, 0.25);
    }
    .legalpro-client-nav .nav-link.active .legalpro-client-nav-icon,
    .legalpro-client-nav .nav-link:hover .legalpro-client-nav-icon {
        background-color: rgba(255, 255, 255, 0.2);
        color: #fff !important;
    }
    .legalpro-client-nav .nav-link.active .legalpro-client-nav-icon i,
    .legalpro-client-nav .nav-link:hover .legalpro-client-nav-icon i {
        color: #fff !important;
        opacity: 1 !important;
    }
    .legalpro-client-nav .nav-link.active .legalpro-client-nav-text,
    .legalpro-client-nav .nav-link:hover .legalpro-client-nav-text {
        color: #fff !important;
    }
    .legalpro-client-nav .nav-scroll {
        flex: 1;
        overflow-y: auto;
        padding-right: 0.25rem;
        margin-right: -0.25rem;
    }
    .legalpro-client-nav .nav-scroll::-webkit-scrollbar {
        width: 4px;
    }
    .legalpro-client-nav .nav-scroll::-webkit-scrollbar-thumb {
        background: rgba(52, 71, 103, 0.3);
        border-radius: 4px;
    }
</style>

<aside class="sidenav bg-white navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl fixed-start ms-4 legalpro-client-nav" id="sidenav-main" style="height: calc(100vh - 2rem); top: 1rem; display: flex; flex-direction: column; overflow: hidden;">
    <div class="sidenav-header" style="flex-shrink: 0; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: center; position: relative; width: 100%;">
        <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
        <a class="navbar-brand m-0" href="client-dashboard.php" style="display: flex; align-items: center; justify-content: center; margin: 0 auto; width: 100%;">
            <img src="../assets/img/logo-ct-dark.png" width="26" height="26" class="navbar-brand-img" alt="LegalPro logo">
            <span class="ms-2 font-weight-bold" style="font-size: 0.875rem;">LegalPro</span>
        </a>
    </div>
    <hr class="horizontal dark mt-0 mb-0" style="flex-shrink: 0; margin: 0;">
    <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
        <div class="nav-scroll">
            <ul class="navbar-nav" style="flex: 1; display: flex; flex-direction: column; justify-content: flex-start; padding: 0.25rem 0 0.5rem; margin: 0;">
                <?php foreach ($clientMenuItems as $item): ?>
                    <li class="nav-item" style="flex-shrink: 0; margin: 0;">
                        <a class="nav-link <?php echo clientNavIsActive($item['id'], $currentPage) ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($item['url']); ?>">
                            <div class="d-flex align-items-center">
                                <div class="icon icon-shape border-radius-md text-center me-2 d-flex align-items-center justify-content-center legalpro-client-nav-icon">
                                    <i class="<?php echo htmlspecialchars($item['icon']); ?> text-xs opacity-10"></i>
                                </div>
                                <span class="nav-link-text legalpro-client-nav-text" style="font-size: 0.95rem;"><?php echo htmlspecialchars($item['title']); ?></span>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="sidenav-footer" style="flex-shrink: 0; padding: 0.75rem 1rem; border-top: 1px solid rgba(0,0,0,0.1);">
        <div class="text-center">
            <p class="text-xs text-muted mb-1" style="font-size: 0.7rem;">Logged in as</p>
            <p class="text-sm font-weight-bold mb-2"><?php echo htmlspecialchars($clientDisplayName); ?></p>
            <a href="client-logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
        </div>
    </div>
</aside>
