/**
 * Webasyst Plugin for preview images
 * @author GolubevMark
 * @version 0.0.1
 * */

var waGallery = ( function($) {

    waGallery = function($links, options) {
        var that = this;

        if (!options) {
            options = {};
        }

        // SETTINGS
        that.settings = $.extend({
            animate_time: 1,
            close_time: 150,
            zoom: 0,
            width: 600,
            isFrame: false,
            forceFullPreview: false
        }, options);

        // DOM
        that.$links = $links;
        that.$body = that.settings.isFrame ? $("iframe").contents().find("body") : $("body");
        that.$top_body = $("body");

        // VARS
        that.storage = {
            animated: "is-animated",
            shown: "is-shown",
            invisible: "is-invisible",
            active: "is-active",
            body_active_class: "wa-gallery-is-shown"
        };

        // DYNAMIC VARS
        that.links = [];
        that.active_link_index = false;
        that.slider = {};
        that.$close = false;
        that.is_zoom = false;
        that.ctrl_pressed = false;

        // INIT
        that.initGallery();
    };

    waGallery.prototype.initGallery = function() {
        var that = this;

        if (that.$links.length) {
            // If images exist
            that.bindEvents();
        }
    };

    waGallery.prototype.bindEvents = function() {
        var that = this;

        that.$links.each( function(index) {
            var $link = $(this);
            $link.off();
            $link.on("click", function() {
                that.showPreview( index );
                return false;
            });

            if (!that.settings.isFrame) {
                $link.on("mouseleave", function() {
                    var is_rendered = that.links[index].is_active;
                    if (!is_rendered) {
                        that.unsetActive();
                    }
                    return false;
                });
            }

            // Generate Links Array
            that.links.push({
                // Static
                $link: $link,
                full_image_src: $link.attr("href"),
                //preview_image_src: $link.find("img").attr("src"),
                preview_image_src: $link.data("preview") || $link.find("img").attr("src"),
                linkArea: {
                    top: false,
                    left: false,
                    width: false,
                    height: false
                },
                // Dynamic
                $preview: false,
                is_active: false,
                timer: 0
            });
        });

        $(window).on("wa_dispatched", function() {
            that.destroy();
        });

        window.document.addEventListener("wa-gallery-unload", function(e) {
            that.destroy();
        }, false);
    };

    waGallery.prototype.showPreview = function( index ) {
        var that = this,
            link = that.links[index],
            $preview = $('<div class="wa-gallery-preview"></div>'),
            previewItems = that.getPreviewItems(index),
            invisible_class = that.storage.invisible,
            zoom = that.settings['zoom'],
            width = that.settings['width'];

        if (that.is_opening) return;
        that.is_opening = true;

        // set Area
        var $link = link.$link;
        link.linkArea = {
            top: that.settings.isFrame ? 0 : $link.offset().top,
            left: that.settings.isFrame ? 0 : $link.offset().left,
            width: that.settings.isFrame ? "100%" : $link.outerWidth(),
            height: that.settings.isFrame ? "100%" : $link.outerHeight()
        };

        that.setActive(index);

        // Set preview size
        $preview
            .css({
                width: link.linkArea.width,
                height: link.linkArea.height,
                top: link.linkArea.top,
                left: link.linkArea.left,
                position: "fixed"
            })
            .addClass(invisible_class)
            .html(previewItems);

        // Render Preview
        that.$body.append($preview);

        // Save data
        link.$preview = $preview;
        link.is_active = true;

        var previewArea = {
            width: link.linkArea.width,
            height: link.linkArea.height,
            top: link.linkArea.top,
            left: link.linkArea.left
        };

        if (zoom) {
            previewArea = that.offsetCorrection({
                width: parseInt(link.linkArea.width * zoom),
                height: parseInt(link.linkArea.height * zoom),
                top: link.linkArea.top - parseInt(link.linkArea.height * (zoom - 1) / 2),
                left: link.linkArea.left - parseInt(link.linkArea.width * (zoom - 1) / 2)
            });
        }
        if (width && !that.settings.isFrame) {
            previewArea = that.offsetCorrection({
                width: width,
                height: width,
                top: link.linkArea.top - parseInt( (width - link.linkArea.width)/2 ),
                left: link.linkArea.left - parseInt( (width - link.linkArea.width)/2 )
            });
        }

        // Animation + new bind events
        $preview
            .removeClass(invisible_class)
            .css(previewArea)
            .on("click", function() {
                that.showFullImage( index );
                return false;
            });

        if (that.settings.isFrame) {
            that.renderControls( index, true );
        } else {
            $preview.on("mouseleave", function() {
                clearTimeout( link.timer );

                link.timer = setTimeout( function() {
                    that.hidePreview( link );
                }, that.settings['close_time']);
            }).on("mouseenter", function() {
                clearTimeout( link.timer );
            });
        }

        if (that.settings.forceFullPreview) {
            setTimeout(() => that.showFullImage( index ), 100);
        }
        setTimeout(() => {
            that.is_opening = false;
        }, 700);
    };

    waGallery.prototype.hidePreview = function( link ) {
        var that = this,
            $preview = link.$preview,
            linkArea = link.linkArea,
            time = that.settings['animate_time'];

        if (that.is_opening) return;

        var is_animated_hide = true;
        if ( is_animated_hide && !time ) {
            time = 333;
        }

        if ($preview.length) {
            $preview
                .css({
                    width: linkArea.width,
                    height: linkArea.height,
                    top: linkArea.top,
                    left: linkArea.left,
                    zIndex: "auto"
                })
                .addClass(that.storage.invisible);

            // Set data
            link.$preview = false;
            link.is_active = false;

            // Render
            setTimeout( function() {
                // Remove DOM
                $preview.remove();
            }, time);
        }

    };

    waGallery.prototype.showFullImage = function( index ) {
        var that = this,
            link = that.links[index],
            $preview = link.$preview,
            body_active_class = that.storage.body_active_class,
            animate_class = that.storage.animated,
            is_shown_class = that.storage.shown;

        if (!$preview.length) {
            return false;
        }

        $preview.addClass(animate_class);

        // BODY
        that.$top_body.addClass(body_active_class);
        if (that.settings.isFrame) {
            that.$body.remove($preview);
            that.$top_body.append($preview);
        }

        // bind Off events
        $preview
            .off("mouseleave")
            .off("mousemove")
            .off("click")
            .addClass(is_shown_class)
            .css({
                top: 0 + $(window)['scrollTop'](),
                left: 0,
                width: "100%",
                height: "100%"
            });

        that.renderControls( index, false );

        that.addImage();
    };

    waGallery.prototype.unsetActive = function() {
        var that = this,
            index = that.active_link_index;

        if (index || index === 0) {
            var active_link = that.links[index],
                $preview = active_link['$preview'],
                is_preview_render = ($preview.length);

            if (is_preview_render) {
                that.hidePreview(active_link);
            }

            clearTimeout(active_link['timer']);
        }
    };

    waGallery.prototype.setActive = function( index ) {
        var that = this;

        that.unsetActive();

        that.active_link_index = index;
    };

    waGallery.prototype.getPreviewItems = function( index ) {
        var that = this,
            html = "";

        $.each(that.links, function(i, link) {
            var $link = link.$link,
                image_src = link.preview_image_src,
                active_class = (i == index) ? that.storage['active'] : "";

            html += '<div class="wa-gallery-item ' + active_class + '" data-href="' + $link.attr("href") + '" style="background-image: url(' + image_src + ')"><img src="' + image_src + '" alt ="" /></div>';
        });

        return html;
    };

    waGallery.prototype.renderControls = function( index, is_in_frame ) {
        var that = this,
            link = that.links[index],
            $preview = link.$preview,
            show_slider = !is_in_frame && that.links.length > 1,
            body_active_class = that.storage.body_active_class;

        if (!$preview.length) {
            return false;
        }

        $preview.find(".wa-gallery-controls").remove();

        // Render close button
        var $topControlWrapper = $('<div class="wa-gallery-controls top"></div>'),
            $bottomControlWrapper = $('<div class="wa-gallery-controls bottom"></div>'),
            $close = $('<a href="javascript:void(0);" class="wa-gallery-close largest custom-pr-16"><i class="fas fa-times"></i></a>'),
            $zoomIn = $('<a href="javascript:void(0);" class="wa-gallery-zoom-in wa-gallery-arrow largest"><i class="fas fa-search-plus"></i></a>'),
            $zoomOut = $('<a href="javascript:void(0);" class="wa-gallery-zoom-out wa-gallery-arrow largest"><i class="fas fa-search-minus"></i></a>'),
            $download = $('<a href="javascript:void(0);" class="wa-gallery-download largest"><i class="fas fa-file-download"></i></a>'),
            $rightArrow = $('<a href="javascript:void(0);" class="wa-gallery-arrow right largest custom-pr-16"><i class="fas fa-chevron-right"></i></a>'),
            $leftArrow = $('<a href="javascript:void(0);" class="wa-gallery-arrow left largest"><i class="fas fa-chevron-left"></i></a>');

        $topControlWrapper.append($zoomIn);
        $topControlWrapper.append($zoomOut);
        $topControlWrapper.append($download);
        $topControlWrapper.append($close);
        $preview.append($topControlWrapper);

        if ( show_slider ) {
            $bottomControlWrapper.append($leftArrow);
            $bottomControlWrapper.append($rightArrow);
            $preview.append($bottomControlWrapper);
        }

        // INIT
        that.initSlider(index);
        that.initZoom();

        // BIND EVENTS
        $close.on("click", function() {
            that.$body.removeClass(body_active_class);
            that.hidePreview( link );
            return false;
        });

        $download.on("click", function() {
            var href = that.slider.$activeSlide.data("href");
            if (href) {
                location.href = href;
                return false;
            }
        });

        if (show_slider) {
            $leftArrow.on("click", function() {
                that.changeSlide( false );
                return false;
            });
            $rightArrow.on("click", function() {
                that.changeSlide( true );
                return false;
            });
        }

        var initKeyBinds = function(event) {
            var is_escape = (event.keyCode == 27),
                is_left = (event.keyCode == 37),
                is_right = (event.keyCode == 39);

            if (is_escape) {
                $close.trigger("click");
                that.$body.off("keyup", initKeyBinds);
            }

            if (show_slider) {
                if (is_left) {
                    $leftArrow.trigger("click");
                }
                if (is_right) {
                    $rightArrow.trigger("click");
                }
            }
        };

        var closeFullPreview = function() {
            if (that.ctrl_pressed) return;
            $close.trigger("click");
            that.$body.off("mousewheel DOMMouseScroll", closeFullPreview);
        };

        var onDrop = function(event) {
            var files = event.originalEvent.dataTransfer.files;
            if (files.length) {
                $close.trigger("click");
                that.$body.off("drop", onDrop);
            }
        };

        that.$body
            .on("keyup", initKeyBinds)
            .on("drop", onDrop)
            .on("mousewheel DOMMouseScroll", closeFullPreview);

        // Save data
        that.$close = $close;
    };

    waGallery.prototype.initSlider = function( index ) {
        var that = this,
            link = that.links[index],
            $slides = link.$preview.find(".wa-gallery-item");

        that.slider = {
            is_lock: false,
            current_slide: index,
            slide_count: that.links.length - 1,
            $slides: $slides,
            $activeSlide: $slides.eq(index)
        }
    };

    waGallery.prototype.initZoom = function() {
        const that = this;
        const $image = that.slider.$activeSlide;
        const $preview = $image.closest('.wa-gallery-preview');
        const $zoomIn = $preview.find('.wa-gallery-zoom-in');
        const $zoomOut = $preview.find('.wa-gallery-zoom-out');

        const MIN_SCALE = 1;
        const MAX_SCALE = 6;
        let transformation = {
            originX: 0,
            originY: 0,
            translateX: 0,
            translateY: 0,
            scale: 1
        };
        let previousPosition = {
            x: null,
            y: null
        };

        that.is_zoom = false;
        that.ctrl_pressed = false
        updateTransform();

        // Events
        const onZoomIn = () => {
            const previousScale = transformation.scale;
            transformation.scale++;
            transformation.scale = Math.min(transformation.scale, MAX_SCALE);
            // находим центральную точку изображения
            if (previousPosition.x === null) {
                const rect = $image[0].getBoundingClientRect();
                previousPosition.x = (rect.right + rect.left)/transformation.scale;
                previousPosition.y = (rect.bottom + rect.top)/transformation.scale;
            }
            updateTransformWithOrigin({ ...previousPosition,  previousScale });
        };

        const onZoomOut = () => {
            transformation.scale--;
            transformation.scale = Math.max(transformation.scale, MIN_SCALE);
            updateTransform();
        };

        const onWheel = (e) => {
            that.ctrl_pressed = e.ctrlKey;
            if (!that.ctrl_pressed) return;
            e.preventDefault();

            const previousScale = transformation.scale;
            const delta = e.originalEvent.deltaY;
            if (delta < 0) {
                transformation.scale += 0.1;
                transformation.scale = Math.min(transformation.scale, MAX_SCALE);
            } else {
                transformation.scale -= 0.1
                transformation.scale = Math.max(transformation.scale, MIN_SCALE);
            }

            updateTransformWithOrigin({ ...getCoords(e), previousScale });
        };

        const onMove = (e) => {
            if (transformation.scale === MIN_SCALE) return;
            e.preventDefault();

            previousPosition = { x: null, y: null };
            const previousPos = getCoords(e);

            $image.on('mousemove touchmove', (e) => {
                const { x, y } = getCoords(e);
                const originX = previousPosition.x === null ? previousPos.x - x : x - previousPosition.x; // originX: e.originalEvent.movementX,
                const originY = previousPosition.y === null ? previousPos.y - y : y - previousPosition.y; // originY: e.originalEvent.movementY,

                transformation.translateX += originX;
                transformation.translateY += originY;
                updateTransform();

                previousPosition.x = x;
                previousPosition.y = y;
            });

            $image.one('mouseup touchend', (e) => {
                e.preventDefault();
                $image.off('mousemove touchmove');
            });
        };

        $zoomIn.off('click').on('click', onZoomIn);
        $zoomOut.off('click').on('click', onZoomOut);
        $image.off('wheel').on('wheel', onWheel);
        $image.off('mousedown touchstart').on('mousedown touchstart', onMove);

        // Methods
        function updateTransform () {
            that.is_zoom = transformation.scale !== MIN_SCALE;
            if (!that.is_zoom) {
                clearPosition();
            }
            setTransform();
        };

        function setTransform () {
            const { scale, translateX, translateY } = transformation;
            $image[0].style.transform = `matrix(${scale}, 0, 0, ${scale}, ${translateX}, ${translateY})`;
        };

        function getTranslate (scale) {
            const valueInRange = (scale) => scale <= MAX_SCALE && scale >= MIN_SCALE;

            return ({ pos, prevPos, translate }) => {
                return valueInRange(scale) && pos !== prevPos
                    ? translate + (pos - prevPos * scale) * (1 - 1 / scale)
                    : translate;
            }
        }

        function updateTransformWithOrigin ({ x, y, previousScale }) {
            const image = $image[0];
            const rect = image.getBoundingClientRect();

            const originX = x - rect.left;
            const originY = y - rect.top;

            // Корректируем позицию, чтобы центрировать масштабирование
            const newOriginX = originX / previousScale;
            const newOriginY = originY / previousScale;
            // Перемещаем размер изображения так, чтобы курсор оставался на месте
            image.style.transformOrigin = `${newOriginX}px ${newOriginY}px`;

            const translate = getTranslate(previousScale);
            transformation.translateX = translate({ pos: originX, prevPos: transformation.originX, translate: transformation.translateX });
            transformation.translateY = translate({ pos: originY, prevPos: transformation.originY, translate: transformation.translateY });

            transformation.originX = newOriginX;
            transformation.originY = newOriginY;
            updateTransform();
        }

        function getCoords(e) {
            e = e.touches ? e.touches[0] : e;
            return { x: e.clientX, y: e.clientY };
        }

        function clearPosition () {
            transformation = {
                originX: 0,
                originY: 0,
                translateX: 0,
                translateY: 0,
                scale: 1
            };
            previousPosition = { x: null, y: null };
            $image[0].style.transformOrigin = '50% 50%';
        }
    };

    waGallery.prototype.changeSlide = function( is_next ) {
        var that = this,
            slider = that.slider,
            $current_slide = slider.$activeSlide,
            active_class = that.storage.active,
            animate_time = 333,
            $new_slide;

        if (slider.is_lock) {
            return false;
        }

        slider.is_lock = true;

        if (is_next) {

            if (slider.current_slide < slider.slide_count ) {
                $new_slide = slider.$slides.eq(slider.current_slide + 1);
                slider.current_slide++;
            } else {
                $new_slide = slider.$slides.first();
                slider.current_slide = 0;
            }

        } else {

            if (slider.current_slide >= 1 ) {
                $new_slide = slider.$slides.eq(slider.current_slide - 1);
                slider.current_slide--;
            } else {
                $new_slide = slider.$slides.last();
                slider.current_slide = slider.slide_count;
            }

        }

        // Render
        $current_slide.css("opacity", 0);
        $new_slide.css("opacity", 1);

        slider.timer = setTimeout( function() {
            $current_slide.removeClass(active_class);
            $new_slide.addClass(active_class);

            slider.is_lock = false;
        }, animate_time);

        // Save data
        slider.$activeSlide = $new_slide;

        that.addImage();
        that.initZoom();
    };

    waGallery.prototype.offsetCorrection = function(previewArea) {
        var that = this,
            $document = $(document),
            displayArea = {
                width: $document.width(),
                height: $document.height()
            },
            padding = 10;

        var is_top_problem = ( previewArea.top - padding < 0 ),
            is_bottom_problem = ( previewArea.top + previewArea.height + padding > displayArea.height ),
            is_left_problem = ( previewArea.left - padding < 0 ),
            is_right_problem = ( previewArea.left + previewArea.width + padding > displayArea.width );

        if (is_top_problem) {
            previewArea.top = padding;
        }

        if (is_left_problem) {
            previewArea.left = padding;
        }

        if (is_bottom_problem) {
            previewArea.top -= Math.abs( previewArea.top + previewArea.height - displayArea.height ) + padding;
        }

        if (is_right_problem) {
            previewArea.left -= Math.abs( previewArea.left + previewArea.width - displayArea.width ) + padding;
        }

        return previewArea;
    };

    waGallery.prototype.addImage = function() {
        var that = this,
            $fullImage = $("<img class=\"full-image\" />"),
            $activeSlide = that.slider.$activeSlide,
            full_image_src = $activeSlide.data("href");

        if ($activeSlide.find("img.full-image").length) {
            return false;
        }

        $activeSlide.html("").append($fullImage);

        $fullImage
            .one("load", function() {
                if ($(document).find($activeSlide).length) {
                    $activeSlide
                        .css("background-image", "url(" + full_image_src + ")");
                }
            })
            .on("click", function() {
                if (that.$close && !that.is_zoom) {
                    that.$close.trigger("click");
                }
            })
            .attr("src", full_image_src);
    };

    waGallery.prototype.destroy = function() {
        console.log('destroy wa-gallery');
        var that = this;

        $.each(that.links, function(i, link) {
            if (link.is_active) {
                that.hidePreview( link );
            }
        });

        that.$links.each(() => $(this).off());

        that.showPreview = function() {

        };

        $(".wa-gallery-preview").remove();
    };

    return waGallery;

})(jQuery);
