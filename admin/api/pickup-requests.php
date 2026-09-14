<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/controllers/PickupRequestController.php';

header('Content-Type: application/json; charset=UTF-8');
if (!Auth::check()) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.', 'session_expired' => true, 'redirect' => APP_URL . '/admin/login.php', 'data' => ['requests' => []]], JSON_UNESCAPED_UNICODE); exit; }
if (Auth::userRole() !== 'admin') { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Admin access required.', 'data' => ['requests' => []]], JSON_UNESCAPED_UNICODE); exit; }
session_write_close();
$requests = (new PickupRequestController())->listAdminPendingRequests();
echo json_encode(['success' => true, 'message' => 'Pending pickup requests loaded.', 'data' => ['requests' => $requests], 'validation_errors' => [], 'timestamp' => time()], JSON_UNESCAPED_UNICODE);
