{!! Admin::partial('resources/page-default/page-save', [
    'module' => $module,
    'model'  => \Reels\Models\Reel::class,
    'object' => $object ?? [],
]) !!}
