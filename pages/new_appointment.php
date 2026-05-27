<?php
session_start();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../lib/case_events.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

$message = '';
$messageType = '';
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = urldecode($_GET['msg']);
    $messageType = $_GET['type'];
}

$formData = [
    'appointment_id' => '',
    'case_id' => '',
    'client_name' => '',
    'lawyer_id' => '',
    'date' => '',
    'time' => '',
    'notes' => ''
];

try {
    $pdo->query("ALTER TABLE appointments ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER ends_at");
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'duplicate column name') === false) {
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'save') {
    $appointmentId = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
    $caseId = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
    $lawyerId = isset($_POST['lawyer_id']) ? (int)$_POST['lawyer_id'] : 0;
    $date = isset($_POST['date']) ? trim($_POST['date']) : '';
    $time = isset($_POST['time']) ? trim($_POST['time']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    $clientId = 0;
    $clientName = '';
    if ($caseId) {
        $caseStmt = $pdo->prepare("SELECT c.client_id, cl.first_name, cl.last_name FROM cases c LEFT JOIN clients cl ON cl.id = c.client_id WHERE c.id = ?");
        $caseStmt->execute([$caseId]);
        $caseInfo = $caseStmt->fetch();
        if ($caseInfo) {
            $clientId = $caseInfo['client_id'];
            $clientName = trim($caseInfo['first_name'] . ' ' . $caseInfo['last_name']);
        }
    }

    $formData = [
        'appointment_id' => $appointmentId ? $appointmentId : '',
        'case_id' => $caseId,
        'client_name' => $clientName,
        'lawyer_id' => $lawyerId,
        'date' => $date,
        'time' => $time,
        'notes' => $notes
    ];

    if (empty($caseId) || empty($lawyerId) || empty($date) || empty($time)) {
        $message = 'Case, lawyer, date, and time are required.';
        $messageType = 'danger';
    } else {
        $lawyerCheck = $pdo->prepare("SELECT id FROM lawyers WHERE id = ? AND is_active = 1");
        $lawyerCheck->execute([$lawyerId]);
        if (!$lawyerCheck->fetch()) {
            $message = 'Please select a valid lawyer from the list.';
            $messageType = 'danger';
        } else {
            $dateTime = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
            if (!$dateTime) {
                $message = 'Invalid date or time format.';
                $messageType = 'danger';
            } else {
                $startsAt = $dateTime->format('Y-m-d H:i:s');
                $endsAt = $dateTime->modify('+1 hour')->format('Y-m-d H:i:s');

                try {
                    if ($appointmentId) {
                        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
                        $stmt->execute([$appointmentId]);
                        $oldAppointment = $stmt->fetch();

                        $stmt = $pdo->prepare("
                            UPDATE appointments
                            SET client_id = ?, case_id = ?, lawyer_id = ?, starts_at = ?, ends_at = ?, notes = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$clientId, $caseId, $lawyerId, $startsAt, $endsAt, $notes, $appointmentId]);

                        if ($oldAppointment) {
                            CaseEvents::trackAppointmentUpdated($caseId, $oldAppointment, [
                                'client_id' => $clientId,
                                'case_id' => $caseId,
                                'lawyer_id' => $lawyerId,
                                'starts_at' => $startsAt,
                                'ends_at' => $endsAt,
                                'notes' => $notes,
                                'status' => $oldAppointment['status']
                            ]);
                        }

                        $msg = 'Appointment updated successfully.';
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO appointments (client_id, case_id, lawyer_id, starts_at, ends_at, notes, status)
                            VALUES (?, ?, ?, ?, ?, ?, 'pending')
                        ");
                        $stmt->execute([$clientId, $caseId, $lawyerId, $startsAt, $endsAt, $notes]);

                        CaseEvents::trackAppointmentCreated($caseId, [
                            'starts_at' => $startsAt,
                            'ends_at' => $endsAt,
                            'notes' => $notes
                        ]);

                        $msg = 'Appointment booked successfully.';
                    }

                    header('Location: appointments.php?msg=' . urlencode($msg) . '&type=success');
                    exit;
                } catch (PDOException $e) {
                    $message = 'Error saving appointment: ' . htmlspecialchars($e->getMessage());
                    $messageType = 'danger';
                }
            }
        }
    }
}

