<?php
if(isset($widget)){
echo $widget["before_widget"];
echo $widget["before_title"];
echo $widget["after_title"];
}
if (isset($form)) : ?>
    <div class="container">
        <form class=" visualinmueble-formulario-widget-inicio" id="<?php echo $form["id"]; ?>" action="<?php echo $form["action"]; ?>" method="<?php echo $form["method"]; ?>" x-data="VISUALINMU_SEARCH_FORM">
            <div class="row justify-content-md-center">
                <div class="col-md-2 visua_inmueble_codigo">
                    <div class="form-floating">
                        <input type="text" class="form-control" name="<?php echo $form["filters"]["codigo"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["codigo"]["old"]) ? $form["filters"]["codigo"]["old"] : ""; ?>">
                        <label><?php echo $form["filters"]["codigo"]["label"]; ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_tipo_gestion">
                    <select class="form-control" id="<?php echo $form["filters"]["tipoGestion"]["id"] ?>" name="<?php echo $form["filters"]["tipoGestion"]["inputName"]; ?>">
                        <option value="" <?php echo empty($form["filters"]["tipoGestion"]["old"]) ? "selected" : "" ?>><?php echo $form["filters"]["tipoGestion"]["label"]; ?></option>
                        <template x-for="tipoGestion in tiposGestion">
                            <option x-model="tipoGestion.codigo" x-text="tipoGestion.nombre"></option>
                        </template>
                        <?php foreach ($form["filters"]["tipoGestion"]["options"] as $tipoGestion) : ?>
                            <option value="<?php echo $tipoGestion->codigo(); ?>" <?php echo $tipoGestion->codigo() == $form["filters"]["tipoGestion"]["old"] ? "selected" : "" ?>><?php echo $tipoGestion->nombre(); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_tipo_inmueble">
                    <select class="form-control" id="<?php echo $form["filters"]["tipoInmueble"]["id"] ?>" name="<?php echo $form["filters"]["tipoInmueble"]["inputName"]; ?>">
                        <option value="" <?php echo empty($form["filters"]["tipoInmueble"]["old"]) ? "selected" : "" ?>><?php echo $form["filters"]["tipoInmueble"]["label"]; ?></option>
                        <template x-for="tipoInmueble in tiposInmueble">
                            <option x-model="tipoInmueble.codigo" x-text="tipoInmueble.nombre"></option>
                        </template>
                        <?php foreach ($form["filters"]["tipoInmueble"]["options"] as $tipoInmueble) : ?>
                            <option value="<?php echo $tipoInmueble->codigo(); ?>" <?php echo $tipoInmueble->codigo() == $form["filters"]["tipoInmueble"]["old"] ? "selected" : "" ?>><?php echo $tipoInmueble->nombre(); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_departamentos">
                    <select class="form-control" name="<?php echo $form["filters"]["departamentos"]["inputName"] ?>" id="<?php echo $form["filters"]["departamentos"]["id"]; ?>" x-model="tipoDepartamentoSelect" x-on:change="cambioDepartamento($event)">
                        <option value="" <?php echo empty($form["filters"]["departamentos"]["old"]) ? "selected" : "" ?>><?php echo $form["filters"]["departamentos"]["label"]; ?></option>
                        <template x-for="departamento in departamentos">
                            <option x-model="departamento.codigo" x-text="departamento.nombre"></option>
                        </template>
                        <?php
                        /**
                         * @var $departamento Codwelt\VisualInmueble\Core\Modelos\Departamento
                         */
                        foreach ($form["filters"]["departamentos"]["options"] as $departamento) : ?>
                            <option value="<?php echo $departamento->codigo(); ?>" <?php echo $departamento->codigo() == $form["filters"]["departamentos"]["old"] ? "selected" : "" ?>><?php echo $departamento->nombre(); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_ciudades">
                    <select class="form-control" name="<?php echo $form["filters"]["ciudades"]["inputName"] ?>" id="<?php echo $form["filters"]["ciudades"]["id"]; ?>" x-model="ciudadSelected" x-on:change="cambioCiudad($event)">
                        <option value="" <?php echo empty($form["filters"]["ciudades"]["old"]) ? "selected" : "" ?>><?php echo $form["filters"]["ciudades"]["label"]; ?></option>
                        <template x-for="ciudad in ciudades">
                            <option :selected="ciudad.codigo == ciudadSelected" :key="ciudad.codigo" :value="ciudad.codigo" x-text="ciudad.nombre"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_barrios">
                    <select class="form-control" name="<?php echo $form["filters"]["barrios"]["inputName"] ?>" id="<?php echo $form["filters"]["barrios"]["id"]; ?>" x-model="barrioSelected" x-on:change="cambioBarrio($event)">
                        <option value="" <?php echo empty($form["filters"]["barrios"]["old"]) ? "selected" : "" ?>><?php echo $form["filters"]["barrios"]["label"]; ?></option>
                        <template x-for="barrio in barrios">
                            <option :selected="barrio.codigo==barrioSelected" :key="barrio.codigo" :value="barrio.codigo" x-text="barrio.nombre"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_advisors">
                    <select class="form-control" name="<?php echo $form["filters"]["advisors"]["inputName"] ?>" id="<?php echo $form["filters"]["advisors"]["id"]; ?>" x-model="advisorSelected" x-on:change="cambioAdvisor($event)">
                        <option value="" <?php echo empty($form["filters"]["advisors"]["old"]) ? "selected" : "" ?>><?php echo $form["filters"]["advisors"]["label"]; ?></option>
                        <template x-for="advisor in advisors">
                            <option :selected="advisor.id==advisorSelected" :key="advisor.id" :value="advisor.id" x-text="advisor.nombre"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_allies">
                    <select class="form-control" name="<?php echo $form["filters"]["allies"]["inputName"] ?>" id="<?php echo $form["filters"]["allies"]["id"]; ?>" x-model="allySelected" x-on:change="cambioAlly($event)">
                        <option value="" <?php echo empty($form["filters"]["allies"]["old"]) ? "selected" : "" ?>><?php echo $form["filters"]["allies"]["label"]; ?></option>
                        <template x-for="ally in allies">
                            <option :selected="ally.id==allySelected" :key="ally.id" :value="ally.id" x-text="ally.nombre"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_precio_minimo">
                    <div class="form-floating">
                        <input type="text" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>" name="<?php echo $form["filters"]["precioMin"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["precioMin"]["old"]) ? $form["filters"]["precioMin"]["old"] : ""; ?>">
                        <label><?php echo $form["filters"]["precioMin"]["label"]; ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_precio_maximo">
                    <div class="form-floating">
                        <input type="text" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>" name="<?php echo $form["filters"]["precioMax"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["precioMax"]["old"]) ? $form["filters"]["precioMax"]["old"] : ""; ?>">
                        <label><?php echo $form["filters"]["precioMax"]["label"]; ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_area_maximo">
                    <div class="form-floating">
                        <input type="number" class="form-control <?php echo (isset($form["filters"]["areaMax"]["class"]) ? $form["filters"]["areaMax"]["class"] : '' ); ?>" name="<?php echo $form["filters"]["areaMax"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["areaMax"]["old"]) ? $form["filters"]["areaMax"]["old"] : ""; ?>">
                        <label><?php echo $form["filters"]["areaMax"]["label"]; ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_area_minima">
                    <div class="form-floating">
                        <input type="number" class="form-control <?php echo (isset($form["filters"]["areaMin"]["class"]) ? $form["filters"]["areaMin"]["class"] : ''); ?>" name="<?php echo $form["filters"]["areaMin"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["areaMin"]["old"]) ? $form["filters"]["areaMin"]["old"] : ""; ?>">
                        <label><?php echo $form["filters"]["areaMin"]["label"]; ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_banos">
                    <div class="form-floating">
                        <input type="number" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>" name="<?php echo $form["filters"]["banos"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["banos"]["old"]) ? $form["filters"]["banos"]["old"] : ""; ?>">
                        <label><?php echo $form["filters"]["banos"]["label"]; ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_alcobas">
                    <div class="form-floating ">
                        <input type="number" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>" name="<?php echo $form["filters"]["alcobas"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["alcobas"]["old"]) ? $form["filters"]["alcobas"]["old"] : ""; ?>">
                        <label><?php echo $form["filters"]["alcobas"]["label"]; ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_garajes">
                    <div class="form-floating">
                        <input type="number" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>" name="<?php echo $form["filters"]["garajes"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["garajes"]["old"]) ? $form["filters"]["garajes"]["old"] : ""; ?>">
                        <label><?php echo $form["filters"]["garajes"]["label"]; ?></label>
                    </div>
                </div>
                <div class="col-md-2 vi-boton-buscar">
                    <button type="submit" class="btn btn-primary btn-bus">Buscar</button>
                </div>
                <div class="col-md-2 vi-boton-limpiar">
                    <a href="<?php echo $form["clear"]; ?>" class="btn btn-primary btn-lim">Limpiar</a>
                </div>
            </div>
        </form>
    </div>
<?php endif;
if(isset($widget)){
echo $widget["after_widget"];
}
?>