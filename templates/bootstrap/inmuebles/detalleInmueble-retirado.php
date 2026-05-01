


<div class="container visualinmueble-detalle-inmueble ">
  
    <div class="row">
        <?php
        /**
         * @var \Codwelt\VisualInmueble\Core\Modelos\Inmueble $inmueble
         */
        if (isset($inmueble)) : ?>
            <div class="col-xl-9 col-lg-9 col-md-8 visualinmueble-inmueble-retirado">
            <div id="overlay-retirado">
        <div class="overlay-container">
            <div class="overlay-message">
                <h4>⚠️ Inmueble Retirado</h4>
                <p>Este inmueble ya no esta disponible, se encuentra arrendado o vendido.</p>
                
                <?php
                    $titulo1 = $inmueble->tipoInmueble()->nombre() ." en ". $inmueble->gestion()->nombre();
                    $urlQuery1 = visualinmu_route_search_url(['tiposInmueble' => $inmueble->tipoInmueble()->codigo(),'tipoGestion' => $inmueble->gestion()->codigo()]);
                ?>
                <?php
                    $titulo2 = $inmueble->tipoInmueble()->nombre() ." en ". $inmueble->ciudad()->nombre();
                    $urlQuery2 = visualinmu_route_search_url(['tiposInmueble' => $inmueble->tipoInmueble()->codigo(),'ciudades' => $inmueble->ciudad()->codigo()]);
                ?>
                <p class="text-white">
                    <a  class ="button btn-link overlay-button" title="<?php echo $titulo2 ?>" href="<?php echo $urlQuery2  ?>">+ <?php echo $titulo2; ?></a>
                </p>
                <p class="text-white">
                <a class ="button btn-link overlay-button" title="<?php echo $titulo1; ?>" href="<?php echo $urlQuery1 ?>">+ <?php echo $titulo1 ?></a>
                </p>
               
                
                
            </div>
        </div>
</div>    
            <div class="overlay-content-blur">
            <div class="row">
                    <div class="col-md-12 breadcrumbSection">
                        <!-- BREADCRUMB -->
                        <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/breadcrumb.php", ["page" => $page, "nombre" => $inmueble->nombre(),"inmueble" => $inmueble]); ?>
                    </div>
                    <div class="col-md-12">
                        <!-- TITULO -->
                        <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/titulo.php", ["codigo" => $inmueble->codigo(), "barrio" => $inmueble->barrio()->nombre(), "ciudad" => $inmueble->ciudad()->nombre(), "departamento" => $inmueble->departamento()->nombre(), "nombre" => $inmueble->nombre()]); ?>
                        <div class="tagsSection">
                            <!-- TAGS -->
                            <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/tags.php", ["tags" => $inmueble->tags()]); ?>
                        </div>                        <!-- TIPO DE GESTIÓN -->
                        <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/gestion.php", [
                            "gestion" => $inmueble->gestion(),
                            "valores" => [
                                "canon" => "xxxxxxx",
                                "venta" => "xxxxxxx",
                                "administracion_format" => "xxxxxx",
                                "administracion" => 0,

                            ],
                            'precioConAdmin' => $inmueble->precioConAdministracion()
                        ]); ?>
                    </div>
                </div>
                <div class="row">
                    <!-- SLIDERS-->
                    <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/sliders.php", ["nombre" => $inmueble->nombre(), "slider" => ["fotos" => $inmueble->imagenes(), "s360" => $inmueble->imagenes360(), "video" => $inmueble->video(), "video360" => $inmueble->video360()]]); ?>
                </div>
                <div class="row">
                  
                    <div class="col-md-12 sectionDescripcion">
                        <!-- DESCRIPCIÓN-->
                        <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/descripcion.php", ["descripcion" => $inmueble->descripcion()]); ?>
                    </div>
                 
                    <div class="col-md-12 sectionCarcateristicasPrincipales">
                        <!-- CARACTERÍSTICAS PRINCIPALES-->
                        <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/caracteristicasPrincipales.php", ["nombre" => $inmueble->nombre(), "caracteristicas" => ["areaLote" => $inmueble->areaLote(), "areaConstruida" => $inmueble->areaConstruida(), "banos" => $inmueble->nBaños(), "alcobas" => $inmueble->nAlcobas(), "garajes" => $inmueble->nGarajes(), "estrato" => $inmueble->estrato(), "edad" => $inmueble->edad()]]); ?>
                    </div>

                    <div class="col-md-12 sectionCaracaresticas">
                        <!-- CARACTERÍSTICAS-->
                        <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/caracteristicas.php", ["nombre" => $inmueble->nombre(),"inmueble" => $inmueble]); ?>
                    </div>
                   
                </div>
            
            </div>
            </div>
           
            <div class="col-xl-3 col-lg-3 col-md-4 barra-lateral-visual-inmueble">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card sectionPerfil" style="width: 100%;">
                            <div class="card-body">
                                <?php if ($inmueble->asesor() != null) : ?>
                                    <!-- PERFIL ASESOR-->
                                    <?php visualinmu_load_template("inmuebles/componentes/detalleInmueble/perfilAsesor.php", ["asesor" => $inmueble->asesor(), "nombre" => $inmueble->nombre(), "codigo" => $inmueble->codigo(), "slug" => $inmueble->slug(),"route" => visualinmu_route_detalleInmueble($inmueble->slug())]); ?>
                                <?php endif; ?>
                               </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
