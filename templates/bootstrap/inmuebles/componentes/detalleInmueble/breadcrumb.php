<nav aria-label="breadcrumb" id="breadcrums" onclick="gtag('event', 'breadcrum_open', {
    'origin': 'propert',
    'label': 'breadcrum_open'
  });">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a style=" font-weight: bold;" href="<?php echo $page["back"]; ?>"><i class="icon-homlity icon-uniEA40"></i> Volver</a></li>
       <?php if(isset($inmueble)){ ?>
        <li class="breadcrumb-item"><a target="_blank" href="<?php echo visualinmu_route_search([
            "type_bussiness" => $inmueble->gestion()->nombre()
        ]); ?>"><?php echo $inmueble->gestion()->nombre(); ?></a></li>
       <li class="breadcrumb-item"><a target="_blank" href="<?php echo visualinmu_route_search([
             "type_bussiness" => $inmueble->gestion()->nombre(),
            "type_property" => $inmueble->tipoInmueble()->nombre()
        ]); ?>"><?php echo $inmueble->tipoInmueble()->nombre(); ?></a></li>
    <li class="breadcrumb-item"><a target="_blank" href="<?php echo visualinmu_route_search([
             "type_bussiness" => $inmueble->gestion()->nombre(),
            "type_property" => $inmueble->tipoInmueble()->nombre(),
            "city" => $inmueble->ciudad()->nombre()
        ]); ?>"><?php echo $inmueble->ciudad()->nombre(); ?></a></li>
    <li class="breadcrumb-item"><a target="_blank" href="<?php echo visualinmu_route_search([
             "type_bussiness" => $inmueble->gestion()->nombre(),
            "type_property" => $inmueble->tipoInmueble()->nombre(),
            "city" => $inmueble->ciudad()->nombre(),
            "neighborhood" => $inmueble->barrio()->nombre()
        ]); ?>"><?php echo $inmueble->barrio()->nombre(); ?></a></li>
       

        <?php 
       }
       ?>       
    </ol>
</nav>