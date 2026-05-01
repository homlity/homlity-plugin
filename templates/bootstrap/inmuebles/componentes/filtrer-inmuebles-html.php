<form class="row visualinmueble-formulario-widget-busqueda" id="<?php echo $form["id"]; ?>"
          action="<?php echo $form["action"]; ?>" method="<?php echo $form["method"]; ?>"
          x-data="VISUALINMU_SEARCH_FORM">
        <?php if (isset($_GET["tags"])) { ?>
            <input class="form-control" type="hidden" name="tags" value="<?php echo $_GET["tags"]; ?>"/>
        <?php } ?>
        <div class="col-md-12 visua_inmueble_codigo mt-3">
            <label for="floatingInputGrid"><?php echo $form["filters"]["codigo"]["label"]; ?></label>
            <input type="text" class="form-control" id="floatingInputGrid"
                   name="<?php echo $form["filters"]["codigo"]["inputName"]; ?>"
                   value="<?php echo !empty($form["filters"]["codigo"]["old"]) ? $form["filters"]["codigo"]["old"] : ""; ?>">
        </div>
        <div class="col-md-12 visua_inmueble_tipo_inmueble mt-3">
            <label for="floatingInputGrid"><?php echo $form["filters"]["tipoInmueble"]["label"]; ?></label>
            <select class="form-control" id="<?php echo $form["filters"]["tipoInmueble"]["id"] ?>"
                    name="<?php echo $form["filters"]["tipoInmueble"]["inputName"]; ?>">
                <option value="" <?php echo empty($form["filters"]["tipoInmueble"]["old"]) ? "selected" : "" ?>
                ></option>
                <template x-for="tipoInmueble in tiposInmueble">
                    <option x-model="tipoInmueble.codigo" x-text="tipoInmueble.nombre"></option>
                </template>
                <?php foreach ($form["filters"]["tipoInmueble"]["options"] as $tipoInmueble): ?>
                    <option value="<?php echo $tipoInmueble->codigo(); ?>"
                        <?php echo $tipoInmueble->codigo() == $form["filters"]["tipoInmueble"]["old"] ? "selected" : "" ?>
                    ><?php echo $tipoInmueble->nombre(); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-12 visua_inmueble_tipo_gestion mt-3">
            <label for="floatingInputGrid"><?php echo $form["filters"]["tipoGestion"]["label"]; ?></label>
            <select class="form-control" id="<?php echo $form["filters"]["tipoGestion"]["id"] ?>"
                    name="<?php echo $form["filters"]["tipoGestion"]["inputName"]; ?>">
                <option value="" <?php echo empty($form["filters"]["tipoGestion"]["old"]) ? "selected" : "" ?>
                ></option>
                <template x-for="tipoGestion in tiposGestion">
                    <option x-model="tipoGestion.codigo" x-text="tipoGestion.nombre"></option>
                </template>
                <?php foreach ($form["filters"]["tipoGestion"]["options"] as $tipoGestion): ?>
                    <option value="<?php echo $tipoGestion->codigo(); ?>"
                        <?php echo $tipoGestion->codigo() == $form["filters"]["tipoGestion"]["old"] ? "selected" : "" ?>
                    ><?php echo $tipoGestion->nombre(); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <!--        <div class="col-md-12 visua_inmueble_departamentos">-->
        <!--            <label for="floatingInputGrid">-->
        <?php //echo $form["filters"]["departamentos"]["label"]; ?><!--</label>-->
        <!--            <select class="form-control" name="-->
        <?php //echo $form["filters"]["departamentos"]["inputName"] ?><!--"-->
        <!--                    id="--><?php //echo $form["filters"]["departamentos"]["id"]; ?><!--"-->
        <!--                    x-model="tipoDepartamentoSelect"-->
        <!--                    x-on:change="cambioDepartamento($event)">-->
        <!--                <option value="" --><?php //echo empty($form["filters"]["departamentos"]["old"]) ? "selected" : "" ?>
        <!--                ></option>-->
        <!--                <template x-for="departamento in departamentos">-->
        <!--                    <option x-model="departamento.codigo" x-text="departamento.nombre"></option>-->
        <!--                </template>-->
        <!--                --><?php
        //                /**
        //                 * @var $departamento \Codwelt\VisualInmueble\Core\Modelos\Departamento
        //                 */
        //                foreach ($form["filters"]["departamentos"]["options"] as $departamento): ?>
        <!--                    <option value="--><?php //echo $departamento->codigo(); ?><!--"-->
        <!--                        --><?php //echo $departamento->codigo() == $form["filters"]["departamentos"]["old"] ? "selected" : "" ?>
        <!--                    >--><?php //echo $departamento->nombre(); ?><!--</option>-->
        <!--                --><?php //endforeach; ?>
        <!--            </select>-->
        <!--        </div>-->
        <div class="col-md-12 visua_inmueble_ciudades mt-3">
            <label for="floatingInputGrid"><?php echo $form["filters"]["ciudades"]["label"]; ?></label>
            <select class="form-control" name="<?php echo $form["filters"]["ciudades"]["inputName"] ?>"
                    id="<?php echo $form["filters"]["ciudades"]["id"]; ?>" x-model="ciudadSelected"
                    x-on:change="cambioCiudad($event)">
                <option value="" <?php echo empty($form["filters"]["ciudades"]["old"]) ? "selected" : "" ?>
                ></option>
                <template x-for="ciudad in ciudades">
                    <option :selected="ciudad.codigo == ciudadSelected" :key="ciudad.codigo" :value="ciudad.codigo"
                            x-text="ciudad.nombre"></option>
                </template>
            </select>
        </div>
        <div class="col-md-12 visua_inmueble_barrios mt-3">
            <label for="floatingInputGrid"><?php echo $form["filters"]["barrios"]["label"]; ?></label>
            <select class="form-control" name="<?php echo $form["filters"]["barrios"]["inputName"] ?>"
                    id="<?php echo $form["filters"]["barrios"]["id"]; ?>" x-model="barrioSelected"
                    x-on:change="cambioBarrio($event)">
                <option value="" <?php echo empty($form["filters"]["barrios"]["old"]) ? "selected" : "" ?>
                ></option>
                <template x-for="barrio in barrios">
                    <option :selected="barrio.codigo==barrioSelected" :key="barrio.codigo" :value="barrio.codigo"
                            x-text="barrio.nombre"></option>
                </template>
            </select>
        </div>
        <div class="col-md-12  mt-3">
            <div class="row">
                <div class="col-md-12">
                    <label for="">Precio</label>
                </div>
                <div class="col-md-6 visua_inmueble_precio_minimo">
                    <input type="text" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>"
                           id="floatingInputGrid" placeholder="<?php echo $form["filters"]["precioMin"]["label"]; ?>"
                           name="<?php echo $form["filters"]["precioMin"]["inputName"]; ?>"
                           value="<?php echo !empty($form["filters"]["precioMin"]["old"]) ? $form["filters"]["precioMin"]["old"] : ""; ?>">
                </div>
                <div class="col-md-6 visua_inmueble_precio_maximo">
                    <input type="text" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>"
                           id="floatingInputGrid" placeholder="<?php echo $form["filters"]["precioMax"]["label"]; ?>"
                           name="<?php echo $form["filters"]["precioMax"]["inputName"]; ?>"
                           value="<?php echo !empty($form["filters"]["precioMax"]["old"]) ? $form["filters"]["precioMax"]["old"] : ""; ?>">
                </div>

            </div>
        </div>
        <div class="col-md-12  mt-3">
            <div class="row">
                <div class="col-md-12">
                    <label for="">Area</label>
                </div>
                <div class="col-md-6 visua_inmueble_area_maximo">
                    <input type="number" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>"
                           id="floatingInputGrid" placeholder="<?php echo $form["filters"]["areaMax"]["label"]; ?>"
                           name="<?php echo $form["filters"]["areaMax"]["inputName"]; ?>"
                           value="<?php echo !empty($form["filters"]["areaMax"]["old"]) ? $form["filters"]["areaMax"]["old"] : ""; ?>">
                </div>
                <div class="col-md-6  visua_inmueble_area_minima">
                    <input type="number" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>"
                           id="floatingInputGrid" placeholder="<?php echo $form["filters"]["areaMin"]["label"]; ?>"
                           name="<?php echo $form["filters"]["areaMin"]["inputName"]; ?>"
                           value="<?php echo !empty($form["filters"]["areaMin"]["old"]) ? $form["filters"]["areaMin"]["old"] : ""; ?>">
                </div>
            </div>
        </div>
        <div class="col-md-12 visua_inmueble_banos mt-3">
            <div class="row">
                <div class="col-md-12">
                    <label for="floatingInputGrid"><?php echo $form["filters"]["baños"]["label"]; ?></label>
                </div>
                <div class="col-md-12">
                    <?php for ($bano = 1; $bano <= 5; $bano++) { ?>
                        <input type="radio" class="btn-check"
                               name="<?php echo $form["filters"]["baños"]["inputName"]; ?>"
                               id="success-outlined-baños-<?php echo $bano; ?>"
                               value="<?php echo $bano; ?>"
                            <?php echo !empty($form["filters"]["baños"]["old"]) && $form["filters"]["baños"]["old"] == $bano ? "checked" : ""; ?>>
                        <label class="btn btn-outline-success"
                               for="success-outlined-baños-<?php echo $bano; ?>"><?php echo $bano; ?></label>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-md-12 visua_inmueble_alcobas mt-3">
            <div class="row">
                <div class="col-md-12">
                    <label for="floatingInputGrid"><?php echo $form["filters"]["alcobas"]["label"]; ?></label>
                </div>
                <div class="col-md-12">
                    <?php for ($alcoba = 1; $alcoba <= 7; $alcoba++) { ?>
                        <input type="radio" class="btn-check"
                               name="<?php echo $form["filters"]["alcobas"]["inputName"]; ?>"
                               id="success-outlined-alcobas-<?php echo $alcoba; ?>"
                               value="<?php echo $alcoba; ?>"
                            <?php echo !empty($form["filters"]["alcobas"]["old"]) && $form["filters"]["alcobas"]["old"] == $alcoba ? "checked" : ""; ?>>
                        <label class="btn btn-outline-success"
                               for="success-outlined-alcobas-<?php echo $alcoba; ?>"><?php echo $alcoba; ?></label>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-md-12 visua_inmueble_garajes mt-3">
            <div class="row">
                <div class="col-md-12"><label
                            for="floatingInputGrid"><?php echo $form["filters"]["garajes"]["label"]; ?></label></div>
                <div class="col-md-12">
                    <?php for ($garaje = 1; $garaje <= 5; $garaje++) { ?>
                        <input type="radio" class="btn-check"
                               name="<?php echo $form["filters"]["garajes"]["inputName"]; ?>"
                               id="success-outlined-garaje-<?php echo $garaje; ?>"
                               value="<?php echo $garaje; ?>" <?php echo !empty($form["filters"]["garajes"]["old"]) && $form["filters"]["garajes"]["old"] == $garaje ? "checked" : ""; ?>>
                        <label class="btn btn-outline-success"
                               for="success-outlined-garaje-<?php echo $garaje; ?>"><?php echo $garaje; ?></label>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-md-12 boton-buscar mt-3">
            <button type="submit" class="btn btn-primary btn-bus btn-block">Buscar</button>
            <br> <br>
            <a href="#!" data-bs-toggle="modal" data-bs-target="#exampleModal">¿No encuentra el inmueble que
                desea?</a><br>
            <a href="#!" data-bs-toggle="modal" data-bs-target="#exampleModalconsignacion">¿Desea consignar su
                inmueble?</a>
        </div>
    </form>