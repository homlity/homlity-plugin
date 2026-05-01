<a target="_blank" rel="nofollow" onclick="gtag('event', 'wp_open_advisor_prop', {
    'origin': 'card',
    'label': 'Whatsapp abri por inmueble',
    'value': '<?php echo $inmueble->nombre(); ?>' // Este valor puede ser un número
  });" href="<?php echo homlity_signed_url_make([
    'adviser_id' =>$inmueble->asesor()->getId(),
    "url" =>visualinmu_redsocial_url(["nombre" => "whatsapp", "phone" => $inmueble->asesor()->telefono(), "texto" => "Buen dia, me interesa " . $inmueble->nombre() . " que encontre en su pagina web código: " . $inmueble->codigo()], visualinmu_route_detalleInmueble($inmueble->slug())),
    "code" => $inmueble->codigo()]) ?>"
                                        class=""> <span class="icon"><i class="icon-homlity icon-uniE9D7"></i></span> Más información</a>