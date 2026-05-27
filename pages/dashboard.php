<?php
session_start();
require_once __DIR__ . '/../inc/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

// Ensure cases table has all required columns
try {
    $pdo->query("ALTER TABLE cases ADD COLUMN user_id INT NULL AFTER client_id");
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'duplicate column name') === false) {
        throw $e;
    }
}
try {
    $pdo->query("ALTER TABLE cases ADD COLUMN priority VARCHAR(50) DEFAULT 'Normal' AFTER status");
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'duplicate column name') === false) {
        throw $e;
    }
}
try {
    $pdo->query("ALTER TABLE cases ADD COLUMN category VARCHAR(50) DEFAULT 'Civil' AFTER priority");
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'duplicate column name') === false) {
        throw $e;
    }
}
try {
    $pdo->query("ALTER TABLE cases ADD COLUMN estimated_fees DECIMAL(10,2) DEFAULT 0.00 AFTER category");
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'duplicate column name') === false) {
        throw $e;
    }
}
try {
    $pdo->query("ALTER TABLE cases ADD COLUMN start_date DATE NULL AFTER estimated_fees");
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'duplicate column name') === false) {
        throw $e;
    }
}
try {
    $pdo->query("ALTER TABLE cases ADD COLUMN expected_completion DATE NULL AFTER start_date");
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'duplicate column name') === false) {
        throw $e;
    }
}

// Fetch dashboard statistics
$totalCases = 0;
$activeCases = 0;
$completedCases = 0;
$pendingTasks = 0;
$newCasesThisWeek = 0;

try {
    // Total cases
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cases");
    $result = $stmt->fetch();
    $totalCases = (int)$result['total'];
    
    // Active cases (not closed)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cases WHERE status != 'closed'");
    $result = $stmt->fetch();
    $activeCases = (int)$result['total'];
    
    // Completed cases (closed)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cases WHERE status = 'closed'");
    $result = $stmt->fetch();
    $completedCases = (int)$result['total'];
    
    // New cases this week
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM cases WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $result = $stmt->fetch();
    $newCasesThisWeek = (int)$result['total'];
    
    // Pending tasks (using pending appointments as tasks)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'pending'");
    $result = $stmt->fetch();
    $pendingTasks = (int)$result['total'];
    
    // Due today (appointments today)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM appointments WHERE DATE(starts_at) = CURDATE() AND status = 'pending'");
    $result = $stmt->fetch();
    $dueToday = (int)$result['total'];
    
} catch (PDOException $e) {
    // Use defaults if error
    $dueToday = 0;
}

// Today at a glance metrics
$appointmentsToday = 0;
$appointmentsThisWeek = 0;
$unpaidInvoices = 0;
$adminDisplayName = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin';
$welcomeDate = date('l, j F Y');

