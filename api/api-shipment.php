<?php
header("Content-Type: application/json");
include_once(__DIR__ . "/config.php");


requireRole('user'); 
// Helper to send JSON response
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);
    exit();
}

// Create (Book Shipment)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        sendResponse(false, "Invalid JSON input.");
    }

    $sender_name   = $conn->real_escape_string(trim($input['sender_name']));
    $receiver_name = $conn->real_escape_string(trim($input['receiver_name']));
    $origin        = $conn->real_escape_string(trim($input['origin']));
    $destination   = $conn->real_escape_string(trim($input['destination']));
    $weight        = $conn->real_escape_string(trim($input['weight']));
    $package       = $conn->real_escape_string(trim($input['package']));
    $user_id       = $_SESSION['user_id'];

    $sql = "INSERT INTO shipments 
            (user_id, sender_name, receiver_name, origin, destination, weight, package_description, status, created_at) 
            VALUES ('$user_id', '$sender_name', '$receiver_name', '$origin', '$destination', '$weight', '$package', 'Pending', NOW())";

    if ($conn->query($sql) === TRUE) {
        sendResponse(true, "Shipment booked successfully.");
    } else {
        sendResponse(false, "Error: " . $conn->error);
    }
}

// Read (Get all shipments for the logged-in user)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = $_SESSION['user_id'];
    $result = $conn->query("SELECT * FROM shipments WHERE user_id = '$user_id' ORDER BY created_at DESC");

    $shipments = [];
    while ($row = $result->fetch_assoc()) {
        $shipments[] = $row;
    }
    sendResponse(true, "Shipments retrieved", $shipments);
}
?>
