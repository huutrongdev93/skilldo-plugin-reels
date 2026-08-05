<?php

namespace Reels\Ajax\Web;

use Reels\Models\Reel;
use Reels\Services\ReelService;
use Reels\Supports\ReelHelper;
use SkillDo\Http\Request;

class ReelAjax
{
    /**
     * Trang tiếp theo của feed: trả cả HTML cho lưới lẫn JSON cho viewer để
     * hai chỗ luôn cùng thứ tự, khỏi phải query lại lần hai.
     */
    public static function load(Request $request): void
    {
        $tab = ReelService::normalizeTab($request->input('tab'));

        $page = max(1, (int) $request->input('page'));

        $feed = ReelService::feed($tab, $page);

        $html = '';

        foreach ($feed['items'] as $index => $reel)
        {
            $html .= view('reels::web/partials/card', ['reel' => $reel]);
        }

        response()->success('', [
            'html'    => $html,
            'items'   => ReelService::payload($feed['items']),
            'page'    => $feed['page'],
            'hasMore' => $feed['hasMore'],
        ]);
    }

    public static function view(Request $request): void
    {
        $reel = static::find($request);

        $visitor = ReelHelper::visitorKey();

        $count = (int) $reel->view_count;

        if (ReelService::react((int) $reel->id, 'view', $visitor))
        {
            $count = ReelService::counter((int) $reel->id, 'view_count', 1);
        }

        response()->success('', [
            'id'    => (int) $reel->id,
            'count' => $count,
            'text'  => ReelHelper::shortNumber($count),
        ]);
    }

    public static function like(Request $request): void
    {
        $reel = static::find($request);

        $visitor = ReelHelper::visitorKey();

        $unlike = $request->input('act') === 'unlike';

        $count = (int) $reel->like_count;

        if ($unlike)
        {
            if (ReelService::unreact((int) $reel->id, 'like', $visitor))
            {
                $count = ReelService::counter((int) $reel->id, 'like_count', -1);
            }
        }
        else if (ReelService::react((int) $reel->id, 'like', $visitor))
        {
            $count = ReelService::counter((int) $reel->id, 'like_count', 1);
        }

        response()->success('', [
            'id'    => (int) $reel->id,
            'liked' => !$unlike,
            'count' => $count,
            'text'  => ReelHelper::shortNumber($count),
        ]);
    }

    protected static function find(Request $request): Reel
    {
        $id = (int) $request->input('id');

        $reel = ($id > 0) ? Reel::published()->whereKey($id)->select('id', 'view_count', 'like_count')->first() : null;

        if (noItems($reel))
        {
            response()->error(trans('reels::web.message.error'));
        }

        return $reel;
    }
}
