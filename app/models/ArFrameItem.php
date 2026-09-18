<?php

/**
 * One photo/video pair inside a Living Photo frame.
 *
 * A frame is the gift and its QR sticker; items are what the recipient points
 * the camera at. Each item has its own compiled target and its own live test,
 * because one photo matching proves nothing about the others.
 */
class ArFrameItem extends BaseModel
{
    protected string $table = 'ar_frame_items';

    /**
     * Every item's target is downloaded when the sticker is scanned, and the
     * tracker checks each camera frame against all of them, so both the wait
     * and the per-frame work grow with this number.
     */
    public const MAX_PER_FRAME = 8;

    /** How the video appears once the photo is found. */
    public const PLAYBACK_MODES = [
        'fullscreen' => 'Full screen',
        'overlay'    => 'On the photo (DEx overlay)',
    ];

    public static function playbackMode(?string $mode): string
    {
        return isset(self::PLAYBACK_MODES[(string)$mode]) ? (string)$mode : 'fullscreen';
    }

    /** Items in display order, which is also the order they are bundled in. */
    public function forFrame(int $frameId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM ar_frame_items WHERE frame_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$frameId]);
        return $stmt->fetchAll();
    }

    /** An item, only if it belongs to the given frame — ids in URLs are never trusted on their own. */
    public function findForFrame(int $frameId, int $itemId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ar_frame_items WHERE id = ? AND frame_id = ? LIMIT 1');
        $stmt->execute([$itemId, $frameId]);
        return $stmt->fetch() ?: null;
    }

    public function countForFrame(int $frameId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM ar_frame_items WHERE frame_id = ?');
        $stmt->execute([$frameId]);
        return (int)$stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        if (!isset($data['sort_order'])) {
            $stmt = $this->db->prepare('SELECT COALESCE(MAX(sort_order) + 1, 0) FROM ar_frame_items WHERE frame_id = ?');
            $stmt->execute([(int)$data['frame_id']]);
            $data['sort_order'] = (int)$stmt->fetchColumn();
        }
        return $this->insertInto('ar_frame_items', $data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->updateTable('ar_frame_items', $id, $data);
    }

    /** Record a passed live test for every item of a frame that has a target. */
    public function markAllVerified(int $frameId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE ar_frame_items SET verified_at = NOW() WHERE frame_id = ? AND target_path IS NOT NULL'
        );
        $stmt->execute([$frameId]);
    }
}
