<article itemprop="itemListElement" itemscope
    itemtype="<?php echo homlity_get_sheme_type_name_from_property($inmueble->tipoInmueble()->codigo()); ?>"
    class="card card-space" style="width: 100%; position: relative;">
    <?php if (isset($cont)) {
        ?>
        <meta itemprop="position" content="<?php echo $cont; ?>" />
        <?php
    }
    ?>

    <meta itemprop="availability" content="https://schema.org/InStock" />
    <meta itemprop="url" content="<?php echo visualinmu_route_detalleInmueble($inmueble->slug()); ?>" />


    <div itemprop="aggregateRating" itemtype="https://schema.org/AggregateRating" itemscope>
        <meta itemprop="reviewCount" content="4" />
        <meta itemprop="ratingValue" content="4" />
    </div>
    <div itemprop="review" itemtype="https://schema.org/Review" itemscope>
        <?php if (method_exists($inmueble, "asesor") && $inmueble->asesor()) { ?>
            <div itemprop="author" itemtype="https://schema.org/Person" itemscope>
                <meta itemprop="name" content="<?php echo $inmueble->asesor()->nombre() ?>" />
            </div>
        <?php } ?>
        <div itemprop="reviewRating" itemtype="https://schema.org/Rating" itemscope>
            <meta itemprop="ratingValue" content="4" />
            <meta itemprop="bestRating" content="5" />
        </div>
    </div>
    <a target="_blank"
        onclick="gtag('event', 'property_open', { 'origin': 'card', 'label': 'property_open', 'value': '<?php echo $inmueble->nombre(); ?>' });"
       title="<?php echo $inmueble->nombre(); ?>"
        href="<?php echo visualinmu_route_detalleInmueble($inmueble->slug()); ?>" class="inmueblelink">

        <div class="position-relative">
            <!-- Imagen real -->
            <img src="<?php echo $inmueble->fotoPortada(); ?>" alt="<?php echo $inmueble->nombre(); ?>"
                title="<?php echo $inmueble->nombre(); ?>" class="card-img-top" itemprop="image"
                style="height: 35vh; object-fit: cover; width: 100%; background-color: #f0f0f0;"
                onerror="this.onerror=null; this.src='data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';">

            <!-- Badges -->
            <?php foreach ($inmueble->tags() as $tag): ?>
                <?php if (in_array($tag->key(), ["VISUALINMU_DESTACADO", "DESTACADO"])): ?>
                    <span class="badge rounded-pill bg-primary position-absolute top-0 end-0 m-2">Propiedad destacada</span>
                <?php elseif ($tag->key() === "VISUALINMU_DELUJO"): ?>
                    <span class="badge rounded-pill bg-warning position-absolute top-0 end-0 m-2">Propiedad de lujo</span>
                <?php elseif ($tag->key() === "VISUALINMU_VERIFICADO"): ?>
                    <span class="badge rounded-pill bg-success position-absolute top-0 end-0 m-2" data-bs-toggle="tooltip"
                        data-bs-placement="top" title="Este inmueble y sus propietarios fueron previamente verificados.">
                        <i class="icon-homlity icon-uniE9AF"></i> Propiedad verificada
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </a>

    <div class="card-body">
        <div class="card-inmueble-container">
            <div class="card-inmueble-caracteristicas">
                <?php visualinmu_load_template("inmuebles/componentes/card/features.php", get_defined_vars()); ?>
            </div>
            <div class="card-inmueble-contacto">
                <?php if (method_exists($inmueble, "asesor") && $inmueble->asesor()): ?>
                    <div class="row justify-content-center text-center">
                        <div class="col-md-12 card-btn">
                            <?php visualinmu_load_template("inmuebles/componentes/card/advisor.php", get_defined_vars()); ?>
                        </div>

                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</article>