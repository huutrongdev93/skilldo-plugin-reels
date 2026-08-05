{!! Admin::partial('resources/page-default/page-index', [
    'name'   => trans('reels::admin.name'),
    'module' => $module,
    'model'  => \Reels\Models\Reel::class,
    'table'  => $table,
]) !!}
