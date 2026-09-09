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
        $assignment = $this->getAssignment($assignmentId, $junkshopAccountId);
        if ($assignment === null) {
            return ['success' => false, 'message' => 'Assignment not found.'];
        }

        try {
            $statement = $this->db->query(
                'UPDATE junkshop_assignments SET status = :status, responded_at = CURRENT_TIMESTAMP WHERE id = :id AND junkshop_id = :junkshop_id AND status = :expected_status',
                ['status' => 'Accepted', 'id' => $assignmentId, 'junkshop_id' => $junkshopAccountId, 'expected_status' => 'Matched']
            );
            if ($statement->rowCount() !== 1) {
                return ['success' => false, 'message' => 'Assignment is no longer available.'];
            }

            $this->db->query(
                'UPDATE pickup_requests SET current_status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['status' => 'Accepted', 'id' => $assignment['pickup_request_id']]
            );

            StatusLogger::logChange((int) $assignment['pickup_request_id'], 'Matched', 'Accepted', 'Junkshop', (int) $assignment['junkshop_id']);

            return ['success' => true, 'message' => 'Pickup request accepted.', 'assignment' => $assignment];
        } catch (Throwable $e) {
            error_log('Accept request error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to accept the pickup request.'];
        }
    }

    /**
     * Decline a matched pickup assignment and rematch the next closest junkshop when available.
     */
    public function declineRequest(int $assignmentId, int $junkshopAccountId): array
    {
        $assignment = $this->getAssignment($assignmentId, $junkshopAccountId);
        if ($assignment === null) {
            return ['success' => false, 'message' => 'Assignment not found.'];
        }

        try {
            $statement = $this->db->query(
                'UPDATE junkshop_assignments SET status = :status, responded_at = CURRENT_TIMESTAMP WHERE id = :id AND junkshop_id = :junkshop_id AND status = :expected_status',
                ['status' => 'Declined', 'id' => $assignmentId, 'junkshop_id' => $junkshopAccountId, 'expected_status' => 'Matched']
            );
            if ($statement->rowCount() !== 1) {
                return ['success' => false, 'message' => 'Assignment is no longer available.'];
            }

            $materialIds = $this->getMaterialIdsForRequest((int) $assignment['pickup_request_id']);
            if (empty($materialIds)) {
                $this->db->query(
                    'UPDATE pickup_requests SET current_status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                    ['status' => 'Cancelled by Junkshop', 'id' => $assignment['pickup_request_id']]
                );

                StatusLogger::logChange((int) $assignment['pickup_request_id'], 'Matched', 'Cancelled by Junkshop', 'Junkshop', $junkshopAccountId);

                return ['success' => true, 'message' => 'Request was declined and has no rematchable material data. Booking cancelled.', 'assignment' => $assignment];
            }

            $matches = MatchingEngine::findMatches(
                (int) $assignment['pickup_request_id'],
                $materialIds,
                $this->getApproximateDistance((int) $assignment['pickup_request_id'])
            );

            if (empty($matches)) {
                $this->db->query(
                    'UPDATE pickup_requests SET current_status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                    ['status' => 'Cancelled by Junkshop', 'id' => $assignment['pickup_request_id']]
                );

                StatusLogger::logChange((int) $assignment['pickup_request_id'], 'Matched', 'Cancelled by Junkshop', 'Junkshop', $junkshopAccountId);

                return ['success' => true, 'message' => 'No other eligible junkshops were available. Request cancelled.', 'assignment' => $assignment];
            }

            $nextMatch = $matches[0];
            $this->db->query(
                'INSERT INTO junkshop_assignments (pickup_request_id, junkshop_id, status, distance_km, assigned_at, responded_at) VALUES (:pickup_request_id, :junkshop_id, :status, :distance_km, CURRENT_TIMESTAMP, NULL)',
                [
                    'pickup_request_id' => $assignment['pickup_request_id'],
                    'junkshop_id' => $nextMatch['junkshop_id'],
                    'status' => 'Matched',
                    'distance_km' => number_format((float) $nextMatch['distance_km'], 2, '.', ''),
                ]
            );

            $this->db->query(
                'UPDATE pickup_requests SET current_status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['status' => 'Declined', 'id' => $assignment['pickup_request_id']]
            );
            StatusLogger::logChange((int) $assignment['pickup_request_id'], 'Matched', 'Declined', 'Junkshop', $junkshopAccountId);

            $this->db->query(
                'UPDATE pickup_requests SET current_status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['status' => 'Rematched', 'id' => $assignment['pickup_request_id']]
            );

            StatusLogger::logChange((int) $assignment['pickup_request_id'], 'Declined', 'Rematched', 'System');
            $this->db->query(
                'UPDATE pickup_requests SET current_status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['status' => 'Matched', 'id' => $assignment['pickup_request_id']]
            );
            StatusLogger::logChange((int) $assignment['pickup_request_id'], 'Rematched', 'Matched', 'System');

            return [
                'success' => true,
                'message' => 'Request declined and rematched.',
                'new_assignment' => [
                    'junkshop_id' => $nextMatch['junkshop_id'],
                    'distance_km' => $nextMatch['distance_km'],
                ],
                'assignment' => $assignment,
            ];
        } catch (Throwable $e) {
            error_log('Decline request error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to process the decline decision.'];
        }
    }

    private function getAssignment(int $assignmentId, int $junkshopAccountId): ?array
    {
        $row = $this->db->query(
            'SELECT id, pickup_request_id, junkshop_id, status, distance_km, assigned_at, responded_at FROM junkshop_assignments WHERE id = :id AND junkshop_id = :junkshop_id LIMIT 1',
            ['id' => $assignmentId, 'junkshop_id' => $junkshopAccountId]
        )->fetch();

        return $row && (string) $row['status'] === 'Matched' ? $row : null;
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
