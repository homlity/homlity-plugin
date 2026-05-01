<div class="container visualinmueble-detalle-asesor">
    <div class="section-perfil-asesor">
        <?php visualinmu_load_template("asesores/componentes/asesores/detalle.php", ["asesor" => $asesor]); ?>
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="float-start">
                    <h3>Inmuebles del asesor</h3>
                </div>
                <div class="clearfix mb-3 ">
                    <a class="float-end" type="button"
                        href="<?php echo visualinmu_url_parameters_append(['direccion_order' => 'asc', 'column_order' => 'price']); ?>"
                        data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="custom-tooltip"
                        title="Menor a mayor">
                        <i class="btn icon-homlity icon-uniEA3E"></i>
                    </a>
                    <a class="float-end" type="button"
                        href="<?php echo visualinmu_url_parameters_append(['direccion_order' => 'desc', 'column_order' => 'price']); ?>"
                        data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="custom-tooltip"
                        title="Mayor a menor">
                        <i class="btn icon-homlity icon-uniEA3A"></i></a>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <?php visualinmu_load_template("inmuebles/componentes/search/cards.php", ["inmuebles" => $inmuebles]); ?>
        <?php if (isset($paginador)) {
            /**  PAGINADOR */
            visualinmu_load_template("inmuebles/componentes/search/paginator-form.php", ["paginador" => $paginador]);
        } ?>
    </div>
</div>