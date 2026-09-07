/**
 * Element "Video ngắn" (ReelsListElement).
 *
 * Không dùng thư viện ngoài: cuộn ngang của lưới và cuộn dọc của trình xem đều
 * giao cho CSS scroll-snap, JS chỉ lo phát/dừng video, đếm lượt và dựng slide.
 *
 * Trình xem được gắn vào <body> chứ không nằm trong vỏ element: hiệu ứng cuộn
 * (AOS) đặt transform lên vỏ, mà transform khiến position:fixed neo theo vỏ.
 */
class ReelsListWidget
{
    constructor(scope, $)
    {
        this.scope = scope;

        this.$ = $;

        this.root = scope.find('[data-reels-element]').get(0);

        if (!this.root) return;

        this.config = this.readConfig();

        this.items = this.readItems();

        this.lang = this.config.lang || {};

        this.list = this.root.querySelector('.reels-el__list');

        this.cards = Array.prototype.slice.call(this.root.querySelectorAll('.reels-el__card'));

        this.previewObserver = null;

        this.viewer = null;

        this.slides = [];

        this.slideObserver = null;

        this.index = -1;

        this.open = false;

        this.viewTimer = null;

        this.muted = ReelsListWidget.store.get('reels_muted', '1') !== '0';

        this.onKeydown = this.handleKeydown.bind(this);

        this.onMessage = this.handleYoutubeMessage.bind(this);

        this.bindCards();

        this.bindNav();

        this.initPreview();
    }

    /* ------------------------------------------------------------------ */
    /* Đọc dữ liệu server nhúng                                            */
    /* ------------------------------------------------------------------ */

    readConfig()
    {
        try
        {
            return JSON.parse(this.root.getAttribute('data-config') || '{}');
        }
        catch (e)
        {
            return {};
        }
    }

    readItems()
    {
        const node = this.root.querySelector('[data-reels-items]');

        if (!node) return [];

        try
        {
            const items = JSON.parse(node.textContent || '[]');

            return Array.isArray(items) ? items : [];
        }
        catch (e)
        {
            return [];
        }
    }

    /* ------------------------------------------------------------------ */
    /* Lưới                                                                */
    /* ------------------------------------------------------------------ */

    bindCards()
    {
        const self = this;

        this.$(this.root).on('click.reelsEl', '[data-reel-open]', function (event)
        {
            // Không mở được trình xem (chế độ "mở trang video", hoặc payload
            // rỗng) thì để thẻ <a> chạy như một link bình thường.
            if (!self.config.popup || !self.items.length) return;

            const index = parseInt(this.getAttribute('data-reel-open'), 10);

            if (isNaN(index) || !self.items[index]) return;

            event.preventDefault();

            self.openViewer(index);
        });
    }

    bindNav()
    {
        const self = this;

        this.$(this.root).on('click.reelsEl', '[data-reel-prev]', function ()
        {
            self.slide(-1);
        });

        this.$(this.root).on('click.reelsEl', '[data-reel-next]', function ()
        {
            self.slide(1);
        });
    }

    /** Cuộn danh sách đúng một "trang" theo chiều rộng thẻ đầu tiên. */
    slide(direction)
    {
        if (!this.list || !this.cards.length) return;

        const step = this.cards[0].offsetWidth + 16;

        this.list.scrollBy({ left: step * direction, behavior: 'smooth' });
    }

    /**
     * Phát thử (tắt tiếng) thẻ đang nằm trong khung nhìn. Chỉ áp dụng cho video
     * tải lên: mỗi YouTube preview là một iframe nặng, mở 5 cái cùng lúc trên
     * mobile là đủ để trang khựng.
     */
    initPreview()
    {
        if (!this.config.preview || !this.cards.length) return;

        if (!('IntersectionObserver' in window)) return;

        // Trình duyệt tiết kiệm dữ liệu / người dùng ngại chuyển động thì bỏ qua
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const self = this;

        this.previewObserver = new IntersectionObserver(function (entries)
        {
            entries.forEach(function (entry)
            {
                if (entry.isIntersecting) self.previewStart(entry.target);
                else self.previewStop(entry.target);
            });
        }, { threshold: 0.6 });

        this.cards.forEach(function (card)
        {
            if (card.getAttribute('data-reel-source') === 'upload' && card.getAttribute('data-reel-src'))
            {
                self.previewObserver.observe(card);
            }
        });
    }

