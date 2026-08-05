@php
    use Reels\Supports\ReelHelper;

    $feedUrl = ReelHelper::feedUrl();

    $tabs = [
        \Reels\Models\Reel::TAB_NEW  => trans('reels::web.tab.new'),
        \Reels\Models\Reel::TAB_VIEW => trans('reels::web.tab.view'),
        \Reels\Models\Reel::TAB_LIKE => trans('reels::web.tab.like'),
    ];

    $config = [
        'tab'       => $tab,
        'page'      => $reelsPage ?? 1,
        'hasMore'   => (bool) ($reelsHasMore ?? false),
        'feedUrl'   => $feedUrl,
        'openIndex' => (int) ($reelsOpenIndex ?? -1),
        'zalo'      => ReelHelper::zaloLink(),
        'facebook'  => ReelHelper::facebookLink(),
        'lang'      => [
            'view'     => trans('reels::web.action.view'),
            'like'     => trans('reels::web.action.like'),
            'mute'     => trans('reels::web.action.mute'),
            'unmute'   => trans('reels::web.action.unmute'),
            'zalo'     => trans('reels::web.action.zalo'),
            'facebook' => trans('reels::web.action.facebook'),
            'more'     => trans('reels::web.action.more'),
            'less'     => trans('reels::web.action.less'),
            'share'    => trans('reels::web.action.share'),
            'copied'   => trans('reels::web.action.copied'),
            'error'    => trans('reels::web.message.error'),
        ],
    ];
@endphp

<div class="reels-page"
     id="reels-page"
     data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE) }}">

    <div class="container">

        <h1 class="reels-page__title">{{ trans('reels::web.title') }}</h1>

        <div class="reels-tabs" role="tablist">
            @foreach($tabs as $key => $label)
                <a class="reels-tab {{ $tab === $key ? 'is-active' : '' }}"
                   href="{{ $feedUrl }}?tab={{ $key }}"
                   data-reel-tab="{{ $key }}"
                   role="tab"
                   aria-selected="{{ $tab === $key ? 'true' : 'false' }}">{{ $label }}</a>
            @endforeach
        </div>

        @if(hasItems($reels))
            <div class="reels-grid" id="reels-grid">
                {{-- Dùng view() thay cho @include: BladeOne không hiểu tiền tố
                     namespace trong @include, còn view() đi qua ViewResolver nên
                     theme vẫn override được từng partial. --}}
                @foreach($reels as $reel)
                    {!! view('reels::web/partials/card', ['reel' => $reel]) !!}
                @endforeach
            </div>

            <div class="reels-more">
                <button type="button" class="reels-more__btn" id="reels-more"
                        @if(empty($reelsHasMore)) hidden @endif>
                    {{ trans('reels::web.load_more') }}
                </button>
            </div>
        @else
            <p class="reels-empty">{{ trans('reels::web.empty') }}</p>
        @endif
    </div>

    <script type="application/json" id="reels-data">{!! json_encode($reelsPayload ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</div>

{!! view('reels::web/partials/viewer') !!}
