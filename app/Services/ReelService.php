<?php

namespace Reels\Services;

use Reels\Models\Reel;
use Reels\Supports\ReelHelper;
use Illuminate\Support\Facades\DB;

class ReelService
{
    public const TABS = [Reel::TAB_NEW, Reel::TAB_VIEW, Reel::TAB_LIKE];

    public static function normalizeTab($tab): string
    {
        return in_array($tab, static::TABS, true) ? $tab : Reel::TAB_NEW;
    }

    /**
     * Một trang của feed.
     *
     * @return array{items: mixed, total: int, page: int, perPage: int, hasMore: bool}
     */
    public static function feed(string $tab, int $page = 1, ?int $perPage = null): array
    {
        $tab = static::normalizeTab($tab);

        $page = max(1, $page);

        $perPage = $perPage ?: ReelHelper::perPage();

        $query = Reel::published();

        $total = (int) (clone $query)->count();

        $items = Reel::published()
            ->tab($tab)
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return [
            'items'   => $items,
            'total'   => $total,
            'page'    => $page,
            'perPage' => $perPage,
            'hasMore' => ($page * $perPage) < $total,
        ];
    }

    /**
     * Vị trí (0-based) của một video trong feed của tab, để mở deep link
     * đúng chỗ mà không phải tải toàn bộ danh sách.
     */
    public static function positionOf(Reel $reel, string $tab): int
    {
        $tab = static::normalizeTab($tab);

        $query = Reel::published();

        // Điều kiện "đứng trước" phải khớp đúng thứ tự sort của tab, nếu không
        // deep link sẽ mở nhầm video khác.
        match ($tab) {
            Reel::TAB_VIEW => $query->where(function ($q) use ($reel) {
                $q->where('view_count', '>', (int) $reel->view_count)
                  ->orWhere(function ($q2) use ($reel) {
                      $q2->where('view_count', (int) $reel->view_count)->where('id', '>', (int) $reel->id);
                  });
            }),
            Reel::TAB_LIKE => $query->where(function ($q) use ($reel) {
                $q->where('like_count', '>', (int) $reel->like_count)
                  ->orWhere(function ($q2) use ($reel) {
                      $q2->where('like_count', (int) $reel->like_count)->where('id', '>', (int) $reel->id);
                  });
            }),
            default => $query->where(function ($q) use ($reel) {
                $q->where('order', '<', (int) $reel->order)
                  ->orWhere(function ($q2) use ($reel) {
                      $q2->where('order', (int) $reel->order)->where('created', '>', $reel->created);
                  })
                  ->orWhere(function ($q2) use ($reel) {
                      $q2->where('order', (int) $reel->order)
                         ->where('created', $reel->created)
                         ->where('id', '>', (int) $reel->id);
                  });
            }),
        };

        return (int) $query->count();
    }

    /**
     * Dữ liệu tối giản cho JS dựng slide trong viewer.
     */
    public static function payload($items): array
    {
        $payload = [];

        foreach ($items as $reel)
        {
            $payload[] = [
                'id'      => (int) $reel->id,
                'title'   => (string) $reel->title,
                // Cột kiểu `wysiwyg` được lưu dạng htmlspecialchars — giải mã và
                // bỏ thẻ để JS gán bằng textContent, không dính lỗ hổng XSS.
                'excerpt' => trim(strip_tags(html_entity_decode((string) $reel->excerpt, ENT_QUOTES | ENT_HTML5))),
                'slug'    => (string) $reel->slug,
                'source'  => (string) $reel->source,
                'src'     => (string) $reel->getVideoSrc(),
                'poster'  => (string) $reel->getPoster(),
                'url'     => (string) $reel->getPermalink(),
                'views'   => (int) $reel->view_count,
                'likes'   => (int) $reel->like_count,
                'product' => (string) view('reels::web/partials/attachment-card', ['reel' => $reel]),
            ];
        }

        return $payload;
    }

    /**
     * Ghi nhận một tương tác. Trả về true khi đây là lần đầu của khách này —
     * chỉ khi đó counter mới được cộng.
     */
    public static function react(int $reelId, string $type, string $visitorKey): bool
    {
        // insertOrIgnore + unique index là chốt chặn thật sự: hai tab cùng bắn
        // một lúc thì chỉ một dòng vào được, khỏi cần transaction.
        $inserted = DB::table('reels_reactions')->insertOrIgnore([
            'reel_id'     => $reelId,
            'type'        => $type,
            'visitor_key' => $visitorKey,
            'created'     => gmdate('Y-m-d H:i:s', time() + 7 * 3600),
        ]);

        return $inserted > 0;
    }

    public static function unreact(int $reelId, string $type, string $visitorKey): bool
    {
        $deleted = DB::table('reels_reactions')
            ->where('reel_id', $reelId)
            ->where('type', $type)
            ->where('visitor_key', $visitorKey)
            ->delete();

        return $deleted > 0;
    }

    public static function hasReacted(int $reelId, string $type, string $visitorKey): bool
    {
        return DB::table('reels_reactions')
            ->where('reel_id', $reelId)
            ->where('type', $type)
            ->where('visitor_key', $visitorKey)
            ->exists();
    }

    public static function counter(int $reelId, string $column, int $step): int
    {
        $query = DB::table('reels')->where('id', $reelId);

        if ($step > 0)
        {
            $query->increment($column, $step);
        }
        else
        {
            // Kẹp sàn 0: unsignedInteger trừ dưới 0 sẽ ném lỗi ở MySQL strict mode.
            DB::table('reels')->where('id', $reelId)->where($column, '>', 0)->decrement($column, abs($step));
        }

        return (int) (DB::table('reels')->where('id', $reelId)->value($column) ?? 0);
    }
}