if (empty($formData['appointment_id']) && isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $editId = (int)$_GET['id'];
    $stmt = $pdo->prepare("
        SELECT a.*, c.title as case_title, c.id as case_id,
               cl.first_name, cl.last_name
        FROM appointments a
        LEFT JOIN cases c ON c.id = a.case_id
        LEFT JOIN clients cl ON cl.id = c.client_id
        WHERE a.id = ?
    ");
    $stmt->execute([$editId]);
    $appointment = $stmt->fetch();

    if ($appointment) {
        $startsAt = $appointment['starts_at'] ? new DateTime($appointment['starts_at']) : null;
        $clientName = trim((isset($appointment['first_name']) ? $appointment['first_name'] : '') . ' ' . (isset($appointment['last_name']) ? $appointment['last_name'] : ''));
        $formData = [
            'appointment_id' => $appointment['id'],
            'case_id' => $appointment['case_id'],
            'client_name' => $clientName,
            'lawyer_id' => $appointment['lawyer_id'],
            'date' => $startsAt ? $startsAt->format('Y-m-d') : '',
            'time' => $startsAt ? $startsAt->format('H:i') : '',
            'notes' => $appointment['notes']
        ];
    } else {
        $message = 'Appointment not found.';
        $messageType = 'danger';
    }
}

try {
    $casesList = $pdo->query("
        SELECT
            c.id,
            c.title,
            CONCAT('C-', LPAD(c.id, 4, '0'), ' · ', c.title) as case_display,
            cl.first_name,
            cl.last_name,
            CONCAT(cl.first_name, ' ', cl.last_name) as client_name
        FROM cases c
        LEFT JOIN clients cl ON cl.id = c.client_id
        ORDER BY c.title
    ")->fetchAll();
} catch (PDOException $e) {
    $casesList = [];
    if (!$message) {
        $message = 'Unable to load cases list: ' . htmlspecialchars($e->getMessage());
        $messageType = 'danger';
    }
}

try {
    $lawyersList = $pdo->query("
        SELECT l.id, l.first_name, l.last_name, u.username
        FROM lawyers l
        LEFT JOIN users u ON u.id = l.user_id
        WHERE l.is_active = 1
        ORDER BY l.last_name, l.first_name
    ")->fetchAll();
} catch (PDOException $e) {
    $lawyersList = [];
    if (!$message) {
        $message = 'Unable to load lawyers list: ' . htmlspecialchars($e->getMessage());
        $messageType = 'danger';
    }
}

$caseOptions = '<option value="">Select case</option>';
foreach ($casesList as $case) {
    $selected = ((int)$formData['case_id'] === (int)$case['id']) ? ' selected' : '';
    $caseOptions .= '<option value="' . (int)$case['id'] . '" data-client="' . htmlspecialchars($case['client_name']) . '"' . $selected . '>' . htmlspecialchars($case['case_display']) . '</option>';
}

$lawyerOptions = '<option value="">Select lawyer</option>';
foreach ($lawyersList as $lawyer) {
    $label = trim($lawyer['first_name'] . ' ' . $lawyer['last_name']);
    if (!empty($lawyer['username'])) {
        $label .= ' (' . $lawyer['username'] . ')';
    }
    $selected = ((int)$formData['lawyer_id'] === (int)$lawyer['id']) ? ' selected' : '';
    $lawyerOptions .= '<option value="' . (int)$lawyer['id'] . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
}

$isEditing = !empty($formData['appointment_id']);
$formTitle = $isEditing ? 'Update Appointment' : 'Book Appointment';
$pageTitle = $isEditing ? 'Edit Appointment' : 'New Appointment';
$submitLabel = $isEditing ? 'Save Changes' : 'Submit Request';
$cancelLink = '<a href="appointments.php" class="btn btn-outline-secondary btn-sm mb-0" title="Back to appointments"><i class="ni ni-bold-left me-1"></i> Back to list</a>';

$messageHtml = '';
if ($message) {
    $messageHtml = '<div class="alert alert-' . htmlspecialchars($messageType) . ' alert-dismissible fade show" role="alert">
        ' . htmlspecialchars($message) . '
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
	<link rel="icon" type="image/png" href="../assets/img/favicon.png">
	<title>LegalPro Case Manager - {PAGE_TITLE}</title>
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
	<link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-icons.css" rel="stylesheet" />
	<link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-svg.css" rel="stylesheet" />
	<script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
	<link id="pagestyle" href="../assets/css/argon-dashboard.css?v=2.1.0" rel="stylesheet" />
<link href="../assets/css/app-font-montserrat.css?v=1" rel="stylesheet" />
</head>
<body class="g-sidenav-show bg-gray-100 legalpro-admin-portal">
	<div class="min-height-300 bg-legalpro-admin position-absolute w-100"></div>
	<aside class="sidenav bg-white navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-4 " id="sidenav-main">
		<div class="sidenav-header">
			<a class="navbar-brand m-0" href="dashboard.php">LegalPro</a>
		</div>
	</aside>
	<main class="main-content position-relative border-radius-lg ">
		<nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl" id="navbarBlur" data-scroll="false">
			<div class="container-fluid py-1 px-3">
				<nav aria-label="breadcrumb">
					<ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
						<li class="breadcrumb-item text-sm"><a class="opacity-5 text-white" href="appointments.php">Appointments</a></li>
						<li class="breadcrumb-item text-sm text-white active" aria-current="page">{PAGE_TITLE}</li>
					</ol>
					<h6 class="font-weight-bolder text-white mb-0">{PAGE_TITLE}</h6>
				</nav>
			</div>
		</nav>
		<div class="container-fluid py-4">
			{MESSAGE}

			<div class="row justify-content-center">
				<div class="col-lg-8">
					<div class="card" id="appointment-form">
						<div class="card-header pb-0 pt-3">
							<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
								<div class="d-flex align-items-center">
									<div class="icon icon-shape icon-md bg-gradient-dark shadow text-center border-radius-md me-3">
										<i class="ni ni-calendar-grid-58 text-white text-lg opacity-10"></i>
									</div>
									<div>
										<h6 class="mb-0">{FORM_TITLE}</h6>
										<p class="text-xs text-muted mb-0">Fill in the details below</p>
									</div>
								</div>
								{CANCEL_EDIT_LINK}
							</div>
						</div>
						<div class="card-body pt-3">
							<form method="post" id="appointmentForm">
								<input type="hidden" name="form_type" value="save">
								<input type="hidden" name="appointment_id" value="{APPOINTMENT_ID}">

								<div class="form-group mb-3">
									<label class="form-control-label text-sm font-weight-bold">Case <span class="text-danger">*</span></label>
									<select class="form-control" name="case_id" id="case_select" required>
										{CASE_OPTIONS}
									</select>
								</div>

								<div class="form-group mb-3">
									<label class="form-control-label text-sm font-weight-bold">Client</label>
									<input class="form-control" type="text" id="client_display" value="{CLIENT_NAME}" readonly>
									<small class="text-muted">Automatically populated based on selected case</small>
								</div>

								<div class="form-group mb-3">
									<label class="form-control-label text-sm font-weight-bold">Lawyer / Staff <span class="text-danger">*</span></label>
									<select class="form-control" name="lawyer_id" required>
										{LAWYER_OPTIONS}
									</select>
								</div>

								<div class="row">
									<div class="col-md-6">
										<div class="form-group mb-3">
											<label class="form-control-label text-sm font-weight-bold">Date <span class="text-danger">*</span></label>
											<input class="form-control" type="date" name="date" value="{DATE_VALUE}" required>
										</div>
									</div>
									<div class="col-md-6">
										<div class="form-group mb-3">
											<label class="form-control-label text-sm font-weight-bold">Time <span class="text-danger">*</span></label>
											<input class="form-control" type="time" name="time" value="{TIME_VALUE}" required>
										</div>
									</div>
								</div>

								<div class="form-group mb-4">
									<label class="form-control-label text-sm font-weight-bold">Notes</label>
									<textarea class="form-control" rows="3" name="notes" placeholder="Brief reason for appointment or additional details...">{NOTES_VALUE}</textarea>
								</div>

								<div class="d-flex gap-2">
									<button class="btn btn-dark btn-sm mb-0" type="submit">
										<i class="ni ni-check-bold me-1"></i> {SUBMIT_LABEL}
									</button>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<script src="../assets/js/core/popper.min.js"></script>
	<script src="../assets/js/core/bootstrap.min.js"></script>
	<script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
	<script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
	<script src="../assets/js/argon-dashboard.min.js?v=2.1.0"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			var caseSelect = document.getElementById('case_select');
			var clientDisplay = document.getElementById('client_display');

			if (caseSelect && clientDisplay) {
				caseSelect.addEventListener('change', function() {
					var selectedOption = this.options[this.selectedIndex];
					if (selectedOption && selectedOption.value) {
						clientDisplay.value = selectedOption.getAttribute('data-client') || '';
					} else {
						clientDisplay.value = '';
					}
				});

				if (caseSelect.value) {
					caseSelect.dispatchEvent(new Event('change'));
				}
			}
		});
	</script>
</body>
</html>
HTML;

$html = str_replace('{PAGE_TITLE}', htmlspecialchars($pageTitle), $html);
$html = str_replace('{FORM_TITLE}', htmlspecialchars($formTitle), $html);
$html = str_replace('{MESSAGE}', $messageHtml, $html);
$html = str_replace('{APPOINTMENT_ID}', htmlspecialchars($formData['appointment_id']), $html);
$html = str_replace('{CASE_OPTIONS}', $caseOptions, $html);
$html = str_replace('{CLIENT_NAME}', htmlspecialchars($formData['client_name']), $html);
$html = str_replace('{LAWYER_OPTIONS}', $lawyerOptions, $html);
$html = str_replace('{DATE_VALUE}', htmlspecialchars($formData['date']), $html);
$html = str_replace('{TIME_VALUE}', htmlspecialchars($formData['time']), $html);
$html = str_replace('{NOTES_VALUE}', htmlspecialchars($formData['notes']), $html);
$html = str_replace('{SUBMIT_LABEL}', htmlspecialchars($submitLabel), $html);
$html = str_replace('{CANCEL_EDIT_LINK}', $cancelLink, $html);

$html = preg_replace('/href="([^"\']+)\.html"/i', 'href="$1.php"', $html);
ob_start();
include __DIR__ . '/../inc/menunav.php';
$sidebar = ob_get_clean();
$html = preg_replace('/<aside[\s\S]*?<\/aside>/', $sidebar, $html, 1);
ob_start();
include __DIR__ . '/../inc/footer.php';
$footer = ob_get_clean();
$html = preg_replace('/<\/body>\s*<\/html>$/i', $footer . "\n</body>\n</html>", $html);

echo $html;
?>
