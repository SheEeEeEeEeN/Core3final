<?php

if (!function_exists('shipmentColumnExists')) {
    function shipmentColumnExists(mysqli $conn, string $table, string $column): bool
    {
        $table = mysqli_real_escape_string($conn, $table);
        $column = mysqli_real_escape_string($conn, $column);
        $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");

        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('getShipmentDateColumn')) {
    function getShipmentDateColumn(mysqli $conn): string
    {
        return shipmentColumnExists($conn, 'shipments', 'booked_date') ? 'booked_date' : 'created_at';
    }
}

if (!function_exists('ensureShipmentLogsTable')) {
    function ensureShipmentLogsTable(mysqli $conn): void
    {
        static $initialized = false;

        if ($initialized) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS shipment_logs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            shipment_id INT NOT NULL,
            tracking_no VARCHAR(100) NOT NULL,
            status VARCHAR(100) NOT NULL,
            location VARCHAR(255) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            icon VARCHAR(120) DEFAULT NULL,
            logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_shipment_id (shipment_id),
            KEY idx_tracking_no (tracking_no),
            KEY idx_logged_at (logged_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        mysqli_query($conn, $sql);
        $initialized = true;
    }
}

if (!function_exists('getShipmentTrackingNo')) {
    function getShipmentTrackingNo(array $shipment): string
    {
        $candidates = [
            $shipment['tracking_no'] ?? null,
            $shipment['tracking_number'] ?? null,
            $shipment['shipment_code'] ?? null,
            $shipment['contract_number'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!empty($candidate)) {
                return (string) $candidate;
            }
        }

        $id = isset($shipment['id']) ? (int) $shipment['id'] : 0;
        return 'TRK' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('normalizeShipmentStatus')) {
    function normalizeShipmentStatus(string $status): string
    {
        $value = strtoupper(trim($status));
        $value = str_replace(['-', ' '], '_', $value);

        return $value;
    }
}

if (!function_exists('getShipmentStatusMeta')) {
    function getShipmentStatusMeta(string $status): array
    {
        $normalized = normalizeShipmentStatus($status);

        return match ($normalized) {
            'BOOKING_CREATED', 'BOOKED', 'PENDING' => [
                'label' => 'Booking Created',
                'icon' => 'fa-solid fa-box-open',
                'variant' => 'warning',
            ],
            'READY_TO_DISPATCH', 'CONSOLIDATED' => [
                'label' => 'Consolidated',
                'icon' => 'fa-solid fa-layer-group',
                'variant' => 'info',
            ],
            'AT_SORTING_HUB', 'SORTING_HUB' => [
                'label' => 'At Sorting Hub',
                'icon' => 'fa-solid fa-warehouse',
                'variant' => 'info',
            ],
            'IN_TRANSIT', 'ARRIVED' => [
                'label' => 'In Transit',
                'icon' => 'fa-solid fa-truck-fast',
                'variant' => 'primary',
            ],
            'OUT_FOR_DELIVERY' => [
                'label' => 'Out for Delivery',
                'icon' => 'fa-solid fa-route',
                'variant' => 'primary',
            ],
            'DELIVERED' => [
                'label' => 'Delivered',
                'icon' => 'fa-solid fa-circle-check',
                'variant' => 'success',
            ],
            'CANCELLED' => [
                'label' => 'Cancelled',
                'icon' => 'fa-solid fa-circle-xmark',
                'variant' => 'danger',
            ],
            'ARCHIVED' => [
                'label' => 'Archived',
                'icon' => 'fa-solid fa-box-archive',
                'variant' => 'secondary',
            ],
            default => [
                'label' => ucwords(str_replace('_', ' ', strtolower($normalized ?: 'Update'))),
                'icon' => 'fa-solid fa-location-dot',
                'variant' => 'secondary',
            ],
        };
    }
}

if (!function_exists('createShipmentTimelineEvent')) {
    function createShipmentTimelineEvent(string $status, string $time, string $location, string $notes, ?string $icon = null, ?string $variant = null): array
    {
        $meta = getShipmentStatusMeta($status);

        return [
            'status' => $meta['label'],
            'raw_status' => $status,
            'time' => $time,
            'location' => $location,
            'notes' => $notes,
            'desc' => $notes,
            'icon' => $icon ?: $meta['icon'],
            'variant' => $variant ?: $meta['variant'],
            'completed' => true,
        ];
    }
}

if (!function_exists('buildFallbackShipmentTimeline')) {
    function buildFallbackShipmentTimeline(array $shipment): array
    {
        $createdAt = !empty($shipment['created_at']) ? strtotime($shipment['created_at']) : time();
        $updatedAt = !empty($shipment['updated_at']) ? strtotime($shipment['updated_at']) : $createdAt;
        $origin = $shipment['origin_address'] ?? 'Origin warehouse';
        $destination = $shipment['destination_address'] ?? ($shipment['specific_address'] ?? 'Destination');
        $status = normalizeShipmentStatus((string) ($shipment['status'] ?? 'PENDING'));
        $cancelReason = trim((string) ($shipment['cancel_reason'] ?? ($shipment['feedback_text'] ?? '')));

        $events = [
            [
                'timestamp' => $createdAt,
                'event' => createShipmentTimelineEvent(
                    'BOOKING_CREATED',
                    date('M d, Y g:i A', $createdAt),
                    $origin,
                    'Shipment request received and queued for dispatch planning.'
                ),
            ],
        ];

        if (in_array($status, ['READY_TO_DISPATCH', 'CONSOLIDATED', 'IN_TRANSIT', 'ARRIVED', 'OUT_FOR_DELIVERY', 'DELIVERED'], true)) {
            $events[] = [
                'timestamp' => max($createdAt + 3600, $createdAt),
                'event' => createShipmentTimelineEvent(
                    'CONSOLIDATED',
                    date('M d, Y g:i A', max($createdAt + 3600, $createdAt)),
                    $origin,
                    'Cargo was grouped and prepared for line-haul movement.'
                ),
            ];
        }

        if (in_array($status, ['IN_TRANSIT', 'ARRIVED', 'OUT_FOR_DELIVERY', 'DELIVERED'], true)) {
            $events[] = [
                'timestamp' => max($createdAt + 4 * 3600, $createdAt),
                'event' => createShipmentTimelineEvent(
                    'IN_TRANSIT',
                    date('M d, Y g:i A', max($createdAt + 4 * 3600, $createdAt)),
                    $destination,
                    'Shipment departed the sorting hub and is moving through the network.'
                ),
            ];
        }

        if (in_array($status, ['OUT_FOR_DELIVERY', 'DELIVERED'], true)) {
            $events[] = [
                'timestamp' => max($updatedAt - 2 * 3600, $createdAt + 5 * 3600),
                'event' => createShipmentTimelineEvent(
                    'OUT_FOR_DELIVERY',
                    date('M d, Y g:i A', max($updatedAt - 2 * 3600, $createdAt + 5 * 3600)),
                    $destination,
                    'Driver is on the final route toward the receiver location.'
                ),
            ];
        }

        if ($status === 'DELIVERED') {
            $events[] = [
                'timestamp' => $updatedAt,
                'event' => createShipmentTimelineEvent(
                    'DELIVERED',
                    date('M d, Y g:i A', $updatedAt),
                    $destination,
                    !empty($shipment['proof_image'])
                        ? 'Delivery completed and proof of delivery is attached.'
                        : 'Delivery completed. Proof of delivery is still pending upload.'
                ),
            ];
        } elseif ($status === 'CANCELLED') {
            $events[] = [
                'timestamp' => $updatedAt,
                'event' => createShipmentTimelineEvent(
                    'CANCELLED',
                    date('M d, Y g:i A', $updatedAt),
                    $destination,
                    $cancelReason !== ''
                        ? 'Cancellation reason: ' . $cancelReason
                        : 'Shipment was cancelled before completion.'
                ),
            ];
        } elseif ($status === 'ARCHIVED') {
            $events[] = [
                'timestamp' => $updatedAt,
                'event' => createShipmentTimelineEvent(
                    'ARCHIVED',
                    date('M d, Y g:i A', $updatedAt),
                    $destination,
                    'Shipment record was archived for long-term reference.'
                ),
            ];
        }

        usort($events, static function (array $left, array $right): int {
            return $right['timestamp'] <=> $left['timestamp'];
        });

        return array_map(static fn(array $item): array => $item['event'], $events);
    }
}

if (!function_exists('getShipmentTimeline')) {
    function getShipmentTimeline(mysqli $conn, array $shipment): array
    {
        ensureShipmentLogsTable($conn);

        $timeline = [];
        $trackingNo = getShipmentTrackingNo($shipment);
        $shipmentId = (int) ($shipment['id'] ?? 0);

        if ($shipmentId > 0) {
            $stmt = $conn->prepare(
                "SELECT status, location, notes, icon, logged_at
                 FROM shipment_logs
                 WHERE shipment_id = ? OR tracking_no = ?
                 ORDER BY logged_at DESC, id DESC"
            );

            if ($stmt) {
                $stmt->bind_param('is', $shipmentId, $trackingNo);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $timeValue = !empty($row['logged_at']) ? strtotime($row['logged_at']) : time();
                    $location = trim((string) ($row['location'] ?? ''));
                    $notes = trim((string) ($row['notes'] ?? ''));

                    $timeline[] = createShipmentTimelineEvent(
                        (string) ($row['status'] ?? 'Update'),
                        date('M d, Y g:i A', $timeValue),
                        $location !== '' ? $location : 'Shipment network',
                        $notes !== '' ? $notes : 'Status update recorded.',
                        !empty($row['icon']) ? (string) $row['icon'] : null
                    );
                }

                $stmt->close();
            }
        }

        if (!$timeline) {
            return buildFallbackShipmentTimeline($shipment);
        }

        $latestRawStatus = normalizeShipmentStatus((string) ($timeline[0]['raw_status'] ?? ''));
        $currentStatus = normalizeShipmentStatus((string) ($shipment['status'] ?? ''));

        if ($currentStatus !== '' && $latestRawStatus !== $currentStatus) {
            $location = in_array($currentStatus, ['DELIVERED', 'OUT_FOR_DELIVERY', 'CANCELLED', 'ARCHIVED'], true)
                ? (string) ($shipment['destination_address'] ?? 'Destination')
                : (string) ($shipment['origin_address'] ?? 'Origin hub');
            $notes = $currentStatus === 'CANCELLED'
                ? 'Cancellation reason: ' . trim((string) ($shipment['cancel_reason'] ?? ($shipment['feedback_text'] ?? 'No reason provided.')))
                : 'Latest shipment status synchronized from the main record.';

            array_unshift(
                $timeline,
                createShipmentTimelineEvent(
                    $currentStatus,
                    date('M d, Y g:i A', !empty($shipment['updated_at']) ? strtotime($shipment['updated_at']) : time()),
                    $location,
                    $notes
                )
            );
        }

        return $timeline;
    }
}

if (!function_exists('logShipmentEvent')) {
    function logShipmentEvent(mysqli $conn, int $shipmentId, string $trackingNo, string $status, ?string $location = null, ?string $notes = null, ?string $loggedAt = null, ?string $icon = null): bool
    {
        ensureShipmentLogsTable($conn);

        $loggedAt = $loggedAt ?: date('Y-m-d H:i:s');
        $stmt = $conn->prepare(
            "INSERT INTO shipment_logs (shipment_id, tracking_no, status, location, notes, icon, logged_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('issssss', $shipmentId, $trackingNo, $status, $location, $notes, $icon, $loggedAt);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}

