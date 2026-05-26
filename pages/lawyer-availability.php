<?php
session_start();
require_once __DIR__ . '/../inc/db.php';

// Check if lawyer is logged in
if (!isset($_SESSION['lawyer_id'])) {
    header('Location: lawyer-login.php');
    exit;
}

$lawyerId = $_SESSION['lawyer_id'];
$message = '';
$messageType = '';
$daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

try {
    $pdo->query("ALTER TABLE lawyer_time_slots ADD COLUMN slot_date DATE NULL AFTER day_of_week");
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'duplicate column') === false && stripos($e->getMessage(), 'duplicate column name') === false) {
        // Continue; save errors will show a detailed message if the schema is unavailable.
    }
}

// Handle time slot management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_slot']) || isset($_POST['add_slot'])) {
        $slotId = isset($_POST['slot_id']) ? (int)$_POST['slot_id'] : 0;
        $slotDate = isset($_POST['slot_date']) ? trim($_POST['slot_date']) : '';
        $startTime = isset($_POST['start_time']) ? $_POST['start_time'] : '';
        $endTime = isset($_POST['end_time']) ? $_POST['end_time'] : '';
        $slotType = isset($_POST['slot_type']) ? $_POST['slot_type'] : 'available';
        $timestamp = $slotDate !== '' ? strtotime($slotDate) : false;
        $dayOfWeek = $timestamp !== false ? strtolower(date('l', $timestamp)) : '';

        if ($timestamp === false || !in_array($dayOfWeek, $daysOfWeek, true) || empty($startTime) || empty($endTime) || !in_array($slotType, ['available', 'unavailable'], true)) {
            $message = 'Please provide a valid date, time range, and availability status.';
            $messageType = 'danger';
        } elseif (strtotime($startTime) >= strtotime($endTime)) {
            $message = 'End time must be after start time.';
            $messageType = 'danger';
        } else {
            try {
                if ($slotId > 0) {
                    $checkStmt = $pdo->prepare("SELECT id FROM lawyer_time_slots WHERE id = ? AND lawyer_id = ?");
                    $checkStmt->execute([$slotId, $lawyerId]);
                    if (!$checkStmt->fetch()) {
                        $message = 'Time slot not found or access denied.';
                        $messageType = 'danger';
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE lawyer_time_slots
                            SET day_of_week = ?, slot_date = ?, start_time = ?, end_time = ?, slot_type = ?
                            WHERE id = ? AND lawyer_id = ?
                        ");
                        $stmt->execute([$dayOfWeek, $slotDate, $startTime, $endTime, $slotType, $slotId, $lawyerId]);
                        $message = 'Time slot updated successfully!';
                        $messageType = 'success';
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO lawyer_time_slots (lawyer_id, day_of_week, slot_date, start_time, end_time, slot_type) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$lawyerId, $dayOfWeek, $slotDate, $startTime, $endTime, $slotType]);
                    $message = 'Time slot added successfully!';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Error saving time slot: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    } elseif (isset($_POST['delete_slot'])) {
        $slotId = (int)$_POST['slot_id'];

        try {
            $stmt = $pdo->prepare("DELETE FROM lawyer_time_slots WHERE id = ? AND lawyer_id = ?");
            $stmt->execute([$slotId, $lawyerId]);
            $message = 'Time slot deleted successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error deleting time slot: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch current time slots
$timeSlots = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM lawyer_time_slots WHERE lawyer_id = ? ORDER BY COALESCE(slot_date, '9999-12-31'), day_of_week, slot_order, start_time");
    $stmt->execute([$lawyerId]);
    $timeSlots = $stmt->fetchAll();
} catch (PDOException $e) {
    $timeSlots = [];
}

$messageHtml = '';
if ($message) {
    $successClass = ($messageType === 'success') ? ' text-white' : '';
    $closeClass = ($messageType === 'success') ? ' btn-close-white' : '';
    $messageHtml = '<div class="alert alert-' . htmlspecialchars($messageType) . ' alert-dismissible fade show' . $successClass . '" role="alert">' . htmlspecialchars($message) . '<button type="button" class="btn-close' . $closeClass . '" data-bs-dismiss="alert" aria-label="Close"></button></div>';
}

$availabilityEvents = [];
foreach ($timeSlots as $slot) {
    $slotDate = isset($slot['slot_date']) ? $slot['slot_date'] : '';
    if (empty($slotDate)) {
        continue;
    }

    $slotType = $slot['slot_type'] === 'available' ? 'available' : 'unavailable';
    $startTime = substr($slot['start_time'], 0, 5);
    $endTime = substr($slot['end_time'], 0, 5);
    $availabilityEvents[] = [
        'id' => (string)$slot['id'],
        'title' => ucfirst($slotType) . ' - ' . date('g:i A', strtotime($slot['start_time'])) . ' - ' . date('g:i A', strtotime($slot['end_time'])),
        'start' => $slotDate . 'T' . $startTime . ':00',
        'end' => $slotDate . 'T' . $endTime . ':00',
        'backgroundColor' => $slotType === 'available' ? '#2dce89' : '#f5365c',
        'borderColor' => $slotType === 'available' ? '#2dce89' : '#f5365c',
        'textColor' => '#ffffff',
        'extendedProps' => [
            'slotId' => (int)$slot['id'],
            'day' => $slot['day_of_week'],
            'slotDate' => $slotDate,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'slotType' => $slotType
        ]
    ];
}

$html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <title>LegalPro - My Availability</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="https://demos.creative-tim.com/argon-dashboard-pro/assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link id="pagestyle" href="../assets/css/argon-dashboard.css?v=2.1.0" rel="stylesheet" />
<link href="../assets/css/app-font-montserrat.css?v=2" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/simple-calendar.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.10.1/main.min.css" />
    <style>
        #availabilityCalendar { min-height: 620px; }
        .fc-event {
            cursor: pointer;
            font-weight: 700;
            border-radius: 0.45rem;
            padding: 2px 4px;
        }
        .fc .fc-prev-button,
        .fc .fc-next-button {
            background: #ffffff !important;
            border-color: #ffffff !important;
            color: #344767 !important;
        }
        .fc .fc-prev-button:hover,
        .fc .fc-next-button:hover,
        .fc .fc-prev-button:focus,
        .fc .fc-next-button:focus {
            background: #f8f9fa !important;
            border-color: #f8f9fa !important;
            color: #1f2b4d !important;
            box-shadow: none !important;
        }
        .fc .fc-prev-button .fc-icon,
        .fc .fc-next-button .fc-icon {
            color: #344767 !important;
        }
        .availability-hero {
            background: linear-gradient(140deg, rgba(45, 63, 111, 0.08), rgba(111, 127, 210, 0.12));
            border: 1px solid #dbe4f7;
            border-radius: 1rem;
            padding: 1rem;
        }
        .availability-fallback-calendar {
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            overflow: hidden;
        }
        .availability-fallback-header,
        .availability-fallback-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(120px, 1fr));
        }
        .availability-fallback-header > div {
            background: #f6f8fc;
            border-right: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
            color: #344767;
            font-size: 0.75rem;
            font-weight: 800;
            padding: 0.75rem;
            text-transform: uppercase;
        }
        .availability-fallback-day {
            min-height: 150px;
            border-right: 1px solid #e9ecef;
            padding: 0.75rem;
        }
        .availability-fallback-event {
            border-radius: 0.45rem;
            color: #fff;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
            padding: 0.35rem 0.45rem;
        }
    </style>
