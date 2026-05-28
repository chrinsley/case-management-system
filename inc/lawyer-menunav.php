<?php
// inc/lawyer-menunav.php — Lawyer portal sidebar (purple icons, matches admin menunav)
// Usage from pages/: ob_start(); include __DIR__ . '/../inc/lawyer-menunav.php'; $navHtml = ob_get_clean();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$lawyerMenuItems = [
    ['title' => 'Dashboard', 'url' => 'lawyer-dashboard.php', 'icon' => 'ni ni-tv-2', 'id' => 'lawyer-dashboard'],
    ['title' => 'My Tasks', 'url' => 'tasks.php', 'icon' => 'ni ni-check-bold', 'id' => 'tasks'],
    ['title' => 'My Cases', 'url' => 'lawyer-cases.php', 'icon' => 'ni ni-folder-17', 'id' => 'lawyer-cases'],
    ['title' => 'My Clients', 'url' => 'lawyer-clients.php', 'icon' => 'ni ni-circle-08', 'id' => 'lawyer-clients'],
    ['title' => 'Appointments', 'url' => 'lawyer-appointments.php', 'icon' => 'ni ni-calendar-grid-58', 'id' => 'lawyer-appointments'],
    ['title' => 'Court Tracking', 'url' => 'lawyer-court-tracking.php', 'icon' => 'ni ni-collection', 'id' => 'lawyer-court-tracking'],
    ['title' => 'My Availability', 'url' => 'lawyer-availability.php', 'icon' => 'ni ni-time-alarm', 'id' => 'lawyer-availability'],
];

function lawyerNavIsActive($itemId, $currentPage)
{
    if ($itemId === 'lawyer-cases' && in_array($currentPage, ['lawyer-cases', 'lawyer-case-view'], true)) {
        return true;
    }
    if ($itemId === 'lawyer-clients' && in_array($currentPage, ['lawyer-clients', 'lawyer-client-view'], true)) {
        return true;
    }
    return $itemId === $currentPage;
}

$lawyerDisplayName = isset($_SESSION['lawyer_name']) ? (string) $_SESSION['lawyer_name'] : 'Lawyer';
$companyBranding = getCompanyBranding();
$companyName = $companyBranding['name'];
$companyLogoUrl = $companyBranding['logo_url'];
?>

<link href="../assets/css/legalpro-lawyer-portal.css?v=4" rel="stylesheet" />
<style>
    .legalpro-lawyer-nav .nav-link {
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
    .legalpro-lawyer-nav .nav-link .legalpro-lawyer-nav-icon {
        min-width: 36px;
        width: 36px;
        height: 36px;
        background-color: rgba(94, 114, 228, 0.08);
        color: #5e72e4;
        transition: all 0.15s ease;
    }
    .legalpro-lawyer-nav .nav-link .legalpro-lawyer-nav-icon i {
        color: inherit !important;
        opacity: 0.9;
    }
    .legalpro-lawyer-nav .nav-link.active,
    .legalpro-lawyer-nav .nav-link:hover {
        background: linear-gradient(135deg, #5e72e4, #825ee4);
        color: #fff;
        box-shadow: 0 10px 20px rgba(94, 114, 228, 0.25);
    }
    .legalpro-lawyer-nav .nav-link.active .legalpro-lawyer-nav-icon,
    .legalpro-lawyer-nav .nav-link:hover .legalpro-lawyer-nav-icon {
        background-color: rgba(255, 255, 255, 0.2);
        color: #fff !important;
    }
    .legalpro-lawyer-nav .nav-link.active .legalpro-lawyer-nav-icon i,
    .legalpro-lawyer-nav .nav-link:hover .legalpro-lawyer-nav-icon i {
        color: #fff !important;
        opacity: 1 !important;
    }
    .legalpro-lawyer-nav .nav-link.active .legalpro-lawyer-nav-text,
    .legalpro-lawyer-nav .nav-link:hover .legalpro-lawyer-nav-text {
        color: #fff !important;
    }
    .legalpro-lawyer-nav .nav-scroll {
        flex: 1;
        overflow-y: auto;
        padding-right: 0.25rem;
        margin-right: -0.25rem;
    }
    .legalpro-lawyer-nav .nav-scroll::-webkit-scrollbar {
        width: 4px;
    }
    .legalpro-lawyer-nav .nav-scroll::-webkit-scrollbar-thumb {
        background: rgba(52, 71, 103, 0.3);
        border-radius: 4px;
    }
</style>

<aside class="sidenav bg-white navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl fixed-start ms-4 legalpro-lawyer-nav" id="sidenav-main" style="height: calc(100vh - 2rem); top: 1rem; display: flex; flex-direction: column; overflow: hidden;">
    <div class="sidenav-header" style="flex-shrink: 0; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: center; position: relative; width: 100%;">
        <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
        <a class="navbar-brand m-0" href="lawyer-dashboard.php" style="display: flex; align-items: center; justify-content: center; margin: 0 auto; width: 100%;">
            <img src="<?php echo htmlspecialchars($companyLogoUrl); ?>" width="26" height="26" class="navbar-brand-img" alt="<?php echo htmlspecialchars($companyName); ?> logo" style="object-fit: contain;">
            <span class="ms-2 font-weight-bold" style="font-size: 0.875rem;"><?php echo htmlspecialchars($companyName); ?></span>
        </a>
    </div>
    <hr class="horizontal dark mt-0 mb-0" style="flex-shrink: 0; margin: 0;">
    <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
        <div class="nav-scroll">
            <ul class="navbar-nav" style="flex: 1; display: flex; flex-direction: column; justify-content: flex-start; padding: 0.25rem 0 0.5rem; margin: 0;">
                <?php foreach ($lawyerMenuItems as $item): ?>
                    <li class="nav-item" style="flex-shrink: 0; margin: 0;">
                        <a class="nav-link <?php echo lawyerNavIsActive($item['id'], $currentPage) ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($item['url']); ?>">
                            <div class="d-flex align-items-center">
                                <div class="icon icon-shape border-radius-md text-center me-2 d-flex align-items-center justify-content-center legalpro-lawyer-nav-icon">
                                    <i class="<?php echo htmlspecialchars($item['icon']); ?> text-xs opacity-10"></i>
                                </div>
                                <span class="nav-link-text legalpro-lawyer-nav-text" style="font-size: 0.95rem;"><?php echo htmlspecialchars($item['title']); ?></span>
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
            <p class="text-sm font-weight-bold mb-2"><?php echo htmlspecialchars($lawyerDisplayName); ?></p>
            <a href="lawyer-logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
        </div>
    </div>
</aside>