    previewStart(card)
    {
        if (this.open) return;

        const media = card.querySelector('.reels-el__media');

        if (!media || media.querySelector('video')) return;

        const video = document.createElement('video');

        video.className = 'reels-el__video';
        video.src         = card.getAttribute('data-reel-src');
        video.muted       = true;
        video.loop        = true;
        video.playsInline = true;
        video.preload     = 'metadata';
        video.setAttribute('muted', 'muted');
        video.setAttribute('playsinline', 'playsinline');

        media.insertBefore(video, media.firstChild);

        card.classList.add('is-previewing');

        const play = video.play();

        // Autoplay vẫn có thể bị chặn dù đã muted; im lặng bỏ qua, thẻ giữ poster.
        if (play && typeof play.catch === 'function')
        {
            play.catch(function () {});
        }
    }

    previewStop(card)
    {
        const video = card.querySelector('video');

        if (!video) return;

        video.pause();

        video.removeAttribute('src');

        video.load();

        video.remove();

        card.classList.remove('is-previewing');
    }

    previewStopAll()
    {
        const self = this;

        this.cards.forEach(function (card) { self.previewStop(card); });
    }

    /* ------------------------------------------------------------------ */
    /* Trình xem                                                           */
    /* ------------------------------------------------------------------ */

    ensureViewer()
    {
        if (this.viewer) return;

        const self = this;

        const viewer = document.createElement('div');

        viewer.className = 'reels-elv';
        viewer.setAttribute('role', 'dialog');
        viewer.setAttribute('aria-modal', 'true');
        viewer.hidden = true;

        const track = document.createElement('div');

        track.className = 'reels-elv__track';
        track.tabIndex  = -1;

        this.items.forEach(function (item, index)
        {
            const slide = self.buildSlide(item, index);

            track.appendChild(slide);

            self.slides.push(slide);
        });

        viewer.appendChild(track);

        viewer.appendChild(this.iconButton('reels-elv__close', 'fa-times', this.lang.close, 'close'));

        viewer.appendChild(this.iconButton('reels-elv__nav reels-elv__nav--prev', 'fa-chevron-up', this.lang.prev, 'prev'));

        viewer.appendChild(this.iconButton('reels-elv__nav reels-elv__nav--next', 'fa-chevron-down', this.lang.next, 'next'));

        if (this.config.feedUrl)
        {
            const all = document.createElement('a');

            all.className   = 'reels-elv__all';
            all.href        = this.config.feedUrl;
            all.textContent = this.lang.all || '';

            viewer.appendChild(all);
        }

        document.body.appendChild(viewer);

        this.viewer = viewer;

        this.track = track;

        this.$(viewer).on('click.reelsElv', '[data-elv-action]', function (event)
        {
            self.action(this.getAttribute('data-elv-action'), this, event);
        });

        // Chạm vào khung video (không phải nút) thì dừng/phát tiếp
        this.$(viewer).on('click.reelsElv', '.reels-elv__media', function (event)
        {
            if (event.target.closest('[data-elv-action]')) return;

            if (event.target.closest('a')) return;

            self.togglePlayback();
        });

        this.initSlideObserver();

        window.addEventListener('message', this.onMessage);
    }

