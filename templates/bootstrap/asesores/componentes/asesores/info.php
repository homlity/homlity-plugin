<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<h1><?php echo esc_html( $asesor->nombre() ); ?></h1>
<p><a
        href="<?php echo esc_url( visualinmu_redsocial_url( [ 'nombre' => 'whatsapp', 'phone' => $asesor->telefono(), 'texto' => 'Hola, ' . $asesor->nombre() . ' me podrias ayudar.' ], '' ) ); ?>">Teléfono:
        <?php echo esc_html( $asesor->telefono() ); ?></a></p>
<p><a href="mailto:<?php echo esc_attr( antispambot( sanitize_email( $asesor->email() ) ) ); ?>">Correo: <?php echo esc_html( $asesor->email() ); ?></a></p>
