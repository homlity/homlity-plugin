<template>
  <div class="simulador-venta">

    <!-- Valor del inmueble -->
    <div class="sim-section">
      <div class="row sim-controls">
        <div class="col-12">
          <div :class="['form-group', { 'has-error': errors.valorInmueble }]">
            <label><strong>Valor del inmueble:</strong> ${{ fmt(valorInmueble) }}</label>
            <input
              type="number"
              v-model.number="valorInmueble"
              min="0"
              placeholder="0"
            />
            <span v-if="errors.valorInmueble" class="field-error">{{ errors.valorInmueble }}</span>
          </div>
        </div>
      </div>
    </div>

    <hr />

    <!-- Tipo de propiedad — comisión urbana / rural -->
    <div v-if="mostrarComisionPropiedad" class="sim-section">
      <div class="row sim-controls">
        <div class="col-12">
          <div class="form-group">
            <label><strong>¿El inmueble es urbano o rural?</strong>
              <span class="campo-ayuda">Seleccione el tipo para incluir la comisión correspondiente en la liquidación. El porcentaje es configurable desde la administración.</span>
            </label>
            <div class="radio-group">
              <label>
                <input type="radio" v-model="tipoPropiedad" value="no_aplica" />
                Sin comisión de propiedad
              </label>
              <label v-if="nc.comisionPropiedadUrbanaHabilitada">
                <input type="radio" v-model="tipoPropiedad" value="urbana" />
                {{ nc.comisionPropiedadUrbanaLabel }} — {{ nc.comisionPropiedadUrbanaPorcentaje }}%
              </label>
              <label v-if="nc.comisionPropiedadRuralHabilitada">
                <input type="radio" v-model="tipoPropiedad" value="rural" />
                {{ nc.comisionPropiedadRuralLabel }} — {{ nc.comisionPropiedadRuralPorcentaje }}%
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <hr v-if="mostrarComisionPropiedad" />

    <!-- ===================== VENDEDOR ===================== -->
    <div class="sim-section">
      <h3 class="sim-section-title">Vendedor</h3>
      <div class="row sim-controls">

        <!-- Persona jurídica -->
        <div class="col-4">
          <div class="form-group">
            <label><strong>¿El vendedor es persona jurídica?</strong></label>
            <div class="radio-group">
              <label><input type="radio" v-model.number="vendedorJuridico" :value="1" /> SÍ</label>
              <label><input type="radio" v-model.number="vendedorJuridico" :value="0" /> NO</label>
            </div>
          </div>
        </div>

        <!-- Cancelación hipoteca vendedor -->
        <div class="col-4">
          <div class="form-group">
            <label><strong>¿Cancelación de hipoteca?</strong></label>
            <div class="radio-group">
              <label><input type="radio" v-model.number="cancelacionHipotecaSinCuantia" :value="1" /> SÍ</label>
              <label><input type="radio" v-model.number="cancelacionHipotecaSinCuantia" :value="0" /> NO</label>
            </div>
            <div v-if="cancelacionHipotecaSinCuantia === 1" class="sub-input">
              <label>Valor hipoteca: ${{ fmt(hipotecavendedor) }}</label>
              <input
                type="number"
                v-model.number="hipotecavendedor"
                min="0"
                placeholder="0"
                :class="{ 'input-error': errors.hipotecavendedor }"
              />
              <span v-if="errors.hipotecavendedor" class="field-error">{{ errors.hipotecavendedor }}</span>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Limitaciones y gravámenes del inmueble vendido -->
    <div class="sim-section sim-section--protecciones">
      <h3 class="sim-section-title sim-section-title--collapse" @click="ui.expandirLimitacionesVendedor = !ui.expandirLimitacionesVendedor">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline v-if="ui.expandirLimitacionesVendedor" points="18 15 12 9 6 15"/>
          <polyline v-else points="6 9 12 15 18 9"/>
        </svg>
        Limitaciones y gravámenes del inmueble vendido
      </h3>

      <div v-show="ui.expandirLimitacionesVendedor" class="sim-section-body">

        <!-- 3.1 Afectación a vivienda familiar existente -->
        <div class="proteccion-bloque">
          <div class="form-group">
            <label><strong>¿El inmueble tiene afectación a vivienda familiar inscrita?</strong>
              <span class="campo-ayuda">La afectación a vivienda familiar es una restricción legal que limita la libre disposición del inmueble destinado a vivienda del hogar.</span>
            </label>
            <div class="radio-group">
              <label><input type="radio" v-model="pf.vendedor.afectacionViviendaFamiliar.estado" value="no" /> No</label>
              <label><input type="radio" v-model="pf.vendedor.afectacionViviendaFamiliar.estado" value="si" /> Sí</label>
              <label><input type="radio" v-model="pf.vendedor.afectacionViviendaFamiliar.estado" value="no_sabe" /> No estoy seguro</label>
            </div>
          </div>

          <!-- Sub-preguntas afectación vendedor -->
          <div v-if="pf.vendedor.afectacionViviendaFamiliar.estado === 'si'" class="sub-bloque">
            <div class="form-group">
              <label>¿La afectación debe levantarse para realizar esta venta?</label>
              <div class="radio-group">
                <label><input type="checkbox" v-model="pf.vendedor.afectacionViviendaFamiliar.requiereLevantamiento" /> Sí, requiere levantamiento</label>
              </div>
            </div>

            <div v-if="pf.vendedor.afectacionViviendaFamiliar.requiereLevantamiento">
              <div class="form-group">
                <label>¿El cónyuge o compañero permanente firmará la escritura?</label>
                <div class="radio-group">
                  <label><input type="radio" v-model="pf.vendedor.afectacionViviendaFamiliar.firmaConyugeOCompanero" value="si" /> Sí firmará</label>
                  <label><input type="radio" v-model="pf.vendedor.afectacionViviendaFamiliar.firmaConyugeOCompanero" value="no" /> No firmará</label>
                  <label><input type="radio" v-model="pf.vendedor.afectacionViviendaFamiliar.firmaConyugeOCompanero" value="no_aplica" /> No aplica</label>
                  <label><input type="radio" v-model="pf.vendedor.afectacionViviendaFamiliar.firmaConyugeOCompanero" value="no_sabe" /> No sé</label>
                </div>
              </div>

              <div class="form-group">
                <label>¿En qué modalidad se realiza el levantamiento?</label>
                <div class="radio-group">
                  <label><input type="radio" v-model="pf.vendedor.afectacionViviendaFamiliar.modalidadLevantamiento" value="misma_escritura_compraventa" /> En la misma escritura de compraventa</label>
                  <label><input type="radio" v-model="pf.vendedor.afectacionViviendaFamiliar.modalidadLevantamiento" value="escritura_independiente" /> En escritura independiente</label>
                  <label><input type="radio" v-model="pf.vendedor.afectacionViviendaFamiliar.modalidadLevantamiento" value="requiere_validacion" /> Requiere validación notarial</label>
                </div>
              </div>
            </div>

            <!-- Alerta firma -->
            <div v-if="alertaFirmaAfectacionVendedor" class="alerta alerta--warning">
              <strong>Firma de cónyuge o compañero permanente:</strong> La afectación a vivienda familiar puede exigir el consentimiento y la firma del cónyuge o compañero permanente para vender o gravar el inmueble. Verifique este requisito con la notaría antes de continuar.
            </div>
          </div>

          <!-- Alerta cuando no sabe -->
          <div v-if="pf.vendedor.afectacionViviendaFamiliar.estado === 'no_sabe'" class="alerta alerta--info">
            Información pendiente de validación. El valor mostrado no debe usarse como liquidación definitiva hasta verificar el certificado de tradición, la escritura antecedente y los requisitos de la notaría.
          </div>
        </div>

        <hr class="sep-bloque" />

        <!-- 3.2 Patrimonio de familia existente -->
        <div class="proteccion-bloque">
          <div class="form-group">
            <label><strong>¿El inmueble tiene patrimonio de familia inembargable inscrito?</strong>
              <span class="campo-ayuda">El patrimonio de familia inembargable protege el inmueble de embargos. Puede ser voluntario, asociado a compra VIS o a subsidio de vivienda.</span>
            </label>
            <div class="radio-group">
              <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.tipo" value="no" /> No</label>
              <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.tipo" value="voluntario" /> Sí, patrimonio de familia voluntario</label>
              <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.tipo" value="vis" /> Sí, asociado a compra VIS</label>
              <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.tipo" value="subsidio" /> Sí, asociado a subsidio de vivienda</label>
              <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.tipo" value="menores" /> Sí, con beneficiarios menores de edad</label>
              <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.tipo" value="no_sabe" /> No estoy seguro</label>
            </div>
          </div>

          <!-- Sub-preguntas patrimonio vendedor -->
          <div v-if="pf.vendedor.patrimonioFamilia.tipo !== 'no'" class="sub-bloque">
            <div class="form-group">
              <label><input type="checkbox" v-model="pf.vendedor.patrimonioFamilia.requiereCancelacion" /> ¿Se requiere cancelar o levantar el patrimonio de familia para realizar la venta?</label>
            </div>

            <div v-if="pf.vendedor.patrimonioFamilia.requiereCancelacion">
              <div class="form-group">
                <label>¿Existen hijos o beneficiarios menores de edad?</label>
                <div class="radio-group">
                  <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.existenBeneficiariosMenores" value="si" /> Sí</label>
                  <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.existenBeneficiariosMenores" value="no" /> No</label>
                  <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.existenBeneficiariosMenores" value="no_sabe" /> No sé</label>
                </div>
              </div>

              <div class="form-group">
                <label>¿Existe subsidio vigente o restricción de transferencia?</label>
                <div class="radio-group">
                  <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.existeSubsidioORestriccion" value="si" /> Sí</label>
                  <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.existeSubsidioORestriccion" value="no" /> No</label>
                  <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.existeSubsidioORestriccion" value="no_sabe" /> No sé</label>
                </div>
              </div>

              <div class="form-group">
                <label>¿Se cuenta con autorización judicial o trámite jurídico aplicable?</label>
                <div class="radio-group">
                  <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.autorizacionJudicial" value="si" /> Sí</label>
                  <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.autorizacionJudicial" value="no" /> No</label>
                  <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.autorizacionJudicial" value="no_aplica" /> No aplica</label>
                  <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.autorizacionJudicial" value="no_sabe" /> No sé</label>
                </div>
              </div>

              <div class="form-group">
                <label>¿En qué modalidad se realiza el levantamiento?</label>
                <div class="radio-group">
                  <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.modalidadLevantamiento" value="escritura_publica" /> Escritura pública</label>
                  <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.modalidadLevantamiento" value="tramite_judicial" /> Trámite judicial</label>
                  <label><input type="radio" v-model="pf.vendedor.patrimonioFamilia.modalidadLevantamiento" value="requiere_validacion" /> Requiere validación</label>
                </div>
              </div>
            </div>

            <!-- Alerta menores -->
            <div v-if="alertaMenoresPatrimonioVendedor" class="alerta alerta--danger">
              <strong>Patrimonio de familia con menores beneficiarios:</strong> El inmueble registra patrimonio de familia con posibles beneficiarios menores de edad. La venta o cancelación puede requerir validación jurídica y, según el caso, autorización judicial. Este simulador no incluye costos de procesos judiciales ni honorarios profesionales.
            </div>

            <!-- Alerta VIS / subsidio -->
            <div v-if="alertaVISPatrimonioVendedor" class="alerta alerta--warning">
              <strong>VIS o subsidio de vivienda:</strong> La operación puede estar sujeta a condiciones especiales de patrimonio de familia, subsidio o restricción de transferencia. Confirme el procedimiento y los costos con la notaría, entidad otorgante o asesor jurídico.
            </div>

            <!-- Alerta cuando no sabe -->
            <div v-if="pf.vendedor.patrimonioFamilia.tipo === 'no_sabe'" class="alerta alerta--info">
              Información pendiente de validación. El valor mostrado no debe usarse como liquidación definitiva hasta verificar el certificado de tradición, la escritura antecedente y los requisitos de la notaría.
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Comisión (opcional) -->
    <div v-if="nc.comisionHabilitada" class="sim-section">
      <h3 class="sim-section-title">{{ nc.labelComision || 'Comisión inmobiliaria / asesor' }}</h3>
      <div class="row sim-controls">
        <div class="col-12">
          <div class="form-group form-group--inline">
            <label class="toggle-label">
              <input type="checkbox" v-model="incluirComision" class="toggle-input" />
              <span class="toggle-text">
                Incluir comisión <strong>{{ nc.porcentajeComision }}%</strong>
                <span v-if="nc.comisionMinima > 0"> (mínimo ${{ fmtShort(nc.comisionMinima) }})</span>
                — actualmente <strong>${{ fmt(comisionCalculada) }}</strong>
              </span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================== COMPRADOR ===================== -->
    <div class="sim-section">
      <h3 class="sim-section-title">Comprador</h3>
      <div class="row sim-controls">

        <!-- Constitución hipoteca comprador -->
        <div class="col-6">
          <div class="form-group">
            <label><strong>¿Constitución de hipoteca?</strong></label>
            <div class="radio-group">
              <label><input type="radio" v-model.number="constitucionHipotecaComprador" :value="1" /> SÍ</label>
              <label><input type="radio" v-model.number="constitucionHipotecaComprador" :value="0" /> NO</label>
            </div>
            <div v-if="constitucionHipotecaComprador === 1" class="sub-input">
              <label>Valor hipoteca: ${{ fmt(hipotecacomprador) }}</label>
              <input
                type="number"
                v-model.number="hipotecacomprador"
                min="0"
                placeholder="0"
                :class="{ 'input-error': errors.hipotecacomprador }"
              />
              <span v-if="errors.hipotecacomprador" class="field-error">{{ errors.hipotecacomprador }}</span>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Protecciones familiares del comprador -->
    <div class="sim-section sim-section--protecciones">
      <h3 class="sim-section-title sim-section-title--collapse" @click="ui.expandirProteccionesComprador = !ui.expandirProteccionesComprador">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline v-if="ui.expandirProteccionesComprador" points="18 15 12 9 6 15"/>
          <polyline v-else points="6 9 12 15 18 9"/>
        </svg>
        Protecciones familiares que solicita el comprador
      </h3>

      <div v-show="ui.expandirProteccionesComprador" class="sim-section-body">

        <!-- 4.1 Afectación a vivienda familiar del comprador -->
        <div class="proteccion-bloque">
          <div class="form-group">
            <label><strong>¿El comprador adquiere el inmueble para vivienda familiar?</strong></label>
            <div class="radio-group">
              <label><input type="radio" v-model="pf.comprador.compraParaViviendaFamiliar" value="si" /> Sí</label>
              <label><input type="radio" v-model="pf.comprador.compraParaViviendaFamiliar" value="no" /> No</label>
              <label><input type="radio" v-model="pf.comprador.compraParaViviendaFamiliar" value="no_sabe" /> No estoy seguro</label>
            </div>
          </div>

          <div v-if="pf.comprador.compraParaViviendaFamiliar === 'si'" class="sub-bloque">
            <div class="form-group">
              <label>¿Situación familiar del comprador?</label>
              <div class="radio-group">
                <label><input type="radio" v-model="pf.comprador.situacionFamiliar" value="casado" /> Casado/a</label>
                <label><input type="radio" v-model="pf.comprador.situacionFamiliar" value="union_marital" /> Unión marital de hecho</label>
                <label><input type="radio" v-model="pf.comprador.situacionFamiliar" value="sin_pareja" /> Sin pareja</label>
                <label><input type="radio" v-model="pf.comprador.situacionFamiliar" value="no_sabe" /> No sé</label>
              </div>
            </div>

            <div class="form-group">
              <label>
                <input type="checkbox" v-model="pf.comprador.afectacionViviendaFamiliar.constituir" />
                ¿Desea incluir la afectación a vivienda familiar en la escritura?
              </label>
            </div>

            <div v-if="pf.comprador.situacionFamiliar === 'no_sabe' && pf.comprador.afectacionViviendaFamiliar.constituir" class="alerta alerta--info">
              Información pendiente de validación. El valor mostrado no debe usarse como liquidación definitiva hasta verificar el certificado de tradición, la escritura antecedente y los requisitos de la notaría.
            </div>
          </div>
        </div>

        <hr class="sep-bloque" />

        <!-- 4.2 Patrimonio de familia del comprador -->
        <div class="proteccion-bloque">
          <div class="form-group">
            <label><strong>¿El comprador desea constituir patrimonio de familia?</strong></label>
            <div class="radio-group">
              <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.tipo" value="no" /> No</label>
              <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.tipo" value="voluntario" /> Sí, patrimonio de familia voluntario</label>
              <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.tipo" value="vis" /> Sí, compra VIS</label>
              <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.tipo" value="no_sabe" /> No estoy seguro</label>
            </div>
          </div>

          <!-- Voluntario -->
          <div v-if="pf.comprador.patrimonioFamilia.tipo === 'voluntario'" class="sub-bloque">
            <div class="form-group">
              <label>¿Existen beneficiarios menores de edad?</label>
              <div class="radio-group">
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.beneficiariosMenores" value="si" /> Sí</label>
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.beneficiariosMenores" value="no" /> No</label>
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.beneficiariosMenores" value="no_sabe" /> No sé</label>
              </div>
            </div>
            <div class="form-group">
              <label>¿El inmueble cumple las condiciones para patrimonio de familia voluntario?</label>
              <div class="radio-group">
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.cumpleCondicionesVoluntario" value="si" /> Sí</label>
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.cumpleCondicionesVoluntario" value="no" /> No</label>
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.cumpleCondicionesVoluntario" value="no_sabe" /> No sé</label>
              </div>
            </div>
            <div class="form-group">
              <label>¿El inmueble tiene hipoteca vigente?</label>
              <div class="radio-group">
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.hipotecaAdquisicion" value="si" /> Sí</label>
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.hipotecaAdquisicion" value="no" /> No</label>
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.hipotecaAdquisicion" value="no_sabe" /> No sé</label>
              </div>
            </div>
            <div v-if="alertaMenoresPatrimonioComprador" class="alerta alerta--danger">
              <strong>Patrimonio de familia con menores beneficiarios:</strong> El inmueble registra patrimonio de familia con posibles beneficiarios menores de edad. La constitución puede requerir validación jurídica. Este simulador no incluye costos de procesos judiciales ni honorarios profesionales.
            </div>
          </div>

          <!-- VIS -->
          <div v-if="pf.comprador.patrimonioFamilia.tipo === 'vis'" class="sub-bloque">
            <div class="form-group">
              <label>¿La compra se encuentra confirmada como VIS?</label>
              <div class="radio-group">
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.compraVISConfirmada" value="si" /> Sí</label>
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.compraVISConfirmada" value="no" /> No</label>
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.compraVISConfirmada" value="no_sabe" /> No sé</label>
              </div>
            </div>
            <div class="form-group">
              <label>¿Existe subsidio de vivienda asociado?</label>
              <div class="radio-group">
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.subsidioAsociado" value="si" /> Sí</label>
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.subsidioAsociado" value="no" /> No</label>
                <label><input type="radio" v-model="pf.comprador.patrimonioFamilia.subsidioAsociado" value="no_sabe" /> No sé</label>
              </div>
            </div>
            <div class="alerta alerta--warning">
              <strong>VIS o subsidio de vivienda:</strong> La operación puede estar sujeta a condiciones especiales de patrimonio de familia, subsidio o restricción de transferencia. Confirme el procedimiento y los costos con la notaría, entidad otorgante o asesor jurídico.
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Distribución avanzada -->
    <div class="dist-avanzada">
      <button type="button" class="dist-toggle" @click="mostrarDistribucionAvanzada = !mostrarDistribucionAvanzada">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline v-if="mostrarDistribucionAvanzada" points="18 15 12 9 6 15"/>
          <polyline v-else points="6 9 12 15 18 9"/>
        </svg>
        Distribución avanzada de gastos
      </button>
      <div v-if="mostrarDistribucionAvanzada" class="dist-body">
        <p class="dist-desc">Ajusta cómo se distribuye cada gasto entre vendedor y comprador. El porcentaje complementario se actualiza automáticamente.</p>
        <div class="table-wrapper">
          <table class="dist-table">
            <thead>
              <tr><th>Concepto</th><th>Vendedor %</th><th>Comprador %</th></tr>
            </thead>
            <tbody>
              <tr v-for="item in distribucionesEditables" :key="item.key">
                <td>{{ item.label }}</td>
                <td>
                  <input
                    type="number" min="0" max="100"
                    :value="distribuciones[item.key]?.vendedor ?? item.defaultVendedor"
                    @change="actualizarDistribucion(item.key, 'vendedor', $event.target.value)"
                  />
                </td>
                <td>
                  <input
                    type="number" min="0" max="100"
                    :value="distribuciones[item.key]?.comprador ?? item.defaultComprador"
                    @change="actualizarDistribucion(item.key, 'comprador', $event.target.value)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===================== RESULTADOS ===================== -->
    <div class="sim-results">
      <div class="card">
        <div class="table-wrapper results-table-wrapper">
          <table ref="tabla" class="results-table">
            <thead>
              <tr>
                <th>Concepto</th>
                <th>Vendedor</th>
                <th>Comprador</th>
                <th>Total</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="linea in lineasCalculo" :key="linea.codigo">
                <tr :class="['results-row', `results-row--${linea.categoria}`, { 'results-row--validacion': linea.estado !== 'calculado' && linea.estado !== 'estimado' }]">
                  <td class="results-cell-label">
                    {{ linea.concepto }}
                    <span v-if="linea.tarifaTexto" class="tarifa-texto"> — {{ linea.tarifaTexto }}</span>
                  </td>
                  <td class="results-cell-value">{{ linea.estado === 'requiere_validacion_juridica' ? '–' : '$' + fmt(linea.valorVendedor + linea.ivaVendedor) }}</td>
                  <td class="results-cell-value">{{ linea.estado === 'requiere_validacion_juridica' ? '–' : '$' + fmt(linea.valorComprador + linea.ivaComprador) }}</td>
                  <td class="results-cell-value">{{ linea.estado === 'requiere_validacion_juridica' ? 'Ver alerta' : '$' + fmt(linea.valorTotal) }}</td>
                  <td>
                    <span :class="['badge', badgeClass(linea.estado)]">{{ badgeLabel(linea.estado) }}</span>
                  </td>
                </tr>
                <tr v-if="linea.alerta" class="results-row--alerta">
                  <td colspan="5">
                    <div class="alerta alerta--warning alerta--sm">{{ linea.alerta }}</div>
                  </td>
                </tr>
              </template>

              <!-- Subtotales -->
              <tr class="row-total">
                <td><strong>Subtotal gastos notariales y retenciones</strong></td>
                <td>${{ fmt(totales.notariales.vendedor) }}</td>
                <td>${{ fmt(totales.notariales.comprador) }}</td>
                <td>${{ fmt(totales.notariales.total) }}</td>
                <td></td>
              </tr>
              <tr v-if="lineasComision.length > 0" class="row-total">
                <td><strong>Subtotal comisiones</strong></td>
                <td>${{ fmt(totales.comision.vendedor) }}</td>
                <td>${{ fmt(totales.comision.comprador) }}</td>
                <td>${{ fmt(totales.comision.total) }}</td>
                <td></td>
              </tr>
              <tr class="row-total">
                <td><strong>Subtotal impuestos y registro</strong></td>
                <td>${{ fmt(totales.impuestos.vendedor) }}</td>
                <td>${{ fmt(totales.impuestos.comprador) }}</td>
                <td>${{ fmt(totales.impuestos.total) }}</td>
                <td></td>
              </tr>
              <tr class="row-grand-total">
                <td><strong>TOTAL GASTOS APROXIMADOS</strong></td>
                <td><strong>${{ fmt(totales.gran.vendedor) }}</strong></td>
                <td><strong>${{ fmt(totales.gran.comprador) }}</strong></td>
                <td><strong>${{ fmt(totales.gran.total) }}</strong></td>
                <td></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tarjetas resumen -->
      <div v-if="valorInmueble > 0" class="summary-cards">
        <div class="summary-card card-vendedor">
          <span>Vendedor</span>
          <div class="card-amount">${{ fmt(totales.gran.vendedor) }}</div>
          <div class="card-sub">{{ pctTotal('vendedor') }}% del valor</div>
        </div>
        <div class="summary-card card-comprador">
          <span>Comprador</span>
          <div class="card-amount">${{ fmt(totales.gran.comprador) }}</div>
          <div class="card-sub">{{ pctTotal('comprador') }}% del valor</div>
        </div>
        <div class="summary-card card-operacion summary-item-highlight">
          <span>Total operación</span>
          <div class="card-amount">${{ fmt(totales.gran.total) }}</div>
          <div class="card-sub">{{ pctTotal('total') }}% del valor</div>
        </div>
      </div>
    </div>

    <!-- Acciones -->
    <div class="sim-actions-bottom">
      <button type="button" class="btn-imprimir" :disabled="!canPrint" @click="imprimir">
        Imprimir / PDF
      </button>
    </div>

    <p v-if="nc.notaPie" class="sim-nota-pie">{{ nc.notaPie }}</p>

  </div>