    buildSlide(item, index)
    {
        const slide = document.createElement('div');

        slide.className = 'reels-elv__slide';
        slide.setAttribute('data-elv-index', index);

        if (item.poster)
        {
            const bg = document.createElement('div');

            bg.className = 'reels-elv__bg';
            bg.style.backgroundImage = 'url(' + item.poster + ')';

            slide.appendChild(bg);
        }

        const media = document.createElement('div');

        media.className = 'reels-elv__media';

        // Trước khi được "mount", slide chỉ có poster: giữ hàng chục thẻ <video>
        // sống cùng lúc là cách nhanh nhất để treo trình duyệt trên mobile.
        if (item.poster)
        {
            const poster = document.createElement('img');

            poster.className = 'reels-elv__poster';
            poster.src       = item.poster;
            poster.alt       = item.title || '';

            media.appendChild(poster);
        }

        const indicator = document.createElement('div');

        indicator.className = 'reels-elv__playpause';
        indicator.setAttribute('aria-hidden', 'true');
        indicator.innerHTML = '<i class="fas fa-play"></i>';

        media.appendChild(indicator);

        const info = document.createElement('div');

        info.className = 'reels-elv__info';

        const title = document.createElement('p');

        title.className   = 'reels-elv__title';
        title.textContent = item.title || '';

        info.appendChild(title);

        if (item.excerpt)
        {
            const desc = document.createElement('p');

            desc.className   = 'reels-elv__desc';
            desc.textContent = item.excerpt;

            info.appendChild(desc);
        }

        if (item.product)
        {
            const box = document.createElement('div');

            box.innerHTML = item.product; // HTML server render, đã escape

            while (box.firstChild) info.appendChild(box.firstChild);
        }

        // Info và cột nút nằm TRONG khung video: trên desktop khung chỉ rộng
        // 9/16 chiều cao màn hình, gắn ra ngoài thì nút trôi ra tận mép màn hình.
        media.appendChild(info);

        media.appendChild(this.buildActions(item));

        slide.appendChild(media);

        return slide;
    }

    buildActions(item)
    {
        const wrap = document.createElement('div');

        wrap.className = 'reels-elv__actions';

        wrap.appendChild(this.actionButton('fa-eye', ReelsListWidget.shortNumber(item.views), 'views'));

        const liked = ReelsListWidget.isLiked(item.id);

        const like = this.actionButton('fa-heart', ReelsListWidget.shortNumber(item.likes), 'like');

        if (liked) like.classList.add('is-active');

        wrap.appendChild(like);

        wrap.appendChild(this.actionButton(this.muted ? 'fa-volume-mute' : 'fa-volume-up', '', 'sound'));

        wrap.appendChild(this.actionButton('fa-share-nodes', this.lang.share || '', 'share'));

        return wrap;
    }

    actionButton(icon, text, role)
    {
        const button = document.createElement('button');

        button.type      = 'button';
        button.className = 'reels-elv__action';

        button.setAttribute('data-elv-action', role);

        const iconEl = document.createElement('span');

        iconEl.className = 'reels-elv__action-icon';
        iconEl.innerHTML = '<i class="fas ' + icon + '" aria-hidden="true"></i>';

        const textEl = document.createElement('span');

        textEl.className   = 'reels-elv__action-text';
        textEl.textContent = text;

        button.appendChild(iconEl);

        button.appendChild(textEl);

        return button;
    }

    iconButton(className, icon, label, role)
    {
        const button = document.createElement('button');

        button.type      = 'button';
        button.className = className;
        button.innerHTML = '<i class="fas ' + icon + '" aria-hidden="true"></i>';

        button.setAttribute('aria-label', label || '');
        button.setAttribute('data-elv-action', role);

        return button;
    }

    /** Slide nào chiếm phần lớn khung nhìn thì slide đó đang được xem. */
    initSlideObserver()
    {
        if (!('IntersectionObserver' in window)) return;

        const self = this;

        this.slideObserver = new IntersectionObserver(function (entries)
        {
            entries.forEach(function (entry)
            {
                if (!entry.isIntersecting) return;

                const index = parseInt(entry.target.getAttribute('data-elv-index'), 10);

                if (!isNaN(index)) self.activate(index);
            });
        }, { root: this.track, threshold: 0.6 });

        this.slides.forEach(function (slide) { self.slideObserver.observe(slide); });
    }

    openViewer(index)
    {
        this.previewStopAll();

        this.ensureViewer();

        this.viewer.hidden = false;

        this.viewer.setAttribute('aria-hidden', 'false');

        document.body.classList.add('reels-elv-open');

        this.open = true;

        // Nhảy tới slide trước khi kích hoạt: IntersectionObserver bắn muộn hơn
        // một nhịp nên phải tự gọi activate, nếu không video đầu không phát.
        this.slides[index].scrollIntoView({ block: 'start' });

        this.index = -1;

        this.activate(index);

        document.addEventListener('keydown', this.onKeydown);

        this.track.focus({ preventScroll: true });
    }

    closeViewer()
    {
        if (!this.open) return;

        this.pause(this.index);

        this.slides.forEach((slide, index) => this.unmount(index));

        this.viewer.hidden = true;

        this.viewer.setAttribute('aria-hidden', 'true');

        document.body.classList.remove('reels-elv-open');

        this.open = false;

        this.index = -1;

        clearTimeout(this.viewTimer);

        document.removeEventListener('keydown', this.onKeydown);
    }

