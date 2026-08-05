{{-- Khung viewer rỗng: các slide do reels.js dựng từ JSON để "xem thêm" chỉ
     cần nối thêm dữ liệu thay vì render lại HTML hai lần. --}}
<div class="reels-viewer" id="reels-viewer" hidden aria-hidden="true" role="dialog" aria-modal="true">

    <button type="button" class="reels-viewer__close" data-reel-close
            aria-label="{{ trans('reels::web.action.close') }}">
        <i class="fas fa-times" aria-hidden="true"></i>
    </button>

    <div class="reels-viewer__track" id="reels-viewer-track" tabindex="-1"></div>

    <button type="button" class="reels-viewer__nav reels-viewer__nav--prev" data-reel-prev
            aria-label="{{ trans('reels::web.action.prev') }}">
        <i class="fas fa-chevron-up" aria-hidden="true"></i>
    </button>

    <button type="button" class="reels-viewer__nav reels-viewer__nav--next" data-reel-next
            aria-label="{{ trans('reels::web.action.next') }}">
        <i class="fas fa-chevron-down" aria-hidden="true"></i>
    </button>
</div>
