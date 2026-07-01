<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="clearfix mb-3 homlity-search-header ">
                <div class="float-start d-flex">
                    <a class="btn btn-primary" id="vi-btn-filtros" data-bs-toggle="offcanvas" href="#offcanvasWithBothOptions" role="button"
                        aria-controls="offcanvasWithBothOptions"><i class="icon-homlity icon-uniE9B7"></i>
                        Filtros
                    </a>
                    <ul class="nav nav-pills mx-2 border rounded" id="vi-bts-gridmap" id="tabSearch" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="btn-listab" data-bs-toggle="pill" data-bs-target="#listtab"
                                type="button" role="tab" aria-controls="listtab" aria-selected="true" href="#listtab"
                                onclick="gtag('event', 'search_list_tab', {
                                        'origin': 'search',
                                        'label': 'Mostrar Listado inmuebles'                                        
                                    });">
                                <i class="icon-homlity icon-uniE911"></i> <?php echo esc_html__( "Inmuebles en Listado", "homlity-real-estate" ); ?>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link d-block" data-bs-toggle="pill" id="btn-mapatab"
                                data-bs-target="#mapa-tab" href="#mapa-tab" type="button" role="tab"
                                aria-controls="listtab" aria-selected="true" onclick="gtag('event', 'search_map_tab', {
                                        'origin': 'search',
                                        'label': 'Mostrar Mapas inmuebles'                                        
                                    });">
                                <i class="icon-homlity icon-uniE9C1"></i> <?php echo esc_html__( "Inmuebles en Mapa", "homlity-real-estate" ); ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="float-end d-flex align-items-center">
                    <?php
                    if (isset($paginador) && !is_null($paginador)):
                        ?>
                        <div class="mx-2 fst-italic text-muted">
                            <small><?php echo esc_html( number_format($paginador->getTotalItems(), 0, ',', '.') . " inmuebles encontrados " ); ?></small>
                        </div>
                    <?php endif; ?>
                    <div class="homlity-btns-order">
                        <a class=" btn btn-primary btn-sm" type="button"
                            href="<?php echo esc_url( visualinmu_url_parameters_append(['direccion_order' => 'asc']) ); ?>"
                            data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="custom-tooltip"
                            title="Menor a mayor">
                            <i class=" icon-homlity icon-uniEA3E"></i>
                        </a>
                        <a class=" btn btn-primary btn-sm" type="button"
                            href="<?php echo esc_url( visualinmu_url_parameters_append(['direccion_order' => 'desc']) ); ?>"
                            data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="custom-tooltip"
                            title="Mayor a menor">
                            <i class=" icon-homlity icon-uniEA3A"></i></a>
                    </div>

                </div>

            </div>
