@php
    /** @var \Reels\Models\Reel $reel */
    $permalink  = $reel->getPermalink();
    $poster     = $reel->getPoster();
    $attachment = $reel->getAttachment();
@endphp
<div class="reels-card" data-reel-id="{{ $reel->id }}">
    <div class="reels-card__media">
        @if(!empty($poster))
            <img src="{{ $poster }}" alt="{{ $reel->title }}" loading="lazy">
        @endif

        {{-- Link phủ kín khung ảnh, nằm DƯỚI lớp overlay để nút Mua ngay và
             link sản phẩm bên trong overlay vẫn bấm được (thẻ <a> cũng không
             được lồng nhau). --}}
        <a class="reels-card__link" href="{{ $permalink }}" data-reel-open title="{{ $reel->title }}">
            <span class="reels-card__play" aria-hidden="true"><i class="fas fa-play"></i></span>
            <span class="reels-sr-only">{{ $reel->title }}</span>
        </a>

        <span class="reels-card__views">
            <i class="fas fa-eye" aria-hidden="true"></i>
            <b data-reel-views="{{ $reel->id }}">{{ \Reels\Supports\ReelHelper::shortNumber($reel->view_count) }}</b>
        </span>

        <div class="reels-card__overlay">
            @if(hasItems($attachment))
                {!! view('reels::web/partials/attachment-card', ['reel' => $reel]) !!}
            @else
                {{-- Không gắn gì thì overlay hiện tiêu đề, tránh để card
                     trơ mỗi tấm ảnh không có ngữ cảnh gì. --}}
                <a class="reels-card__title" href="{{ $permalink }}" data-reel-open>{{ $reel->title }}</a>
            @endif
        </div>
    </div>
</div>
