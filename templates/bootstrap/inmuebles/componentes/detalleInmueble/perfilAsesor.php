<?php visualinmu_load_template("asesores/componentes/asesores/foto.php", ["asesor" => $asesor]); ?>
<a href="<?php echo visualinmu_route_detalleAsesor($asesor->slug()) ?>"
onclick="gtag('event', 'open_advisor_detail', {
    'origin': 'property_advisor',
    'label': 'Abrir perfil asesor',
    'value': '<?php echo  $asesor->nombre(); ?>' // Este valor puede ser un número
  });"
   target="_blank">
    <h5 class="card-title"><?php echo $asesor->nombre(); ?></h5></a>
<ul class="list-group list-group-flush">
    <li class="list-group-item">
        <a onclick="gtag('event', 'wp_open_advisor', {
    'origin': 'property_advisor',
    'label': 'Whatsapp Abir perfil asesor',
    'value': '<?php echo  $asesor->nombre(); ?>' // Este valor puede ser un número
  });" target="_blank" href="<?php echo visualinmu_redsocial_url(["nombre" => "whatsapp", "phone" => $asesor->telefono(),"texto" => "Buen dia, encontre esto en su pagina web y estoy interesado en " .  $nombre ." código: ". $codigo], $route) ?>" target="_blank"><i class="fab fa-whatsapp"></i> Hablar por WhatsApp</a>
    </li>
    <?php if( visualinmueble_valida_dato_contacto('telefono',$asesor->telefono()) ):?> 
        <li class="list-group-item">
            <a 
            onclick="gtag('event', 'phone_open', {
    'origin': 'property_advisor',
    'label': 'phone_open',
    'value': '<?php echo  $asesor->nombre(); ?>' // Este valor puede ser un número
  });"
            href="tel:<?php echo visualinmu_formatear_telefono($asesor->telefono());?>" target="_blank"><i class="fas fa-mobile-alt"></i> Hablar por celular</a>
        </li>
    <?php endif; ?>
    <?php if( visualinmueble_valida_dato_contacto('email',$asesor->email()) ): ?>
        <li class="list-group-item">
            <a 
            onclick="gtag('event', 'email_open', {
    'origin': 'property_advisor',
    'label': 'email_open',
    'value': '<?php echo  $asesor->nombre(); ?>' // Este valor puede ser un número
  });"
            href="mailto:<?php echo $asesor->email(); ?>?subject=Estoy interesado en el inmueble <?php echo $nombre; ?>&body=Buen dia, mi nombre e(nombre solicitante) mi celular es(celular) y estoy interesado en el inmueble <?php echo $nombre; ?> - <?php echo $codigo; ?> <?php echo visualinmu_route_detalleInmueble($slug); ?>"
            target="_blank">
                <i class="fas fa-envelope"></i> Enviar correo
            </a>
        </li>
    <?php endif; ?>
</ul>
