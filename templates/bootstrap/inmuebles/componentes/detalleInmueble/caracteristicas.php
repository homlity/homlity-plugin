<?php
$columns = 3;
$totalFeatures = $inmueble->caracteristicas();
$cars = $inmueble->caracteristicasPorColumnas($columns);
$conteo = count($cars);
if ($conteo > 0) {
    if (count($totalFeatures) > $columns) {
        ?>
        <section class="mt-4">
            <div class="collapse collapse-preview" id="collap-feature">
                <div class="d-flex flex-md-row flex-wrap justify-content-between">
                    <?php foreach ($cars as $grupo) {
                        foreach ($grupo as $caracteristica) { ?>
                            <div class="item-caracteristicas">
                                <i class="icon-homlity icon-uniE954"></i>
                                <?php echo $caracteristica->nombre() .
                                    ((!empty($caracteristica->valor()) && $caracteristica->valor()) != '0' ? ': ' . $caracteristica->valor() : '') ?>
                            </div>
                        <?php }
                    } ?>
                </div>
            </div>
            <div class="text-center">
                <a onclick="gtag('event', 'prop_feature_more', {
    'origin': 'property',
    'label': 'Mostrar mas caracteristicas',
    'value': 1 // Este valor puede ser un número
  });" class="" data-bs-toggle="collapse" href="#collap-feature" role="button" aria-expanded="false"
                    aria-controls="Mostrar mas características">
                    <span id="chevron-feature">&#x2193;</span> <!-- Aquí irá la flecha --> <span
                        class="text-decoration-underline">Mostrar todas las características</span>
                </a>
            </div>

        </section>
        <?php
    } else {
        ?>
        <section class="mt-4">
            <div class="d-flex flex-md-row flex-wrap justify-content-between">
                <?php foreach ($cars as $grupo) {
                    foreach ($grupo as $caracteristica) { ?>
                        <div class="item-caracteristicas">
                            <i class="icon-homlity icon-uniE954"></i>
                            <?php echo $caracteristica->nombre() .
                                ((!empty($caracteristica->valor()) && $caracteristica->valor()) != '0' ? ': ' . $caracteristica->valor() : '') ?>
                        </div>
                    <?php }
                } ?>
            </div>
        </section>
        <?php
    }
}
?>