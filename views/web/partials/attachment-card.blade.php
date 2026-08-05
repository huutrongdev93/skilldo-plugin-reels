@php
    /**
     * Khối đính kèm dưới video — dùng chung cho sản phẩm và item tự nhập.
     * Reel::getAttachment() đã chuẩn hoá về một dạng nên view không cần biết
     * nguồn gốc, và site không có sicommerce vẫn dùng được đúng markup này.
     *
     * @var \Reels\Models\Reel $reel
     */
    $attachment = $reel->getAttachment();
@endphp
@if(hasItems($attachment))
    <div class="reels-product {{ empty($attachment['image']) ? 'reels-product--no-thumb' : '' }}">
        @if(!empty($attachment['image']))
            <a class="reels-product__thumb" href="{{ $attachment['url'] }}" title="{{ $attachment['title'] }}">
                <img src="{{ $attachment['image'] }}" alt="{{ $attachment['title'] }}" loading="lazy">
            </a>
        @endif
        <div class="reels-product__info">
            <a class="reels-product__name" href="{{ $attachment['url'] }}">{{ $attachment['title'] }}</a>
            @if($attachment['price'] !== '' || $attachment['priceOld'] !== '')
                <div class="reels-product__price">
                    @if($attachment['price'] !== '')
                        <span class="reels-product__price-now">{!! $attachment['price'] !!}</span>
                    @endif
                    @if($attachment['priceOld'] !== '')
                        <del class="reels-product__price-old">{!! $attachment['priceOld'] !!}</del>
                    @endif
                </div>
            @endif
        </div>
        <a class="reels-product__buy" href="{{ $attachment['url'] }}">{{ $attachment['button'] }}</a>
    </div>
@endif
