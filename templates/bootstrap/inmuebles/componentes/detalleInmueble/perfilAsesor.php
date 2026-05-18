<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php visualinmu_load_template( 'asesores/componentes/asesores/foto.php', [ 'asesor' => $asesor ] ); ?>
<a href="<?php echo esc_url( visualinmu_route_detalleAsesor( $asesor->slug() ) ); ?>"
onclick="gtag('event', 'open_advisor_detail', {
    'origin': 'property_advisor',
    'label': 'Abrir perfil asesor',
    'value': '<?php echo esc_js( $asesor->nombre() ); ?>'
  });"
   target="_blank">
    <h5 class="card-title"><?php echo esc_html( $asesor->nombre() ); ?></h5></a>
<ul class="list-group list-group-flush">
    <li class="list-group-item">
        <a onclick="gtag('event', 'wp_open_advisor', {
    'origin': 'property_advisor',
    'label': 'Whatsapp Abir perfil asesor',
    'value': '<?php echo esc_js( $asesor->nombre() ); ?>'
  });" target="_blank" href="<?php echo esc_url( visualinmu_redsocial_url( [ 'nombre' => 'whatsapp', 'phone' => $asesor->telefono(), 'texto' => 'Buen dia, encontre esto en su pagina web y estoy interesado en ' . $nombre . ' código: ' . $codigo ], $route ) ); ?>" target="_blank"><i class="fab fa-whatsapp"></i> Hablar por WhatsApp</a>
    </li>
    <?php if ( homlity-plugin_valida_dato_contacto( 'telefono', $asesor->telefono() ) ) : ?>
        <li class="list-group-item">
            <a
            onclick="gtag('event', 'phone_open', {
    'origin': 'property_advisor',
    'label': 'phone_open',
    'value': '<?php echo esc_js( $asesor->nombre() ); ?>'
  });"
            href="tel:<?php echo esc_attr( visualinmu_formatear_telefono( $asesor->telefono() ) ); ?>" target="_blank"><i class="fas fa-mobile-alt"></i> Hablar por celular</a>
        </li>
    <?php endif; ?>
    <?php if ( homlity-plugin_valida_dato_contacto( 'email', $asesor->email() ) ) : ?>
        <li class="list-group-item">
            <a
            onclick="gtag('event', 'email_open', {
    'origin': 'property_advisor',
    'label': 'email_open',
    'value': '<?php echo esc_js( $asesor->nombre() ); ?>'
  });"
            href="mailto:<?php echo esc_attr( antispambot( sanitize_email( $asesor->email() ) ) ); ?>?subject=<?php echo rawurlencode( 'Estoy interesado en el inmueble ' . $nombre ); ?>&body=<?php echo rawurlencode( 'Buen dia, mi nombre e(nombre solicitante) mi celular es(celular) y estoy interesado en el inmueble ' . $nombre . ' - ' . $codigo . ' ' . visualinmu_route_detalleInmueble( $slug ) ); ?>"
            target="_blank">
                <i class="fas fa-envelope"></i> Enviar correo
            </a>
        </li>
    <?php endif; ?>
</ul>
