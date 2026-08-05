<?php

namespace Reels\Ajax\Admin;

use Reels\Models\Reel;
use SkillDo\Http\Request;
use Illuminate\Support\Facades\DB;

class ReelAjax
{
    public static function delete(Request $request): void
    {
        $ids = $request->input('data');

        if (empty($ids))
        {
            response()->error(trans('reels::admin.delete.empty'));
        }

        $ids = is_array($ids) ? $ids : [$ids];

        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (empty($ids))
        {
            response()->error(trans('reels::admin.delete.empty'));
        }

        if (Reel::whereIn('id', $ids)->remove() !== false)
        {
            // Dọn luôn lịch sử view/like, nếu không id được cấp lại sau này sẽ
            // thừa hưởng dedupe của video cũ và khách không tính view được nữa.
            DB::table('reels_reactions')->whereIn('reel_id', $ids)->delete();

            response()->success(trans('ajax.delete.success'));
        }

        response()->error(trans('ajax.delete.error'));
    }
}
