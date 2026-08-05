<?php

namespace Reels\Controllers\Admin;

use Admin\Supports\FormAdminHelper;
use Reels\Models\Reel;
use Reels\Modules\Admin\Reel\Table;
use SkillDo\Cms\Controller;
use SkillDo\Cms\Support\Admin;
use SkillDo\Cms\Support\Cms;
use SkillDo\Http\Request;

class ReelController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        Cms::setData('module', 'reels');
    }

    public function index(Request $request)
    {
        Cms::setData('table', new Table());

        return Cms::view('reels::admin/reel/index');
    }

    public function add(Request $request)
    {
        Cms::setData('form', FormAdminHelper::getForm('reels'));

        return Cms::view('reels::admin/reel/save');
    }

    public function edit(Request $request, $id = '')
    {
        $object = Reel::find($id);

        if (noItems($object))
        {
            return Admin::pageNotFound();
        }

        Cms::setData('object', $object);

        Cms::setData('form', FormAdminHelper::getForm('reels', $object));

        return Cms::view('reels::admin/reel/save');
    }
}
