<div class="row d-flex align-items-stretch flex-wrap" itemscope itemtype="https://schema.org/ItemList">
   
    <meta itemprop="numberOfItems" content="<?php echo count($inmuebles); ?>" />
    <meta itemprop="itemListOrder" content="https://schema.org/ItemListOrderAscending" />

    <?php foreach ($inmuebles as $index => $inmueble): ?>
        <?php $cont = $index + 1; ?>
        <div class="col-xl-3 col-lg-4 col-md-6 col-xs-12  mb-2 ">
            <?php visualinmu_load_template("inmuebles/componentes/card.php", get_defined_vars()); ?>
        </div>
        <?php
        if (visualinmu_configuracion_checkConfiguracion('filtros', 'mostrarAdwordsEnResultadoBusqueda')) {
            if ($cont == 5 || $cont == 11 || $cont == 43) { ?>
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-xs-12 mb-2 ">
                    <div class="card-inmueble flex-fill">
                        <div class="card card-space " style="width: 100%;">
                            <div class="card-body">
                                <?php
                                $version = rand(0, 1) == 0 ? 'A' : 'B';
                                if ($version == 'A') {
                                    $path = "leads/buscarinmueble";
                                } else {
                                    $path = "leads/buscarinmueblestep";
                                }
                                echo do_shortcode('[visualinmu_lead_shortcode height="600" path="' . $path . '"]'); ?>
                            </div>
                        </div>
                    </div>

                </div>
            <?php }
        }
        ?>
    <?php endforeach; ?>
</div>