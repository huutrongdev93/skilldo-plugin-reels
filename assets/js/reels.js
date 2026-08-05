/**
 * Video Reels — lưới video + trình xem full màn hình kiểu TikTok.
 *
 * Không dùng thư viện ngoài: việc vuốt/cuộn giao hết cho CSS scroll-snap,
 * JS chỉ lo phát/dừng video, đếm lượt và nạp thêm dữ liệu.
 */
(function () {
    'use strict';

    var page = document.getElementById('reels-page');

    if (!page) return;

    var cfg = {};

    try { cfg = JSON.parse(page.getAttribute('data-config') || '{}'); } catch (e) { cfg = {}; }

    var items = [];

    var dataNode = document.getElementById('reels-data');

    if (dataNode) {
        try { items = JSON.parse(dataNode.textContent || '[]'); } catch (e) { items = []; }
    }

    var grid    = document.getElementById('reels-grid');
    var moreBtn = document.getElementById('reels-more');
    var viewer  = document.getElementById('reels-viewer');
    var track   = document.getElementById('reels-viewer-track');

    var lang = cfg.lang || {};

    var PRELOAD  = 2;    // số slide mỗi phía còn giữ thẻ <video>
    var VIEW_MS  = 3000; // xem đủ 3 giây mới tính một lượt

    // Chờ đủ lâu để mạng chậm kịp phát, đủ ngắn để người dùng không nhìn màn
    // hình chết trân rồi tưởng web hỏng
    var PLAY_CHECK_MS = 1800;

    /* ------------------------------------------------------------------ */
    /* Tiện ích                                                            */
    /* ------------------------------------------------------------------ */

    var store = {
        get: function (key, fallback) {
            try { var v = window.localStorage.getItem(key); return v === null ? fallback : v; }
            catch (e) { return fallback; }
        },
        set: function (key, value) {
            try { window.localStorage.setItem(key, value); } catch (e) { /* private mode */ }
        }
    };

    var state = {
        tab:     cfg.tab || 'new',
        page:    cfg.page || 1,
        hasMore: !!cfg.hasMore,
        loading: false,
        index:   -1,
        open:    false,
        muted:   store.get('reels_muted', '1') !== '0'
    };

    var slides    = [];
    var viewTimer = null;
    var playTimer = null;
    var observer  = null;

    function likedSet() {
        var raw = store.get('reels_liked', '[]');
        try { var arr = JSON.parse(raw); return Array.isArray(arr) ? arr : []; }
        catch (e) { return []; }
    }

    function isLiked(id) {
        return likedSet().indexOf(id) !== -1;
    }

    function setLiked(id, liked) {
        var arr = likedSet();
        var at  = arr.indexOf(id);

        if (liked && at === -1) arr.push(id);
        if (!liked && at !== -1) arr.splice(at, 1);

        store.set('reels_liked', JSON.stringify(arr));
    }

    function shortNumber(n) {
        n = parseInt(n, 10) || 0;

        if (n < 1000) return String(n);

        var value = n < 1000000 ? n / 1000 : n / 1000000;
        var unit  = n < 1000000 ? 'K' : 'M';

        return String(Math.round(value * 10) / 10) + unit;
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-value"]');

        return meta ? meta.content : '';
    }

    /**
     * Gọi ajax dùng `request` (axios đã gắn CSRF) của theme, fallback fetch
     * để plugin vẫn chạy trên theme không nạp biến toàn cục đó.
     *
     * Gửi dạng x-www-form-urlencoded chứ không phải JSON: dispatcher /admin/ajax
     * đọc `action` từ input bag, body JSON không tới được nó.
     */
    function post(payload) {
        var url = (typeof ajax !== 'undefined') ? ajax : '/admin/ajax';

        var body = new URLSearchParams();

        Object.keys(payload).forEach(function (key) { body.append(key, payload[key]); });

        if (typeof request !== 'undefined' && request && typeof request.post === 'function') {
            return request
                .post(url, body.toString(), {
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                })
                .then(unwrap);
        }

        return fetch(url, {
            method:      'POST',
            body:        body.toString(),
            credentials: 'same-origin',
            headers:     {
                'Content-Type':     'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':     csrfToken()
            }
        }).then(function (r) { return r.json(); });
    }

    /**
     * Theme cài interceptor trả thẳng body, axios thuần thì body nằm ở res.data.
     * Nhận diện bằng khoá `status` của envelope thay vì đoán theo thư viện.
     */
    function unwrap(res) {
        if (res && typeof res.status === 'string') return res;

        return (res && res.data) ? res.data : res;
    }

    function feedUrl() {
        return cfg.feedUrl + (state.tab && state.tab !== 'new' ? '?tab=' + state.tab : '');
    }

    /* ------------------------------------------------------------------ */
    /* Dựng slide                                                          */
    /* ------------------------------------------------------------------ */

    function buildSlide(item) {
        var slide = document.createElement('div');
        slide.className = 'reels-slide';
        slide.setAttribute('data-reel-id', item.id);

        if (item.poster) {
            var bg = document.createElement('div');
            bg.className = 'reels-slide__bg';
            bg.style.backgroundImage = 'url(' + item.poster + ')';
            slide.appendChild(bg);
        }

        var media = document.createElement('div');
        media.className = 'reels-slide__media';

        // Trước khi được "mount", slide chỉ có poster — giữ hàng chục thẻ
        // <video> sống cùng lúc là cách nhanh nhất để treo trình duyệt mobile.
        if (item.poster) {
            var poster = document.createElement('img');
            poster.className = 'reels-slide__poster';
            poster.src = item.poster;
            poster.alt = item.title || '';
            media.appendChild(poster);
        }

        var info = document.createElement('div');
        info.className = 'reels-slide__info';

        var title = document.createElement('p');
        title.className = 'reels-slide__title';
        title.textContent = item.title || '';
        info.appendChild(title);

        if (item.excerpt) {
            info.appendChild(buildDesc(item.excerpt));
        }

        if (item.product) {
            var box = document.createElement('div');
            box.innerHTML = item.product;          // HTML do server render, đã escape
            while (box.firstChild) info.appendChild(box.firstChild);
        }

        // Biểu tượng ▶ hiện giữa video khi đang tạm dừng
        var indicator = document.createElement('div');
        indicator.className = 'reels-slide__playpause';
        indicator.setAttribute('aria-hidden', 'true');
        indicator.innerHTML = '<i class="fas fa-play"></i>';
        media.appendChild(indicator);

        // Thông tin và cột nút nằm TRONG khung video, không phải trong slide:
        // trên desktop khung video chỉ rộng 9/16 chiều cao màn hình, gắn ra
        // ngoài thì nút trôi ra tận mép màn hình, cách video cả gang tay.
        media.appendChild(info);
        media.appendChild(buildActions(item));

        slide.appendChild(media);

        return slide;
    }

    /**
     * Mô tả cắt 2 dòng, bấm vào mới mở hết — giống TikTok. Nút "xem thêm" chỉ
     * hiện khi chữ thực sự bị tràn, đo ở activate() lúc slide đã có kích thước.
     */
    function buildDesc(text) {
        var wrap = document.createElement('div');
        wrap.className = 'reels-slide__desc';
        wrap.setAttribute('data-reel-desc', '');

        var body = document.createElement('span');
        body.className = 'reels-slide__desc-text';
        body.textContent = text;
        wrap.appendChild(body);

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'reels-slide__desc-toggle';
        toggle.textContent = lang.more || '';
        wrap.appendChild(toggle);

        return wrap;
    }

    function syncDesc(index) {
        var slide = slides[index];

        if (!slide) return;

        var wrap = slide.querySelector('[data-reel-desc]');

        if (!wrap || wrap.classList.contains('is-open')) return;

        var body = wrap.querySelector('.reels-slide__desc-text');

        // scrollHeight > clientHeight => line-clamp đang cắt bớt chữ
        wrap.classList.toggle('has-more', body.scrollHeight - body.clientHeight > 1);
    }

    function toggleDesc(wrap) {
        var open = !wrap.classList.contains('is-open');

        wrap.classList.toggle('is-open', open);

        var toggle = wrap.querySelector('.reels-slide__desc-toggle');

        if (toggle) toggle.textContent = open ? (lang.less || '') : (lang.more || '');
    }

    function buildActions(item) {
        var wrap = document.createElement('div');
        wrap.className = 'reels-actions';

        wrap.appendChild(actionButton({
            className: 'reels-action reels-action--view',
            icon:      'fas fa-eye',
            label:     shortNumber(item.views),
            title:     lang.view,
            tag:       'div',
            role:      'views'
        }));

        var liked = isLiked(item.id);

        wrap.appendChild(actionButton({
            className: 'reels-action reels-action--like' + (liked ? ' is-active' : ''),
            icon:      'fas fa-heart',
            label:     shortNumber(item.likes),
            title:     lang.like,
            role:      'like'
        }));

        wrap.appendChild(actionButton({
            className: 'reels-action reels-action--share',
            icon:      'fas fa-share',
            label:     lang.share,
            title:     lang.share,
            role:      'share'
        }));

        wrap.appendChild(actionButton({
            className: 'reels-action reels-action--sound',
            icon:      state.muted ? 'fas fa-volume-mute' : 'fas fa-volume-up',
            label:     '',
            title:     state.muted ? lang.unmute : lang.mute,
            role:      'sound'
        }));

        if (cfg.zalo) {
            wrap.appendChild(actionButton({
                className: 'reels-action reels-action--zalo',
                icon:      'fas fa-comment-dots',
                label:     'Zalo',
                title:     lang.zalo,
                tag:       'a',
                href:      cfg.zalo
            }));
        }

        if (cfg.facebook) {
            wrap.appendChild(actionButton({
                className: 'reels-action reels-action--facebook',
                icon:      'fab fa-facebook-messenger',
                label:     'Messenger',
                title:     lang.facebook,
                tag:       'a',
                href:      cfg.facebook
            }));
        }

        return wrap;
    }

    function actionButton(opts) {
        var el = document.createElement(opts.tag || 'button');

        if (!opts.tag || opts.tag === 'button') el.type = 'button';

        el.className = opts.className;

        if (opts.title) el.setAttribute('aria-label', opts.title);
        if (opts.title) el.title = opts.title;
        if (opts.role)  el.setAttribute('data-reel-action', opts.role);

        if (opts.href) {
            el.href = opts.href;
            el.target = '_blank';
            el.rel = 'noopener noreferrer';
        }

        var icon = document.createElement('span');
        icon.className = 'reels-action__icon';
        icon.innerHTML = '<i class="' + opts.icon + '" aria-hidden="true"></i>';
        el.appendChild(icon);

        var text = document.createElement('span');
        text.className = 'reels-action__text';
        text.textContent = opts.label || '';
        el.appendChild(text);

        return el;
    }

    function appendSlides(from) {
        for (var i = from; i < items.length; i++) {
            var slide = buildSlide(items[i]);

            track.appendChild(slide);

            slides.push(slide);

            if (observer) observer.observe(slide);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Phát / dừng                                                         */
    /* ------------------------------------------------------------------ */

    function mount(index, autoplay) {
        var slide = slides[index];
        var item  = items[index];

        if (!slide || !item || slide.getAttribute('data-mounted') === '1') return;

        var media = slide.querySelector('.reels-slide__media');

        if (item.source === 'youtube') {
            if (!item.src) return;

            var iframe = document.createElement('iframe');

            // autoplay nằm ngay trong URL cho slide đang xem. Trước đây luôn
            // autoplay=0 rồi bắn postMessage('playVideo') ngay sau khi tạo
            // iframe — lệnh đó rơi vào khoảng trắng vì player YouTube chưa
            // load xong, nên video ĐẦU TIÊN không chạy; các video sau chạy
            // được chỉ vì iframe của chúng đã được preload từ lúc trước.
            //
            // mute=1 là bắt buộc: trình duyệt chỉ cho autoplay khi không tiếng.
            // Muốn có tiếng thì play() gửi unMute sau, lúc player đã sẵn sàng.
            // Cố tình KHÔNG dùng loop=1&playlist=ID: embed dạng playlist bị
            // mobile chặn tự phát gắt hơn hẳn. Lặp lại được xử lý tay khi
            // player báo trạng thái "đã hết" (xem onYoutubeMessage).
            iframe.src = 'https://www.youtube-nocookie.com/embed/' + item.src
                + '?enablejsapi=1&mute=1&controls=0&rel=0&playsinline=1'
                + '&autoplay=' + (autoplay ? 1 : 0)
                + youtubeOrigin();

            iframe.setAttribute('allow', 'autoplay; encrypted-media');
            iframe.setAttribute('allowfullscreen', 'allowfullscreen');
            iframe.setAttribute('frameborder', '0');

            iframe.addEventListener('load', function () {
                // Bắt tay để YouTube chịu gửi onReady / onStateChange về
                youtubeListen(iframe);

                // onReady mới là tín hiệu thật; timer chỉ là lưới an toàn khi
                // sự kiện không về (chặn cookie bên thứ ba, proxy...).
                setTimeout(function () { markYoutubeReady(iframe); }, 400);
            });

            // Chèn lên đầu để phần tử phát luôn nằm dưới info/nút trong DOM
            media.insertBefore(iframe, media.firstChild);

            var shield = document.createElement('div');
            shield.className = 'reels-slide__shield';
            media.insertBefore(shield, iframe.nextSibling);
        }
        else {
            if (!item.src) return;

            var video = document.createElement('video');

            video.src = item.src;
            video.poster = item.poster || '';
            video.loop = true;
            video.playsInline = true;
            video.muted = state.muted;
            video.preload = 'metadata';
            video.setAttribute('playsinline', 'playsinline');

            video.addEventListener('playing', function () { markPlaying(slide); });

            media.insertBefore(video, media.firstChild);
        }

        var poster = media.querySelector('.reels-slide__poster');

        if (poster) poster.remove();

        slide.setAttribute('data-mounted', '1');
    }

    function unmount(index) {
        var slide = slides[index];
        var item  = items[index];

        if (!slide || slide.getAttribute('data-mounted') !== '1') return;

        var media = slide.querySelector('.reels-slide__media');

        // Chỉ gỡ phần tử phát. Xoá sạch media sẽ cuốn theo cả .reels-slide__info
        // lẫn cột nút — chúng cũng là con của media để bám mép khung video.
        media.querySelectorAll('video, iframe, .reels-slide__shield')
             .forEach(function (el) { el.remove(); });

        if (item && item.poster && !media.querySelector('.reels-slide__poster')) {
            var poster = document.createElement('img');
            poster.className = 'reels-slide__poster';
            poster.src = item.poster;
            poster.alt = item.title || '';
            media.insertBefore(poster, media.firstChild);
        }

        slide.removeAttribute('data-mounted');
    }

    /**
     * origin giúp YouTube chấp nhận lệnh postMessage. Bỏ qua khi mở bằng
     * file:// vì location.origin lúc đó là "null" và YouTube sẽ chặn.
     */
    function youtubeOrigin() {
        if (location.protocol !== 'http:' && location.protocol !== 'https:') return '';

        return '&origin=' + encodeURIComponent(location.origin);
    }

    function youtubeCommand(iframe, func, args) {
        if (!iframe) return;

        if (iframe.getAttribute('data-yt-ready') === '1') {
            youtubeSend(iframe, func, args);
            return;
        }

        iframe._ytQueue = iframe._ytQueue || [];

        iframe._ytQueue.push([func, args]);
    }

    function youtubePost(iframe, payload) {
        if (!iframe || !iframe.contentWindow) return;

        try { iframe.contentWindow.postMessage(JSON.stringify(payload), '*'); } catch (e) { /* iframe đã gỡ */ }
    }

    function youtubeSend(iframe, func, args) {
        youtubePost(iframe, { event: 'command', func: func, args: args || [], id: 1, channel: 'widget' });
    }

    function youtubeListen(iframe) {
        youtubePost(iframe, { event: 'listening', id: 1, channel: 'widget' });
    }

    function markYoutubeReady(iframe) {
        if (!iframe || iframe.getAttribute('data-yt-ready') === '1') return;

        iframe.setAttribute('data-yt-ready', '1');

        var queue = iframe._ytQueue || [];

        iframe._ytQueue = [];

        queue.forEach(function (cmd) { youtubeSend(iframe, cmd[0], cmd[1]); });
    }

    function slideOfWindow(source) {
        for (var i = 0; i < slides.length; i++) {
            var iframe = slides[i].querySelector('iframe');

            if (iframe && iframe.contentWindow === source) return slides[i];
        }

        return null;
    }

    /**
     * Đánh dấu video ĐANG CHẠY THẬT. Khác với "đã gửi lệnh play": mobile có thể
     * nuốt lệnh, nên chỉ tín hiệu từ player mới đáng tin.
     */
    function markPlaying(slide) {
        if (!slide) return;

        slide.setAttribute('data-playing', '1');
        slide.removeAttribute('data-paused');
    }

    window.addEventListener('message', function (event) {
        if (!/^https?:\/\/(www\.)?youtube(-nocookie)?\.com$/.test(event.origin)) return;

        var data;

        try { data = (typeof event.data === 'string') ? JSON.parse(event.data) : event.data; }
        catch (e) { return; }

        if (!data) return;

        var slide = slideOfWindow(event.source);

        if (!slide) return;

        var iframe = slide.querySelector('iframe');

        if (data.event === 'onReady' || data.event === 'initialDelivery') markYoutubeReady(iframe);

        var playerState = null;

        if (data.info && typeof data.info.playerState === 'number') playerState = data.info.playerState;
        else if (typeof data.info === 'number') playerState = data.info;

        if (playerState === null) return;

        // 1 = đang chạy, 3 = đang tải đệm
        if (playerState === 1 || playerState === 3) markPlaying(slide);

        // 0 = hết video: tự tua lại vì đã bỏ tham số loop
        if (playerState === 0) youtubeCommand(iframe, 'playVideo');
    });

    function play(index) {
        var slide = slides[index];

        if (!slide) return;

        // Cờ tạm dừng đặt trên slide vì với iframe YouTube ta không đọc được
        // trạng thái phát; đây là nguồn sự thật duy nhất cho cả mp4 lẫn youtube.
        slide.removeAttribute('data-paused');

        var video = slide.querySelector('video');

        if (video) {
            video.muted = state.muted;

            var p = video.play();

            // Trình duyệt vẫn có thể chặn autoplay (ví dụ khi đã bật tiếng);
            // rơi về muted rồi phát lại thay vì để màn hình đứng im.
            if (p && typeof p.catch === 'function') {
                p.catch(function () {
                    video.muted = true;
                    video.play().catch(function () {});
                });
            }

            return;
        }

        var iframe = slide.querySelector('iframe');

        if (iframe) {
            youtubeCommand(iframe, state.muted ? 'mute' : 'unMute');
            youtubeCommand(iframe, 'playVideo');
        }
    }

    function pause(index) {
        var slide = slides[index];

        if (!slide) return;

        slide.setAttribute('data-paused', '1');

        var video = slide.querySelector('video');

        if (video) { video.pause(); return; }

        var iframe = slide.querySelector('iframe');

        if (iframe) youtubeCommand(iframe, 'pauseVideo');
    }

    function isPaused(index) {
        return !!slides[index] && slides[index].getAttribute('data-paused') === '1';
    }

    /**
     * Trên mobile, tự phát có thể bị chặn dù đã muted — và trình duyệt không
     * báo lỗi gì, video chỉ đứng im. Sau PLAY_CHECK_MS mà player chưa báo
     * "đang chạy" thì coi như bị chặn: hiện nút ▶ để người dùng chạm phát.
     * Một cú chạm là user gesture thật, không trình duyệt nào chặn.
     */
    function watchPlayback(index) {
        clearTimeout(playTimer);

        var slide = slides[index];

        if (!slide) return;

        slide.removeAttribute('data-playing');

        playTimer = setTimeout(function () {
            if (!state.open || state.index !== index) return;

            var current = slides[index];

            if (!current || current.getAttribute('data-playing') === '1') return;

            // Người dùng tự bấm dừng thì đã có sẵn cờ, không đụng vào
            if (current.getAttribute('data-paused') === '1') return;

            var video = current.querySelector('video');

            // Thẻ <video> đọc được trạng thái thật, đừng đoán qua sự kiện
            if (video && !video.paused) { markPlaying(current); return; }

            current.setAttribute('data-paused', '1');
        }, PLAY_CHECK_MS);
    }

    /** Bấm vào video: đang chạy thì dừng, đang dừng thì chạy tiếp. */
    function togglePlayback() {
        if (state.index < 0 || !slides[state.index]) return;

        if (isPaused(state.index)) play(state.index);
        else pause(state.index);
    }

    function applyMute() {
        slides.forEach(function (slide) {
            var video = slide.querySelector('video');

            if (video) video.muted = state.muted;

            var iframe = slide.querySelector('iframe');

            if (iframe) youtubeCommand(iframe, state.muted ? 'mute' : 'unMute');

            var btn = slide.querySelector('[data-reel-action="sound"]');

            if (btn) {
                btn.querySelector('.reels-action__icon').innerHTML =
                    '<i class="' + (state.muted ? 'fas fa-volume-mute' : 'fas fa-volume-up') + '" aria-hidden="true"></i>';
                btn.title = state.muted ? lang.unmute : lang.mute;
            }
        });

        store.set('reels_muted', state.muted ? '1' : '0');
    }

    /* ------------------------------------------------------------------ */
    /* Điều hướng                                                          */
    /* ------------------------------------------------------------------ */

    function activate(index, replaceUrl) {
        if (index === state.index || !slides[index]) return;

        if (state.index >= 0) pause(state.index);

        state.index = index;

        for (var i = 0; i < slides.length; i++) {
            // Chỉ slide đang xem mới được autoplay ngay trong URL iframe
            if (i >= index - PRELOAD && i <= index + PRELOAD) mount(i, i === index);
            else unmount(i);
        }

        play(index);

        watchPlayback(index);

        syncDesc(index);

        scheduleView(index);

        if (replaceUrl !== false && items[index] && items[index].url) {
            try { history.replaceState({ reels: true }, '', items[index].url); } catch (e) {}
        }

        // Sắp hết danh sách thì nạp tiếp để cuộn không bị khựng ở cuối.
        if (state.hasMore && index >= items.length - 3) loadMore();
    }

    function scheduleView(index) {
        clearTimeout(viewTimer);

        var item = items[index];

        if (!item) return;

        viewTimer = setTimeout(function () {
            if (state.index !== index || !state.open) return;

            var key = 'reels_viewed_' + item.id;

            try {
                if (window.sessionStorage.getItem(key)) return;
                window.sessionStorage.setItem(key, '1');
            } catch (e) { /* bỏ qua, server vẫn chống trùng bằng cookie */ }

            post({ action: 'Reels\\Ajax\\Web\\ReelAjax::view', id: item.id })
                .then(function (res) {
                    if (!res || res.status !== 'success' || !res.data) return;

                    item.views = res.data.count;

                    updateCounter(index, 'views', res.data.text);

                    var badge = document.querySelector('[data-reel-views="' + item.id + '"]');

                    if (badge) badge.textContent = res.data.text;
                })
                .catch(function () {});
        }, VIEW_MS);
    }

    function updateCounter(index, role, text) {
        var slide = slides[index];

        if (!slide) return;

        var selector = role === 'views' ? '[data-reel-action="views"]' : '[data-reel-action="like"]';

        var btn = slide.querySelector(selector);

        if (btn) btn.querySelector('.reels-action__text').textContent = text;
    }

    function goTo(index) {
        if (!slides[index]) return;

        slides[index].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function openViewer(index, pushUrl) {
        if (!items.length) return;

        if (!slides.length) appendSlides(0);

        viewer.hidden = false;
        viewer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('reels-viewer-open');

        state.open = true;

        // Nhảy tức thì (không smooth) để không thấy cảnh lướt qua hết các slide.
        slides[index].scrollIntoView({ block: 'start' });

        if (pushUrl !== false && items[index] && items[index].url) {
            try { history.pushState({ reels: true }, '', items[index].url); } catch (e) {}
        }

        state.index = -1;

        activate(index, false);

        track.focus({ preventScroll: true });
    }

    function closeViewer(pushUrl) {
        if (!state.open) return;

        clearTimeout(viewTimer);
        clearTimeout(playTimer);

        if (state.index >= 0) pause(state.index);

        viewer.hidden = true;
        viewer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('reels-viewer-open');

        state.open = false;

        if (pushUrl !== false) {
            try { history.pushState({ reels: false }, '', feedUrl()); } catch (e) {}
        }
    }

    /* ------------------------------------------------------------------ */
    /* Nạp thêm / đổi tab                                                  */
    /* ------------------------------------------------------------------ */

    function loadMore() {
        if (state.loading || !state.hasMore) return Promise.resolve();

        state.loading = true;

        if (moreBtn) moreBtn.disabled = true;

        return post({
            action: 'Reels\\Ajax\\Web\\ReelAjax::load',
            tab:    state.tab,
            page:   state.page + 1
        }).then(function (res) {
            state.loading = false;

            if (moreBtn) moreBtn.disabled = false;

            if (!res || res.status !== 'success' || !res.data) return;

            var from = items.length;

            items = items.concat(res.data.items || []);

            state.page    = res.data.page;
            state.hasMore = !!res.data.hasMore;

            if (grid && res.data.html) grid.insertAdjacentHTML('beforeend', res.data.html);

            if (slides.length) appendSlides(from);

            if (moreBtn) moreBtn.hidden = !state.hasMore;
        }).catch(function () {
            state.loading = false;

            if (moreBtn) moreBtn.disabled = false;
        });
    }

    function switchTab(tab) {
        if (tab === state.tab || state.loading) return;

        state.loading = true;

        post({ action: 'Reels\\Ajax\\Web\\ReelAjax::load', tab: tab, page: 1 })
            .then(function (res) {
                state.loading = false;

                if (!res || res.status !== 'success' || !res.data) return;

                state.tab     = tab;
                state.page    = res.data.page;
                state.hasMore = !!res.data.hasMore;

                items = res.data.items || [];

                if (grid) grid.innerHTML = res.data.html || '';

                // Viewer dựng lại từ đầu: thứ tự slide phải khớp tab mới.
                slides.forEach(function (slide) { if (observer) observer.unobserve(slide); });
                slides = [];
                track.innerHTML = '';
                state.index = -1;

                page.querySelectorAll('[data-reel-tab]').forEach(function (el) {
                    var active = el.getAttribute('data-reel-tab') === tab;
                    el.classList.toggle('is-active', active);
                    el.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                if (moreBtn) moreBtn.hidden = !state.hasMore;

                try { history.replaceState({ reels: false }, '', feedUrl()); } catch (e) {}
            })
            .catch(function () { state.loading = false; });
    }

    /* ------------------------------------------------------------------ */
    /* Sự kiện                                                             */
    /* ------------------------------------------------------------------ */

    if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting || !state.open) return;

                var index = slides.indexOf(entry.target);

                if (index !== -1) activate(index);
            });
        }, { root: track, threshold: 0.6 });
    }

    if (grid) {
        grid.addEventListener('click', function (event) {
            var link = event.target.closest('[data-reel-open]');

            if (!link) return;

            // Ctrl/Cmd/giữa chuột: để trình duyệt mở tab mới như link bình thường
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;

            var card = link.closest('.reels-card');

            var cards = Array.prototype.slice.call(grid.querySelectorAll('.reels-card'));

            var index = cards.indexOf(card);

            if (index === -1) return;

            event.preventDefault();

            openViewer(index);
        });
    }

    if (moreBtn) {
        moreBtn.addEventListener('click', function () { loadMore(); });
    }

    page.addEventListener('click', function (event) {
        var tabLink = event.target.closest('[data-reel-tab]');

        if (!tabLink) return;

        if (event.metaKey || event.ctrlKey || event.shiftKey) return;

        event.preventDefault();

        switchTab(tabLink.getAttribute('data-reel-tab'));
    });

    viewer.addEventListener('click', function (event) {
        if (event.target.closest('[data-reel-close]')) { closeViewer(); return; }

        if (event.target.closest('[data-reel-prev]')) { goTo(state.index - 1); return; }

        if (event.target.closest('[data-reel-next]')) { goTo(state.index + 1); return; }

        var sound = event.target.closest('[data-reel-action="sound"]');

        if (sound) {
            state.muted = !state.muted;
            applyMute();

            // Bật/tắt tiếng không được tự phát lại video mà người dùng đã dừng
            if (state.index >= 0 && !isPaused(state.index)) play(state.index);

            return;
        }

        var like = event.target.closest('[data-reel-action="like"]');

        if (like) {
            toggleLike(like);
            return;
        }

        var share = event.target.closest('[data-reel-action="share"]');

        if (share) {
            shareCurrent(share);
            return;
        }

        // Bấm bất kỳ đâu trong khối mô tả đều mở/thu, không bắt người dùng
        // nhắm đúng chữ "xem thêm" bé xíu.
        var desc = event.target.closest('[data-reel-desc]');

        if (desc && desc.classList.contains('has-more')) {
            toggleDesc(desc);
            return;
        }

        // Bấm vào vùng video thì dừng/chạy. Khối thông tin và cột nút cũng nằm
        // trong .reels-slide__media nên phải loại trừ, không thì bấm mua hàng
        // hay bấm tim cũng làm video đứng hình.
        if (event.target.closest('.reels-slide__media')
            && !event.target.closest('.reels-slide__info')
            && !event.target.closest('.reels-actions')) {
            togglePlayback();
        }
    });

    function toggleLike(button) {
        var index = slides.indexOf(button.closest('.reels-slide'));

        var item = items[index];

        if (!item) return;

        var liked = !button.classList.contains('is-active');

        // Cập nhật ngay rồi mới gọi server: chờ round-trip mới đổi màu tim
        // khiến nút có cảm giác liệt.
        button.classList.toggle('is-active', liked);

        setLiked(item.id, liked);

        item.likes = Math.max(0, (parseInt(item.likes, 10) || 0) + (liked ? 1 : -1));

        updateCounter(index, 'like', shortNumber(item.likes));

        post({
            action: 'Reels\\Ajax\\Web\\ReelAjax::like',
            id:     item.id,
            act:    liked ? 'like' : 'unlike'
        }).then(function (res) {
            if (!res || res.status !== 'success' || !res.data) return;

            item.likes = res.data.count;

            updateCounter(index, 'like', res.data.text);
        }).catch(function () {});
    }

    /**
     * Chia sẻ video đang xem: ưu tiên hộp chia sẻ của hệ điều hành, không có
     * thì chép link. Cả navigator.share lẫn navigator.clipboard đều đòi secure
     * context nên site chạy http vẫn phải còn đường lui bằng execCommand.
     */
    function shareCurrent(button) {
        var item = items[state.index];

        if (!item || !item.url) return;

        if (navigator.share) {
            navigator.share({ title: item.title || '', url: item.url }).catch(function () {});
            return;
        }

        copyText(item.url).then(function (ok) {
            if (!ok) return;

            flashLabel(button, lang.copied || '');
        });
    }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(function () { return true; })
                .catch(function () { return legacyCopy(text); });
        }

        return Promise.resolve(legacyCopy(text));
    }

    function legacyCopy(text) {
        var area = document.createElement('textarea');

        area.value = text;
        area.setAttribute('readonly', 'readonly');
        area.style.position = 'fixed';
        area.style.opacity = '0';

        document.body.appendChild(area);
        area.select();

        var ok = false;

        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }

        area.remove();

        return ok;
    }

    function flashLabel(button, text) {
        if (!button) return;

        var node = button.querySelector('.reels-action__text');

        if (!node) return;

        var before = node.textContent;

        node.textContent = text;

        button.classList.add('is-active');

        setTimeout(function () {
            node.textContent = before;
            button.classList.remove('is-active');
        }, 1600);
    }

    document.addEventListener('keydown', function (event) {
        if (!state.open) return;

        if (event.key === 'Escape')    { closeViewer(); event.preventDefault(); return; }
        if (event.key === 'ArrowUp')   { goTo(state.index - 1); event.preventDefault(); return; }
        if (event.key === 'ArrowDown') { goTo(state.index + 1); event.preventDefault(); return; }

        // Space dừng/chạy — trừ khi đang focus một nút, lúc đó space là "bấm nút"
        if (event.key === ' ' || event.key === 'Spacebar') {
            if (event.target.closest('button, a, input, textarea, select')) return;

            togglePlayback();

            event.preventDefault();
        }
    });

    window.addEventListener('popstate', function () {
        if (state.open) closeViewer(false);
    });

    /* ------------------------------------------------------------------ */
    /* Khởi động                                                           */
    /* ------------------------------------------------------------------ */

    if (typeof cfg.openIndex === 'number' && cfg.openIndex >= 0 && items[cfg.openIndex]) {
        openViewer(cfg.openIndex, false);
    }
})();
