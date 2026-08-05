<?php

use SkillDo\Cms\Support\Ajax;

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Ajax::admin('Reels\Ajax\Admin\ReelAjax::delete', 'post');

/*
|--------------------------------------------------------------------------
| Frontend — dùng Ajax::client vì người xem không cần đăng nhập
|--------------------------------------------------------------------------
*/
Ajax::client('Reels\Ajax\Web\ReelAjax::load');
Ajax::client('Reels\Ajax\Web\ReelAjax::view', 'post');
Ajax::client('Reels\Ajax\Web\ReelAjax::like', 'post');
