<?php
class NotificationController
{
    private Database $db;
    public function __construct() { $this->db = Database::getInstance(); }
    public function listForUser(int $accountId): array
    {
        return $this->db->query("SELECT id, title, message, link_url, read_at, DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s+08:00') AS created_at FROM notifications WHERE recipient_account_id = :account_id ORDER BY created_at DESC LIMIT 100", ['account_id' => $accountId])->fetchAll();
    }
    public function unreadCount(int $accountId): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM notifications WHERE recipient_account_id = :account_id AND read_at IS NULL', ['account_id' => $accountId])->fetchColumn();
    }
    public function latestUnreadForUser(int $accountId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));
        return $this->db->query(
            "SELECT id, title, message, link_url, read_at, DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s+08:00') AS created_at FROM notifications WHERE recipient_account_id = :account_id AND read_at IS NULL ORDER BY created_at DESC LIMIT " . $limit,
            ['account_id' => $accountId]
        )->fetchAll();
    }
    public function markRead(int $accountId, ?int $notificationId = null): void
    {
        $sql = 'UPDATE notifications SET read_at = COALESCE(read_at, CURRENT_TIMESTAMP) WHERE recipient_account_id = :account_id';
        $params = ['account_id' => $accountId];
        if ($notificationId !== null) { $sql .= ' AND id = :notification_id'; $params['notification_id'] = $notificationId; }
        $this->db->query($sql, $params);
    }
}