<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php

use Codwelt\homlity-real-estate\Core\Modelos\Inmueble;
use Codwelt\homlity-real-estate\Core\Modelos\InmueblePreview;
?> <div>
    <div class="card-inmueble h-100  d-flex align-items-stretch">
        <div class="card card-space h-100 " style="width: 100%;">
            <?php visualinmu_load_template("inmuebles/componentes/card/cover-image.php", array_merge_recursive(get_defined_vars(), ['attrs' => ['withLinktoDetail' => false]])); ?>
            <div class="card-body">
                <div class="card-inmueble-container">
                    <div class="card-inmueble-caracteristicas">
                        <?php visualinmu_load_template("inmuebles/componentes/card/features.php", array_merge_recursive(get_defined_vars(), ['attrs' => ['withLinktoDetail' => false]])); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>