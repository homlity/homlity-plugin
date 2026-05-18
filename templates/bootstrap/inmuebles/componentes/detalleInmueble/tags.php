<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php foreach ($tags as $tag) { ?>
    <?php if ($tag->key() === "VISUALINMU_DESTACADO" || $tag->key() === "DESTACADO") { ?>
        <a href="/inmuebles/?tags=DESTACADO" target="_blank"
           class="badge bg-light text-dark badgePropiedadDestacada">Propiedad destacada</a>
    <?php } ?>
    <?php if ($tag->key() === "VISUALINMU_DELUJO") { ?>
        <a href="/inmuebles/?tags=VISUALINMU_DELUJO" target="_blank"
           class="badge bg-light text-dark badgePropiedadDeLujo">Propiedad de lujo</a>
    <?php } ?>
    <?php if ($tag->key() === "VISUALINMU_VERIFICADO") { ?>
        <a href="/inmuebles/?tags=VISUALINMU_VERIFICADO" target="_blank" class="badge bg-light text-dark badgePropiedadDeLujo"  data-bs-toggle="tooltip" data-bs-placement="top" title="Este inmueble y sus propietarios fueron previamente verificados a través de nuestro software de análisis jurídico y se encuentra CALIFICADA para ser comercializada; encuentra la información legal de esta propiedad descargando gratis la ficha técnica."><i class="icon-homlity icon-uniE9AF"></i> Propiedad verificada</a>
    <?php } ?>
<?php } ?>