</template>

<script>
import { formatMoney, formatMoneyShort } from './composables/formatMoney.js';

const DEFAULT_DISTRIBUCIONES = {
  derechosNotariales: { vendedor: 50, comprador: 50 },
  retencionFuente: { vendedor: 100, comprador: 0 },
  otrosGastos: { vendedor: 50, comprador: 50 },
  cancelacionHipoteca: { vendedor: 100, comprador: 0 },
  constitucionHipoteca: { vendedor: 0, comprador: 100 },
  impuestoDepartamental: { vendedor: 0, comprador: 100 },
  derechosRegistro: { vendedor: 0, comprador: 100 },
  impuestoTimbre: { vendedor: 50, comprador: 50 },
  comision: { vendedor: 100, comprador: 0 },
  comisionPropiedad: { vendedor: 100, comprador: 0 },
  // Protecciones familiares — distribuciones fijas
  CANCELACION_AFECTACION_VIVIENDA_FAMILIAR: { vendedor: 100, comprador: 0 },
  CONSTITUCION_AFECTACION_VIVIENDA_FAMILIAR: { vendedor: 0, comprador: 100 },
  CANCELACION_PATRIMONIO_FAMILIA: { vendedor: 100, comprador: 0 },
  CONSTITUCION_PATRIMONIO_FAMILIA_VOLUNTARIO: { vendedor: 0, comprador: 100 },
  CONSTITUCION_PATRIMONIO_FAMILIA_VIS: { vendedor: 0, comprador: 100 },
};

