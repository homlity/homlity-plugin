<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Homlity\PluginInmobiliario\Services\PropertyTaxonomies;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$columns = 3;
$totalFeatures = array_values(array_filter((array) $inmueble->caracteristicas(), static function ($caracteristica): bool {
    if (!is_object($caracteristica) || !method_exists($caracteristica, 'nombre')) {
        return true;
    }

    $term = get_term_by('name', (string) $caracteristica->nombre(), PropertyTaxonomies::TAXONOMY_FEATURE);
    if (!$term instanceof WP_Term) {
        return true;
    }

    return PropertyTaxonomies::isFeatureTermVisible($term);
}));
$cars = array_chunk($totalFeatures, max(1, (int) ceil(count($totalFeatures) / $columns)));
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
                                <?php echo esc_html( $caracteristica->nombre() .
                                    ((!empty($caracteristica->valor()) && $caracteristica->valor()) != '0' ? ': ' . $caracteristica->valor() : '') ); ?>
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
                            <?php echo esc_html( $caracteristica->nombre() .
                                ((!empty($caracteristica->valor()) && $caracteristica->valor()) != '0' ? ': ' . $caracteristica->valor() : '') ); ?>
                        </div>
                    <?php }
                } ?>
            </div>
        </section>
        <?php
    }
}
?>
