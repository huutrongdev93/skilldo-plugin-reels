<?php

use SkillDo\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Trang video ngắn
|--------------------------------------------------------------------------
| Slug lấy từ config chứ không phải Option: file route được nạp rất sớm,
| bảng `system` chưa chắc sẵn sàng ở thời điểm đó.
|
| Hai route này phải nằm trước catch-all /{slug} của CmsRouteServiceProvider —
| route của plugin được đăng ký trước nên thứ tự đã đúng sẵn.
*/
Route::localized(function ()
{
    $slug = config('reels::config.slug', 'video');

    $controller = \Reels\Controllers\Web\ReelController::class;

    Route::get($slug, [$controller, 'index'])->name('reels.index');

    Route::get($slug . '/{reelSlug}', [$controller, 'detail'])->name('reels.detail');
});
