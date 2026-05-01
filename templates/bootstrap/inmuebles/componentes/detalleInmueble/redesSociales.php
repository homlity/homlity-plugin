<?php
    if(isset($inmueble)) {
        $texto = visualinmu_text_shared($inmueble);
    }else {
        $texto = "Hola, mira este inmueble " . $nombre . " - código: " . $codigo;
    }
    
?>
<ul class="socio-links">
    <li>Compartir en:</li>
    <li>
        <a 
        onclick="gtag('event', 'facebook_open', {
    'origin': 'property_shared',
    'label': 'facebook_open',
    'value': '<?php echo  $nombre; ?>' // Este valor puede ser un número
  });" target="_blank"
        href="<?php echo visualinmu_redsocial_url(["nombre" => "facebook", "texto" => $texto ], $route); ?>"
           title="Facebook" target="_blank"><i class="fa-brands fa-square-facebook"></i></a></li>
    <li>
        <a 
        onclick="gtag('event', 'twitter_open', {
    'origin': 'property_shared',
    'label': 'twitter_open',
    'value': '<?php echo  $nombre; ?>' // Este valor puede ser un número
  });" target="_blank"
        href="<?php echo visualinmu_redsocial_url(["nombre" => "x", "texto" => $texto], $route); ?>"
           title="X" target="_blank"><i class="fa-brands fa-square-x-twitter"></i></a></li>
    <li>
        <a 
        onclick="gtag('event', 'wp_o_shared_prop', {
    'origin': 'property_shared',
    'label': 'Whatsapp compartir propiedad',
    'value': '<?php echo  $nombre; ?>' // Este valor puede ser un número
  });" target="_blank"
        href="<?php echo visualinmu_redsocial_url(["nombre" => "whatsapp", "texto" => $texto], $route); ?>"
           title="Whatsapp" target="_blank"><i class="fa-brands fa-square-whatsapp"></i></a></li>
    <li>
        <a 
        onclick="gtag('event', 'linkedin_open', {
    'origin': 'property_shared',
    'label': 'linkedin_open',
    'value': '<?php echo  $nombre; ?>' // Este valor puede ser un número
  });" target="_blank"
        href="<?php echo visualinmu_redsocial_url(["nombre" => "linkedin", "texto" => $texto], $route); ?>"
           title="Linkedin" target="_blank"><i class="fab fa-linkedin-in"></i></a></li>
    <li>
        <a 
    onclick="gtag('event', 'email_open', {
    'origin': 'email_open',
    'label': 'linkedin_open',
    'value': '<?php echo  $nombre; ?>' // Este valor puede ser un número
  });" target="_blank"
        href="<?php echo visualinmu_redsocial_url(["nombre" => "email", "texto" => $texto . " descripción: ".$descripcion,"asunto" => $nombre], $route);?>"
           title="Correo" target="_blank"><i class="fas fa-envelope"></i></a></li>
</ul>