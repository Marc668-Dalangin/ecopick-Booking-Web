<?php
/**
 * Controller for junkshop assignment lifecycle actions.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../services/MatchingEngine.php';

class JunkshopAssignmentController
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Accept a matched pickup assignment.
     */
    public function acceptRequest(int $assignmentId, int $junkshopAccountId): array
    {
        $request = $this->getDirectPickupRequest($assignmentId, $junkshopAccountId);
        if ($request === null) {
            return ['success' => false, 'message' => 'Pickup request not found for this junkshop.'];
        }

        try {
            $previousStatus = (string) ($request['current_status'] ?? 'Pending Request');
            $statement = $this->db->query(
                'UPDATE pickup_requests SET current_status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND junkshop_id = :junkshop_id AND current_status IN (:pending_status, :pending_legacy_status, :matched_status)',
                [
                    'status' => 'Accepted',
                    'id' => $assignmentId,
                    'junkshop_id' => $junkshopAccountId,
                    'pending_status' => 'Pending Request',
                    'pending_legacy_status' => 'Pending',
                    'matched_status' => 'Matched',
                ]
            );
            if ($statement->rowCount() !== 1) {
                return ['success' => false, 'message' => 'This request is no longer available to accept.'];
            }

            StatusLogger::logChange((int) $assignmentId, $previousStatus === '' ? 'Pending Request' : $previousStatus, 'Accepted', 'Junkshop', (int) $junkshopAccountId);

            return ['success' => true, 'message' => 'Pickup request accepted.', 'assignment' => $request];
        } catch (Throwable $e) {
            error_log('Accept request error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to accept the pickup request.'];
        }
    }

    /**
     * Decline a pickup request for the selected junkshop.
     */
    public function declineRequest(int $assignmentId, int $junkshopAccountId): array
    {
        $request = $this->getDirectPickupRequest($assignmentId, $junkshopAccountId);
        if ($request === null) {
            return ['success' => false, 'message' => 'Pickup request not found for this junkshop.'];
        }

        try {
            $previousStatus = (string) ($request['current_status'] ?? 'Pending Request');
            $statement = $this->db->query(
                'UPDATE pickup_requests SET current_status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND junkshop_id = :junkshop_id AND current_status IN (:pending_status, :pending_legacy_status, :matched_status)',
                [
                    'status' => 'Declined',
                    'id' => $assignmentId,
                    'junkshop_id' => $junkshopAccountId,
                    'pending_status' => 'Pending Request',
                    'pending_legacy_status' => 'Pending',
                    'matched_status' => 'Matched',
                ]
            );
            if ($statement->rowCount() !== 1) {
                return ['success' => false, 'message' => 'This request is no longer available to decline.'];
            }

            StatusLogger::logChange((int) $assignmentId, $previousStatus === '' ? 'Pending Request' : $previousStatus, 'Declined', 'Junkshop', (int) $junkshopAccountId);

            return ['success' => true, 'message' => 'Pickup request declined.', 'assignment' => $request];
        } catch (Throwable $e) {
            error_log('Decline request error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to process the decline decision.'];
        }
    }

    private function getDirectPickupRequest(int $pickupRequestId, int $junkshopAccountId): ?array
    {
        $row = $this->db->query(
            'SELECT id AS pickup_request_id, id AS assignment_id, junkshop_id, current_status, booking_reference FROM pickup_requests WHERE id = :id AND junkshop_id = :junkshop_id LIMIT 1',
            ['id' => $pickupRequestId, 'junkshop_id' => $junkshopAccountId]
        )->fetch();

        return $row ?: null;
    }

    private function getMaterialIdsForRequest(int $pickupRequestId): array
    {
        $row = $this->db->query(
            'SELECT material_id FROM pickup_request_items WHERE pickup_request_id = :pickup_request_id ORDER BY id ASC',
            ['pickup_request_id' => $pickupRequestId]
        )->fetchAll();

        return array_values(array_unique(array_map(
            static fn (array $item): int => (int) $item['material_id'],
            $row
        )));
    }

    private function getApproximateDistance(int $pickupRequestId): float
    {
        $row = $this->db->query(
            'SELECT approximate_distance_km FROM pickup_requests WHERE id = :pickup_request_id LIMIT 1',
            ['pickup_request_id' => $pickupRequestId]
        )->fetch();

        return max(0.0, (float) ($row['approximate_distance_km'] ?? 0.0));
    }
}
