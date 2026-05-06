<?php

function getActivityRetentionDays()
{
    return 7;
}

function cleanupActivityTables($conn, $days = null)
{
    $retentionDays = (int) ($days ?? getActivityRetentionDays());
    $conn->query("DELETE FROM activity_log WHERE login_time < DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)");
    $conn->query("DELETE FROM admin_activity WHERE `date` < DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)");
}

function getActivityActorName()
{
    if (!empty($_SESSION['username'])) {
        return trim((string) $_SESSION['username']);
    }

    if (!empty($_SESSION['email'])) {
        return trim((string) $_SESSION['email']);
    }

    return 'System';
}

function formatAdminActivityMessage($activity)
{
    $activity = trim((string) $activity);
    if ($activity === '') {
        return $activity;
    }

    if (preg_match('/^\[[^\]]+\]\s*/', $activity)) {
        return $activity;
    }

    return '[' . getActivityActorName() . '] ' . $activity;
}

function splitAdminActivityMessage($activity)
{
    $activity = trim((string) $activity);

    if (preg_match('/^\[(.*?)\]\s*(.*)$/', $activity, $matches)) {
        return [
            'actor' => $matches[1] !== '' ? $matches[1] : 'Admin/System',
            'activity' => $matches[2] !== '' ? $matches[2] : $activity,
        ];
    }

    return [
        'actor' => 'Admin/System',
        'activity' => $activity,
    ];
}

function summarizeAdminActivity($actor, $module, $activity, $status)
{
    $actor = trim((string) $actor) !== '' ? trim((string) $actor) : 'An admin';
    $module = trim((string) $module) !== '' ? trim((string) $module) : 'system';
    $activity = trim((string) $activity);
    $status = trim((string) $status) !== '' ? trim((string) $status) : 'Recorded';

    return $actor . ' worked in the ' . $module . ' module and performed this action: ' . $activity . '. Current status: ' . $status . '.';
}