</head>
<body class="g-sidenav-show bg-gray-100 legalpro-lawyer-portal lawyer-availability-page">
    <div class="min-height-300 bg-legalpro-lawyer position-absolute w-100"></div>
    <aside class="sidenav bg-white navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start ms-4" id="sidenav-main">
        <div class="sidenav-header">
            <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
            <a class="navbar-brand m-0" href="#">
                <img src="../assets/img/logo-ct-dark.png" width="26px" height="26px" class="navbar-brand-img h-100" alt="LegalPro logo">
                <span class="ms-1 font-weight-bold">LegalPro</span>
            </a>
        </div>
        <hr class="horizontal dark mt-0">
        <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="lawyer-dashboard.php">
                        <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                            <i class="ni ni-tv-2 text-primary text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text ms-1">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="tasks.php">
                        <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                            <i class="ni ni-check-bold text-success text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text ms-1">My Tasks</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="lawyer-cases.php">
                        <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                            <i class="ni ni-folder-17 text-warning text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text ms-1">My Cases</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="lawyer-clients.php">
                        <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                            <i class="ni ni-circle-08 text-info text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text ms-1">My Clients</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="lawyer-appointments.php">
                        <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                            <i class="ni ni-calendar-grid-58 text-success text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text ms-1">Appointments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="lawyer-court-tracking.php">
                        <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                            <i class="ni ni-collection text-primary text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text ms-1">Court Tracking</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="lawyer-availability.php">
                        <div class="icon icon-shape icon-sm border-radius-md text-center me-2 d-flex align-items-center justify-content-center">
                            <i class="ni ni-time-alarm text-danger text-sm opacity-10"></i>
                        </div>
                        <span class="nav-link-text ms-1">My Availability</span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="sidenav-footer position-absolute bottom-0 w-100">
            <div class="text-center">
                <p class="text-xs text-muted mb-1">Logged in as</p>
                <p class="text-sm font-weight-bold mb-2">{$lawyerName}</p>
                <a href="lawyer-logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
            </div>
        </div>
    </aside>
    <main class="main-content position-relative border-radius-lg">
        <!-- Navbar -->
        <nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl" id="navbarBlur" navbar-scroll="true">
            <div class="container-fluid py-1 px-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-white" href="javascript:;">Pages</a></li>
                        <li class="breadcrumb-item text-sm text-white active" aria-current="page">My Availability</li>
                    </ol>
                    <h6 class="font-weight-bolder text-white">Manage My Availability</h6>
                </nav>
                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                    <ul class="navbar-nav justify-content-end">
                        <!-- <li class="nav-item d-flex align-items-center">
                            <span class="text-sm text-white">
                                <i class="fa fa-user me-sm-1"></i>
                                Lawyer Portal
                            </span> 
                        </li> -->
                    </ul>
                </div>
            </div>
        </nav>
        <!-- End Navbar -->
        <div class="container-fluid py-4">
            {$message}

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Set Your Weekly Availability</h6>
                            <p class="text-sm text-muted mb-0">Configure your available time slots for each day of the week</p>
                        </div>
                        <div class="card-body">
                            <div class="availability-hero mb-4">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                    <div>
                                        <h6 class="mb-1">Availability Calendar</h6>
                                        <p class="text-sm text-muted mb-0">Click a day to add hours, or click an existing slot to edit it.</p>
                                    </div>
                                    <button type="button" class="btn btn-primary mb-0" onclick="openAvailabilityModal()">Add Time Slot</button>
                                </div>
                            </div>
                            <div id="availabilityCalendar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="availabilityModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="availabilityModalTitle">Add Time Slot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="save_slot" value="1">
                        <input type="hidden" name="slot_id" id="slot_id" value="">
                        <input type="hidden" name="day_of_week" id="day_of_week" value="">
                        <div class="mb-3">
                            <label class="form-control-label">Date</label>
                            <input type="date" class="form-control" name="slot_date" id="slot_date" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-control-label">Start Time</label>
                                <input type="time" class="form-control" name="start_time" id="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-control-label">End Time</label>
                                <input type="time" class="form-control" name="end_time" id="end_time" required>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-control-label">Availability Status</label>
                            <select class="form-control" name="slot_type" id="slot_type" required>
                                <option value="available">Available for appointments</option>
                                <option value="unavailable">Unavailable / break</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="availabilitySaveButton">Save Slot</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="../assets/js/fullcalendar/fallback.js"></script>
    <script src="../assets/js/argon-dashboard.min.js?v=2.1.0"></script>
    <script>
        var availabilityEvents = {AVAILABILITY_EVENTS_JSON};
        var calendarLoaded = false;

        function dayNameFromDate(date) {
            return ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][date.getDay()];
        }

        function dateToInputValue(date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        }

        function openAvailabilityModal(day, slotId, slotDate, startTime, endTime, slotType) {
            document.getElementById('availabilityModalTitle').textContent = slotId ? 'Edit Time Slot' : 'Add Time Slot';
            document.getElementById('availabilitySaveButton').textContent = slotId ? 'Update Slot' : 'Save Slot';
            document.getElementById('slot_id').value = slotId || '';
            document.getElementById('slot_date').value = slotDate || '';
            document.getElementById('day_of_week').value = day || '';
            document.getElementById('start_time').value = startTime || '';
            document.getElementById('end_time').value = endTime || '';
            document.getElementById('slot_type').value = slotType || 'available';
            new bootstrap.Modal(document.getElementById('availabilityModal')).show();
        }

        function initAvailabilityCalendar() {
            var calendarEl = document.getElementById('availabilityCalendar');
            if (!calendarEl) return;
            try {
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    events: availabilityEvents,
                    height: 'auto',
                    eventDisplay: 'block',
                    dateClick: function(info) {
                        openAvailabilityModal(dayNameFromDate(info.date), '', dateToInputValue(info.date));
                    },
                    eventClick: function(info) {
                        info.jsEvent.preventDefault();
                        var props = info.event.extendedProps || {};
                        openAvailabilityModal(props.day, props.slotId || info.event.id, props.slotDate || '', props.startTime, props.endTime, props.slotType);
                    },
                    eventDidMount: function(info) {
                        info.el.setAttribute('title', 'Click to edit this time slot');
                    }
                });
                calendar.render();
            } catch (error) {
                renderAvailabilityFallbackCalendar();
            }
        }

        function renderAvailabilityFallbackCalendar() {
            var calendarEl = document.getElementById('availabilityCalendar');
            if (!calendarEl) return;
            var dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            var html = '<div class="availability-fallback-calendar"><div class="availability-fallback-header">';
            dayNames.forEach(function(dayName) { html += '<div>' + dayName + '</div>'; });
            html += '</div><div class="availability-fallback-grid">';
            dayNames.forEach(function(dayName, dayIndex) {
                html += '<div class="availability-fallback-day">';
                var eventsForDay = availabilityEvents.filter(function(event) {
                    return event.start && new Date(event.start).getDay() === dayIndex;
                });
                if (eventsForDay.length === 0) {
                    html += '<p class="text-sm text-muted mb-0">No hours set</p>';
                } else {
                    eventsForDay.forEach(function(event) {
                        var props = event.extendedProps || {};
                        html += '<div class="availability-fallback-event" style="background-color:' + event.backgroundColor + '" onclick="openAvailabilityModal(\'' + props.day + '\', \'' + (props.slotId || event.id) + '\', \'' + (props.slotDate || '') + '\', \'' + props.startTime + '\', \'' + props.endTime + '\', \'' + props.slotType + '\')">' + event.title + '</div>';
                    });
                }
                html += '</div>';
            });
            html += '</div></div>';
            calendarEl.innerHTML = html;
        }

        document.addEventListener('DOMContentLoaded', function() {
            var slotDateInput = document.getElementById('slot_date');
            if (slotDateInput) {
                slotDateInput.addEventListener('change', function() {
                    if (this.value) {
                        var parts = this.value.split('-').map(Number);
                        document.getElementById('day_of_week').value = dayNameFromDate(new Date(parts[0], parts[1] - 1, parts[2]));
                    }
                });
            }

            if (typeof FullCalendar !== 'undefined') {
                calendarLoaded = true;
                initAvailabilityCalendar();
            } else {
                var script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/5.10.1/main.min.js';
                script.onload = function() {
                    calendarLoaded = true;
                    initAvailabilityCalendar();
                };
                script.onerror = renderAvailabilityFallbackCalendar;
                document.head.appendChild(script);
                setTimeout(function() {
                    if (!calendarLoaded) renderAvailabilityFallbackCalendar();
                }, 3500);
            }
        });
    </script>
</body>
</html>
HTML;

$html = str_replace('{$message}', $messageHtml, $html);
$html = str_replace('{$lawyerName}', htmlspecialchars($_SESSION['lawyer_name']), $html);
$html = str_replace('{AVAILABILITY_EVENTS_JSON}', json_encode($availabilityEvents), $html);

echo $html;
?>
