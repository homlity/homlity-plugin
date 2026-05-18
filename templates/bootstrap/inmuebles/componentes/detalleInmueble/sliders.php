<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php

$conVideo = !empty($slider["video"]);
$conVideo360 = !empty($slider["video360"]);
$conFotos360 = !empty($slider["s360"]) && count($slider["s360"]) > 0;
$firstVideo = visualinmu_configuracion_checkConfiguracion("filtros", "defaultShowFirstTabPropertyDetail", 'video') === 'video';

?>
<div class="col-md-12">
    <nav>
        <ul class="nav nav-tabs tabs-media" id="tabCabeceraInmueble" role="tablist">
            <?php
            if ($firstVideo) {
                visualinmu_load_template("inmuebles/componentes/detalleInmueble/tabs/button-tab-video.php", get_defined_vars());
                visualinmu_load_template("inmuebles/componentes/detalleInmueble/tabs/button-tab-fotos.php", get_defined_vars());
            } else {
                visualinmu_load_template("inmuebles/componentes/detalleInmueble/tabs/button-tab-fotos.php", get_defined_vars());
                visualinmu_load_template("inmuebles/componentes/detalleInmueble/tabs/button-tab-video.php", get_defined_vars());
            } ?>
            <?php if ($conFotos360) { ?>
                <li class="nav-item" role="presentation">
                <button onclick="gtag('event', 'wp_property_tab_photo360', {
 });" class="nav-link" id="fotos-360-tab" data-bs-toggle="tab" data-bs-target="#fotos360" type="button" role="tab"
                    aria-controls="fotos360" aria-selected="false">
                    <i class="icon-homlity icon-uniE9A2"></i> Fotos 360
                </button>
                </li>
            <?php } ?>
            <?php if ($conVideo360) { ?>
                 <li class="nav-item" role="presentation">
                <button onclick="gtag('event', 'wp_property_tab_r360', {

// Este valor puede ser un número
 });" class="nav-link" id="video360-tab" data-bs-toggle="tab" data-bs-target="#video360" type="button" role="tab"
                    aria-controls="video360" aria-selected="false">
                    <i class="icon-homlity icon-uniE932"></i> Recorrido 360
                </button>
                </li>
            <?php } ?>
        </ul>
    </nav>
    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade  <?php echo ($conVideo && $firstVideo) ? '' : 'show active' ?>" id="fotos"
            role="tabpanel" aria-labelledby="fotos-tab">
            <?php if (\Codwelt\WordPress\homlity-plugin\Configuracion::checkConfiguracion("filtros", 'sliderDetalleInmueble') == 1) { ?>
                <ul id="visualinmo-inmueble-slider" style="width: 100%;" class="pre-slider">
                    <?php foreach ($slider["fotos"] as $foto): ?>
                        <li data-thumb="<?php echo esc_url( $foto->url() ); ?>" data-src="<?php echo esc_url( $foto->url() ); ?>">
                            <img loading="lazy" src="<?php echo esc_url( $foto->url() ); ?>" class="itemslider"
                                alt="<?php echo esc_attr( $nombre ); ?>" height="40vh" width="100%" />
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php } else { ?>
                <div class="swiper homlity-property-photos-swiper">
                    <div class="swiper-wrapper">
                    <?php foreach ($slider["fotos"] as $foto): ?>
                        <div class="swiper-slide" data-thumb="<?php echo esc_url( $foto->url() ); ?>" data-src="<?php echo esc_url( $foto->url() ); ?>">
                            <img src="<?php echo esc_url( $foto->url() ); ?>" style="height:55vh;object-fit:cover;width:100%;" />
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
                <script>
                    (function () {
                        function initHomlityLegacyPhotosSwiper() {
                            var container = document.querySelector('.homlity-property-photos-swiper');
                            if (!container || typeof window.Swiper !== 'function' || container.dataset.swiperReady === '1') {
                                return;
                            }
                            container.dataset.swiperReady = '1';
                            new window.Swiper(container, {
                                slidesPerView: 1,
                                spaceBetween: 8,
                                loop: true,
                                pagination: {
                                    el: container.querySelector('.swiper-pagination'),
                                    clickable: true
                                },
                                navigation: {
                                    nextEl: container.querySelector('.swiper-button-next'),
                                    prevEl: container.querySelector('.swiper-button-prev')
                                }
                            });
                        }
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', initHomlityLegacyPhotosSwiper);
                        } else {
                            initHomlityLegacyPhotosSwiper();
                        }
                    })();
                </script>
            <?php } ?>
        </div>
        <?php if ($conFotos360) { ?>
            <div class="tab-pane fade" id="fotos360" role="tabpanel" aria-labelledby="fotos360-tab">
                <homlity-plugin-slider360></homlity-plugin-slider360>
                <script type="text/javascript">
                    window.homlity-plugin_FOTOS = <?php echo json_encode($slider["s360"], JSON_PRETTY_PRINT); ?>;
                    window.homlity-plugin_SITE_URL = "<?php echo esc_url( \get_site_url() ); ?>";
                </script>
            </div>
        <?php } ?>
        <?php if ($conVideo):
            $url = $slider["video"]->url();
            ?>
            <div class="tab-pane fade <?php echo ($conVideo && $firstVideo) ? 'show active' : '' ?>" id="video"
                role="tabpanel" aria-labelledby="video-tab">
                <iframe src="<?php echo esc_url( $slider['video']->url() ); ?>" title="<?php echo esc_attr( $nombre ); ?>"
                    style="width: 100%; height: 70vh;" frameborder="0"
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen
                    id="homlity_slider_video"></iframe>
            </div>
        <?php endif; ?>
        <?php if ($conVideo360) { ?>
            <div class="tab-pane fade" id="video360" role="tabpanel" aria-labelledby="video360-tab">
                <iframe src="<?php echo esc_url( $slider["video360"]->url() ); ?>" title="<?php echo esc_attr( $nombre ); ?>" frameborder="0"
                    style="width: 100%; height: 50vh;"
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
            </div>
        <?php } ?>
    </div>
</div>
