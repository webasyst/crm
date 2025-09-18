/*! iFrame Resizer (iframeSizer.min.js ) - v2.6.2 - 2014-10-11
 *  Desc: Force cross domain iframes to size to content.
 *  Requires: iframeResizer.contentWindow.min.js to be loaded into the target frame.
 *  Copyright: (c) 2014 David J. Bradshaw - dave@bradshaw.net
 *  License: MIT
 */

!function () { "use strict"; function a (a, b, c) { "addEventListener" in window ? a.addEventListener(b, c, !1) : "attachEvent" in window && a.attachEvent("on" + b, c); } function b () { var a, b = ["moz", "webkit", "o", "ms"]; for (a = 0; a < b.length && !w; a += 1)w = window[b[a] + "RequestAnimationFrame"]; w || c(" RequestAnimationFrame not supported"); } function c (a) { y.log && "object" == typeof console && console.log(s + "[Host page" + u + "]" + a); } function d (a) { function b () { function a () { h(z), f(), y.resizedCallback(z); } i(a, z, "resetPage"); } function d (a) { var b = a.id; c(" Removing iFrame: " + b), a.parentNode.removeChild(a), y.closedCallback(b), c(" --"); } function e () { var a = x.substr(t).split(":"); return { iframe: document.getElementById(a[0]), id: a[0], height: a[1], width: a[2], type: a[3] }; } function j (a) { var b = Number(y["max" + a]), d = Number(y["min" + a]), e = a.toLowerCase(), f = Number(z[e]); if (d > b) throw new Error("Value for min" + a + " can not be greater than max" + a); c(" Checking " + e + " is in range " + d + "-" + b), d > f && (f = d, c(" Set " + e + " to min value")), f > b && (f = b, c(" Set " + e + " to max value")), z[e] = "" + f; } function k () { var b = a.origin, d = z.iframe.src.split("/").slice(0, 3).join("/"); if (y.checkOrigin && (c(" Checking connection is from: " + d), "" + b != "null" && b !== d)) throw new Error("Unexpected message received from: " + b + " for " + z.iframe.id + ". Message was: " + a.data + ". This error can be disabled by adding the checkOrigin: false option."); return !0; } function l () { return s === ("" + x).substr(0, t); } function m () { var a = z.type in { "true": 1, "false": 1 }; return a && c(" Ignoring init message from meta parent page"), a; } function n () { var a = x.substr(x.indexOf(":") + r + 6); c(" MessageCallback passed: {iframe: " + z.iframe.id + ", message: " + a + "}"), y.messageCallback({ iframe: z.iframe, message: a }), c(" --"); } function o () { if (null === z.iframe) throw new Error("iFrame (" + z.id + ") does not exist on " + u); return !0; } function q () { c(" Reposition requested from iFrame"), v = { x: z.width, y: z.height }, f(); } function w () { switch (z.type) { case "close": d(z.iframe), y.resizedCallback(z); break; case "message": n(); break; case "scrollTo": q(); break; case "reset": g(z); break; case "init": b(), y.initCallback(z.iframe); break; default: b(); } } var x = a.data, z = {}; l() && (c(" Received: " + x), z = e(), j("Height"), j("Width"), !m() && o() && k() && (w(), p = !1)); } function e () { null === v && (v = { x: void 0 !== window.pageXOffset ? window.pageXOffset : document.documentElement.scrollLeft, y: void 0 !== window.pageYOffset ? window.pageYOffset : document.documentElement.scrollTop }, c(" Get position: " + v.x + "," + v.y)); } function f () { null !== v && (window.scrollTo(v.x, v.y), c(" Set position: " + v.x + "," + v.y), v = null); } function g (a) { function b () { h(a), j("reset", "reset", a.iframe); } c(" Size reset requested by " + ("init" === a.type ? "host page" : "iFrame")), e(), i(b, a, "init"); } function h (a) { function b (b) { a.iframe.style[b] = a[b] + "px", c(" IFrame (" + a.iframe.id + ") " + b + " set to " + a[b] + "px"); } y.sizeHeight && b("height"), y.sizeWidth && b("width"); } function i (a, b, d) { d !== b.type && w ? (c(" Requesting animation frame"), w(a)) : a(); } function j (a, b, d) { c("[" + a + "] Sending msg to iframe (" + b + ")"), d.contentWindow.postMessage(s + b, "*"); } function k () { function b () { function a (a) { 1 / 0 !== y[a] && 0 !== y[a] && (k.style[a] = y[a] + "px", c(" Set " + a + " = " + y[a] + "px")); } a("maxHeight"), a("minHeight"), a("maxWidth"), a("minWidth"); } function d (a) { return "" === a && (k.id = a = "iFrameResizer" + o++, c(" Added missing iframe ID: " + a + " (" + k.src + ")")), a; } function e () { c(" IFrame scrolling " + (y.scrolling ? "enabled" : "disabled") + " for " + l), k.style.overflow = !1 === y.scrolling ? "hidden" : "auto", k.scrolling = !1 === y.scrolling ? "no" : "yes"; } function f () { ("number" == typeof y.bodyMargin || "0" === y.bodyMargin) && (y.bodyMarginV1 = y.bodyMargin, y.bodyMargin = "" + y.bodyMargin + "px"); } function h () { return l + ":" + y.bodyMarginV1 + ":" + y.sizeWidth + ":" + y.log + ":" + y.interval + ":" + y.enablePublicMethods + ":" + y.autoResize + ":" + y.bodyMargin + ":" + y.heightCalculationMethod + ":" + y.bodyBackground + ":" + y.bodyPadding + ":" + y.tolerance; } function i (b) { a(k, "load", function () { var a = p; j("iFrame.onload", b, k), !a && y.heightCalculationMethod in x && g({ iframe: k, height: 0, width: 0, type: "init" }); }), j("init", b, k); } var k = this, l = d(k.id); e(), b(), f(), i(h()); } function l (a) { if ("object" != typeof a) throw new TypeError("Options is not an object."); } function m () { function a (a) { if ("IFRAME" !== a.tagName.toUpperCase()) throw new TypeError("Expected <IFRAME> tag, found <" + a.tagName + ">."); k.call(a); } function b (a) { a = a || {}, l(a); for (var b in z) z.hasOwnProperty(b) && (y[b] = a.hasOwnProperty(b) ? a[b] : z[b]); } return function (c, d) { b(c), Array.prototype.forEach.call(document.querySelectorAll(d || "iframe"), a); }; } function n (a) { a.fn.iFrameResize = function (b) { return b = b || {}, l(b), y = a.extend({}, z, b), this.filter("iframe").each(k).end(); }; } var o = 0, p = !0, q = "message", r = q.length, s = "[iFrameSizer]", t = s.length, u = "", v = null, w = window.requestAnimationFrame, x = { max: 1, scroll: 1, bodyScroll: 1, documentElementScroll: 1 }, y = {}, z = { autoResize: !0, bodyBackground: null, bodyMargin: null, bodyMarginV1: 8, bodyPadding: null, checkOrigin: !0, enablePublicMethods: !1, heightCalculationMethod: "offset", interval: 32, log: !1, maxHeight: 1 / 0, maxWidth: 1 / 0, minHeight: 0, minWidth: 0, scrolling: !1, sizeHeight: !0, sizeWidth: !1, tolerance: 0, closedCallback: function () { }, initCallback: function () { }, messageCallback: function () { }, resizedCallback: function () { } }; b(), a(window, "message", d), window.jQuery && n(jQuery), "function" == typeof define && define.amd ? define(function () { return m(); }) : window.iFrameResize = m(); }();
//# sourceMappingURL=../src/iframeResizer.map