    activate(index)
    {
        if (index === this.index || !this.slides[index]) return;

        if (this.index >= 0) this.pause(this.index);

        this.index = index;

        for (let i = 0; i < this.slides.length; i++)
        {
            // Chỉ slide đang xem mới được autoplay ngay trong URL iframe
            if (i >= index - 1 && i <= index + 1) this.mount(i, i === index);
            else this.unmount(i);
        }

        this.play(index);

        this.scheduleView(index);
    }

    move(direction)
    {
        const next = this.index + direction;

        if (!this.slides[next]) return;

        this.slides[next].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* ------------------------------------------------------------------ */
    /* Mount / phát                                                        */
    /* ------------------------------------------------------------------ */

    mount(index, autoplay)
    {
        const slide = this.slides[index];

        const item = this.items[index];

        if (!slide || !item || slide.getAttribute('data-mounted') === '1') return;

        const media = slide.querySelector('.reels-elv__media');

        if (!media || !item.src) return;

        if (item.source === 'youtube')
        {
            const iframe = document.createElement('iframe');

            const self = this;

            // Không dùng loop=1: playlist lặp làm player không bao giờ báo
            // trạng thái "đã hết", ta tự tua lại trong handleYoutubeMessage.
            iframe.src = 'https://www.youtube-nocookie.com/embed/' + item.src
                + '?enablejsapi=1&mute=1&controls=0&rel=0&playsinline=1'
                + '&autoplay=' + (autoplay ? 1 : 0)
                + ReelsListWidget.youtubeOrigin();

            iframe.setAttribute('allow', 'autoplay; encrypted-media');
            iframe.setAttribute('allowfullscreen', 'allowfullscreen');
            iframe.setAttribute('frameborder', '0');

            iframe.addEventListener('load', function ()
            {
                ReelsListWidget.youtubeListen(iframe);

                // onReady mới là tín hiệu thật; timer chỉ là lưới an toàn khi
                // sự kiện không về (chặn cookie bên thứ ba, proxy...).
                setTimeout(function () { self.youtubeReady(iframe); }, 400);
            });

            media.insertBefore(iframe, media.firstChild);

            const shield = document.createElement('div');

            shield.className = 'reels-elv__shield';

            media.insertBefore(shield, iframe.nextSibling);
        }
        else
        {
            const video = document.createElement('video');

            video.src         = item.src;
            video.poster      = item.poster || '';
            video.loop        = true;
            video.playsInline = true;
            video.muted       = this.muted;
            video.preload     = 'metadata';
            video.setAttribute('playsinline', 'playsinline');

            media.insertBefore(video, media.firstChild);
        }

        const poster = media.querySelector('.reels-elv__poster');

        if (poster) poster.remove();

        slide.setAttribute('data-mounted', '1');
    }

    unmount(index)
    {
        const slide = this.slides[index];

        const item = this.items[index];

        if (!slide || slide.getAttribute('data-mounted') !== '1') return;

        const media = slide.querySelector('.reels-elv__media');

        // Chỉ gỡ phần tử phát: info và cột nút cũng là con của media.
        media.querySelectorAll('video, iframe, .reels-elv__shield').forEach(function (el) { el.remove(); });

        if (item && item.poster && !media.querySelector('.reels-elv__poster'))
        {
            const poster = document.createElement('img');

            poster.className = 'reels-elv__poster';
            poster.src       = item.poster;
            poster.alt       = item.title || '';

            media.insertBefore(poster, media.firstChild);
        }

        slide.removeAttribute('data-mounted');

        slide.removeAttribute('data-paused');
    }

    play(index)
    {
        const slide = this.slides[index];

        if (!slide) return;

        // Cờ tạm dừng đặt trên slide vì với iframe YouTube không đọc được trạng
        // thái phát; đây là nguồn sự thật duy nhất cho cả mp4 lẫn youtube.
        slide.removeAttribute('data-paused');

        const video = slide.querySelector('video');

        if (video)
        {
            video.muted = this.muted;

            const promise = video.play();

            // Trình duyệt vẫn chặn autoplay khi đã bật tiếng: rơi về muted rồi
            // phát lại thay vì để màn hình đứng im.
            if (promise && typeof promise.catch === 'function')
            {
                promise.catch(function ()
                {
                    video.muted = true;

                    video.play().catch(function () {});
                });
            }

            return;
        }

        const iframe = slide.querySelector('iframe');

        if (iframe)
        {
            this.youtubeCommand(iframe, this.muted ? 'mute' : 'unMute');

            this.youtubeCommand(iframe, 'playVideo');
        }
    }

    pause(index)
    {
        const slide = this.slides[index];

        if (!slide) return;

        slide.setAttribute('data-paused', '1');

        const video = slide.querySelector('video');

        if (video)
        {
            video.pause();

            return;
        }

        const iframe = slide.querySelector('iframe');

        if (iframe) this.youtubeCommand(iframe, 'pauseVideo');
    }

    togglePlayback()
    {
        const slide = this.slides[this.index];

        if (!slide) return;

        if (slide.getAttribute('data-paused') === '1') this.play(this.index);
        else this.pause(this.index);
    }

    applyMute()
    {
        const self = this;

        this.slides.forEach(function (slide)
        {
            const video = slide.querySelector('video');

            if (video) video.muted = self.muted;

            const iframe = slide.querySelector('iframe');

            if (iframe) self.youtubeCommand(iframe, self.muted ? 'mute' : 'unMute');

            const button = slide.querySelector('[data-elv-action="sound"]');

            if (button)
            {
                button.querySelector('.reels-elv__action-icon').innerHTML =
                    '<i class="fas ' + (self.muted ? 'fa-volume-mute' : 'fa-volume-up') + '" aria-hidden="true"></i>';

                button.setAttribute('aria-label', self.muted ? (self.lang.unmute || '') : (self.lang.mute || ''));
            }
        });

        ReelsListWidget.store.set('reels_muted', this.muted ? '1' : '0');
    }

    /* ------------------------------------------------------------------ */
    /* Tương tác                                                           */
    /* ------------------------------------------------------------------ */

    action(role, button, event)
    {
        event.preventDefault();

        if (role === 'close')
        {
            this.closeViewer();

            return;
        }

        if (role === 'prev')
        {
            this.move(-1);

            return;
        }

        if (role === 'next')
        {
            this.move(1);

            return;
        }

        if (role === 'sound')
        {
            this.muted = !this.muted;

            this.applyMute();

            return;
        }

        if (role === 'like')
        {
            this.like(button);

            return;
        }

        if (role === 'share') this.share();
    }

    like(button)
    {
        const item = this.items[this.index];

        if (!item) return;

        const liked = button.classList.contains('is-active');

        button.classList.toggle('is-active', !liked);

        ReelsListWidget.setLiked(item.id, !liked);

        const self = this;

        ReelsListWidget.post({
            action: 'Reels\\Ajax\\Web\\ReelAjax::like',
            id:     item.id,
            act:    liked ? 'unlike' : 'like'
        }).then(function (res)
        {
            if (!res || res.status !== 'success' || !res.data) return;

            item.likes = res.data.count;

            const text = button.querySelector('.reels-elv__action-text');

            if (text) text.textContent = res.data.text;
        }).catch(function ()
        {
            // Máy chủ từ chối thì trả lại trạng thái cũ, đừng để nút nói dối
            button.classList.toggle('is-active', liked);

            ReelsListWidget.setLiked(item.id, liked);

            if (typeof SkilldoMessage !== 'undefined' && self.lang.error)
            {
                SkilldoMessage.error(self.lang.error);
            }
        });
    }

    share()
    {
        const item = this.items[this.index];

        if (!item || !item.url) return;

        if (navigator.share)
        {
            navigator.share({ title: item.title || '', url: item.url }).catch(function () {});

            return;
        }

        const self = this;

        if (navigator.clipboard && navigator.clipboard.writeText)
        {
            navigator.clipboard.writeText(item.url).then(function ()
            {
                if (typeof SkilldoMessage !== 'undefined' && self.lang.copied)
                {
                    SkilldoMessage.success(self.lang.copied);
                }
            }).catch(function () {});
        }
    }

    /**
     * Đếm lượt xem sau 3 giây: mở lướt qua không phải là một lượt xem.
     * sessionStorage chỉ để bớt request thừa, chống trùng thật nằm ở server.
     */
    scheduleView(index)
    {
        clearTimeout(this.viewTimer);

        const item = this.items[index];

        if (!item) return;

        const self = this;

        this.viewTimer = setTimeout(function ()
        {
            if (!self.open || self.index !== index) return;

            const key = 'reels_viewed_' + item.id;

            try
            {
                if (window.sessionStorage.getItem(key)) return;

                window.sessionStorage.setItem(key, '1');
            }
            catch (e) { /* private mode: server vẫn chống trùng bằng cookie */ }

            ReelsListWidget.post({ action: 'Reels\\Ajax\\Web\\ReelAjax::view', id: item.id })
                .then(function (res)
                {
                    if (!res || res.status !== 'success' || !res.data) return;

                    item.views = res.data.count;

                    const slide = self.slides[index];

                    const text = slide ? slide.querySelector('[data-elv-action="views"] .reels-elv__action-text') : null;

                    if (text) text.textContent = res.data.text;

                    const badge = self.root.querySelector('[data-reel-views="' + item.id + '"]');

                    if (badge) badge.textContent = res.data.text;
                })
                .catch(function () {});
        }, 3000);
    }

    handleKeydown(event)
    {
        if (!this.open) return;

        if (event.key === 'Escape') this.closeViewer();

        if (event.key === 'ArrowDown') this.move(1);

        if (event.key === 'ArrowUp') this.move(-1);
    }

    /* ------------------------------------------------------------------ */
    /* YouTube                                                             */
    /* ------------------------------------------------------------------ */

    handleYoutubeMessage(event)
    {
        if (!this.open || !event.origin || event.origin.indexOf('youtube') === -1) return;

        let data = event.data;

        if (typeof data === 'string')
        {
            try { data = JSON.parse(data); } catch (e) { return; }
        }

        if (!data || typeof data !== 'object') return;

        const iframe = this.iframeOfWindow(event.source);

        if (!iframe) return;

        if (data.event === 'onReady' || data.event === 'initialDelivery') this.youtubeReady(iframe);

        let playerState = null;

        if (data.info && typeof data.info.playerState === 'number') playerState = data.info.playerState;
        else if (typeof data.info === 'number') playerState = data.info;

        // 0 = hết video: tự tua lại vì đã bỏ tham số loop
        if (playerState === 0) this.youtubeCommand(iframe, 'playVideo');
    }

    iframeOfWindow(source)
    {
        for (let i = 0; i < this.slides.length; i++)
        {
            const iframe = this.slides[i].querySelector('iframe');

            if (iframe && iframe.contentWindow === source) return iframe;
        }

        return null;
    }

    youtubeCommand(iframe, func, args)
    {
        if (!iframe) return;

        if (iframe.getAttribute('data-yt-ready') === '1')
        {
            ReelsListWidget.youtubeSend(iframe, func, args);

            return;
        }

        iframe._ytQueue = iframe._ytQueue || [];

        iframe._ytQueue.push([func, args]);
    }

    youtubeReady(iframe)
    {
        if (!iframe || iframe.getAttribute('data-yt-ready') === '1') return;

        iframe.setAttribute('data-yt-ready', '1');

        const queue = iframe._ytQueue || [];

        iframe._ytQueue = [];

        queue.forEach(function (command)
        {
            ReelsListWidget.youtubeSend(iframe, command[0], command[1]);
        });
    }

    static youtubeSend(iframe, func, args)
    {
        ReelsListWidget.youtubePost(iframe, {
            event:   'command',
            func:    func,
            args:    args || [],
            id:      1,
            channel: 'widget'
        });
    }

    static youtubeListen(iframe)
    {
        ReelsListWidget.youtubePost(iframe, { event: 'listening', id: 1, channel: 'widget' });
    }

    static youtubePost(iframe, payload)
    {
        if (!iframe || !iframe.contentWindow) return;

        try { iframe.contentWindow.postMessage(JSON.stringify(payload), '*'); } catch (e) { /* iframe đã gỡ */ }
    }

    /**
     * origin giúp YouTube chấp nhận lệnh postMessage. Bỏ qua khi mở bằng
     * file:// vì location.origin lúc đó là "null" và YouTube sẽ chặn.
     */
    static youtubeOrigin()
    {
        if (location.protocol !== 'http:' && location.protocol !== 'https:') return '';

        return '&origin=' + encodeURIComponent(location.origin);
    }

    /* ------------------------------------------------------------------ */
    /* Tiện ích                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Gửi dạng x-www-form-urlencoded chứ không phải JSON: dispatcher /admin/ajax
     * đọc `action` từ input bag, body JSON không tới được nó.
     */
    static post(payload)
    {
        const url = (typeof ajax !== 'undefined') ? ajax : '/admin/ajax';

        const body = new URLSearchParams();

        Object.keys(payload).forEach(function (key) { body.append(key, payload[key]); });

        if (typeof request !== 'undefined' && request && typeof request.post === 'function')
        {
            return request
                .post(url, body.toString(), { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } })
                .then(ReelsListWidget.unwrap);
        }

        const meta = document.querySelector('meta[name="csrf-value"]');

        return fetch(url, {
            method:      'POST',
            body:        body.toString(),
            credentials: 'same-origin',
            headers:     {
                'Content-Type':     'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':     meta ? meta.content : ''
            }
        }).then(function (response) { return response.json(); });
    }

