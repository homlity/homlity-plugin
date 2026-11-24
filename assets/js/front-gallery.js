(function () {
    function initGLightbox() {
        if (!window.GLightbox) {
            return;
        }

        document.querySelectorAll('[data-homlity-gallery="light"]').forEach(function (node) {
            if (node.dataset.galleryReady === '1') {
                return;
            }

            node.dataset.galleryReady = '1';

            var links = node.querySelectorAll('.property-gallery__item--light, .property-gallery__slide-link');
            if (!links.length) {
                return;
            }

            var elements = Array.from(links).map(function (el) {
                return { href: el.getAttribute('href'), type: 'image' };
            });

            var lightbox = window.GLightbox({
                elements: elements,
                touchNavigation: true,
                loop: false,
                closeButton: true,
                closeOnOutsideClick: false,
                openEffect: 'fade',
                closeEffect: 'fade',
            });
            node._homlityLightbox = lightbox;

            links.forEach(function (el, idx) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (node._homlityLightbox && typeof node._homlityLightbox.openAt === 'function') {
                        node._homlityLightbox.openAt(idx);
                    }
                });
            });
        });
    }

    function initOwlGallery() {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.owlCarousel) {
            return;
        }

        window.jQuery('.property-gallery--owl .property-gallery__track').each(function () {
            const $track = window.jQuery(this);
            if ($track.hasClass('owl-loaded')) {
                return;
            }

            $track.owlCarousel({
                items: 1,
                loop: $track.children().length > 1,
                margin: 12,
                nav: false,
                dots: true,
                smartSpeed: 520,
                autoplay: $track.children().length > 1,
                autoplayTimeout: 4200,
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const config = window.homlityPluginFrontGallery || {};

        if (config.mode === 'owl_gallery') {
            initOwlGallery();
            return;
        }

        initGLightbox();
    });
}());
