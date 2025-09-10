window.CRMContactEmbedded = class {
    constructor(options) {
        this.options = options || {};
        this.app_url = backend_url + 'crm/';
        this.app_static_url = wa_url + 'wa-apps/crm/';
        this.wrapper = options.wrapper || document.body;

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

    init () {
        this.render();
        this.initToggleIframeWindow();

        return this.widget_wrapper;
    }

    destroy () {
        this.widget_wrapper.remove();
    }

    render () {
        this.widget_wrapper = this.renderWrapper();
        this.iframe_el = this.renderIframeWindow(this.options.contact_id, true);
        this.userpic_el = this.renderUserpic(this.options.userpic);

        this.close_window_button= this.renderButtonCloseWindow();
        this.iframe_el.parentElement.appendChild(this.close_window_button);
    }

    renderWrapper () {
        const widgetDiv = document.createElement('div');
        widgetDiv.classList.add('crm-widget-contact');
        this.wrapper.appendChild(widgetDiv);
        return widgetDiv;
    }

    renderIframeWindow (contact_id, default_hide = false) {
        const iframe = document.createElement('iframe');
        iframe.classList.add('crm-widget-iframe');
        iframe.loading = 'lazy';
        iframe.style.visibility = 'hidden';
        iframe.src = `${this.app_url}frame/widget/contact/${contact_id}/`;

        const iframe_wrapper = document.createElement('div');
        iframe_wrapper.classList.add('crm-iframe-wrapper', (default_hide ? 'hidden' : ''));
        iframe_wrapper.appendChild(iframe);
        this.widget_wrapper.appendChild(iframe_wrapper);


        return iframe;
    }

    renderUserpic (userpic) {
        const img_wrapper = document.createElement('div');
        img_wrapper.classList.add('crm-widget-userpic-wrapper');

        const img = document.createElement('img');
        img.classList.add('crm-widget-userpic');
        img.setAttribute('src', userpic);
        img_wrapper.appendChild(img);

        this.widget_wrapper.appendChild(img_wrapper);
        return img;
    }

    renderButtonCloseWindow () {
        const button = document.createElement('a');
        button.href = 'javascript:void(0)';
        button.classList.add('crm-widget-close-button', 'button', 'nobutton', 'small', 'circle', 'inline-link', 'hidden');
        button.style.background = 'var(--background-color-btn-light-gray) !important';

        const icon = document.createElement('i');
        icon.classList.add('fas', 'fa-times');
        button.appendChild(icon);

        return button;
    }

    initToggleIframeWindow () {
        const toggleIframeWindow = () => {
            const el = this.iframe_el.parentElement;
            const is_hidden = el.classList.contains('hidden');
            el.classList.toggle('hidden', !is_hidden);
            if (this.close_window_button.classList.contains('hidden')) {
                const showFrameWindow = () => {
                    this.iframe_el.style.visibility = 'visible';
                    this.close_window_button.classList.remove('hidden');
                };
                if (this.iframe_el.contentWindow.document.body?.firstChild) {
                    showFrameWindow();
                } else {
                    this.iframe_el.onload = showFrameWindow;
                }
            }
        };
        this.userpic_el.addEventListener('click', toggleIframeWindow);
        this.close_window_button.addEventListener('click', toggleIframeWindow);
    }
};