try {
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM appointments WHERE DATE(starts_at) = CURDATE()");
    $appointmentsToday = (int)$stmt->fetch()['total'];

    $stmt = $pdo->query("
        SELECT COUNT(*) AS total FROM appointments
        WHERE starts_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
        AND starts_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
    ");
    $appointmentsThisWeek = (int)$stmt->fetch()['total'];

    $stmt = $pdo->query("
        SELECT COUNT(*) AS total FROM invoices
        WHERE status IS NULL OR LOWER(status) NOT IN ('paid', 'cancelled')
    ");
    $unpaidInvoices = (int)$stmt->fetch()['total'];
} catch (PDOException $e) {
    // keep defaults
}

// Financial chart — last 6 months invoiced vs paid
$chartLabels = [];
$chartInvoiced = [];
$chartPaid = [];
for ($i = 5; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthKey = date('Y-m', strtotime($monthStart));
    $chartLabels[] = date('M Y', strtotime($monthStart));

    $inv = 0;
    $paid = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total FROM invoices
            WHERE DATE_FORMAT(COALESCE(issue_date, created_at), '%Y-%m') = ?
        ");
        $stmt->execute([$monthKey]);
        $inv = (float)$stmt->fetch()['total'];

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total FROM payments
            WHERE DATE_FORMAT(COALESCE(payment_date, created_at), '%Y-%m') = ?
        ");
        $stmt->execute([$monthKey]);
        $paid = (float)$stmt->fetch()['total'];
    } catch (PDOException $e) {
        // zeros
    }
    $chartInvoiced[] = round($inv, 2);
    $chartPaid[] = round($paid, 2);
}

$chartInvoicedTotal = array_sum($chartInvoiced);
$chartPaidTotal = array_sum($chartPaid);
$chartTrendLabel = $chartPaidTotal >= $chartInvoicedTotal * 0.8
    ? 'Strong collection rate'
    : 'Track outstanding invoices';

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <title>LegalPro Case Manager - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link id="pagestyle" href="../assets/css/argon-dashboard.css?v=2.1.0" rel="stylesheet" />
    <link href="../assets/css/app-font-montserrat.css?v=1" rel="stylesheet" />
    <link href="../assets/css/dashboard-enhancements.css?v=3" rel="stylesheet" />
</head>
<body class="g-sidenav-show bg-gray-100 legalpro-admin-portal">
    <div class="min-height-300 bg-legalpro-admin position-absolute w-100"></div>
    <aside class="sidenav bg-white navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-4 " id="sidenav-main">
    </aside>
    <main class="main-content position-relative border-radius-lg ">
        <nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl" id="navbarBlur" data-scroll="false">
            <div class="container-fluid py-1 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-white" href="javascript:;">Pages</a></li>
                        <li class="breadcrumb-item text-sm text-white active" aria-current="page">Dashboard</li>
                    </ol>
                    <h6 class="font-weight-bolder text-white mb-0">Dashboard</h6>
                    <p class="dashboard-welcome-sub text-white mb-0 mt-1">Welcome back, {ADMIN_NAME} · {WELCOME_DATE}</p>
                </nav>
                <div class="dashboard-quick-actions ms-auto d-none d-md-flex">
                    <a href="case-new.php" class="btn btn-sm btn-white text-primary mb-0">+ New Case</a>
                    <a href="appointments.php" class="btn btn-sm btn-outline-white mb-0">Appointments</a>
                    <a href="clients.php" class="btn btn-sm btn-outline-white mb-0">Clients</a>
                </div>
            </div>
        </nav>

        <div class="container-fluid py-4">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-glance">
                        <a href="appointments.php" class="dashboard-glance__item">
                            <div class="dashboard-glance__icon dashboard-glance__icon--primary"><i class="ni ni-time-alarm"></i></div>
                            <div>
                                <div class="dashboard-glance__value">{APPOINTMENTS_TODAY}</div>
                                <div class="dashboard-glance__label">Today</div>
                            </div>
                        </a>
                        <a href="appointments.php" class="dashboard-glance__item">
                            <div class="dashboard-glance__icon dashboard-glance__icon--info"><i class="ni ni-calendar-grid-58"></i></div>
                            <div>
                                <div class="dashboard-glance__value">{APPOINTMENTS_WEEK}</div>
                                <div class="dashboard-glance__label">This week</div>
                            </div>
                        </a>
                        <a href="appointments.php" class="dashboard-glance__item">
                            <div class="dashboard-glance__icon dashboard-glance__icon--warning"><i class="ni ni-bell-55"></i></div>
                            <div>
                                <div class="dashboard-glance__value">{DUE_TODAY}</div>
                                <div class="dashboard-glance__label">Pending today</div>
                            </div>
                        </a>
                        <a href="invoices.php" class="dashboard-glance__item">
                            <div class="dashboard-glance__icon dashboard-glance__icon--success"><i class="ni ni-credit-card"></i></div>
                            <div>
                                <div class="dashboard-glance__value">{UNPAID_INVOICES}</div>
                                <div class="dashboard-glance__label">Open invoices</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <a href="tables.php" style="text-decoration: none; color: inherit;">
                        <div class="card dashboard-stat-card">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-8">
                                        <div class="numbers">
                                            <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Cases</p>
                                            <h5 class="font-weight-bolder">{TOTAL_CASES}</h5>
                                            <p class="mb-0">
                                                <span class="text-success text-sm font-weight-bolder">+{NEW_CASES_WEEK}</span>
                                                new this week
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="icon icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                                            <i class="ni ni-collection text-lg opacity-10" aria-hidden="true"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <a href="tables.php" style="text-decoration: none; color: inherit;">
                        <div class="card dashboard-stat-card">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-8">
                                        <div class="numbers">
                                            <p class="text-sm mb-0 text-uppercase font-weight-bold">Active Cases</p>
                                            <h5 class="font-weight-bolder">{ACTIVE_CASES}</h5>
                                            <p class="mb-0">
                                                <span class="text-info text-sm font-weight-bolder">In Progress</span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="icon icon-shape bg-gradient-danger shadow-danger text-center rounded-circle">
                                            <i class="ni ni-world text-lg opacity-10" aria-hidden="true"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
                    <a href="tables.php" style="text-decoration: none; color: inherit;">
                        <div class="card dashboard-stat-card">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-8">
                                        <div class="numbers">
                                            <p class="text-sm mb-0 text-uppercase font-weight-bold">Completed Cases</p>
                                            <h5 class="font-weight-bolder">{COMPLETED_CASES}</h5>
                                            <p class="mb-0">
                                                <span class="text-success text-sm font-weight-bolder">{COMPLETION_RATE}%</span>
                                                completion rate
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                            <i class="ni ni-paper-diploma text-lg opacity-10" aria-hidden="true"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-xl-3 col-sm-6">
                    <a href="appointments.php" style="text-decoration: none; color: inherit;">
                        <div class="card dashboard-stat-card">
                            <div class="card-body p-3">
                                <div class="row">
                                    <div class="col-8">
                                        <div class="numbers">
                                            <p class="text-sm mb-0 text-uppercase font-weight-bold">Pending Tasks</p>
                                            <h5 class="font-weight-bolder">{PENDING_TASKS}</h5>
                                            <p class="mb-0">
                                                <span class="text-danger text-sm font-weight-bolder">{DUE_TODAY}</span>
                                                due today
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <div class="icon icon-shape bg-gradient-warning shadow-warning text-center rounded-circle">
                                            <i class="ni ni-time-alarm text-lg opacity-10" aria-hidden="true"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card z-index-2 h-100">
                        <div class="card-header pb-0 pt-3 bg-transparent">
                            <h6 class="text-capitalize">Financial Overview</h6>
                            <p class="text-sm mb-0 text-muted">
                                <span class="font-weight-bold text-dark">{CHART_TREND_LABEL}</span> · last 6 months
                            </p>
                        </div>
                        <div class="card-body p-3">
                            <div class="chart">
                                <canvas id="chart-line" class="chart-canvas" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="footer pt-3">
                <div class="container-fluid">
                    <div class="row align-items-center justify-content-lg-between">
                        <div class="col-lg-6 mb-lg-0 mb-4">
                            <div class="copyright text-center text-sm text-muted text-lg-start">
                                © <script>document.write(new Date().getFullYear())</script>, LegalPro Case Manager.
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </main>

    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="../assets/js/plugins/chartjs.min.js"></script>
    <script>
        var ctx1 = document.getElementById("chart-line").getContext("2d");
        var gradientStroke1 = ctx1.createLinearGradient(0, 230, 0, 50);
        gradientStroke1.addColorStop(1, 'rgba(94, 114, 228, 0.2)');
        gradientStroke1.addColorStop(0.2, 'rgba(94, 114, 228, 0.0)');
        gradientStroke1.addColorStop(0, 'rgba(94, 114, 228, 0)');
        var gradientStroke2 = ctx1.createLinearGradient(0, 230, 0, 50);
        gradientStroke2.addColorStop(1, 'rgba(45, 206, 137, 0.2)');
        gradientStroke2.addColorStop(0, 'rgba(45, 206, 137, 0)');
        new Chart(ctx1, {
            type: "line",
            data: {
                labels: {CHART_LABELS_JSON},
                datasets: [{
                    label: "Invoiced",
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: "#5e72e4",
                    borderColor: "#5e72e4",
                    backgroundColor: gradientStroke1,
                    borderWidth: 2,
                    fill: true,
                    data: {CHART_INVOICED_JSON}
                }, {
                    label: "Paid",
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: "#2dce89",
                    borderColor: "#2dce89",
                    backgroundColor: gradientStroke2,
                    borderWidth: 2,
                    fill: true,
                    data: {CHART_PAID_JSON}
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: '#67748e',
                            font: { family: 'Montserrat', size: 11 }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                scales: {
                    y: {
                        grid: {
                            drawBorder: false,
                            display: true,
                            drawOnChartArea: true,
                            drawTicks: false,
                            borderDash: [5, 5]
                        },
                        ticks: {
                            display: true,
                            padding: 10,
                            color: '#8392ab',
                            font: {
                                size: 11,
                                family: "Montserrat",
                                style: 'normal',
                                lineHeight: 2
                            },
                        }
                    },
                    x: {
                        grid: {
                            drawBorder: false,
                            display: false,
                            drawOnChartArea: false,
                            drawTicks: false,
                            borderDash: [5, 5]
                        },
                        ticks: {
                            display: true,
                            color: '#ccc',
                            padding: 20,
                            font: {
                                size: 11,
                                family: "Montserrat",
                                style: 'normal',
                                lineHeight: 2
                            },
                        }
                    },
                },
            },
        });
    </script>
    <script src="../assets/js/argon-dashboard.min.js?v=2.1.0"></script>
    <script src="../assets/js/spa-nav.js"></script>
</body>
</html>
HTML;

// Calculate completion rate
$completionRate = $totalCases > 0 ? round(($completedCases / $totalCases) * 100) : 0;

// Replace placeholders
$html = str_replace('{ADMIN_NAME}', htmlspecialchars($adminDisplayName), $html);
$html = str_replace('{WELCOME_DATE}', htmlspecialchars($welcomeDate), $html);
$html = str_replace('{APPOINTMENTS_TODAY}', $appointmentsToday, $html);
$html = str_replace('{APPOINTMENTS_WEEK}', $appointmentsThisWeek, $html);
$html = str_replace('{UNPAID_INVOICES}', $unpaidInvoices, $html);
$html = str_replace('{CHART_TREND_LABEL}', htmlspecialchars($chartTrendLabel), $html);
$html = str_replace('{CHART_LABELS_JSON}', json_encode($chartLabels), $html);
$html = str_replace('{CHART_INVOICED_JSON}', json_encode($chartInvoiced), $html);
$html = str_replace('{CHART_PAID_JSON}', json_encode($chartPaid), $html);
$html = str_replace('{TOTAL_CASES}', $totalCases, $html);
$html = str_replace('{ACTIVE_CASES}', $activeCases, $html);
$html = str_replace('{COMPLETED_CASES}', $completedCases, $html);
$html = str_replace('{PENDING_TASKS}', $pendingTasks, $html);
$html = str_replace('{NEW_CASES_WEEK}', $newCasesThisWeek, $html);
$html = str_replace('{COMPLETION_RATE}', $completionRate, $html);
$html = str_replace('{DUE_TODAY}', $dueToday, $html);
// rewrite internal links from .html to .php
$html = preg_replace('/href="([^"\']+)\.html"/i', 'href="$1.php"', $html);

// capture shared sidebar HTML
ob_start();
include __DIR__ . '/../inc/menunav.php';
$sidebar = ob_get_clean();

// replace the first <aside>...</aside> with the sidebar include output
$html = preg_replace('/<aside[\s\S]*?<\/aside>/', $sidebar, $html, 1);

// capture footer/scripts
ob_start();
include __DIR__ . '/../inc/footer.php';
$footer = ob_get_clean();

// insert footer before closing </body>
$html = preg_replace('/<\/body>\s*<\/html>$/i', $footer . "\n</body>\n</html>", $html);

echo $html;
?>
