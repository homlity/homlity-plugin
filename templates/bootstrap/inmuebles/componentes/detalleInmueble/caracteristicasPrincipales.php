<div class="d-flex flex-md-row flex-wrap">
    <?php
        if($caracteristicas["areaLote"] > 0){
    ?>
    <div class=" itemCaracteristica caracteristicaAreaLote">
        <div class="flex d-flex align-items-center">
            <i class="icon-homlity icon-uniE914"></i>
            <span class="" >Área lote: <?php echo $caracteristicas["areaLote"]; ?> M<sup>2</sup></span>
        </div>
    </div>
    <?php 
    }
    ?>
    <?php
    if($caracteristicas["areaConstruida"] > 0){
    ?>
    <div class="itemCaracteristica caracteristicaAreaConstruida">
        <div class="flex d-flex align-items-center">
            <i class="icon-homlity icon-uniE95E"></i>
            <span>Área cons: <?php echo $caracteristicas["areaConstruida"]; ?> M<sup>2</sup></span>            
        </div>
    </div>
    <?php 
    }
    ?>
     <?php
    if( $caracteristicas["banos"] > 0){
    ?>
    <div class="itemCaracteristica caracteristicaAreaLote">
        <div class="flex d-flex align-items-center">
            <i class="icon-homlity icon-uniE93E"></i>
            <span ><?php echo ($caracteristicas["banos"] === 1) ? "Baño: " . $caracteristicas["banos"] : "Baños: " . $caracteristicas["banos"]; ?></span>
        </div>
    </div>
    <?php 
    }
    ?>
    <?php
    if( $caracteristicas["alcobas"] > 0){
    ?>
    <div class="itemCaracteristica caracteristicaAlcobas">
        <div class="flex d-flex align-items-center">
            <i class="icon-homlity icon-uniE951"></i>
            <span ><?php echo ($caracteristicas["alcobas"] === 1) ? "Alcoba: " . $caracteristicas["alcobas"] : "Alcobas: " . $caracteristicas["alcobas"]; ?></span>
        </div>
    </div>
    <?php 
    }
    ?>
     <?php
    if( $caracteristicas["garajes"] > 0){
    ?>
    <div class="itemCaracteristica caracteristicaGarajes">
        <div class="flex d-flex align-items-center">
            <i class="icon-homlity icon-uniE9A5"></i>
            <span><?php echo ($caracteristicas["garajes"] === 1) ? "Garaje: " . $caracteristicas["garajes"] : "Garajes: " . $caracteristicas["garajes"]; ?></span>
        </div>
    </div>
    <?php 
    }
    ?>
     <?php
    if( $caracteristicas["estrato"] > 0){
    ?>
    <div class="itemCaracteristica caracteristicaEstrato">
        <div class="flex d-flex align-items-center">
           <i class="icon-homlity icon-uniE9AF"></i></span>
           <span> Estrato: <?php 
           if(homlity_sync_integrator_current_is_wasi()) {
                if($caracteristicas["estrato"] == 7) {
                    echo __('Rural','visualinmueble');
                } else if ($caracteristicas["estrato"] == 8) {
                    echo __('Comercial','visualinmueble');
                }else {
                    echo $caracteristicas["estrato"];
                }
           }else if(homlity_sync_integrator_current_is_simi()) {
                 if($caracteristicas["estrato"] == 8) {
                    echo __('Rural','visualinmueble');;
                } else if ($caracteristicas["estrato"] == 7) {
                    echo __('Comercial','visualinmueble');
                }else {
                    echo $caracteristicas["estrato"];
                }
           } else {
                echo $caracteristicas["estrato"];
           }
        ?></span>
        </div>
    </div>
    <?php 
    }
    ?>
     <?php
    if( $caracteristicas["edad"] > 0){
    ?>
    <div class="itemCaracteristica caracteristicaEdad">
        <div class="flex d-flex align-items-center">
            <i class="icon-homlity icon-uniE98A"></i>
            <span>Edad: <?php echo ($caracteristicas["edad"] === 1) ? $caracteristicas["edad"] . " año" : $caracteristicas["edad"] . " años"; ?></span>
        </div>
    </div>
    <?php 
    }
    ?>
</div>