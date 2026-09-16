<?php

/**
 * Partner credit balances, their ledger, and "buy credits" requests.
 *
 * A balance is only ever changed by apply(), which locks the partner row and
 * writes the ledger row in the same transaction — two tabs spending at once
 * cannot both pass the balance check, and the history always adds up.
 */
class ArPartnerCredit extends BaseModel
{
    protected string $table = 'ar_partner_credit_ledger';

    public const TYPES = [
        'added'    => 'Added',
        'used'     => 'Used',
        'refunded' => 'Refunded',
        'adjusted' => 'Adjusted',
    ];

    public function db(): PDO
    {
        return $this->db;
    }

    /**
     * Change a partner's balance and record why.
     *
     * Must run inside a transaction the caller owns, so a debit can commit or
     * roll back together with the content it pays for. Refuses a debit that
     * would take the balance below zero unless $allowNegative (admin corrections).
     *
     * @param array{frame_id?: int|null, request_id?: int|null, created_by?: int|null} $refs
     * @return array{ok: bool, balance?: int, error?: string}
     */
    public function apply(int $partnerId, int $amount, string $type, string $description, array $refs = [], bool $allowNegative = false): array
    {
        if (!$this->db->inTransaction()) {
            throw new LogicException('ArPartnerCredit::apply() must run inside a transaction.');
        }

        $stmt = $this->db->prepare('SELECT credit_balance FROM ar_partners WHERE id = ? FOR UPDATE');
        $stmt->execute([$partnerId]);
        $balance = $stmt->fetchColumn();
        if ($balance === false) {
            return ['ok' => false, 'error' => 'That partner no longer exists.'];
        }

        $after = (int)$balance + $amount;
        if ($after < 0 && !$allowNegative) {
            return ['ok' => false, 'error' => sprintf(
                'This costs %s credits and the balance is %s — %s credits short.',
                number_format(-$amount), number_format((int)$balance), number_format(-$after)
            ), 'balance' => (int)$balance];
        }

        $this->db->prepare('UPDATE ar_partners SET credit_balance = ? WHERE id = ?')->execute([$after, $partnerId]);
        $this->insertInto('ar_partner_credit_ledger', [
            'partner_id'    => $partnerId,
            'type'          => isset(self::TYPES[$type]) ? $type : 'adjusted',
            'amount'        => $amount,
            'balance_after' => $after,
            'description'   => mb_substr($description, 0, 255),
            'frame_id'      => $refs['frame_id'] ?? null,
            'request_id'    => $refs['request_id'] ?? null,
            'created_by'    => $refs['created_by'] ?? null,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'balance' => $after];
    }

    /** apply() in a transaction of its own, for changes that pay for nothing else. */
    public function applyNow(int $partnerId, int $amount, string $type, string $description, array $refs = [], bool $allowNegative = false): array
    {
        $this->db->beginTransaction();
        try {
            $result = $this->apply($partnerId, $amount, $type, $description, $refs, $allowNegative);
            if (empty($result['ok'])) {
                $this->db->rollBack();
                return $result;
            }
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function ledger(int $partnerId, int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ar_partner_credit_ledger WHERE partner_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit)
        );
        $stmt->execute([$partnerId]);
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------- requests

    public function createRequest(int $partnerId, int $price, int $credits, ?int $requestedBy): int
    {
        return $this->insertInto('ar_partner_credit_requests', [
            'partner_id'   => $partnerId,
            'price'        => $price,
            'credits'      => $credits,
            'requested_by' => $requestedBy,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public function requests(int $partnerId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.name AS requested_by_name
             FROM ar_partner_credit_requests r
             LEFT JOIN ar_partner_users u ON u.id = r.requested_by
             WHERE r.partner_id = ? ORDER BY r.id DESC LIMIT ' . max(1, $limit)
        );
        $stmt->execute([$partnerId]);
        return $stmt->fetchAll();
    }

    public function pendingCount(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM ar_partner_credit_requests WHERE status = 'pending'")->fetchColumn();
    }

    public function hasRecentPending(int $partnerId, int $price, int $minutes = 10): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM ar_partner_credit_requests
             WHERE partner_id = ? AND price = ? AND status = 'pending' AND created_at > ? LIMIT 1"
        );
        $stmt->execute([$partnerId, $price, date('Y-m-d H:i:s', time() - $minutes * 60)]);
        return $stmt->fetch() !== false;
    }

    /** The request row, locked, if it belongs to the partner. Call inside a transaction. */
    public function lockRequest(int $partnerId, int $requestId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ar_partner_credit_requests WHERE id = ? AND partner_id = ? FOR UPDATE');
        $stmt->execute([$requestId, $partnerId]);
        return $stmt->fetch() ?: null;
    }

    public function updateRequest(int $id, array $data): bool
    {
        return $this->updateTable('ar_partner_credit_requests', $id, $data);
    }
}