    /**
     * Theme cài interceptor trả thẳng body, axios thuần thì body nằm ở res.data.
     * Nhận diện bằng khoá `status` của envelope thay vì đoán theo thư viện.
     */
    static unwrap(res)
    {
        if (res && typeof res.status === 'string') return res;

        return (res && res.data) ? res.data : res;
    }

    /** 1234 → 1.2K. Cột nút rất hẹp nên không hiển thị số thô. */
    static shortNumber(number)
    {
        number = parseInt(number, 10) || 0;

        if (number < 1000) return String(number);

        const value = number < 1000000 ? (number / 1000) : (number / 1000000);

        const unit = number < 1000000 ? 'K' : 'M';

        return value.toFixed(1).replace(/\.0$/, '') + unit;
    }

    static isLiked(id)
    {
        return ReelsListWidget.likedSet().indexOf(id) !== -1;
    }

    static setLiked(id, liked)
    {
        const list = ReelsListWidget.likedSet();

        const at = list.indexOf(id);

        if (liked && at === -1) list.push(id);

        if (!liked && at !== -1) list.splice(at, 1);

        ReelsListWidget.store.set('reels_liked', JSON.stringify(list));
    }

    static likedSet()
    {
        try
        {
            const list = JSON.parse(ReelsListWidget.store.get('reels_liked', '[]'));

            return Array.isArray(list) ? list : [];
        }
        catch (e)
        {
            return [];
        }
    }