(() => {

    class CRMFormEmbedded {
        /**
         * @param {Object} options - Параметры инициализации.
         * @param {string} options.iframe_url - URL для загрузки iframe.
         * @param {string} [options.header=""] - Заголовок формы.
         * @param {string} [options.theme] - Тема.
         * @param {"dialog"|"drawer"} [options.display_container="dialog"] - Тип контейнера отображения.
         * @param {"fab"|"inline"} [options.display_conditions] - Условия отображения формы.
         * @param {string} [options.display_fab_text] - Текст на кнопке.
         * @param {string} [options.display_fab_color] - Цвет фона кнопки. В HEX формате.
         * @param {string} [options.container] - Селектор контейнера для вставки формы.
         * @param {string} [options.custom_element] - Селектор элемента по клику на который открывается форма.
         * @param {number} [options.display_timeout] - Задержка перед показом формы в миллисекундах.
         * @param {number} [options.show_after_timeout] - Период, который форма не будет показываться в автоматическом режиме. В миллисекундах.
         * @param {number} [options.display_scroll] - Прокрутка страницы вниз в пикселях.
         * @param {number} [options.max_width] - Максимальная ширина контейнера в пикселях.
         * @param {boolean} [options.no_brending]
         * @param {string} [options.display_fab_icon] - Иконка в формате fontawesome
         */
        constructor(options) {

            if (!this.isValidOptions(options)) return;

            this.iframeUrl = options.iframe_url;
            this.header = options.header || "";
            this.theme = options.theme;
            this.displayContainer = options.display_container || "dialog";
            this.displayConditions = options.display_conditions;
            this.displayFabText = options.display_fab_text;
            this.displayFabColor = options.display_fab_color;
            this.displayTimeout = options.display_timeout;
            this.container = options.container;
            this.customElement = options.custom_element;
            this.showAfterTimeout = options.show_after_timeout || 24 * 60 * 60 * 1000;
            this.displayScroll = options.display_scroll;
            this.maxWidth = options.max_width || 400;
            this.noBrending = options.no_brending;
            this.displayFabIcon  = options.display_fab_icon;
            this.iframeId = `wa-crm-iframe-${this.generateUniqueId()}`;
            this.locale = options.locale || "en";
            this.powered_by = '<a href="https://www.webasyst.com/store/app/crm/" target="_blank" rel="noopener">Powered by Webasyst CRM</a>';
            if(this.locale == 'ru') {
                this.powered_by = '<a href="https://www.webasyst.ru/store/app/crm/" target="_blank" rel="noopener">Работает на Webasyst CRM</a>';
            }
            this.panel = null;
            this.overlay = null;
            this.escHandlerInited = false;

            this.availableIcons = ['fas fa-phone', 'fas fa-comment-dots', 'fas fa-question-circle', 'fas fa-envelope', 'fas fa-bell', 'fas fa-info-circle', 'fas fa-concierge-bell', 'fas fa-life-ring'];

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.init());
            } else {
                this.init();
            }
        }

        /**
         * Валидация параметров конструктора
         * @param {Object} options - Параметры для валидации
         * @returns {boolean}
         */
        isValidOptions (options) {
            try {
                if (!options || typeof options !== 'object') {
                    throw new Error('CRMFormEmbedded: options должен быть объектом');
                }

                if (!options.iframe_url) {
                    throw new Error('CRMFormEmbedded: параметр iframe_url обязателен');
                }

                if (typeof options.iframe_url !== 'string') {
                    throw new Error('CRMFormEmbedded: iframe_url должен быть строкой');
                }

                try {
                    new URL(options.iframe_url);
                } catch (e) {
                    throw new Error('CRMFormEmbedded: iframe_url должен быть валидным URL');
                }

                if (options.header !== undefined && typeof options.header !== 'string') {
                    throw new Error('CRMFormEmbedded: header должен быть строкой');
                }

                if (options.theme !== undefined && typeof options.theme !== 'string') {
                    throw new Error('CRMFormEmbedded: theme должен быть строкой');
                }

                if (options.display_fab_text !== undefined && typeof options.display_fab_text !== 'string') {
                    throw new Error('CRMFormEmbedded: display_fab_text должен быть строкой');
                }

                if (options.container !== undefined && typeof options.container !== 'string') {
                    throw new Error('CRMFormEmbedded: container должен быть строкой');
                }

                if (options.custom_element !== undefined && typeof options.custom_element !== 'string') {
                    throw new Error('CRMFormEmbedded: custom_element должен быть строкой');
                }

                if (options.display_fab_icon !== undefined && typeof options.display_fab_icon !== 'string') {
                    throw new Error('CRMFormEmbedded: display_fab_icon должен быть строкой');
                }

                if (options.display_container !== undefined) {
                    const validContainers = ['dialog', 'drawer'];
                    if (!validContainers.includes(options.display_container)) {
                        throw new Error(`CRMFormEmbedded: display_container должен быть одним из: ${validContainers.join(', ')}`);
                    }
                }

                if (options.display_conditions !== undefined) {
                    const validConditions = ['fab', 'inline'];
                    if (!validConditions.includes(options.display_conditions)) {
                        throw new Error(`CRMFormEmbedded: display_conditions должен быть одним из: ${validConditions.join(', ')}`);
                    }
                }

                if (options.display_timeout !== undefined) {
                    if (!this.isValidNumber(options.display_timeout) || options.display_timeout < 0) {
                        throw new Error('CRMFormEmbedded: display_timeout должен быть неотрицательным числом');
                    }
                }

                if (options.show_after_timeout !== undefined) {
                    if (!this.isValidNumber(options.show_after_timeout) || options.show_after_timeout < 0) {
                        throw new Error('CRMFormEmbedded: show_after_timeout должен быть неотрицательным числом');
                    }
                }

                if (options.display_scroll !== undefined) {
                    if (!this.isValidNumber(options.display_scroll) || options.display_scroll < 0) {
                        throw new Error('CRMFormEmbedded: display_scroll должен быть неотрицательным числом');
                    }
                }

                if (options.max_width !== undefined) {
                    if (!this.isValidNumber(options.max_width) || options.max_width <= 0) {
                        throw new Error('CRMFormEmbedded: max_width должен быть положительным числом');
                    }
                }

                if (options.no_brending !== undefined && typeof options.no_brending !== 'boolean') {
                    throw new Error('CRMFormEmbedded: no_brending должен быть boolean');
                }

                return true;
            } catch (e) {
                console.error(e.message);
                return false;
            }
        }

        isValidNumber (value) {
            return typeof value === 'number' && !isNaN(value) && isFinite(value);
        }

        init () {
            this.createIframe();

            if (this.displayConditions === 'inline' && this.container) {
                this.insertFrameToContainer();
                return;
            }

            this.addScrollListener();
            if (this.displayTimeout) {
                this.showContainerInAutoMode(this.displayTimeout);
            }
            if (this.displayConditions === 'fab') {
                this.createCircle();
            }
            if (this.customElement) {
                this.addCustomElementHandler();
            }
        }

        addCustomElementHandler () {
            document.addEventListener('click', (e) => {
                if (e.target.closest(this.customElement)) {
                    this.showContainer();
                }
            });
        }

        addScrollListener () {
            const handleScroll = () => {
                const scrollTop = window.scrollY || document.documentElement.scrollTop;
                if (scrollTop > this.displayScroll) {
                    this.showContainerInAutoMode();
                    document.removeEventListener('scroll', handleScroll);
                }
            };
            if (this.displayScroll) {
                document.addEventListener('scroll', handleScroll);
            }
        }

        /**
         * Генерация уникального ID для iframe.
         * @returns {string} Уникальный идентификатор.
         */
        generateUniqueId () {
            return Date.now().toString(36) + Math.random().toString(36).substring(2);
        }

        isDarkTheme () {
            if (this.theme) return this.theme === 'dark';

            try {
                const parentTheme = window.parent?.document?.documentElement?.dataset?.theme;
                if (parentTheme) return parentTheme === 'dark';
            } catch { }

            return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
        }

        createIframe () {
            let iframeSrc = this.iframeUrl;

            if (this.isDarkTheme()) {
                const url = new URL(iframeSrc, window.location.origin);
                url.searchParams.set('theme', 'dark');
                iframeSrc = url.toString();
            }

            this.iframe = document.createElement("iframe");
            this.iframe.id = this.iframeId;
            this.iframe.name = this.iframeId;
            this.iframe.src = iframeSrc;
            this.iframe.style.width = "100%";
            this.iframe.style.minHeight = "400px";
            this.iframe.style.border = "none";
            this.iframe.style.opacity = "0";
            this.iframe.style.transition = "height 0.3s ease, opacity 0.3s ease";
            this.iframe.setAttribute("frameborder", "0");
            this.iframe.setAttribute("marginheight", "0");
            this.iframe.setAttribute("marginwidth", "0");
            this.iframe.setAttribute("scrolling", "no");
        }

        showContainerInAutoMode (timer = 0) {
            setTimeout(() => {
                if (!this.shouldShowForm()) return;
                this.showContainer();
            }, timer);
        }

        showContainer (timer = 0) {
            setTimeout(() => {
                this.show({ type: this.displayContainer, content: this.iframe.outerHTML });
            }, timer);
        }

        createRootElement () {
            const rootElement = document.createElement('div');
            rootElement.id = 'wa-embeded-widget-iframe';
            return rootElement;
        }

        createWrapper (content, withHeader) {
            return `
                ${withHeader ? `
                    <div class="wa-embeded-widget-iframe-header">
                    <div class="wa-embeded-widget-iframe-title">${this.header}</div>
                    <div class="wa-embeded-widget-iframe-close"></div>
                </div>    
                ` : ''}
                <div class="wa-embeded-widget-iframe-drawer">
                    <div class="wa-embeded-widget-iframe-loader">
                        <div class="wa-embeded-widget-iframe-spinner"></div>
                    </div>
                    ${content}
                </div>
                ${!this.noBrending ? `<div class="wa-embeded-widget-iframe-brending">
                    <svg width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_11077_59454)"><path d="M11.04 15.953c.028-.096.165-.096.192 0l.361 1.276a.348.348 0 0 0 .24.24l1.277.362c.096.027.096.163 0 .19l-1.277.362a.348.348 0 0 0-.24.24l-.361 1.276c-.027.097-.164.097-.191 0l-.362-1.276a.348.348 0 0 0-.24-.24l-1.276-.361c-.096-.027-.096-.164 0-.191l1.276-.361a.348.348 0 0 0 .24-.24l.362-1.277Z" fill="url(#paint0_linear_11077_59454)"/><path d="M17.383 10.515c.034-.118.2-.118.234 0l.441 1.56c.04.142.152.253.294.293l1.56.442c.117.033.117.2 0 .233l-1.56.442a.425.425 0 0 0-.294.293l-.441 1.56c-.034.118-.2.118-.234 0l-.441-1.56a.425.425 0 0 0-.294-.293l-1.56-.441c-.117-.034-.117-.2 0-.234l1.56-.442a.425.425 0 0 0 .294-.293l.441-1.56Z" fill="url(#paint1_linear_11077_59454)"/><path d="M6.215 10.206c.042-.15.255-.15.297 0l.562 1.985c.051.181.193.323.373.374l1.986.562c.15.042.15.255 0 .297l-1.986.562a.541.541 0 0 0-.373.374l-.562 1.985c-.042.15-.255.15-.297 0l-.562-1.985a.542.542 0 0 0-.373-.374l-1.986-.562c-.15-.042-.15-.255 0-.297l1.985-.562a.541.541 0 0 0 .374-.374l.562-1.985Z" fill="url(#paint2_linear_11077_59454)"/><path d="M1.95 5.953c.027-.096.164-.096.191 0l.361 1.276a.348.348 0 0 0 .24.24l1.277.362c.096.027.096.164 0 .19l-1.277.362a.348.348 0 0 0-.24.24L2.142 9.9c-.028.097-.165.097-.192 0l-.362-1.276a.348.348 0 0 0-.24-.24L.073 8.022c-.096-.027-.096-.164 0-.191L1.35 7.47a.348.348 0 0 0 .24-.24l.36-1.277Z" fill="url(#paint3_linear_11077_59454)"/><path d="M5.802.507c.03-.107.182-.107.213 0l.401 1.418c.037.13.138.23.267.267l1.418.401c.107.03.107.182 0 .213l-1.418.401a.387.387 0 0 0-.267.267l-.401 1.418c-.03.107-.182.107-.213 0l-.401-1.418a.387.387 0 0 0-.267-.267l-1.418-.401c-.107-.03-.107-.182 0-.213l1.418-.401a.387.387 0 0 0 .267-.267L5.802.507Z" fill="url(#paint4_linear_11077_59454)"/><path d="M17.642 1.4c.025-.085.146-.085.17 0l.322 1.135a.31.31 0 0 0 .213.213l1.134.321c.086.024.086.146 0 .17l-1.134.321a.31.31 0 0 0-.213.214l-.322 1.134c-.024.086-.145.086-.17 0l-.32-1.134a.31.31 0 0 0-.214-.214l-1.135-.32c-.085-.025-.085-.147 0-.17l1.135-.322a.31.31 0 0 0 .213-.213l.321-1.135Z" fill="url(#paint5_linear_11077_59454)"/><path d="M12.203 5.879c3.46 0 6.325 1.151 6.646 1.245.322.094.322.567 0 .66l-4.254 1.248c-.387.113-.69.427-.8.828-.11.402-1.116 4.072-1.204 4.406a.328.328 0 0 1-.637 0c-.428-1.732-.749-2.682-1.205-4.406a1.182 1.182 0 0 0-.8-.828c-1.657-.502-2.593-.76-4.254-1.247-.322-.094-.387-.503 0-.66 1.526-.62 3.046-1.246 6.508-1.246Z" fill="url(#paint6_linear_11077_59454)"/><path d="M12.292.417a2.292 2.292 0 1 1 0 4.583 2.292 2.292 0 0 1 0-4.583Z" fill="url(#paint7_linear_11077_59454)"/></g><defs><linearGradient id="paint0_linear_11077_59454" x1="21.5207" y1="15.775" x2="6.89238" y2="32.3596" gradientUnits="userSpaceOnUse"><stop offset="0.215638" stop-color="#999999"/><stop offset="0.791315" stop-color="#949494"/></linearGradient><linearGradient id="paint1_linear_11077_59454" x1="30.192" y1="10.2974" x2="12.313" y2="30.5674" gradientUnits="userSpaceOnUse"><stop offset="0.317655" stop-color="#BABABA"/><stop offset="0.577647" stop-color="#949494"/></linearGradient><linearGradient id="paint2_linear_11077_59454" x1="22.5169" y1="9.9291" x2="-0.238151" y2="35.7273" gradientUnits="userSpaceOnUse"><stop offset="0.215638" stop-color="#999999"/><stop offset="0.791315" stop-color="#949494"/></linearGradient><linearGradient id="paint3_linear_11077_59454" x1="12.4298" y1="5.77501" x2="-2.19844" y2="22.3596" gradientUnits="userSpaceOnUse"><stop offset="0.215638" stop-color="#999999"/><stop offset="0.791315" stop-color="#949494"/></linearGradient><linearGradient id="paint4_linear_11077_59454" x1="14.8091" y1="0.107188" x2="-8.83326" y2="27.7688" gradientUnits="userSpaceOnUse"><stop offset="0.19232" stop-color="#BDBDBD"/><stop offset="0.297596" stop-color="#909090"/></linearGradient><linearGradient id="paint5_linear_11077_59454" x1="26.9579" y1="1.24185" x2="13.955" y2="15.9837" gradientUnits="userSpaceOnUse"><stop offset="0.215638" stop-color="#CCCCCC"/><stop offset="0.685172" stop-color="#8C8C8C"/></linearGradient><linearGradient id="paint6_linear_11077_59454" x1="39.0111" y1="5.27172" x2="0.97381" y2="75.671" gradientUnits="userSpaceOnUse"><stop offset="0.150602" stop-color="#C1C1C1"/><stop offset="0.205293" stop-color="#9F9F9F"/></linearGradient><linearGradient id="paint7_linear_11077_59454" x1="21.2665" y1="0.0947595" x2="-2.57291" y2="27.9869" gradientUnits="userSpaceOnUse"><stop offset="0.137358" stop-color="#C4C4C4"/><stop offset="0.297596" stop-color="#9C9C9C"/></linearGradient><clipPath id="clip0_11077_59454"><rect width="20" height="20" fill="white"/></clipPath></defs></svg>
                    ${this.powered_by}
                </div>` : ''}
            `;
        }

        addFrameResizer () {
            iFrameResize({
                resizedCallback: () => {
                    const frame = document.getElementById(this.iframeId);
                    const loader = document.querySelector('.wa-embeded-widget-iframe-loader');

                    frame.style.minHeight = '';
                    frame.style.opacity = '1';
                    loader.style.display = 'none';
                }
            }, `#${this.iframeId}`);
        }

        insertFrameToContainer () {
            const inlineContainer = document.querySelector(this.container);
            if (!inlineContainer) return;
            const rootElement = this.createRootElement();
            rootElement.innerHTML = this.createWrapper(this.iframe.outerHTML);
            inlineContainer.innerHTML = rootElement.outerHTML;
            this.addFrameResizer();
        }

        /**
         * Проверяет, нужно ли отображать форму (не показывать чаще, чем раз в showAfterTimeout).
         * @returns {boolean} true, если форму можно показывать.
         */
        shouldShowForm () {
            const storage = this.getStorage();
            if (!storage[this.iframeUrl]) return true;
            return Date.now() - storage[this.iframeUrl] > this.showAfterTimeout;
        }

        /**
         * Обновляет данные в localStorage.
         * @param {Object} data - Объект данных для сохранения.
         */
        updateStorage (data) {
            const storage = this.getStorage();
            const newData = { ...storage, ...data };
            localStorage.setItem('crm/formembeded', JSON.stringify(newData));
        }

        /**
         * Получает данные из localStorage.
         * @returns {Object} Данные или пустой объект.
         */
        getStorage () {
            if (typeof localStorage === 'undefined') return {};

            const raw = localStorage.getItem('crm/formembeded');
            if (!raw) return {};

            try {
                const parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch {
                return {};
            }
        }

        createPanel ({ type, content }) {
            this.overlay = document.createElement('div');
            Object.assign(this.overlay.style, {
                position: 'fixed',
                top: 0,
                left: 0,
                width: '100%',
                height: '100%',
                background: 'rgba(0,0,0,0.23)',
                zIndex: 9998,
                opacity: '0',
                transition: 'opacity 0.3s ease'
            });

            this.panel = this.createRootElement();

            if (this.theme) {
                this.panel.dataset.theme = this.theme;
            }
            Object.assign(this.panel.style, {
                zIndex: 9999,
                boxSizing: 'border-box',
                transition: type === 'drawer'
                    ? 'right 0.3s ease'
                    : 'transform 0.3s ease, opacity 0.3s ease'
            });

            if (type === 'dialog') {
                Object.assign(this.panel.style, {
                    position: 'fixed',
                    borderRadius: '20px',
                    maxWidth: `${this.maxWidth}px`,
                    width: '100%',
                    boxShadow: '0px 0px 70px 0px rgba(0, 0, 0, 0.25)',
                    top: '50%',
                    left: '50%',
                    transform: 'translate(-50%, -50%) scale(0.9)',
                    opacity: '0',
                    maxHeight: '90vh',
                    display: 'flex',
                    flexDirection: 'column'
                });
            } else if (type === 'drawer') {
                Object.assign(this.panel.style, {
                    position: 'fixed',
                    top: 0,
                    right: `${-this.maxWidth}px`,
                    maxWidth: `${this.maxWidth}px`,
                    width: '100%',
                    height: '100%',
                    boxShadow: '0px 0px 70px 0px #00000040',
                    display: 'flex',
                    flexDirection: 'column'
                });
            }

            this.panel.innerHTML = this.createWrapper(content, true);

            document.body.appendChild(this.overlay);
            document.body.appendChild(this.panel);
            document.body.classList.add('wa-embeded-widget-opened');

            this.addFrameResizer();

            this.overlay.addEventListener('click', () => this.close(type));
            this.panel.querySelector('.wa-embeded-widget-iframe-close').addEventListener('click', () => this.close(type));

            // Анимация появления
            requestAnimationFrame(() => {
                this.overlay.style.opacity = '1';
                if (type === 'dialog') {
                    this.panel.style.transform = 'translate(-50%, -50%) scale(1)';
                    this.panel.style.opacity = '1';
                } else if (type === 'drawer') {
                    this.panel.style.right = '0';
                }
            });
        }

        show ({ type = 'dialog', content = '' }) {
            if (!this.overlay) {
                this.createPanel({ type, content });
                
                if (!this.escHandlerInited) {
                    this.escHandlerInited = true;
                    document.addEventListener('keydown', (e) => {
                        if (e.code === 'Escape') {
                            this.close(type);
                        }
                    });
                }
            }
        }

        close (type) {
            if (this.overlay && this.panel) {
                this.overlay.style.opacity = '0';
                if (type === 'dialog') {
                    this.panel.style.transform = 'translate(-50%, -50%) scale(0.9)';
                    this.panel.style.opacity = '0';
                } else if (type === 'drawer') {
                    this.panel.style.right = `${-this.maxWidth}px`;
                }

                setTimeout(() => {
                    this.overlay.remove();
                    this.panel.remove();
                    this.overlay = null;
                    this.panel = null;
                    document.body.classList.remove('wa-embeded-widget-opened');

                    this.updateStorage({
                        [this.iframeUrl]: Date.now()
                    });
                }, 300);
            }
        }

        createCircle () {
            const circle = document.createElement("div");
            circle.className = 'wa-embeded-widget-fab';
            if (this.displayFabText) {
                circle.classList.add('wa-embeded-widget-fab--text');
                circle.innerText = this.displayFabText;
            }
            if (this.availableIcons.includes(this.displayFabIcon)) {
                circle.classList.add('wa-embeded-widget-fab--icon');
                circle.dataset.icon = this.displayFabIcon;
            }
            if (/^#([0-9A-Fa-f]{3}){1,2}([0-9A-Fa-f]{2})?$/.test(this.displayFabColor)) {
                circle.style.backgroundColor = this.displayFabColor;
            }
            circle.addEventListener('click', () => {
                this.showContainer();
            });
            document.body.appendChild(circle);
        }
    }

    window.createCRMWidget = (config) => {
       new CRMFormEmbedded(config); 
    }

})();
