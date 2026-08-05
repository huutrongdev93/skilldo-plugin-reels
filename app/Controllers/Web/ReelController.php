<?php

namespace Reels\Controllers\Web;

use Reels\Models\Reel;
use Reels\Services\ReelService;
use Reels\Supports\ReelHelper;
use SkillDo\Cms\Controller;
use SkillDo\Cms\Support\Cms;
use SkillDo\Cms\Support\Theme;
use SkillDo\Http\Request;

class ReelController extends Controller
{
    public function index(Request $request)
    {
        $tab = ReelService::normalizeTab($request->input('tab'));

        $feed = ReelService::feed($tab, 1);

        $this->share($tab, $feed, null, 0);

        return Cms::view(
            apply_filters('template_view_reels_index', 'reels::web/index'),
            apply_filters('template_layout_reels_index', 'template-full-width')
        );
    }

    /**
     * Deep link tới một video: hiển thị đúng trang feed chứa nó và mở sẵn
     * viewer tại vị trí đó.
     */
    public function detail(Request $request, $reelSlug = '')
    {
        $reel = Reel::published()->where('slug', $reelSlug)->first();

        if (noItems($reel))
        {
            Theme::page404();
        }

        $tab = ReelService::normalizeTab($request->input('tab'));

        $position = ReelService::positionOf($reel, $tab);

        $perPage = ReelHelper::perPage();

        // Nạp đúng trang chứa video thay vì nạp từ trang 1: video thứ 500 mà
        // tải cả 500 bản ghi thì trang mở rất chậm.
        $page = intdiv($position, $perPage) + 1;

        $feed = ReelService::feed($tab, $page, $perPage);

        $this->share($tab, $feed, $reel, $position % $perPage);

        return Cms::view(
            apply_filters('template_view_reels_detail', 'reels::web/index'),
            apply_filters('template_layout_reels_detail', 'template-full-width')
        );
    }

    protected function share(string $tab, array $feed, ?Reel $reel, int $openIndex): void
    {
        Cms::setData('tab', $tab);

        Cms::setData('reels', $feed['items']);

        Cms::setData('reelsPayload', ReelService::payload($feed['items']));

        Cms::setData('reelsPage', $feed['page']);

        Cms::setData('reelsHasMore', $feed['hasMore']);

        Cms::setData('reelsTotal', $feed['total']);

        Cms::setData('reel', $reel);

        Cms::setData('reelsOpenIndex', $reel ? $openIndex : -1);
    }
}
