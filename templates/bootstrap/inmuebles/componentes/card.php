<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="card-inmueble ">
    <?php 
        $passwordRequired = homlity_property_required_password_in_tag($inmueble);
        if($passwordRequired) {
            visualinmu_load_template("inmuebles/componentes/card-password.php",get_defined_vars());
        }else {
            visualinmu_load_template("inmuebles/componentes/card-default.php",get_defined_vars());
        } 
    ?>
</div>