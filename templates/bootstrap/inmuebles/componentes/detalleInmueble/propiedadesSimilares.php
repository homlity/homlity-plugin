<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="row">
    <div class="col-md-12">
        <h3 class="homlity-real-estate-titulos-propiedades-similares h4">Inmuebles similares</h3>
    </div>
    <?php
    /**
     * @var \Codwelt\homlity-real-estate\Core\Modelos\Inmueble $inmueble
     */
    foreach ($similares as $cont => $inmueble) : ?>
        <div class="col-sm-12 col-md-6">
            <div class="card mb-3">
                <div class="row g-0">
                    <div class="col-md-6 imagenportada"
                         style="background-image: url(<?php echo esc_url( $inmueble->fotoPortada() ); ?>);">
                        <?php if ($inmueble->gestion()->esArriendoVenta()) : ?>
                            <span class="badge rounded-pill bg-primary"><i
                                        class="icon-homlity icon-uniE9C2"></i> VENTA</span>
                            <span class="badge rounded-pill bg-light text-dark"><i
                                        class="icon-homlity icon-uniE9C2"></i> ARRIENDO</span>
                        <?php elseif ($inmueble->gestion()->esAriendo()) : ?>
                            <span class="badge rounded-pill bg-light text-dark"><i
                                        class="icon-homlity icon-uniE9C2"></i> ARRIENDO</span>
                        <?php else : ?>
                            <span class="badge rounded-pill bg-primary"><i
                                        class="icon-homlity icon-uniE9C2"></i> VENTA</span>
                        <?php endif; ?>

                    </div>
                    <div class="col-md-6">
                        <div class="card-body">
                            <a href="<?php echo esc_url( visualinmu_route_detalleInmueble($inmueble->slug()) ); ?>"
                               target="_blank">
                                <h5
                                        class="card-title"><?php echo esc_html( $inmueble->codigo() ); ?><?php echo esc_html( $inmueble->nombre() ); ?></h5>
                            </a>
                            <hr>
                            <p>
                                <a href="<?php echo esc_url( visualinmu_route_detalleInmueble($inmueble->slug()) ); ?>"
                                   target="_blank">
                                    <i class="icon-homlity icon-uniE91C"></i> <?php echo esc_html( $inmueble->ciudad()->nombre() ); ?>
                                    - <?php echo esc_html( $inmueble->departamento()->nombre() ); ?></a></p>
                            <?php if ($inmueble->gestion()->esArriendoVenta()) : ?>
                                <p class="precios"><strong>V. venta</strong>
                                    $<?php echo esc_html( $inmueble->valorVenta(true) ); ?></p>
                                <p class="precios"><strong>V. arriendo</strong>
                                    $<?php echo esc_html( $inmueble->valorCanon(true) ); ?></p>
                            <?php elseif ($inmueble->gestion()->esAriendo()) : ?>
                                <p class="precios"><strong>V. arriendo</strong>
                                    $<?php echo esc_html( $inmueble->valorCanon(true) ); ?></p>
                            <?php else : ?>
                                <p class="precios"><strong>V. venta</strong>
                                    $<?php echo esc_html( $inmueble->valorVenta(true) ); ?></p>
                            <?php endif; ?>
                            <div class="row d-flex justify-content-center text-center">
                                <div class="col">
                                    <p>
                                        <i class="icon-homlity icon-uniE938"></i> <?php echo esc_html( $inmueble->nAlcobas() ); ?>
                                    </p>
                                </div>
                                <div class="col">
                                    <p>
                                        <i class="icon-homlity icon-uniE93E"></i> <?php echo esc_html( $inmueble->nBaños() ); ?>
                                    </p>
                                </div>
                                <div class="col">
                                    <p>
                                        <i class="icon-homlity icon-uniE9CC"></i> <?php echo esc_html( $inmueble->nGarajes() ); ?>
                                    </p>
                                </div>
                                <div class="col  vi-compartir-inmuebles">
                                    <div class="dropdown">
                                        <a class="dropdown-toggle" href="#" role="button"
                                           id="dropdownMenuLink" data-bs-toggle="dropdown"
                                           aria-expanded="false"><i
                                                    class="fas fa-share-alt"></i></a>
                                        <ul class="dropdown-menu"
                                            aria-labelledby="dropdownMenuLink">
                                            <li>
                                                <a class="dropdown-item"
                                                   href="<?php echo esc_url( visualinmu_redsocial_url(["nombre" => "facebook"], visualinmu_route_detalleInmueble($inmueble->slug())) ); ?>"
                                                   target="_blank"><i
                                                            class="fab fa-facebook-f"></i></a></li>
                                            <li><a class="dropdown-item"
                                                   href="<?php echo esc_url( visualinmu_redsocial_url(["nombre" => "linkedin"], visualinmu_route_detalleInmueble($inmueble->slug())) ); ?>"
                                                   target="_blank"><i
                                                            class="fab fa-linkedin-in"></i></a>
                                            </li>
                                            <li><a class="dropdown-item"
                                                   href="<?php echo esc_url( visualinmu_redsocial_url(["nombre" => "twitter"], visualinmu_route_detalleInmueble($inmueble->slug())) ); ?>"
                                                   target="_blank"><i
                                                            class="fab fa-twitter"></i></a></li>
                                            <li><a class="dropdown-item"
                                                   href="<?php echo esc_url( visualinmu_redsocial_url(["nombre" => "whatsapp"], visualinmu_route_detalleInmueble($inmueble->slug())) ); ?>"
                                                   target="_blank"><i
                                                            class="fab fa-whatsapp"></i></a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