    /* ------------------------------------------------------------------ */
    /* Dọn dẹp                                                             */
    /* ------------------------------------------------------------------ */

    destroy()
    {
        if (!this.root) return;

        this.closeViewer();

        if (this.previewObserver)
        {
            this.previewObserver.disconnect();

            this.previewObserver = null;
        }

        if (this.slideObserver)
        {
            this.slideObserver.disconnect();

            this.slideObserver = null;
        }

        this.previewStopAll();

        window.removeEventListener('message', this.onMessage);

        document.removeEventListener('keydown', this.onKeydown);

        this.$(this.root).off('.reelsEl');

        if (this.viewer)
        {
            this.$(this.viewer).off('.reelsElv');

            this.viewer.remove();

            this.viewer = null;
        }

        this.slides = [];

        this.root = null;

        this.scope = null;
    }
}

/** localStorage có thể ném lỗi ở chế độ riêng tư — bọc lại một lần cho gọn. */
ReelsListWidget.store = {
    get: function (key, fallback)
    {
        try
        {
            const value = window.localStorage.getItem(key);

            return value === null ? fallback : value;
        }
        catch (e)
        {
            return fallback;
        }
    },
    set: function (key, value)
    {
        try { window.localStorage.setItem(key, value); } catch (e) { /* private mode */ }
    }
};

$(window).on('elementor/frontend/init', function ()
{
    elementorFrontend.hooks.addAction(
        'frontend/ready/ReelsListElement.default',
        function (scope, $)
        {
            const instance = new ReelsListWidget(scope, $);

            scope.data('onDestroy', function ()
            {
                instance.destroy();
            });
        }
    );
});
