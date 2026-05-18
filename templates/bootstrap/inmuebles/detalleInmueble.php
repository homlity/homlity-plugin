<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="container homlity-plugin-detalle-inmueble homlity-plugin_detalle_inmueble">
    <div class="row">
        <div class="col-md-12 breadcrumbSection">
            <!-- BREADCRUMB -->
            <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/breadcrumb.php", ["inmueble" => $inmueble, "page" => $page, "nombre" => $inmueble->nombre()]); ?>
        </div>
    </div>

    <div class="row">
        <?php
        /**
         * @var \Codwelt\homlity-plugin\Core\Modelos\Inmueble $inmueble
         */
        if (isset($inmueble)): ?>
            <div class="col-xl-9 col-lg-9 col-md-8">

                <div class="row">
                    <!-- SLIDERS-->
                    <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/sliders.php", ["nombre" => $inmueble->nombre(), "slider" => ["fotos" => $inmueble->imagenes(), "s360" => $inmueble->imagenes360(), "video" => $inmueble->video(), "video360" => $inmueble->video360()]]);
                    ; ?>
                </div>
                <div class="row">

                    <div class="col-md-12 header-inmueble">
                        <!-- TITULO -->
                        <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/titulo.php", ["codigo" => $inmueble->codigo(), "barrio" => $inmueble->barrio()->nombre(), "ciudad" => $inmueble->ciudad()->nombre(), "departamento" => $inmueble->departamento()->nombre(), "nombre" => $inmueble->nombre()]); ?>
                        <?php if (count($inmueble->tags()) > 0) { ?>
                            <div class="tagsSection">
                                <!-- TAGS -->
                                <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/tags.php", ["tags" => $inmueble->tags()]); ?>
                            </div>
                        <?php } ?>
                        <!-- TIPO DE GESTIÓN -->
                        <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/gestion.php", [
                            "gestion" => $inmueble->gestion(),
                            "valores" => [
                                "canon" => $inmueble->valorCanon(true),
                                "venta" => $inmueble->valorVenta(true),
                                "administracion_format" => $inmueble->valorAdmin(true),
                                "administracion" => $inmueble->valorAdmin(true),

                            ],
                            'precioConAdmin' => $inmueble->precioConAdministracion()
                        ]); ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div onclick="gtag('event', 'prop_narracion', {
    'origin': 'property',
    'label': 'Escuchar inmueble',
    'value': '<?php echo esc_attr($inmueble->nombre()); ?>' // Este valor puede ser un número
  });">
                            <homlity-plugin-narracion></homlity-plugin-narracion>
                        </div>

                        <script type="text/javascript">
                            window.homlity-plugin_INMUEBLE = <?php echo wp_kses_post( $inmuebleJson ); ?>;
                        </script>
                        <!-- REDES SOCIALES -->
                        <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/redesSociales.php", ["inmueble" => $inmueble, "nombre" => $inmueble->nombre(), "codigo" => $inmueble->codigo(), "descripcion" => $inmueble->descripcion(), "route" => visualinmu_route_detalleInmueble($inmueble->slug())]); ?>

                    </div>
                    <div class="col-md-12 sectionDescripcion">
                        <!-- DESCRIPCIÓN-->
                        <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/descripcion.php", ["descripcion" => $inmueble->descripcion()]); ?>
                    </div>
                    <div class="col-md-12 mt-3 mb-3">
                        <div class="row">
                            <h2 class="h2"><?php echo esc_html__( 'Características del inmueble', 'homlity-plugin' ); ?></h2>
                            <div class="col-md-12 sectionCarcateristicasPrincipales">
                                <!-- CARACTERÍSTICAS PRINCIPALES-->
                                <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/caracteristicasPrincipales.php", ["nombre" => $inmueble->nombre(), "caracteristicas" => ["areaLote" => $inmueble->areaLote(), "areaConstruida" => $inmueble->areaConstruida(), "banos" => $inmueble->nBaños(), "alcobas" => $inmueble->nAlcobas(), "garajes" => $inmueble->nGarajes(), "estrato" => $inmueble->estrato(), "edad" => $inmueble->edad()]]); ?>
                            </div>
                            <div class="col-md-12 sectionCaracaresticas">
                                <!-- CARACTERÍSTICAS-->
                                <?php

                                if (count($inmueble->caracteristicas()) > 0) {
                                    visualinmu_load_template("inmuebles/componentes/detalleInmueble/caracteristicas.php", ["nombre" => $inmueble->nombre(), "inmueble" => $inmueble]);
                                }

                                ?>
                            </div>
                        </div>

                    </div>
                    <div class="col-md-12 sectionMapa">
                        <!-- UBICACIÓN-->
                        <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/ubicacion.php", ["nombre" => $inmueble->nombre(), "mapa" => ["latitud" => $inmueble->latitud(), "longitud" => $inmueble->longitud(), "propiedadesSimilares" => $similaresJson]]); ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-3 col-md-4 barra-lateral-visual-inmueble">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card sectionPerfil" style="width: 100%;">
                            <div class="card-body">
                                <?php if ($inmueble->asesor() != null): ?>
                                    <!-- PERFIL ASESOR-->
                                    <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/perfilAsesor.php", ["asesor" => $inmueble->asesor(), "nombre" => $inmueble->nombre(), "codigo" => $inmueble->codigo(), "slug" => $inmueble->slug(), "route" => visualinmu_route_detalleInmueble($inmueble->slug())]); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>                  
                    <div class="col-md-12">
                        <?php if (visualinmu_is_sidebar_active("lateral_pagina_detalle_visual_inmueble")): ?>
                            <?php dynamic_sidebar('lateral_pagina_detalle_visual_inmueble'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if (isset($similares) && count($similares) > 0): ?>
                <div class="col-xl-12 col-md-12 section-propiedades-similares">
                    <!-- PROPIEDADES SIMILARES -->
                    <div class="row">
                        <div class="col-md-12">
                            <h2 class="homlity-plugin-titulos-propiedades-similares h2">Inmuebles <span
                                    style="text-transform: lowercase;">similares al <?php echo esc_html( $inmueble->nombre() ); ?></span>
                            </h2>
                        </div>
                        <div class="col-md-12">
                            <?php if (count($similares) > 0) {
                                /** CARDS **/
                                visualinmu_load_template("inmuebles/componentes/search/cards.php", ["inmuebles" => $similares]);
                            } else { ?>
                                <label> Sin Resultados </label>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
