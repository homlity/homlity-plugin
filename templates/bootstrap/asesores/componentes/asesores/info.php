<h1><?php echo $asesor->nombre(); ?></h1>
<p><a
        href="<?php echo visualinmu_redsocial_url(["nombre" => "whatsapp", "phone" => $asesor->telefono(), "texto" => "Hola, " . $asesor->nombre() . " me podrias ayudar."], "") ?>">Teléfono:
        <?php echo $asesor->telefono() ?></a></p>
<p><a href="email:<?php echo $asesor->email() ?>">Correo: <?php echo $asesor->email() ?></a></p>