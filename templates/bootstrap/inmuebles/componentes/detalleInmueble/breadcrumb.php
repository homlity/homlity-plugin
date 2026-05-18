<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<nav aria-label="breadcrumb" id="breadcrums" onclick="gtag('event', 'breadcrum_open', {
    'origin': 'propert',
    'label': 'breadcrum_open'
  });">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a style=" font-weight: bold;" href="<?php echo esc_url( $page['back'] ); ?>"><i class="icon-homlity icon-uniEA40"></i> Volver</a></li>
       <?php if(isset($inmueble)){ ?>
        <li class="breadcrumb-item"><a target="_blank" href="<?php echo esc_url( visualinmu_route_search([
            "type_bussiness" => $inmueble->gestion()->nombre()
        ]) ); ?>"><?php echo esc_html( $inmueble->gestion()->nombre() ); ?></a></li>
       <li class="breadcrumb-item"><a target="_blank" href="<?php echo esc_url( visualinmu_route_search([
             "type_bussiness" => $inmueble->gestion()->nombre(),
            "type_property" => $inmueble->tipoInmueble()->nombre()
        ]) ); ?>"><?php echo esc_html( $inmueble->tipoInmueble()->nombre() ); ?></a></li>
    <li class="breadcrumb-item"><a target="_blank" href="<?php echo esc_url( visualinmu_route_search([
             "type_bussiness" => $inmueble->gestion()->nombre(),
            "type_property" => $inmueble->tipoInmueble()->nombre(),
            "city" => $inmueble->ciudad()->nombre()
        ]) ); ?>"><?php echo esc_html( $inmueble->ciudad()->nombre() ); ?></a></li>
    <li class="breadcrumb-item"><a target="_blank" href="<?php echo esc_url( visualinmu_route_search([
             "type_bussiness" => $inmueble->gestion()->nombre(),
            "type_property" => $inmueble->tipoInmueble()->nombre(),
            "city" => $inmueble->ciudad()->nombre(),
            "neighborhood" => $inmueble->barrio()->nombre()
        ]) ); ?>"><?php echo esc_html( $inmueble->barrio()->nombre() ); ?></a></li>
       

        <?php 
       }
       ?>       
    </ol>
</nav>
