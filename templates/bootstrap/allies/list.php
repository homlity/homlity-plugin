<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="container-fluid visualinmu_allies_list">
    <div class="row">
        <?php foreach ($allies as $asesor) { ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo esc_html( $asesor->nombre() ); ?></h5></a>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <a onclick="gtag('event', 'wp_open_allie', {
    'label': 'Whatsapp aliado',
    'value': '<?php echo esc_js( $asesor->nombre() ); ?>' // Este valor puede ser un número
  });" target="_blank" href="<?php echo esc_url( visualinmu_redsocial_url( [ 'nombre' => 'whatsapp', 'phone' => $asesor->telefono(), 'texto' => 'Buen dia, ' . $asesor->nombre() . ' quisiera mas informacion de los inmuebles que estan su pagina web' ], null ) ); ?>"
                                    target="_blank"><i class="fab fa-whatsapp"></i> Hablar por WhatsApp</a>
                            </li>
                            <?php if (homlity-real-estate_valida_dato_contacto('telefono', $asesor->telefono())): ?>
                                <li class="list-group-item">
                                    <a onclick="gtag('event', 'phone_open_allie', {
    'label': 'Telefono aliado',
    'value': '<?php echo esc_js( $asesor->nombre() ); ?>' // Este valor puede ser un número
  });" href="tel:<?php echo esc_attr( visualinmu_formatear_telefono( $asesor->telefono() ) ); ?>" target="_blank"><i
                                            class="fas fa-mobile-alt"></i> <?php echo esc_html( $asesor->telefono() ); ?></a>
                                </li>
                            <?php endif; ?>
                            <?php if (homlity-real-estate_valida_dato_contacto('email', $asesor->email())): ?>
                                <li class="list-group-item">
                                    <a onclick="gtag('event', 'email_open_allie', {
    'label': 'Email aliado',
    'value': '<?php echo esc_js( $asesor->nombre() ); ?>' // Este valor puede ser un número
  });" href="mailto:<?php echo esc_attr( antispambot( sanitize_email( $asesor->email() ) ) ); ?>?subject=Estoy interesado en los inmuebles de su pagina web"
                                        target="_blank">
                                        <i class="fas fa-envelope"></i><?php echo esc_html( $asesor->email() ); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
