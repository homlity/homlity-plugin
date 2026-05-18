<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="homlity-plugin-search">
    <div class="offcanvas offcanvas-start" data-bs-scroll="true" tabindex="-1" id="offcanvasWithBothOptions"
        aria-labelledby="offcanvasWithBothOptionsLabel">
        <div class="offcanvas-header">
            <span class="offcanvas-title h5" id="offcanvasWithBothOptionsLabel">Filtros</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <?php visualinmu_load_template("inmuebles/componentes/search/search-form-sidebar.php", get_defined_vars()); ?>
        </div>
    </div>
    <div class="container-fluid contenedor-visual-inmueble">
        <?php if (isset($error)): ?>
            <div class="row">
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>Información: </strong> <?php echo esc_html( $error ); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
        <div class="d-flex flex-column">
            <?php visualinmu_load_template("inmuebles/componentes/search/search-header.php", ["paginador" => $paginador]); ?>
            <div class="tab-content">
                <div class="tab-pane show active " id="listtab" role="tabpanel" aria-labelledby="list-tab">
                    <div class="cuerpo-grilla-inmuebles">
                        <?php if (isset($inmuebles)): ?>
                            <div>

                                <?php if (count($inmuebles) > 0) {

                                    /** CARDS **/
                                    visualinmu_load_template("inmuebles/componentes/search/cards.php", ["inmuebles" => $inmuebles]);
                                } else { ?>
                                    <div class="row">
                                        <div class="col-12 text-center">
                                            <h3> No existen resultados para esta búsqueda.</h3>
                                            <?php visualinmu_load_template("inmuebles/componentes/search/search-widgets.php"); ?>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>

                        <?php endif; ?>
                        <?php if (isset($paginador)) {
                            /**  PAGINADOR */
                            visualinmu_load_template("inmuebles/componentes/search/paginator-form.php", ["paginador" => $paginador]);
                        } ?>
                    </div>
                </div>
                <div class="tab-pane" id="mapa-tab" role="tabpanel" aria-labelledby="mapa-tab">
                    <?php if (count($inmuebles) > 0) { ?>
                        <?php visualinmu_load_template("inmuebles/componentes/search/mapa.php", ["inmuebles" => $inmueblesJson]); ?>
                        <?php if (isset($paginador)) {
                            /**  PAGINADOR */
                            visualinmu_load_template("inmuebles/componentes/search/paginator-form.php", ["paginador" => $paginador]);
                        } ?>
                    <?php } else {
                        ?>
                        <div class="row">
                            <div class="col-12 text-center">
                                <h3> No existen resultados para esta búsqueda.</h3>
                                <?php visualinmu_load_template("inmuebles/componentes/search/search-widgets.php"); ?>
                            </div>
                        </div>
                        <?php
                    } ?>
                </div>
            </div>
        </div>
    </div>
</div>
