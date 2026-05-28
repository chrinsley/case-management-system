<?php

function ensureAppointmentSlotColumn(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    try {
        $pdo->query('ALTER TABLE lawyer_time_slots ADD COLUMN appointment_id INT NULL AFTER slot_type');
    } catch (PDOException $e) {
        if (stripos($e->getMessage(), 'duplicate column') === false && stripos($e->getMessage(), 'duplicate column name') === false) {
            throw $e;
        }
    }

    $ready = true;
}

function removeAppointmentAvailabilitySlot(PDO $pdo, int $appointmentId, ?int $lawyerId = null): void
{
    if ($appointmentId <= 0) {
        return;
    }

    ensureAppointmentSlotColumn($pdo);

    if ($lawyerId !== null && $lawyerId > 0) {
        $stmt = $pdo->prepare('DELETE FROM lawyer_time_slots WHERE appointment_id = ? AND lawyer_id = ?');
        $stmt->execute([$appointmentId, $lawyerId]);
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM lawyer_time_slots WHERE appointment_id = ?');
    $stmt->execute([$appointmentId]);
}

function syncAppointmentAvailabilitySlot(PDO $pdo, array $appointment): void
{
    $appointmentId = (int) ($appointment['id'] ?? 0);
    $lawyerId = (int) ($appointment['lawyer_id'] ?? 0);
    $startsAtRaw = $appointment['starts_at'] ?? '';

    if ($appointmentId <= 0 || $lawyerId <= 0 || $startsAtRaw === '') {
        return;
    }

    ensureAppointmentSlotColumn($pdo);

    $status = strtolower((string) ($appointment['status'] ?? 'pending'));
    if ($status === 'rejected') {
        removeAppointmentAvailabilitySlot($pdo, $appointmentId);
        return;
    }

    $startsAt = new DateTime($startsAtRaw);
    $endsAtRaw = $appointment['ends_at'] ?? '';
    $endsAt = $endsAtRaw !== '' ? new DateTime($endsAtRaw) : (clone $startsAt)->modify('+1 hour');

    $slotDate = $startsAt->format('Y-m-d');
    $dayOfWeek = strtolower($startsAt->format('l'));
    $startTime = $startsAt->format('H:i:s');
    $endTime = $endsAt->format('H:i:s');

    $stmt = $pdo->prepare('SELECT id FROM lawyer_time_slots WHERE appointment_id = ? LIMIT 1');
    $stmt->execute([$appointmentId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE lawyer_time_slots
            SET lawyer_id = ?, day_of_week = ?, slot_date = ?, start_time = ?, end_time = ?, slot_type = 'unavailable'
            WHERE appointment_id = ?
        ");
        $stmt->execute([$lawyerId, $dayOfWeek, $slotDate, $startTime, $endTime, $appointmentId]);
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO lawyer_time_slots (lawyer_id, day_of_week, slot_date, start_time, end_time, slot_type, appointment_id)
        VALUES (?, ?, ?, ?, ?, 'unavailable', ?)
    ");
    $stmt->execute([$lawyerId, $dayOfWeek, $slotDate, $startTime, $endTime, $appointmentId]);
}

function backfillLawyerAppointmentAvailability(PDO $pdo, int $lawyerId): void
{
    if ($lawyerId <= 0) {
        return;
    }

    ensureAppointmentSlotColumn($pdo);

    $stmt = $pdo->prepare("
        SELECT a.*
        FROM appointments a
        LEFT JOIN lawyer_time_slots l ON l.appointment_id = a.id
        WHERE a.lawyer_id = ?
          AND a.starts_at IS NOT NULL
          AND LOWER(COALESCE(a.status, 'pending')) <> 'rejected'
          AND l.id IS NULL
    ");
    $stmt->execute([$lawyerId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $appointment) {
        syncAppointmentAvailabilitySlot($pdo, $appointment);
    }
}
