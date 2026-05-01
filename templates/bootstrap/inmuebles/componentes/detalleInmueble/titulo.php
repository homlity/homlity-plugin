<h1 class="h2"><?php echo $nombre; ?></h1>
<ul class="listadeprecios d-flex flex-column flex-md-row ">
    <li>

        <p><i class="icon-homlity icon-uniE91C"></i> <?php echo $barrio; ?> - <?php echo $ciudad; ?>
        <?php echo $departamento == "SIN_DEPARTAMENTO" ? "" : " - ".$departamento; ?></p>
    </li>
    <li><p><i class="icon-homlity icon-uniE978"></i> Código inmueble <?php echo $codigo; ?></p></li>
</ul>