export default {
  name: 'SimuladorVenta',

  props: {
    configuracion: { type: Object, default: () => ({}) },
    logo: { type: String, default: '' },
  },

  data() {
    return {
      valorInmueble: 0,
      vendedorJuridico: 0,
      cancelacionHipotecaSinCuantia: 0,
      constitucionHipotecaComprador: 0,

      // Campos heredados (backward compat) — deprecated, se mapean a pf
      cancelacionAfectacionVendedor: 0,
      constitucionAfectacionComprador: 0,

      hipotecavendedor: 0,
      hipotecacomprador: 0,
      tipoPropiedad: 'no_aplica', // 'no_aplica' | 'urbana' | 'rural'
      incluirComision: true,
      mostrarDistribucionAvanzada: false,
      distribuciones: { ...DEFAULT_DISTRIBUCIONES },

      // Estado UI
      ui: {
        expandirLimitacionesVendedor: false,
        expandirProteccionesComprador: false,
      },

      // Estructura nueva de protecciones familiares
      pf: {
        vendedor: {
          afectacionViviendaFamiliar: {
            estado: 'no',
            requiereLevantamiento: false,
            firmaConyugeOCompanero: 'no_aplica',
            modalidadLevantamiento: null,
          },
          patrimonioFamilia: {
            tipo: 'no',
            requiereCancelacion: false,
            existenBeneficiariosMenores: 'no',
            existeSubsidioORestriccion: 'no',
            autorizacionJudicial: 'no_aplica',
            modalidadLevantamiento: null,
          },
        },
        comprador: {
          compraParaViviendaFamiliar: 'no',
          situacionFamiliar: 'sin_pareja',
          afectacionViviendaFamiliar: {
            constituir: false,
          },
          patrimonioFamilia: {
            tipo: 'no',
            beneficiariosMenores: 'no',
            cumpleCondicionesVoluntario: 'no_sabe',
            hipotecaAdquisicion: 'no_sabe',
            subsidioAsociado: 'no',
            compraVISConfirmada: 'no_sabe',
          },
        },
      },
    };
  },

  computed: {
    nc() {
      const d = this.configuracion?.venta ?? this.configuracion ?? {};
      return {
        porcentajeGastosNotariales: Number(d.porcentajeGastosNotariales ?? 0.30),
        valorConstitucionAfectacionViviendaFamiliar: Number(d.valorConstitucionAfectacionViviendaFamiliar ?? 57600),
        porcentajeIva: Number(d.porcentajeIva ?? 19),
        porcentajeConstitucionHipoteca: Number(d.porcentajeConstitucionHipoteca ?? 0.30),
        portcentajeBeneficiencia: Number(d.portcentajeBeneficiencia ?? 1),
        portcentajeRegistroCompra: Number(d.portcentajeRegistroCompra ?? 0.91),
        valorbeneficienciaConstitucionHipoteca: Number(d.valorbeneficienciaConstitucionHipoteca ?? 185600),
        porcentajeTimbre: Number(d.porcentajeTimbre ?? 1.5),
        valorMinimoAplicaImpuestoTimbre: Number(d.valorMinimoAplicaImpuestoTimbre ?? 941300000),
        porcentajeRetencionFuente: Number(d.porcentajeRetencionFuente ?? 1),
        otrosGastos: Number(d.otrosGastos ?? 200000),
        labelImpuestoDepartamental: d.labelImpuestoDepartamental || 'Beneficencia',
        comisionHabilitada: !!d.comisionHabilitada && d.comisionHabilitada !== '0',
        porcentajeComision: Number(d.porcentajeComision ?? 3),
        comisionMinima: Number(d.comisionMinima ?? 0),
        labelComision: d.labelComision || 'Comisión',
        comisionPropiedadUrbanaHabilitada: !!d.comisionPropiedadUrbanaHabilitada && d.comisionPropiedadUrbanaHabilitada !== '0',
        comisionPropiedadUrbanaPorcentaje: Number(d.comisionPropiedadUrbanaPorcentaje ?? 3),
        comisionPropiedadUrbanaLabel: d.comisionPropiedadUrbanaLabel || 'Comisión propiedad urbana',
        comisionPropiedadRuralHabilitada: !!d.comisionPropiedadRuralHabilitada && d.comisionPropiedadRuralHabilitada !== '0',
        comisionPropiedadRuralPorcentaje: Number(d.comisionPropiedadRuralPorcentaje ?? 5),
        comisionPropiedadRuralLabel: d.comisionPropiedadRuralLabel || 'Comisión propiedad rural',
        notaPie: (d.notaPie || '').trim(),
        impuestoTimbreRangos: Array.isArray(d.impuestoTimbreRangos) ? d.impuestoTimbreRangos : null,
        protecciones_familiares: d.protecciones_familiares ?? null,
      };
    },

    mostrarComisionPropiedad() {
      return this.nc.comisionPropiedadUrbanaHabilitada || this.nc.comisionPropiedadRuralHabilitada;
    },

    // Config de protecciones con fallback seguro
    pfConfig() {
      const raw = this.nc.protecciones_familiares;
      const legacyValor = this.nc.valorConstitucionAfectacionViviendaFamiliar;
      return {
        afectacion_vivienda_familiar: {
          cancelacion: {
            activo: raw?.afectacion_vivienda_familiar?.cancelacion?.activo ?? true,
            derechos_notariales: raw?.afectacion_vivienda_familiar?.cancelacion?.derechos_notariales ?? legacyValor,
            aplica_iva: raw?.afectacion_vivienda_familiar?.cancelacion?.aplica_iva ?? true,
            porcentaje_iva: raw?.afectacion_vivienda_familiar?.cancelacion?.porcentaje_iva ?? 19,
            requiere_registro: raw?.afectacion_vivienda_familiar?.cancelacion?.requiere_registro ?? true,
            derechos_registro_primer_folio: raw?.afectacion_vivienda_familiar?.cancelacion?.derechos_registro_primer_folio ?? 29500,
            derechos_registro_folio_adicional: raw?.afectacion_vivienda_familiar?.cancelacion?.derechos_registro_folio_adicional ?? 15300,
            responsable_por_defecto: raw?.afectacion_vivienda_familiar?.cancelacion?.responsable_por_defecto ?? 'vendedor',
            permitir_edicion_responsable: raw?.afectacion_vivienda_familiar?.cancelacion?.permitir_edicion_responsable ?? true,
          },
          constitucion: {
            activo: raw?.afectacion_vivienda_familiar?.constitucion?.activo ?? true,
            derechos_notariales: raw?.afectacion_vivienda_familiar?.constitucion?.derechos_notariales ?? legacyValor,
            aplica_iva: raw?.afectacion_vivienda_familiar?.constitucion?.aplica_iva ?? true,
            porcentaje_iva: raw?.afectacion_vivienda_familiar?.constitucion?.porcentaje_iva ?? 19,
            requiere_registro: raw?.afectacion_vivienda_familiar?.constitucion?.requiere_registro ?? true,
            derechos_registro_primer_folio: raw?.afectacion_vivienda_familiar?.constitucion?.derechos_registro_primer_folio ?? 29500,
            derechos_registro_folio_adicional: raw?.afectacion_vivienda_familiar?.constitucion?.derechos_registro_folio_adicional ?? 15300,
            responsable_por_defecto: raw?.afectacion_vivienda_familiar?.constitucion?.responsable_por_defecto ?? 'comprador',
            permitir_edicion_responsable: raw?.afectacion_vivienda_familiar?.constitucion?.permitir_edicion_responsable ?? true,
          },
        },
        patrimonio_familia: {
          cancelacion: {
            activo: raw?.patrimonio_familia?.cancelacion?.activo ?? true,
            derechos_notariales: raw?.patrimonio_familia?.cancelacion?.derechos_notariales ?? 90600,
            aplica_iva: raw?.patrimonio_familia?.cancelacion?.aplica_iva ?? true,
            porcentaje_iva: raw?.patrimonio_familia?.cancelacion?.porcentaje_iva ?? 19,
            requiere_registro: raw?.patrimonio_familia?.cancelacion?.requiere_registro ?? true,
            derechos_registro_primer_folio: raw?.patrimonio_familia?.cancelacion?.derechos_registro_primer_folio ?? 29500,
            derechos_registro_folio_adicional: raw?.patrimonio_familia?.cancelacion?.derechos_registro_folio_adicional ?? 15300,
            responsable_por_defecto: raw?.patrimonio_familia?.cancelacion?.responsable_por_defecto ?? 'vendedor',
            permitir_edicion_responsable: raw?.patrimonio_familia?.cancelacion?.permitir_edicion_responsable ?? true,
          },
          constitucion_voluntaria: {
            activo: raw?.patrimonio_familia?.constitucion_voluntaria?.activo ?? true,
            derechos_notariales: raw?.patrimonio_familia?.constitucion_voluntaria?.derechos_notariales ?? 90600,
            aplica_iva: raw?.patrimonio_familia?.constitucion_voluntaria?.aplica_iva ?? true,
            porcentaje_iva: raw?.patrimonio_familia?.constitucion_voluntaria?.porcentaje_iva ?? 19,
            requiere_registro: raw?.patrimonio_familia?.constitucion_voluntaria?.requiere_registro ?? true,
            derechos_registro_primer_folio: raw?.patrimonio_familia?.constitucion_voluntaria?.derechos_registro_primer_folio ?? 29500,
            derechos_registro_folio_adicional: raw?.patrimonio_familia?.constitucion_voluntaria?.derechos_registro_folio_adicional ?? 15300,
            responsable_por_defecto: raw?.patrimonio_familia?.constitucion_voluntaria?.responsable_por_defecto ?? 'comprador',
            permitir_edicion_responsable: raw?.patrimonio_familia?.constitucion_voluntaria?.permitir_edicion_responsable ?? true,
          },
          constitucion_vis: {
            activo: raw?.patrimonio_familia?.constitucion_vis?.activo ?? true,
            modo_calculo: raw?.patrimonio_familia?.constitucion_vis?.modo_calculo ?? 'requiere_validacion',
            derechos_notariales: raw?.patrimonio_familia?.constitucion_vis?.derechos_notariales ?? 0,
            aplica_iva: raw?.patrimonio_familia?.constitucion_vis?.aplica_iva ?? false,
            porcentaje_iva: 0,
            requiere_registro: raw?.patrimonio_familia?.constitucion_vis?.requiere_registro ?? true,
            derechos_registro_primer_folio: raw?.patrimonio_familia?.constitucion_vis?.derechos_registro_primer_folio ?? 0,
            derechos_registro_folio_adicional: 0,
            responsable_por_defecto: 'comprador',
            permitir_edicion_responsable: false,
            mensaje_validacion: raw?.patrimonio_familia?.constitucion_vis?.mensaje_validacion ?? 'La constitución de patrimonio de familia asociada a VIS debe confirmarse con la notaría, la constructora y las condiciones del subsidio o financiación.',
          },
        },
      };
    },

    // ============ ALERTAS ============

    alertaFirmaAfectacionVendedor() {
      const avf = this.pf.vendedor.afectacionViviendaFamiliar;
      return avf.estado === 'si' && avf.requiereLevantamiento
        && ['no', 'no_sabe'].includes(avf.firmaConyugeOCompanero);
    },

    alertaMenoresPatrimonioVendedor() {
      const p = this.pf.vendedor.patrimonioFamilia;
      return p.tipo !== 'no'
        && (p.tipo === 'menores' || p.existenBeneficiariosMenores === 'si' || p.existenBeneficiariosMenores === 'no_sabe');
    },

    alertaVISPatrimonioVendedor() {
      const p = this.pf.vendedor.patrimonioFamilia;
      return ['vis', 'subsidio'].includes(p.tipo);
    },

    alertaMenoresPatrimonioComprador() {
      const p = this.pf.comprador.patrimonioFamilia;
      return p.tipo === 'voluntario' && ['si', 'no_sabe'].includes(p.beneficiariosMenores);
    },

    // ============ NORMALIZACIÓN BACKWARD COMPAT ============

    normalizarConfiguracionProteccionesFamiliares() {
      const afv = this.pf.vendedor.afectacionViviendaFamiliar;
      const afc = this.pf.comprador.afectacionViviendaFamiliar;

      // Si los campos nuevos están en el estado inicial pero los heredados están activos, migramos.
      if (afv.estado === 'no' && this.cancelacionAfectacionVendedor === 1) {
        return {
          vendedorAfectacion: { estado: 'si', requiereLevantamiento: true, firmaConyugeOCompanero: 'si', modalidadLevantamiento: 'misma_escritura_compraventa' },
          compradorAfectacion: { constituir: afc.constituir || this.constitucionAfectacionComprador === 1 },
        };
      }
      return null;
    },

    // ============ LÓGICA DE CÁLCULO PROTECCIONES FAMILIARES ============

    cancelacionAfectacionCalculable() {
      const v = this.pf.vendedor.afectacionViviendaFamiliar;
      return v.estado === 'si'
        && v.requiereLevantamiento
        && v.firmaConyugeOCompanero === 'si'
        && v.modalidadLevantamiento !== null
        && v.modalidadLevantamiento !== 'requiere_validacion';
    },

    cancelacionAfectacionRequiereValidacion() {
      const v = this.pf.vendedor.afectacionViviendaFamiliar;
      return v.estado === 'si'
        && v.requiereLevantamiento
        && !this.cancelacionAfectacionCalculable;
    },

    cancelacionAfectacionEstimada() {
      return this.pf.vendedor.afectacionViviendaFamiliar.estado === 'no_sabe';
    },

    cancelacionPatrimonioCalculable() {
      const p = this.pf.vendedor.patrimonioFamilia;
      return p.tipo !== 'no'
        && p.requiereCancelacion
        && p.existenBeneficiariosMenores === 'no'
        && p.existeSubsidioORestriccion === 'no'
        && ['no', 'no_aplica'].includes(p.autorizacionJudicial)
        && p.modalidadLevantamiento === 'escritura_publica';
    },

    cancelacionPatrimonioRequiereValidacion() {
      const p = this.pf.vendedor.patrimonioFamilia;
      return p.tipo !== 'no'
        && p.requiereCancelacion
        && !this.cancelacionPatrimonioCalculable;
    },

    constitucionAfectacionCompradorCalculable() {
      return (this.pf.comprador.compraParaViviendaFamiliar === 'si' || this.pf.comprador.compraParaViviendaFamiliar === 'no_sabe')
        && this.pf.comprador.afectacionViviendaFamiliar.constituir;
    },

    constitucionAfectacionCompradorEstimada() {
      return this.constitucionAfectacionCompradorCalculable
        && this.pf.comprador.situacionFamiliar === 'no_sabe';
    },

    constitucionPatrimonioVoluntarioCalculable() {
      const p = this.pf.comprador.patrimonioFamilia;
      return p.tipo === 'voluntario'
        && p.cumpleCondicionesVoluntario === 'si'
        && p.beneficiariosMenores === 'no'
        && p.hipotecaAdquisicion !== 'no_sabe';
    },

    constitucionPatrimonioVoluntarioRequiereValidacion() {
      const p = this.pf.comprador.patrimonioFamilia;
      return p.tipo === 'voluntario' && !this.constitucionPatrimonioVoluntarioCalculable;
    },

    constitucionPatrimonioVISActivo() {
      return this.pf.comprador.patrimonioFamilia.tipo === 'vis';
    },

    // ============ MOTOR DE CÁLCULO POR LÍNEAS ============

    lineasCalculo() {
      const cfg = this.nc;
      const t = this.valorInmueble;
      const lineas = [];

      const distribuir = (valor, distribKey) => {
        const dist = this.distribuciones[distribKey] ?? DEFAULT_DISTRIBUCIONES[distribKey] ?? { vendedor: 50, comprador: 50 };
        const pctVendedor = Number(dist.vendedor ?? 50);
        const vendedor = Math.round(valor * pctVendedor / 100);
        return { vendedor, comprador: Math.round(valor - vendedor) };
      };

      const calcActoSinCuantia = (subtipoCfg) => {
        const notarial = subtipoCfg.derechos_notariales;
        const iva = subtipoCfg.aplica_iva ? Math.round(notarial * subtipoCfg.porcentaje_iva / 100) : 0;
        const registro = subtipoCfg.requiere_registro ? subtipoCfg.derechos_registro_primer_folio : 0;
        return { notarial, iva, registro, total: notarial + iva + registro };
      };

      const agregarLinea = (codigo, concepto, tarifaTexto, categoria, valorVendedor, ivaVendedor, valorComprador, ivaComprador, estado = 'calculado', alerta = null) => {
        const valorTotal = valorVendedor + ivaVendedor + valorComprador + ivaComprador;
        lineas.push({
          codigo,
          categoria,
          concepto,
          base: 0,
          tarifaTexto,
          derechosNotariales: valorVendedor + valorComprador,
          iva: ivaVendedor + ivaComprador,
          derechosRegistro: 0,
          valorVendedor,
          ivaVendedor,
          valorComprador,
          ivaComprador,
          valorTotal,
          responsablePorDefecto: valorVendedor > valorComprador ? 'vendedor' : 'comprador',
          estado,
          alerta,
          visible: true,
        });
      };

      const agregarLineaConDistribucion = (codigo, concepto, tarifaTexto, categoria, montoBase, ivaBase, distribKey, estado = 'calculado', alerta = null) => {
        const d = distribuir(montoBase, distribKey);
        const dIva = distribuir(ivaBase, distribKey);
        agregarLinea(codigo, concepto, tarifaTexto, categoria, d.vendedor, dIva.vendedor, d.comprador, dIva.comprador, estado, alerta);
      };

      // 1. Gastos notariales compraventa
      const notariales = t * cfg.porcentajeGastosNotariales / 100;
      const ivaNotariales = Math.round(notariales * cfg.porcentajeIva / 100);
      agregarLinea(
        'derechosNotariales',
        `Gastos compraventa notariales (${cfg.porcentajeGastosNotariales}%)`,
        `${cfg.porcentajeGastosNotariales}% s/ valor`,
        'notaria',
        ...(() => { const d = distribuir(notariales, 'derechosNotariales'); const dIva = distribuir(ivaNotariales, 'derechosNotariales'); return [d.vendedor, dIva.vendedor, d.comprador, dIva.comprador]; })()
      );

      // 2. Retención en la fuente (sólo persona natural)
      if (this.vendedorJuridico === 0) {
        const retencion = Math.round(t * cfg.porcentajeRetencionFuente / 100);
        agregarLineaConDistribucion(
          'retencionFuente',
          `Retención en la fuente (${cfg.porcentajeRetencionFuente}%) — persona natural`,
          `${cfg.porcentajeRetencionFuente}%`,
          'retencion',
          retencion, 0, 'retencionFuente'
        );
      }

      // 3. Otros gastos
      agregarLineaConDistribucion(
        'otrosGastos',
        'Otros gastos (copias, folios, certificados)',
        'Valor fijo',
        'notaria',
        cfg.otrosGastos, 0, 'otrosGastos'
      );

      // 4. Cancelación hipoteca vendedor
      if (this.cancelacionHipotecaSinCuantia === 1 && this.hipotecavendedor > 0) {
        const monto = Math.round(this.hipotecavendedor * cfg.porcentajeConstitucionHipoteca / 100);
        agregarLineaConDistribucion(
          'CANCELACION_HIPOTECA_VENDEDOR',
          `Cancelación hipoteca (${cfg.porcentajeConstitucionHipoteca}% s/ $${this.fmtShort(this.hipotecavendedor)})`,
          `${cfg.porcentajeConstitucionHipoteca}%`,
          'actos_vendedor',
          monto, 0, 'cancelacionHipoteca'
        );
      }

      // 5. Constitución hipoteca comprador
      if (this.constitucionHipotecaComprador === 1 && this.hipotecacomprador > 0) {
        const monto = Math.round(this.hipotecacomprador * cfg.porcentajeConstitucionHipoteca / 100);
        agregarLineaConDistribucion(
          'CONSTITUCION_HIPOTECA_COMPRADOR',
          `Constitución hipoteca (${cfg.porcentajeConstitucionHipoteca}% s/ $${this.fmtShort(this.hipotecacomprador)})`,
          `${cfg.porcentajeConstitucionHipoteca}%`,
          'actos_comprador',
          monto, 0, 'constitucionHipoteca'
        );
      }

      // ============ PROTECCIONES FAMILIARES ============

      const pfCfg = this.pfConfig;

      // 6. Cancelación afectación a vivienda familiar — VENDEDOR
      if (this.pf.vendedor.afectacionViviendaFamiliar.estado !== 'no') {
        if (this.cancelacionAfectacionCalculable) {
          const sub = pfCfg.afectacion_vivienda_familiar.cancelacion;
          const acto = calcActoSinCuantia(sub);
          const modalidad = this.pf.vendedor.afectacionViviendaFamiliar.modalidadLevantamiento;
          agregarLinea(
            'CANCELACION_AFECTACION_VIVIENDA_FAMILIAR',
            `Cancelación de afectación a vivienda familiar${modalidad === 'misma_escritura_compraventa' ? ' (en escritura de compraventa)' : ' (escritura independiente)'}`,
            'Acto sin cuantía',
            'protecciones_familiares',
            acto.notarial + acto.registro, acto.iva, 0, 0,
            'calculado', null
          );
        } else if (this.cancelacionAfectacionRequiereValidacion) {
          const alertaFirma = !this.cancelacionAfectacionCalculable
            ? 'La afectación a vivienda familiar puede exigir el consentimiento y la firma del cónyuge o compañero permanente para vender o gravar el inmueble. Verifique este requisito con la notaría antes de continuar.'
            : null;
          agregarLinea(
            'CANCELACION_AFECTACION_VIVIENDA_FAMILIAR',
            'Cancelación de afectación a vivienda familiar',
            'Requiere validación',
            'protecciones_familiares',
            0, 0, 0, 0,
            'requiere_validacion_juridica',
            alertaFirma
          );
        } else if (this.cancelacionAfectacionEstimada) {
          agregarLinea(
            'CANCELACION_AFECTACION_VIVIENDA_FAMILIAR',
            'Cancelación de afectación a vivienda familiar',
            'Estimado',
            'protecciones_familiares',
            0, 0, 0, 0,
            'estimado',
            'Información pendiente de validación. El valor mostrado no debe usarse como liquidación definitiva hasta verificar el certificado de tradición, la escritura antecedente y los requisitos de la notaría.'
          );
        }
      }

      // 7. Cancelación patrimonio de familia — VENDEDOR
      if (this.pf.vendedor.patrimonioFamilia.tipo !== 'no') {
        if (this.cancelacionPatrimonioCalculable) {
          const sub = pfCfg.patrimonio_familia.cancelacion;
          const acto = calcActoSinCuantia(sub);
          agregarLinea(
            'CANCELACION_PATRIMONIO_FAMILIA',
            'Cancelación de patrimonio de familia',
            'Acto sin cuantía',
            'protecciones_familiares',
            acto.notarial + acto.registro, acto.iva, 0, 0,
            'calculado', null
          );
        } else {
          let alertaTexto = 'La cancelación o venta de un inmueble con patrimonio de familia puede requerir documentación adicional, autorización judicial o validación notarial según el tipo de patrimonio y los beneficiarios inscritos.';
          if (['vis', 'subsidio'].includes(this.pf.vendedor.patrimonioFamilia.tipo)) {
            alertaTexto = 'La operación puede estar sujeta a condiciones especiales de patrimonio de familia, subsidio o restricción de transferencia. Confirme el procedimiento y los costos con la notaría, entidad otorgante o asesor jurídico.';
          }
          agregarLinea(
            'CANCELACION_PATRIMONIO_FAMILIA',
            'Cancelación de patrimonio de familia',
            'Requiere validación',
            'protecciones_familiares',
            0, 0, 0, 0,
            'requiere_validacion_juridica',
            alertaTexto
          );
        }
      }

      // 8. Constitución afectación a vivienda familiar — COMPRADOR
      if (this.pf.comprador.afectacionViviendaFamiliar.constituir || this.constitucionAfectacionComprador === 1) {
        if (this.constitucionAfectacionCompradorCalculable) {
          const sub = pfCfg.afectacion_vivienda_familiar.constitucion;
          const acto = calcActoSinCuantia(sub);
          const estado = this.constitucionAfectacionCompradorEstimada ? 'estimado' : 'calculado';
          const alertaEstimado = estado === 'estimado' ? 'Información pendiente de validación. El valor mostrado no debe usarse como liquidación definitiva hasta verificar el certificado de tradición, la escritura antecedente y los requisitos de la notaría.' : null;
          agregarLinea(
            'CONSTITUCION_AFECTACION_VIVIENDA_FAMILIAR',
            'Constitución de afectación a vivienda familiar',
            'Acto sin cuantía',
            'protecciones_familiares',
            0, 0, acto.notarial + acto.registro, acto.iva,
            estado, alertaEstimado
          );
        }
      }

      // 9. Constitución patrimonio de familia voluntario — COMPRADOR
      if (this.pf.comprador.patrimonioFamilia.tipo === 'voluntario') {
        if (this.constitucionPatrimonioVoluntarioCalculable) {
          const sub = pfCfg.patrimonio_familia.constitucion_voluntaria;
          const acto = calcActoSinCuantia(sub);
          agregarLinea(
            'CONSTITUCION_PATRIMONIO_FAMILIA_VOLUNTARIO',
            'Constitución de patrimonio de familia voluntario',
            'Acto sin cuantía',
            'protecciones_familiares',
            0, 0, acto.notarial + acto.registro, acto.iva,
            'calculado', null
          );
        } else {
          agregarLinea(
            'CONSTITUCION_PATRIMONIO_FAMILIA_VOLUNTARIO',
            'Constitución de patrimonio de familia voluntario',
            'Requiere validación',
            'protecciones_familiares',
            0, 0, 0, 0,
            'requiere_validacion_juridica',
            'La cancelación o venta de un inmueble con patrimonio de familia puede requerir documentación adicional, autorización judicial o validación notarial según el tipo de patrimonio y los beneficiarios inscritos.'
          );
        }
      }

      // 10. Constitución patrimonio de familia VIS — COMPRADOR
      if (this.constitucionPatrimonioVISActivo) {
        const sub = pfCfg.patrimonio_familia.constitucion_vis;
        const esRequiereValidacion = sub.modo_calculo === 'requiere_validacion';
        if (esRequiereValidacion) {
          agregarLinea(
            'CONSTITUCION_PATRIMONIO_FAMILIA_VIS',
            'Constitución de patrimonio de familia por compra VIS',
            'Requiere confirmación',
            'protecciones_familiares',
            0, 0, 0, 0,
            'requiere_validacion_juridica',
            sub.mensaje_validacion
          );
        } else {
          const acto = calcActoSinCuantia(sub);
          agregarLinea(
            'CONSTITUCION_PATRIMONIO_FAMILIA_VIS',
            'Constitución de patrimonio de familia por compra VIS',
            'Acto sin cuantía',
            'protecciones_familiares',
            0, 0, acto.notarial + acto.registro, acto.iva,
            'estimado', sub.mensaje_validacion
          );
        }
      }

      // 11. Comisión
      if (cfg.comisionHabilitada && this.incluirComision && t > 0) {
        const monto = Math.round(t * cfg.porcentajeComision / 100);
        const comision = cfg.comisionMinima > 0 ? Math.max(cfg.comisionMinima, monto) : monto;
        agregarLineaConDistribucion(
          'comision',
          `${cfg.labelComision} (${cfg.porcentajeComision}%${cfg.comisionMinima > 0 ? ', mín. $' + this.fmtShort(cfg.comisionMinima) : ''})`,
          `${cfg.porcentajeComision}%`,
          'comision',
          comision, 0, 'comision'
        );
      }

      // 11b. Comisión de propiedad (urbana/rural)
      if (this.tipoPropiedad !== 'no_aplica' && t > 0) {
        const esUrbana = this.tipoPropiedad === 'urbana';
        const habilitada = esUrbana ? cfg.comisionPropiedadUrbanaHabilitada : cfg.comisionPropiedadRuralHabilitada;
        if (habilitada) {
          const pct = esUrbana ? cfg.comisionPropiedadUrbanaPorcentaje : cfg.comisionPropiedadRuralPorcentaje;
          const label = esUrbana ? cfg.comisionPropiedadUrbanaLabel : cfg.comisionPropiedadRuralLabel;
          const monto = Math.round(t * pct / 100);
          agregarLineaConDistribucion(
            'COMISION_PROPIEDAD',
            `${label} (${pct}% s/ valor inmueble)`,
            `${pct}%`,
            'comision',
            monto, 0, 'comisionPropiedad'
          );
        }
      }

      // 12. Beneficencia / impuesto departamental
      agregarLineaConDistribucion(
        'beneficencia',
        `${cfg.labelImpuestoDepartamental} (${cfg.portcentajeBeneficiencia}%)`,
        `${cfg.portcentajeBeneficiencia}%`,
        'impuesto_departamental',
        Math.round(t * cfg.portcentajeBeneficiencia / 100), 0, 'impuestoDepartamental'
      );

      // 12b. Beneficencia constitución hipoteca
      if (this.constitucionHipotecaComprador === 1 && cfg.valorbeneficienciaConstitucionHipoteca > 0) {
        agregarLineaConDistribucion(
          'beneficenciaHipoteca',
          `${cfg.labelImpuestoDepartamental} — constitución hipoteca`,
          'Valor fijo',
          'impuesto_departamental',
          cfg.valorbeneficienciaConstitucionHipoteca, 0, 'impuestoDepartamental'
        );
      }

      // 13. Derechos de registro
      agregarLineaConDistribucion(
        'registroCompra',
        `Derechos de registro (${cfg.portcentajeRegistroCompra}%)`,
        `${cfg.portcentajeRegistroCompra}%`,
        'registro',
        Math.round(t * cfg.portcentajeRegistroCompra / 100), 0, 'derechosRegistro'
      );

      // 14. Impuesto de timbre
      const timbre = this._calcTimbre(t, cfg);
      if (timbre > 0) {
        agregarLineaConDistribucion(
          'impuestoTimbre',
          `Impuesto de timbre (${cfg.porcentajeTimbre}%)`,
          `${cfg.porcentajeTimbre}%`,
          'impuesto_timbre',
          timbre, 0, 'impuestoTimbre'
        );
      }

      return lineas;
    },

    lineasNotariales() {
      return this.lineasCalculo.filter(l => ['notaria', 'retencion', 'actos_vendedor', 'actos_comprador', 'protecciones_familiares'].includes(l.categoria));
    },

    lineasComision() {
      return this.lineasCalculo.filter(l => l.categoria === 'comision');
    },

    lineasImpuestos() {
      return this.lineasCalculo.filter(l => ['impuesto_departamental', 'registro', 'impuesto_timbre'].includes(l.categoria));
    },

    distribucionesEditables() {
      const base = [
        { key: 'derechosNotariales', label: 'Derechos notariales compraventa', defaultVendedor: 50, defaultComprador: 50 },
        { key: 'retencionFuente', label: 'Retención en la fuente', defaultVendedor: 100, defaultComprador: 0 },
        { key: 'otrosGastos', label: 'Otros gastos (copias, folios)', defaultVendedor: 50, defaultComprador: 50 },
        { key: 'cancelacionHipoteca', label: 'Cancelación de hipoteca', defaultVendedor: 100, defaultComprador: 0 },
        { key: 'constitucionHipoteca', label: 'Constitución de hipoteca', defaultVendedor: 0, defaultComprador: 100 },
        { key: 'impuestoDepartamental', label: this.nc.labelImpuestoDepartamental, defaultVendedor: 0, defaultComprador: 100 },
        { key: 'derechosRegistro', label: 'Derechos de registro', defaultVendedor: 0, defaultComprador: 100 },
        { key: 'impuestoTimbre', label: 'Impuesto de timbre', defaultVendedor: 50, defaultComprador: 50 },
      ];

      if (this.nc.comisionHabilitada) {
        base.push({ key: 'comision', label: this.nc.labelComision || 'Comisión asesor', defaultVendedor: 100, defaultComprador: 0 });
      }

      if (this.tipoPropiedad !== 'no_aplica') {
        const esUrbana = this.tipoPropiedad === 'urbana';
        const habilitada = esUrbana ? this.nc.comisionPropiedadUrbanaHabilitada : this.nc.comisionPropiedadRuralHabilitada;
        if (habilitada) {
          const label = esUrbana ? this.nc.comisionPropiedadUrbanaLabel : this.nc.comisionPropiedadRuralLabel;
          base.push({ key: 'comisionPropiedad', label, defaultVendedor: 100, defaultComprador: 0 });
        }
      }

      const pfCancelAfectacion = this.pfConfig.afectacion_vivienda_familiar.cancelacion;
      if (pfCancelAfectacion.permitir_edicion_responsable && this.cancelacionAfectacionCalculable) {
        base.push({ key: 'CANCELACION_AFECTACION_VIVIENDA_FAMILIAR', label: 'Cancelación afectación a vivienda familiar', defaultVendedor: 100, defaultComprador: 0 });
      }
      const pfConstAfectacion = this.pfConfig.afectacion_vivienda_familiar.constitucion;
      if (pfConstAfectacion.permitir_edicion_responsable && this.constitucionAfectacionCompradorCalculable) {
        base.push({ key: 'CONSTITUCION_AFECTACION_VIVIENDA_FAMILIAR', label: 'Constitución afectación a vivienda familiar', defaultVendedor: 0, defaultComprador: 100 });
      }
      const pfCancelPatrimonio = this.pfConfig.patrimonio_familia.cancelacion;
      if (pfCancelPatrimonio.permitir_edicion_responsable && this.cancelacionPatrimonioCalculable) {
        base.push({ key: 'CANCELACION_PATRIMONIO_FAMILIA', label: 'Cancelación patrimonio de familia', defaultVendedor: 100, defaultComprador: 0 });
      }
      const pfConstPatrimonio = this.pfConfig.patrimonio_familia.constitucion_voluntaria;
      if (pfConstPatrimonio.permitir_edicion_responsable && this.constitucionPatrimonioVoluntarioCalculable) {
        base.push({ key: 'CONSTITUCION_PATRIMONIO_FAMILIA_VOLUNTARIO', label: 'Constitución patrimonio de familia voluntario', defaultVendedor: 0, defaultComprador: 100 });
      }

      return base;
    },

    comisionCalculada() {
      const cfg = this.nc;
      if (!cfg.comisionHabilitada || !this.valorInmueble) return 0;
      const monto = this.valorInmueble * cfg.porcentajeComision / 100;
      return Math.round(cfg.comisionMinima > 0 ? Math.max(cfg.comisionMinima, monto) : monto);
    },

    totales() {
      const sum = (arr, key) => arr.reduce((acc, l) => acc + (l[key] || 0), 0);
      const notVendedor = sum(this.lineasNotariales, 'valorVendedor') + sum(this.lineasNotariales, 'ivaVendedor');
      const notComprador = sum(this.lineasNotariales, 'valorComprador') + sum(this.lineasNotariales, 'ivaComprador');
      const comVendedor = sum(this.lineasComision, 'valorVendedor') + sum(this.lineasComision, 'ivaVendedor');
      const comComprador = sum(this.lineasComision, 'valorComprador') + sum(this.lineasComision, 'ivaComprador');
      const impVendedor = sum(this.lineasImpuestos, 'valorVendedor');
      const impComprador = sum(this.lineasImpuestos, 'valorComprador');
      return {
        notariales: { vendedor: Math.round(notVendedor), comprador: Math.round(notComprador), total: Math.round(notVendedor + notComprador) },
        comision: { vendedor: Math.round(comVendedor), comprador: Math.round(comComprador), total: Math.round(comVendedor + comComprador) },
        impuestos: { vendedor: Math.round(impVendedor), comprador: Math.round(impComprador), total: Math.round(impVendedor + impComprador) },
        gran: {
          vendedor: Math.round(notVendedor + comVendedor + impVendedor),
          comprador: Math.round(notComprador + comComprador + impComprador),
          total: Math.round(notVendedor + notComprador + comVendedor + comComprador + impVendedor + impComprador),
        },
      };
    },

    errors() {
      const e = {};
      if (!this.valorInmueble || this.valorInmueble <= 0) e.valorInmueble = 'Ingrese el valor del inmueble.';
      if (this.cancelacionHipotecaSinCuantia === 1 && (!this.hipotecavendedor || this.hipotecavendedor <= 0)) {
        e.hipotecavendedor = 'Ingrese el valor de la hipoteca.';
      }
      if (this.constitucionHipotecaComprador === 1 && (!this.hipotecacomprador || this.hipotecacomprador <= 0)) {
        e.hipotecacomprador = 'Ingrese el valor de la hipoteca.';
      }
      return e;
    },

    canPrint() {
      return Object.keys(this.errors).length === 0;
    },
  },

  watch: {
    configuracion: {
      deep: true,
      handler() {
        // Sync distribuciones if provided
        const dist = this.configuracion?.distribuciones ?? {};
        Object.keys(dist).forEach(k => {
          if (dist[k] && typeof dist[k].vendedor === 'number') {
            this.distribuciones[k] = { vendedor: dist[k].vendedor, comprador: 100 - dist[k].vendedor };
          }
        });
      },
    },

    // Auto-expandir si el usuario activa alguna protección
    'pf.vendedor.afectacionViviendaFamiliar.estado'(val) {
      if (val !== 'no') this.ui.expandirLimitacionesVendedor = true;
    },
    'pf.vendedor.patrimonioFamilia.tipo'(val) {
      if (val !== 'no') this.ui.expandirLimitacionesVendedor = true;
    },
    'pf.comprador.compraParaViviendaFamiliar'(val) {
      if (val !== 'no') this.ui.expandirProteccionesComprador = true;
    },
    'pf.comprador.patrimonioFamilia.tipo'(val) {
      if (val !== 'no') this.ui.expandirProteccionesComprador = true;
    },
  },

  created() {
    // Initialize distribuciones from external config
    const dist = this.configuracion?.distribuciones ?? {};
    Object.keys(dist).forEach(k => {
      if (dist[k] && typeof dist[k].vendedor === 'number') {
        this.distribuciones[k] = { vendedor: dist[k].vendedor, comprador: 100 - dist[k].vendedor };
      }
    });

    // Backward compat: si la config antigua activa cancelacion/constitucion afectacion
    this.normalizarConfiguracionProteccionesFamiliares;
  },

  methods: {
    fmt(v) { return formatMoney(v); },
    fmtShort(v) { return formatMoneyShort(v); },

    pctTotal(party) {
      if (!this.valorInmueble) return '0,00';
      const val = party === 'total' ? this.totales.gran.total
        : party === 'vendedor' ? this.totales.gran.vendedor
        : this.totales.gran.comprador;
      return (val / this.valorInmueble * 100).toFixed(2).replace('.', ',');
    },

    actualizarDistribucion(key, parte, valor) {
      const pct = Math.min(100, Math.max(0, Number(valor) || 0));
      if (parte === 'vendedor') {
        this.distribuciones[key] = { vendedor: pct, comprador: 100 - pct };
      } else {
        this.distribuciones[key] = { comprador: pct, vendedor: 100 - pct };
      }
    },

    _calcTimbre(valor, cfg) {
      if (cfg.impuestoTimbreRangos && Array.isArray(cfg.impuestoTimbreRangos) && cfg.impuestoTimbreRangos.length) {
        let total = 0, limite = 0;
        for (const rango of cfg.impuestoTimbreRangos) {
          if (valor <= limite) break;
          const hasta = rango.hasta ?? Infinity;
          const base = Math.min(valor, hasta) - limite;
          if (base > 0) total += base * (rango.porcentaje / 100);
          limite = hasta;
        }
        return Math.round(total);
      }
      return valor >= cfg.valorMinimoAplicaImpuestoTimbre ? Math.round(valor * cfg.porcentajeTimbre / 100) : 0;
    },

    badgeClass(estado) {
      const map = {
        calculado: 'badge-success',
        estimado: 'badge-warning',
        requiere_validacion_juridica: 'badge-danger',
        requiere_firma: 'badge-warning',
      };
      return map[estado] ?? 'badge-default';
    },

    badgeLabel(estado) {
      const map = {
        calculado: 'Calculado',
        estimado: 'Estimado',
        requiere_validacion_juridica: 'Requiere validación jurídica',
        requiere_firma: 'Requiere firma',
      };
      return map[estado] ?? estado;
    },

    buildPrintHtml() {
      const logo = this.logo ? `<img src="${this.logo}" style="max-height:56px;margin-bottom:10px;display:block;">` : '';
      const fecha = new Date().toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' });

      const resumenLineas = this.lineasCalculo.map(l => `
        <tr>
          <td>${l.concepto}${l.tarifaTexto ? ' — ' + l.tarifaTexto : ''}</td>
          <td>${l.estado === 'requiere_validacion_juridica' ? 'Ver alerta' : '$' + this.fmt(l.valorVendedor + l.ivaVendedor)}</td>
          <td>${l.estado === 'requiere_validacion_juridica' ? 'Ver alerta' : '$' + this.fmt(l.valorComprador + l.ivaComprador)}</td>
          <td>${l.estado === 'requiere_validacion_juridica' ? '–' : '$' + this.fmt(l.valorTotal)}</td>
          <td>${this.badgeLabel(l.estado)}</td>
        </tr>
        ${l.alerta ? `<tr><td colspan="5" style="color:#92400e;font-size:10px;">⚠ ${l.alerta}</td></tr>` : ''}
      `).join('');

      const alertasValidacion = this.lineasCalculo
        .filter(l => l.estado === 'requiere_validacion_juridica' && l.alerta)
        .map(l => `<li><strong>${l.concepto}:</strong> ${l.alerta}</li>`)
        .join('');

      const datosGenerales = [
        `<dt>Valor del inmueble:</dt><dd>$${this.fmt(this.valorInmueble)}</dd>`,
        `<dt>Vendedor persona jurídica:</dt><dd>${this.vendedorJuridico ? 'Sí' : 'No'}</dd>`,
        this.tipoPropiedad !== 'no_aplica' ? `<dt>Tipo de propiedad:</dt><dd>${this.tipoPropiedad === 'urbana' ? this.nc.comisionPropiedadUrbanaLabel : this.nc.comisionPropiedadRuralLabel} (${this.tipoPropiedad === 'urbana' ? this.nc.comisionPropiedadUrbanaPorcentaje : this.nc.comisionPropiedadRuralPorcentaje}%)</dd>` : '',
        this.cancelacionHipotecaSinCuantia ? `<dt>Hipoteca vendedor:</dt><dd>$${this.fmt(this.hipotecavendedor)}</dd>` : '',
        this.constitucionHipotecaComprador ? `<dt>Hipoteca comprador:</dt><dd>$${this.fmt(this.hipotecacomprador)}</dd>` : '',
        // Protecciones familiares
        this.pf.vendedor.afectacionViviendaFamiliar.estado !== 'no' ? `<dt>Afectación vivienda familiar vigente (vendedor):</dt><dd>${this.pf.vendedor.afectacionViviendaFamiliar.estado === 'si' ? 'Sí' : 'Sin confirmar'}</dd>` : '',
        this.pf.vendedor.patrimonioFamilia.tipo !== 'no' ? `<dt>Patrimonio de familia (vendedor):</dt><dd>${this.pf.vendedor.patrimonioFamilia.tipo}</dd>` : '',
        this.pf.comprador.afectacionViviendaFamiliar.constituir ? `<dt>Constitución afectación vivienda familiar (comprador):</dt><dd>Sí</dd>` : '',
        this.pf.comprador.patrimonioFamilia.tipo !== 'no' ? `<dt>Patrimonio de familia (comprador):</dt><dd>${this.pf.comprador.patrimonioFamilia.tipo}</dd>` : '',
      ].filter(Boolean).join('');

      return `
        <div class="ph">${logo}<h1>Simulador de Gastos Notariales de Compraventa</h1><p>Fecha: ${fecha}</p></div>
        <dl style="display:grid;grid-template-columns:auto 1fr;gap:4px 16px;font-size:11px;margin-bottom:16px;">${datosGenerales}</dl>
        <table>
          <thead><tr><th>Concepto</th><th>Vendedor</th><th>Comprador</th><th>Total</th><th>Estado</th></tr></thead>
          <tbody>${resumenLineas}
            <tr class="total-row"><td><strong>TOTAL VENDEDOR</strong></td><td><strong>$${this.fmt(this.totales.gran.vendedor)}</strong></td><td></td><td></td><td></td></tr>
            <tr class="total-row"><td><strong>TOTAL COMPRADOR</strong></td><td></td><td><strong>$${this.fmt(this.totales.gran.comprador)}</strong></td><td></td><td></td></tr>
            <tr class="grand-row"><td><strong>TOTAL GENERAL</strong></td><td colspan="2"></td><td><strong>$${this.fmt(this.totales.gran.total)}</strong></td><td></td></tr>
          </tbody>
        </table>
        ${alertasValidacion ? `<ul style="margin-top:12px;font-size:10px;color:#92400e;">${alertasValidacion}</ul>` : ''}
        <p class="disc">Esta simulación presenta valores aproximados con fines informativos. La constitución, cancelación o levantamiento de afectación a vivienda familiar y patrimonio de familia depende de las inscripciones vigentes, los beneficiarios, la situación familiar, subsidios, VIS y los requisitos de la notaría o autoridad competente. El resultado no reemplaza la revisión jurídica, tributaria ni notarial de la operación.</p>
      `;
    },

    imprimir() {
      if (!this.canPrint) return;
      const estilos = [
        'body{font-family:Arial,sans-serif;font-size:11px;padding:20px;color:#111827;margin:0;}',
        '.ph{margin-bottom:14px;border-bottom:2px solid #374151;padding-bottom:8px;}',
        'table{width:100%;border-collapse:collapse;margin-top:12px;}',
        'th,td{border:1px solid #e5e7eb;padding:6px;text-align:left;font-size:10px;}',
        'th{background:#1f2937;color:#fff;}',
        '.total-row{background:#f3f4f6;}',
        '.grand-row{background:#374151;color:#fff;}',
        '.disc{margin-top:16px;font-size:9px;color:#6b7280;}',
      ].join('');
      const html = this.buildPrintHtml();
      const fullDoc = `<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Simulación de Gastos</title><style>${estilos}</style></head><body>${html}</body></html>`;
      const blob = new Blob([fullDoc], { type: 'text/html;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const ventana = window.open(url, '_blank');
      if (ventana) {
        ventana.addEventListener('load', () => { ventana.print(); URL.revokeObjectURL(url); });
      } else {
        URL.revokeObjectURL(url);
      }
    },
  },
};
</script>

<style scoped>
.sim-section {
  margin-bottom: 4px;
}

.sim-section--protecciones {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 12px;
  background: #f9fafb;
}

.sim-section-title--collapse {
  cursor: pointer;
  user-select: none;
  display: flex;
  align-items: center;
  gap: 6px;
}

.sim-section-body {
  padding-top: 12px;
}

.proteccion-bloque {
  margin-bottom: 16px;
}

.sub-bloque {
  margin-left: 20px;
  margin-top: 8px;
  padding-left: 12px;
  border-left: 3px solid #d1d5db;
}

.sep-bloque {
  border: none;
  border-top: 1px dashed #d1d5db;
  margin: 16px 0;
}

.campo-ayuda {
  display: block;
  font-size: 11px;
  color: #6b7280;
  font-weight: 400;
  margin-top: 2px;
}

.alerta {
  border-radius: 6px;
  padding: 10px 12px;
  font-size: 12px;
  margin-top: 8px;
  line-height: 1.5;
}

.alerta--warning {
  background: #fffbeb;
  border: 1px solid #f59e0b;
  color: #92400e;
}

.alerta--danger {
  background: #fef2f2;
  border: 1px solid #ef4444;
  color: #991b1b;
}

.alerta--info {
  background: #eff6ff;
  border: 1px solid #3b82f6;
  color: #1e40af;
}

.alerta--sm {
  padding: 6px 10px;
  font-size: 11px;
}

.badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 9999px;
  font-size: 10px;
  font-weight: 600;
  white-space: nowrap;
}

.badge-success { background: #d1fae5; color: #065f46; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-danger  { background: #fee2e2; color: #991b1b; }
.badge-default { background: #f3f4f6; color: #374151; }

.results-row--validacion td {
  background: #fff7ed;
}

.tarifa-texto {
  font-size: 10px;
  color: #6b7280;
}

.results-row--alerta td {
  padding: 4px 8px;
  background: #fffbeb;
}
</style>
