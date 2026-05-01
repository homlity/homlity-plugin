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
            <?php if (\Codwelt\WordPress\VisualInmueble\Configuracion::checkConfiguracion("filtros", 'sliderDetalleInmueble') == 1) { ?>
                <ul id="visualinmo-inmueble-slider" style="width: 100%;" class="pre-slider">
                    <?php foreach ($slider["fotos"] as $foto): ?>
                        <li data-thumb="<?php echo $foto->url(); ?>" data-src="<?php echo $foto->url(); ?>">
                            <img loading="lazy" src="<?php echo $foto->url(); ?>" class="itemslider"
                                alt="<?php echo $nombre; ?>" height="40vh" width="100%" />
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php } else { ?>
                <div class="owl-carousel owl-theme">
                    <?php foreach ($slider["fotos"] as $foto): ?>
                        <div class="item" data-thumb="<?php echo $foto->url(); ?>" data-src="<?php echo $foto->url(); ?>"
                            style="width:100%;">
                            <img src="<?php echo $foto->url(); ?>" style="height:55vh;object-fit:cover" />
                        </div>
                    <?php endforeach; ?>
                </div>
                <script>
                    jQuery(function ($) {
                        $(document).ready(function () {
                            $('.owl-carousel').owlCarousel({
                                center: true,
                                items: 2,
                                loop: true,
                                margin: 5,
                                responsive: {
                                    600: {
                                        items: 2
                                    }
                                }
                            });
                            $(".owl-carousel").lightGallery({
                                selector: '.owl-carousel .item'
                            });
                        });
                    });
                </script>
            <?php } ?>
        </div>
        <?php if ($conFotos360) { ?>
            <div class="tab-pane fade" id="fotos360" role="tabpanel" aria-labelledby="fotos360-tab">
                <visualinmueble-slider360></visualinmueble-slider360>
                <script type="text/javascript">
                    window.VISUALINMUEBLE_FOTOS = <?php echo json_encode($slider["s360"], JSON_PRETTY_PRINT); ?>;
                    window.VISUALINMUEBLE_SITE_URL = "<?php echo \get_site_url(); ?>";
                </script>
            </div>
        <?php } ?>
        <?php if ($conVideo):
            $url = $slider["video"]->url();
            ?>
            <div class="tab-pane fade <?php echo ($conVideo && $firstVideo) ? 'show active' : '' ?>" id="video"
                role="tabpanel" aria-labelledby="video-tab">
                <iframe src="<?php echo $slider['video']->url(); ?>" title="<?php echo $nombre; ?>"
                    style="width: 100%; height: 70vh;" frameborder="0"
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen
                    id="homlity_slider_video"></iframe>
            </div>
        <?php endif; ?>
        <?php if ($conVideo360) { ?>
            <div class="tab-pane fade" id="video360" role="tabpanel" aria-labelledby="video360-tab">
                <iframe src="<?php echo $slider["video360"]->url(); ?>" title="<?php echo $nombre; ?>" frameborder="0"
                    style="width: 100%; height: 50vh;"
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
            </div>
        <?php } ?>
    </div>
</div>