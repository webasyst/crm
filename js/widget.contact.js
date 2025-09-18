window.CRMContactEmbedded = class {
    constructor(options) {
        this.options = options || {};
        this.is_premium = options.is_premium;
        this.show_when_available_element = options.show_when_available_element;
        this.contact_id = options.contact_id;
        this.locales = options.locales || {};
        this.unread_message_count = options.unread_message_count;

        // Const
        this.app_url = backend_url + 'crm/';
        this.app_static_url = wa_url + 'wa-apps/crm/';
        this.wrapper = document.body;
        this.storage_keys = {
            userpic_position: 'crm/widget/contact/userpic-position',
            widget_hide_date: 'crm/widget/contact/widget-hide-date',
            last_tab: 'crm/widget/contact/last-tab'
        };
        this.widget_hiding_days = 90;

        // Dynamic vars
        this.disabled_show = false;
        this.iframe_src = '';
        this.userpic_position = this.getCoordFromStorage();

        if (this.isDisabled()) {
            return;
        }

        if (window.CRMContactEmbeddedInstance) {
            window.CRMContactEmbeddedInstance.destroy();
        }
        window.CRMContactEmbeddedInstance = this;

        $.wa.loadSources([
            { type: 'css', uri: this.app_static_url + 'css/widget.contact.css?v=' + Date.now(), id: 'crm-contact-widget-css' }
        ]).then(() => {
            this.init();
        })
    }

    init() {
        this.render();

        this.initShowWhenAvailableElement();

        this.initToggleIframeWindow();

        this.initMoveElements();

        this.iframe_el.onload = () => {
            this.loadingSkeleton(false);
            this.initWidgetDisableHandler();
            this.initLastTabUpdateHandler();
        };

        return this.widget_wrapper;
    }

    destroy() {
        this.widget_wrapper.remove();
    }

    isDisabled() {
        if (this.is_premium) {
            return false;
        }

        const { time: current_time } = this.getCurrentDate();
        const hide_date = new Date(localStorage.getItem(this.storage_keys.widget_hide_date));
        const end_hiding_time = hide_date.setDate(hide_date.getDate() + this.widget_hiding_days);
        if (current_time < end_hiding_time) {
            return true;
        }

        return false;
    }

    initWidgetDisableHandler() {
        this.iframe_el.contentWindow.document.documentElement.addEventListener('spa:hideWidget', () => {
            const { date } = this.getCurrentDate();
            localStorage.setItem(this.storage_keys.widget_hide_date, date);
            this.destroy();
        });
    }

    initLastTabUpdateHandler() {
        this.iframe_el.contentWindow.document.documentElement.addEventListener('spa:navigateType', (e) => {
            if (!e.detail.name.endsWith('Tab')) {
                return;
            }
            const path_array = e.detail.path.split('/');
            if (path_array?.length) {
                const last_tab = path_array[path_array.length - 1];
                if (last_tab) {
                    console.log('last_tab', last_tab);
                    localStorage.setItem(this.storage_keys.last_tab, last_tab);
                }
            }
        });
    }

    getCurrentDate() {
        const date = new Date().toJSON().substring(0, 10);
        return { date, time: new Date(date).getTime() };
    }

    render() {
        this.widget_wrapper = this.renderWrapper();
        this.iframe_el = this.renderIframeWindow(this.contact_id);
        this.userpic_el = this.renderUserpic(this.options.userpic);

        // widget header
        this.link_to_contact_el = this.renderLinkToContact();
        this.iframe_el.parentElement.appendChild(this.link_to_contact_el);
        this.close_window_button = this.renderButtonCloseWindow();
        this.iframe_el.parentElement.appendChild(this.close_window_button);

        this.move_handler_el = document.createElement('div');
        this.move_handler_el.classList.add('crm-widget-move');
        this.iframe_el.parentElement.appendChild(this.move_handler_el);
    }

    renderWrapper() {
        const widgetDiv = document.createElement('div');
        widgetDiv.classList.add('crm-widget-contact');
        this.wrapper.appendChild(widgetDiv);
        return widgetDiv;
    }

    renderIframeWindow(contact_id) {
        const iframe = document.createElement('iframe');
        iframe.classList.add('crm-widget-iframe');
        iframe.loading = 'lazy';
        iframe.style.visibility = 'hidden';
        iframe.setAttribute('draggable', false);

        const last_tab = localStorage.getItem(this.storage_keys.last_tab) ?? '';
        const tab_path = this.unread_message_count > 0 ? 'messages' : last_tab;
        this.iframe_src = `${this.app_url}frame/widget/contact/${contact_id}/${tab_path}`;

        const iframe_wrapper = document.createElement('div');
        iframe_wrapper.classList.add('crm-iframe-wrapper', 'hidden');

        iframe_wrapper.appendChild(iframe);
        this.widget_wrapper.appendChild(iframe_wrapper);

        return iframe;
    }

    renderUserpic(userpic) {
        // userpic
        const img = document.createElement('img');
        img.classList.add('crm-widget-userpic');
        img.setAttribute('src', userpic);
        img.setAttribute('draggable', false);

        const img_outline = document.createElement('div');
        img_outline.classList.add('crm-widget-userpic-outline');
        img_outline.appendChild(img);

        const img_inner = document.createElement('div');
        img_inner.classList.add('crm-widget-userpic-inner');
        img_inner.appendChild(img_outline);

        const img_wrapper = document.createElement('div');
        img_wrapper.classList.add('crm-widget-userpic-wrapper');
        img_wrapper.style.left = `${this.userpic_position[0]}px`;
        img_wrapper.style.top = `${this.userpic_position[1]}px`;
        img_wrapper.appendChild(img_inner);

        // индикатор непрочитанных сообщений
        if (this.unread_message_count > 0) {
            const badge = document.createElement('span');
            badge.classList.add('crm-widget-indicator', 'badge', 'red', 'small');
            badge.appendChild(document.createTextNode(this.unread_message_count));
            img_wrapper.appendChild(badge);
        }
        // анимация блеска
        const img_flare = document.createElement('div');
        img_flare.classList.add('crm-widget-userpic-flare');
        img_outline.appendChild(img_flare);

        this.widget_wrapper.appendChild(img_wrapper);
        return img;
    }

    renderButtonCloseWindow() {
        const button = document.createElement('a');
        button.href = 'javascript:void(0)';
        button.classList.add('crm-widget-close-button', 'button', 'small', 'circle', 'light-gray', 'hidden');

        const icon = document.createElement('i');
        icon.classList.add('fas', 'fa-times');
        button.appendChild(icon);

        return button;
    }

    renderLinkToContact() {
        const link = document.createElement('a');
        link.href = `${this.app_url}contact/${this.contact_id}/`;
        link.classList.add('crm-widget-link-to-contact', 'small', 'hidden');
        link.setAttribute('data-wa-tooltip-content', this.locales.link_to_contact);
        if (typeof $ === 'function' && typeof $().waTooltip === 'function') {
            $(link).waTooltip({ delay: 1000, placement: 'bottom', maxWidth: '9rem' });
        }
        return link;
    }

    initShowWhenAvailableElement() {
        if (!this.show_when_available_element) {
            return;
        }
        const target = this.show_when_available_element;
        const parent = this.wrapper;
        const observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (!parent.contains(target)) {
                    // console.log('Element has been removed');
                    observer.disconnect();
                    this.destroy();
                    break;
                }
                if (target.parentElement && target.parentElement.style.display === 'none') {
                    // console.log('Element was hidden');
                    observer.disconnect();
                    this.destroy();
                    break;
                }
            }
        });
        observer.observe(parent, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['style']
        });
    }

    moveElement(target, options = { disablePointerEventsWhenMoving: false, disableDragClass: '', updateCoords: null }) {
        let shiftX = 0;
        let shiftY = 0;
        let previousClientX = null;
        let previousClientY = null;

        const moveAt = (clientX, clientY) => {
            this.disabled_show = previousClientX !== null && (previousClientX !== clientX || previousClientY !== clientY);

            previousClientX = clientX;
            previousClientY = clientY;
            const x = clientX - shiftX;
            const y = clientY - shiftY;
            target.style.top = `${y}px`;
            target.style.left = `${x}px`;
            if (typeof options.updateCoords === 'function') {
                options.updateCoords(x, y);
            }
        };

        const onMouseMove = (e) => {
            moveAt(e.clientX, e.clientY);
        };

        let mousemove_timeout = null;
        const onMouseDown = (e) => {
            this.disabled_show = false;
            if (options.disableDragClass && !e.target.closest('.' + options.disableDragClass)) {
                return;
            }
            if (options.disablePointerEventsWhenMoving) {
                target.firstChild.style.pointerEvents = 'none';
            }
            shiftX = e.clientX - target.getBoundingClientRect().left;
            shiftY = e.clientY - target.getBoundingClientRect().top;

            mousemove_timeout = setTimeout(() => {
                // moveAt(e.clientX, e.clientY);
                document.addEventListener('mousemove', onMouseMove)
            }, 100);

            target.addEventListener('mouseup', onMouseUp);
        };

        const onMouseUp = () => {
            if (options.disablePointerEventsWhenMoving) {
                target.firstChild.style.pointerEvents = 'all';
            }
            clearTimeout(mousemove_timeout);
            document.removeEventListener('mousemove', onMouseMove);
            target.removeEventListener('mouseup', onMouseUp);

            localStorage.setItem(this.storage_keys.userpic_position, this.userpic_position.join(','));
        };

        target.addEventListener('mousedown', onMouseDown);
    }

    initToggleIframeWindow() {
        const iframe_wrapper = this.iframe_el.parentElement;
        const show_class = 'show-animation';
        const hide_class = 'hide-animation';
        const userpic_wrapper = this.userpic_el.closest('.crm-widget-userpic-wrapper');

        const toggleAnimation = (show, callbackHide) => {
            iframe_wrapper.classList.remove(show_class, hide_class);
            if (show) {
                iframe_wrapper.classList.add(show_class);
            } else {
                setTimeout(() => {
                    iframe_wrapper.classList.add(hide_class);

                    const animation_time = 100;
                    setTimeout(() => {
                        if (typeof callbackHide === 'function') {
                            callbackHide();
                        }
                    }, animation_time);
                });
            }
        };

        const showIframeAndControls = () => {
            this.iframe_el.style.visibility = 'visible';
            this.close_window_button.classList.remove('hidden');
            this.link_to_contact_el.classList.remove('hidden');
            toggleAnimation(true, () => {
                iframe_wrapper.classList.add('hidden')
            });
            userpic_wrapper.querySelector('.crm-widget-indicator')?.remove();
        };

        const onToggleIframe = (e) => {
            if (this.disabled_show) {
                e.preventDefault();
                return;
            }

            const is_hidden = iframe_wrapper.classList.contains('hidden');
            if (is_hidden) {
                iframe_wrapper.classList.remove('hidden');
                this.placePopup(userpic_wrapper, iframe_wrapper, {
                    correct: ({ left, top, chosen, targetRect, popupWidth, popupHeight }) => {
                        if (chosen.name === 'left') {
                            const newLeft = left + 60;
                            const newTop = top - 112;
                            if (newTop + popupHeight <= targetRect.bottom) {
                                top = newTop;
                                if (newLeft + popupWidth <= targetRect.right) {
                                    left = newLeft;
                                }
                            }
                        }
                        return { left, top };
                    }
                });
            }

            // при первом открытии виджета
            if (!this.iframe_el.src) {
                this.iframe_el.src = this.iframe_src;
                this.loadingSkeleton(true);
                showIframeAndControls();
                return;
            } else {
                toggleAnimation(is_hidden, () => {
                    iframe_wrapper.classList.add('hidden');
                });
            }
        };
        userpic_wrapper.addEventListener('click', onToggleIframe);
        this.close_window_button.addEventListener('click', onToggleIframe);
    }

    initMoveElements() {
        const userpic = this.userpic_el.closest('.crm-widget-userpic-wrapper');
        const iframe_parent = this.iframe_el.parentElement;
        let userpicInnerWidth = innerWidth;
        let userpicInnerHeight = innerHeight;
        let iframeInnerWidth = innerWidth;
        let iframeInnerHeight = innerHeight;
        let iframe_left = null;
        let iframe_top = null;

        this.moveElement(userpic, {
            updateCoords: (x, y) => {
                this.userpic_position[0] = x;
                this.userpic_position[1] = y;
                userpicInnerWidth = innerWidth;
                userpicInnerHeight = innerHeight;
            }
        });

        this.moveElement(iframe_parent, {
            disablePointerEventsWhenMoving: true,
            disableDragClass: 'crm-widget-move',
            updateCoords: (x, y) => {
                iframe_left = x;
                iframe_top = y;
                iframeInnerWidth = innerWidth;
                iframeInnerHeight = innerHeight;
            }
        });

        window.addEventListener('resize', () => {
            if (iframe_left === null) {
                const iframe_rect = iframe_parent.getBoundingClientRect();
                iframe_left = iframe_rect.left;
                iframe_top = iframe_rect.top;
            }
            userpic.style.left = `${this.userpic_position[0] + (innerWidth - userpicInnerWidth)}px`;
            userpic.style.top = `${this.userpic_position[1] + (innerHeight - userpicInnerHeight)}px`;
            iframe_parent.style.left  = `${iframe_left + (innerWidth - iframeInnerWidth)}px`;
            iframe_parent.style.top = `${iframe_top + (innerHeight - iframeInnerHeight)}px`;
        });
    }

    loadingSkeleton(show = true) {
        if (!show) {
            this.widget_wrapper.querySelector('.skeleton')?.remove();
            return;
        }

        const skeleton = document.createElement('div');
        skeleton.classList.add('skeleton', 'crm-widget-skeleton');
        for (let i = 0;i < 24;i++) {
            const skeleton_line = document.createElement('div');
            skeleton_line.classList.add('skeleton-line');
            skeleton.appendChild(skeleton_line);
        }
        this.iframe_el.parentElement.appendChild(skeleton);
    }


    getCoordFromStorage () {
        const default_x = innerWidth - 112;
        const default_y = innerHeight - 112;
        const storage_value = localStorage.getItem(this.storage_keys.userpic_position);
        if (!storage_value) {
            return [default_x, default_y];
        }
        const coord = storage_value.split(',').map(Number);
        const x = isNaN(coord[0]) ? default_x : coord[0];
        const y = isNaN(coord[1]) ? default_y : coord[1];

        const result = [Math.min(x, default_x), Math.min(y, default_y)];
        return result;
    }

    placePopup(target, popup, options = {}) {
        const padding = options.padding ?? 8;
        const rect = target.getBoundingClientRect();
        const vw = window.innerWidth; // || 500;
        const vh = window.innerHeight; // || Math.round(innerHeight * 0.7);

        const w = popup.offsetWidth;
        const h = popup.offsetHeight;
        const candidates = [];
        // слева (align top по умолчанию)
        candidates.push({
            name: 'left',
            left: rect.left - w - padding,
            top: rect.top,
            fits: rect.left >= (w + padding)
        });
        candidates.push({
            name: 'right',
            left: rect.right + padding,
            top: rect.top,
            fits: (vw - (rect.right + padding)) >= w
        });
        // снизу (под центр target)
        candidates.push({
            name: 'bottom',
            left: rect.left + (rect.width - w) / 2,
            top: rect.bottom + padding,
            fits: (vh - (rect.bottom + padding)) >= h
        });
        candidates.push({
            name: 'top',
            left: rect.left + (rect.width - w) / 2,
            top: rect.top - h - padding,
            fits: rect.top >= (h + padding)
        });

        let chosen = candidates.find(c => c.fits);
        if (!chosen) {
            // оценка доступного места (положительная величина)
            candidates.forEach(c => {
                if (c.name === 'right') c.space = vw - (rect.right + padding);
                if (c.name === 'left') c.space = rect.left - padding;
                if (c.name === 'bottom') c.space = vh - (rect.bottom + padding);
                if (c.name === 'top') c.space = rect.top - padding;
            });
            candidates.sort((a, b) => b.space - a.space);
            chosen = candidates[0];
        }

        // скорректировать, чтобы не выходило за экран
        const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
        let finalLeft = clamp(chosen.left, padding, Math.max(padding, vw - w - padding));
        let finalTop = clamp(chosen.top, padding, Math.max(padding, vh - h - padding));

        if (typeof options.correct === 'function') {
            let result = options.correct({ left: finalLeft, top: finalTop, chosen, targetRect: rect, popupWidth : w, popupHeight: h });
            finalLeft = result.left;
            finalTop = result.top;
        }
        popup.style.left = `${finalLeft}px`;
        popup.style.top = `${finalTop}px`;
    }
};
