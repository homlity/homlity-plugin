<?php
$checkLink = !(isset($attrs) && isset($attrs['withLinktoDetail']) && $attrs['withLinktoDetail'] == false);
?>
<div class="row">
    <!-- -->
    <div class="col-md-12">
        <a title="Valor propiedad"+
            href="<?php echo $checkLink ? visualinmu_route_detalleInmueble($inmueble->slug()) : ''; ?>" target="_blank">
            <span class="p-0 m-0 h4" itemprop="price" content="<?php echo $inmueble->valor() ?>">
                <?php if ($inmueble->gestion()->esArriendoVenta()):
                    ?>
                    $<?php echo $inmueble->valorCanon(true); ?>
                <?php elseif ($inmueble->gestion()->esAriendo()): ?>
                    $<?php echo $inmueble->valorCanon(true); ?>

                <?php else: ?>
                    $<?php echo $inmueble->valorVenta(true); ?>
                <?php endif; ?>
            </span>
            <?php if (($inmueble->gestion()->esArriendoVenta() || $inmueble->gestion()->esAriendo()) && !$inmueble->precioConAdministracion() && $inmueble->valorAdmin() > 0) { ?>
                <small> + $<?php echo $inmueble->valorAdmin(true); ?> Administración</small>
            <?php } ?>


        </a>
    </div>
    <div class="col-sm-12">
        <?php
        $nameFull = $inmueble->tipoInmueble()->nombreSingular() . " " . __('en', 'visualinmueble') . " " . $inmueble->barrio()->nombre() . " " . __('en', 'visualinmueble');

        $nameFull .= $inmueble->gestion()->nombre();
        ?>
        <a title="<?php echo $nameFull; ?>"
            href="<?php echo $checkLink ? visualinmu_route_detalleInmueble($inmueble->slug()) : ''; ?>" target="_blank"
            class="cb-nombre">
            <h2 class="d-inline-block text-truncate p-0 m-0 h6" style="max-width:100%" itemprop="name">
                <?php echo $nameFull; ?>
            </h2>
        </a>
    </div>
    <div class="col-4 d-inline-block text-truncate">
        <span title="<?php echo $inmueble->codigo(); ?>"><i class="icon-homlity icon-uniE978"></i><?php echo $inmueble->codigo(); ?></span>
    </div>
    <div class="col-8 text-md-end d-inline-block text-truncate" itemprop="address" itemscope
        itemtype="https://schema.org/PostalAddress">
        <i class="icon-homlity icon-uniE91C"></i>
        <meta itemprop="addressLocality" content="<?php echo $inmueble->ciudad()->nombre(); ?>" />
        <meta itemprop="addressSubLocality" content="<?php echo $inmueble->barrio()->nombre(); ?>" />
        <meta itemprop="addressCountry" content="CO" />
        <a href="<?php echo visualinmu_route_search(["city" => $inmueble->ciudad()->nombre()]); ?>"
            class="vi-link-ubicacion"
            target="_blank"
            itemprop="address"
            title="<?php echo $inmueble->ciudad()->nombre(); ?>"><?php echo $inmueble->ciudad()->nombre(); ?></a>,
            <a href="<?php echo visualinmu_route_search(["neighborhood" => $inmueble->barrio()->nombre()]); ?>"
            class="vi-link-ubicacion" 
            target="_blank" 
            itemprop="address"
             title="<?php echo $inmueble->barrio()->nombre(); ?>"><?php echo $inmueble->barrio()->nombre(); ?></a>
    </div>
    <div class="col-12">
        <div class="property-description" itemprop="description">
            <?php echo wp_kses_post($inmueble->descripcion()); ?>
        </div>
    </div>
    <div class="col-md-12">
        <hr>
        <div class="row icons-inmueble">
            <?php if ($inmueble->areaConstruida() > 0) { ?>
                <div class="col">
                    <span data-toggle="tooltip" data-placement="top" title="Área construida" itemprop="floorSize"
                        content="<?php echo $inmueble->areaConstruida(); ?>">
                        <i class="icon-homlity icon-uniE95E"></i><?php echo $inmueble->areaConstruida(); ?> m<sup>2</sup>
                    </span>
                </div>
            <?php }
            if (in_array($inmueble->tipoInmueble()->codigo(), [8, 7, 13, 9, 21]) && $inmueble->areaLote()) { ?>
                <div class="col">
                    <span data-toggle="tooltip" data-placement="top" title="Área lote">
                        <i class="icon-homlity icon-uniE95E"></i><?php echo $inmueble->areaLote(); ?> m<sup>2</sup>
                    </span>
                </div>
            <?php } ?>
            <?php if (
                (($inmueble->tipoInmueble()->codigo() == 1) ||
                    ($inmueble->tipoInmueble()->codigo() == 2) ||
                    ($inmueble->tipoInmueble()->codigo() == 8) ||
                    ($inmueble->tipoInmueble()->codigo() == 10) ||
                    ($inmueble->tipoInmueble()->codigo() == 11) ||
                    ($inmueble->tipoInmueble()->codigo() == 12) ||
                    ($inmueble->tipoInmueble()->codigo() == 14) ||
                    ($inmueble->tipoInmueble()->codigo() == 25))
                && $inmueble->nAlcobas() > 0
            ) { ?>
                <div class="col">

                    <span data-toggle="tooltip" data-placement="top" title="Alcobas" itemprop="floorSize"
                        content="<?php echo $inmueble->nAlcobas(); ?>">
                        <i class="icon-homlity icon-uniE951"></i><?php echo $inmueble->nAlcobas(); ?>
                    </span>

                </div>
            <?php } ?>
            <?php if (
                (($inmueble->tipoInmueble()->codigo() != 7) &&
                    ($inmueble->tipoInmueble()->codigo() != 9))
                && $inmueble->nBaños() > 0
            ) { ?>
                <div class="col">

                    <span data-toggle="tooltip" data-placement="top" title="Baños" itemprop="numberOfBathroomsTotal"
                        content="<?php echo $inmueble->nBaños(); ?>">
                        <i class="icon-homlity icon-uniE93E"></i><?php echo $inmueble->nBaños(); ?>
                    </span>

                </div>
            <?php } ?>
            <?php if (
                ($inmueble->tipoInmueble()->codigo() != 7)
                && $inmueble->nGarajes() > 0
            ) { ?>
                <div class="col">
                    <span data-toggle="tooltip" data-placement="top" title="Garajes">
                        <i class="icon-homlity icon-uniE9CC"></i> <?php echo $inmueble->nGarajes(); ?>
                    </span>
                </div>
            <?php } ?>


        </div>
    </div>
</div>