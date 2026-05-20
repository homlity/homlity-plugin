<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="row">
    <?php if (count($inmuebles) > 0) : ?>
        <?php foreach ($inmuebles as $cont => $inmueble) : ?>
            <div class="col-xs-12 col-md-4 card-space">
                <?php visualinmu_load_template("inmuebles/componentes/card.php", get_defined_vars()); ?>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <label> Sin Resultados </label>
    <?php endif; ?>
</div>