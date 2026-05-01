<ul class="listadeprecios d-flex flex-column flex-md-row">
    <?php
    $tipoGestion = null;
    if ($gestion->esArriendoVenta()) {
        $tipoGestion = "Arriendo/Venta";
        echo "<li><p><i class='icon-homlity icon-uniE9C0'></i> Arriendo <span><a href='" . visualinmu_route_search(["precioMax" => $valores["canon"]]) . "' target='_blank'>$" . $valores["canon"] . " <sub>COP</sub></a></span></p></li>";
        if ($precioConAdmin) {
            echo "Valor incluye administración";
        } elseif (($valores["administracion"] > 0)) {
            echo "<li><p><i class='icon-homlity icon-uniE9C0'></i> Administración <span><a href='" . visualinmu_route_search(["precioMax" => $valores["administracion"], "type_bussiness" => $gestion->nombre()]) . "' target='_blank'>$" . (isset($valores["administracion_format"]) ? $valores["administracion_format"] : $valores["administracion"]) . " <sub>COP</sub></a></span></p></li>";
        }
        echo "<li><p><i class='icon-homlity icon-uniE9C0'></i> Venta <span><a href='" . visualinmu_route_search(["precioMax" => $valores["venta"], "type_bussiness" => $gestion->nombre()]) . "' target='_blank'>$" . $valores["venta"] . " <sub>COP</sub></a></span></p></li>";
    } elseif ($gestion->esAriendo()) {
        $tipoGestion = "Arriendo";
        echo "<li><p><i class='icon-homlity icon-uniE9C0'></i> Arriendo <span><a href='" . visualinmu_route_search(["precioMax" => $valores["canon"], "type_bussiness" => $gestion->nombre()]) . "'  target='_blank'>$" . $valores["canon"] . " <sub>COP</sub></a></span></p></li>";
        if ($precioConAdmin) {
            echo "Valor incluye administración";
        } elseif (($valores["administracion"] > 0)) {
            echo "<li><p><i class='icon-homlity icon-uniE9C0'></i> Administración <span><a href='" . visualinmu_route_search(["precioMax" => $valores["administracion"], "type_bussiness" => $gestion->nombre()]) . "' target='_blank'>$" . (isset($valores["administracion_format"]) ? $valores["administracion_format"] : $valores["administracion"]) . " <sub>COP</sub></a></span></p></li>";
        }
    } else {
        $tipoGestion = "Venta";
        echo "<li><p><i class='icon-homlity icon-uniE9C0'></i> Venta <span><a href='" . visualinmu_route_search(["precioMax" => $valores["venta"], "type_bussiness" => $gestion->nombre()]) . "'  target='_blank'>$" . $valores["venta"] . " <sub>COP</sub></a></span></p></li>";
        if ($precioConAdmin) {
            echo "Valor incluye administración";
        } elseif ($valores["administracion"] > 0) {
            echo "<li><p><i class='icon-homlity icon-uniE9C0'></i> Administración <span><a href='" . visualinmu_route_search(["precioMax" => $valores["administracion"], "type_bussiness" => $gestion->nombre()]) . "' target='_blank'>$" . $valores["administracion_format"] . " <sub>COP</sub></a></span></p></li>";

        }
    }
    ?>
</ul>