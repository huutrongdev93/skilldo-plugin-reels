@php
    use Reels\Supports\ReelHelper;

    /** @var \SkillDo\Cms\Form\Options $options */

    $layout = ($options->layout ?? 'slider') === 'grid' ? 'grid' : 'slider';

    $popup = ($options->clickAction ?? 'popup') === 'popup';

    $feedUrl = ReelHelper::feedUrl($tab === 'new' ? null : $tab);

    $config = [
        'preview' => !empty($options->preview),
        'popup'   => $popup,
        'feedUrl' => $feedUrl,
        'lang'    => [
            'like'   => trans('reels::web.action.like'),
            'view'   => trans('reels::web.action.view'),
            'mute'   => trans('reels::web.action.mute'),
            'unmute' => trans('reels::web.action.unmute'),
            'share'  => trans('reels::web.action.share'),
            'copied' => trans('reels::web.action.copied'),
            'close'  => trans('reels::web.action.close'),
            'prev'   => trans('reels::web.action.prev'),
            'next'   => trans('reels::web.action.next'),
            'all'    => trans('reels::web.view_all'),
            'error'  => trans('reels::web.message.error'),
        ],
    ];
@endphp

<div class="reels-el reels-el--{{ $layout }}" data-reels-element data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE) }}">

    @if(!empty($options->title))
        <h2 class="reels-el__heading">{!! $options->title !!}</h2>
    @endif

    <div class="reels-el__list">
        @foreach($reels as $index => $reel)
            @php
                $permalink  = $reel->getPermalink();
                $poster     = $reel->getPoster();
                $attachment = $reel->getAttachment();
            @endphp
            {{-- data-reel-src chỉ phục vụ phát thử ngay trên lưới; trình xem đọc
                 dữ liệu từ khối JSON ở cuối element. --}}
            <div class="reels-el__card"
                 data-reel-id="{{ $reel->id }}"
                 data-reel-index="{{ $index }}"
                 data-reel-source="{{ $reel->source }}"
                 data-reel-src="{{ $reel->getVideoSrc() }}">
                <div class="reels-el__media">
                    @if(!empty($poster))
                        <img class="reels-el__poster" src="{{ $poster }}" alt="{{ $reel->title }}" loading="lazy">
                    @endif

                    {{-- Link phủ kín khung ảnh, nằm DƯỚI lớp overlay để nút Mua ngay
                         bên trong overlay vẫn bấm được (thẻ <a> không lồng nhau). --}}
                    <a class="reels-el__link" href="{{ $permalink }}" data-reel-open="{{ $index }}" title="{{ $reel->title }}">
                        <span class="reels-el__play" aria-hidden="true"><i class="fas fa-play"></i></span>
                        <span class="reels-el__sr">{{ $reel->title }}</span>
                    </a>

                    @if(!empty($options->showViews))
                        <span class="reels-el__views">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                            <b data-reel-views="{{ $reel->id }}">{{ ReelHelper::shortNumber($reel->view_count) }}</b>
                        </span>
                    @endif

                    @if(!empty($options->showAttach) && hasItems($attachment))
                        <div class="reels-el__overlay">
                            {{-- Dùng view() thay @include: BladeOne không hiểu tiền tố
                                 namespace trong @include, còn view() đi qua ViewResolver
                                 nên theme vẫn override được partial này. --}}
                            {!! view('reels::web/partials/attachment-card', ['reel' => $reel]) !!}
                        </div>
                    @endif
                </div>

                @if(!empty($options->showName))
                    <a class="reels-el__name" href="{{ $permalink }}" data-reel-open="{{ $index }}">{{ $reel->title }}</a>
                @endif
            </div>
        @endforeach
    </div>

    @if($layout === 'slider')
        <button type="button" class="reels-el__nav reels-el__nav--prev" data-reel-prev aria-label="{{ trans('reels::web.action.prev') }}">
            <i class="fas fa-chevron-left" aria-hidden="true"></i>
        </button>
        <button type="button" class="reels-el__nav reels-el__nav--next" data-reel-next aria-label="{{ trans('reels::web.action.next') }}">
            <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </button>
    @endif

    @if(!empty($options->showMore))
        <div class="reels-el__more">
            <a class="reels-el__more-btn" href="{{ $feedUrl }}">{{ $options->moreText ?: trans('reels::web.view_all') }}</a>
        </div>
    @endif

    @if($popup)
        <script type="application/json" data-reels-items>{!! json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
</div>
