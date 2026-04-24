(function () {
    function initLightGallery() {
        if (!window.lightGallery) {
            return;
        }

        document.querySelectorAll('[data-homlity-gallery="light"]').forEach(function (node) {
            if (node.dataset.galleryReady === '1') {
                return;
            }

            node.dataset.galleryReady = '1';
            window.lightGallery(node, {
                download: false,
                licenseKey: '0000-0000-000-0000',
                mobileSettings: {
                    controls: true,
                    showCloseIcon: true,
                },
                selector: '.property-gallery__item--light',
                speed: 400,
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

        initLightGallery();
    });
}());
