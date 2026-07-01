<template>
  <div class="simulador-arriendo">
    <div class="sim-layout">

      <!-- Franja principal de configuración -->
      <section class="sim-form-strip card">
        <div class="strip-grid">

          <!-- Bloque principal: Canon + comisión + tipo -->
          <section class="strip-block strip-block-wide">
            <div class="grid grid-3">

              <!-- Canon -->
              <div class="field">
                <label for="canon">Canon mensual</label>
                <input
                  id="canon"
                  :value="formatInputMoney(form.canon)"
                  type="text"
                  inputmode="numeric"
                  placeholder="0"
                  @input="updateMoneyField('canon', $event.target.value)"
                />
              </div>

              <!-- Comisión porcentaje (solo si no es valor_fijo) -->
              <div v-if="form.comision.activa && form.comision.modalidad !== 'valor_fijo'" class="field">
                <label for="comisionPorcentaje">
                  Comisión (%)
                  <span v-if="form.comision.modalidad === 'porcentaje_con_minimo' && calc.valorComision > 0" class="label-hint">
                    {{ calc.valorComision <= form.comision.valorMinimo ? '→ aplica mínimo' : `→ ${fmtMoney(calc.valorComision)}` }}
                  </span>
                  <span v-else-if="form.comision.modalidad === 'porcentaje' && calc.valorComision > 0" class="label-hint">
                    = {{ fmtMoney(calc.valorComision) }}
                  </span>
                </label>
                <input
                  id="comisionPorcentaje"
                  v-model.number="form.comision.porcentaje"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                />
              </div>

              <!-- Tipo de inmueble -->
              <div class="field">
                <label for="tipoInmueble">Tipo de inmueble</label>
                <select id="tipoInmueble" v-model="form.tipoInmueble">
                  <option v-for="op in tipoInmuebleOpciones" :key="op.value" :value="op.value">{{ op.text }}</option>
                </select>
              </div>
            </div>

            <!-- Badge IVA si requiere validación manual -->
            <div v-if="grupoTributarioInmueble === 'validar'" class="inline-badges" style="margin-bottom:8px;">
              <span class="badge badge-warning">IVA requiere validación manual</span>
            </div>

            <!-- Administración -->
            <div class="field checkbox-field">
              <label>
                <input v-model="form.tieneAdministracion" type="checkbox" />
                ¿El inmueble tiene cuota de administración?
              </label>
            </div>

            <div v-if="form.tieneAdministracion" class="field">
              <label for="valorAdministracion">Valor de la cuota de administración</label>
              <input
                id="valorAdministracion"
                :value="formatInputMoney(form.valorAdministracion)"
                type="text"
                inputmode="numeric"
                placeholder="0"
                @input="updateMoneyField('valorAdministracion', $event.target.value)"
              />
            </div>

            <div class="grid grid-2">
              <!-- Régimen propietario -->
              <div class="field">
                <label for="regimenPropietario">Régimen tributario del propietario</label>
                <select id="regimenPropietario" v-model="form.regimenPropietario">
                  <option value="">— Sin seleccionar —</option>
                  <option value="no_responsable">Persona natural / No responsable de IVA</option>
                  <option value="responsable_iva">Responsable de IVA (régimen común)</option>
                </select>
              </div>

              <!-- Régimen arrendatario -->
              <div class="field">
                <label for="regimenArrendatario">Régimen tributario del arrendatario</label>
                <select id="regimenArrendatario" v-model="form.regimenArrendatario">
                  <option value="">— Sin seleccionar —</option>
                  <option value="no_responsable">Persona natural / No responsable</option>
                  <option value="responsable_iva">Empresa / Agente de retención</option>
                </select>
              </div>
            </div>
          </section>

          <!-- Bloque IVA sobre canon (solo para comercial o validar) -->
          <section v-if="mostrarBloqueIvaCanon" class="strip-block">
            <h4 class="strip-label">IVA sobre canon</h4>
            <div class="field checkbox-field">
              <label>
                <input v-model="form.propietarioResponsableIva" type="checkbox" />
                El propietario es responsable de IVA
              </label>
            </div>
            <div v-if="grupoTributarioInmueble === 'validar'" class="field checkbox-field">
              <label>
                <input v-model="form.aplicaIvaCanonManual" type="checkbox" />
                Aplicar IVA sobre canon (confirmado)
              </label>
            </div>
          </section>

        </div>
      </section>

      <!-- Bloque de descuentos y opciones -->
      <section class="card" v-if="showRestrictedRentOptions">
        <div class="strip-grid">

          <!-- Comisión -->
          <div class="strip-block">
            <h4 class="strip-label">Comisión inmobiliaria</h4>
            <div class="field checkbox-field">
              <label><input v-model="form.comision.activa" type="checkbox" /> Aplicar comisión</label>
            </div>
            <div v-if="form.comision.activa" class="field">
              <label>Modalidad</label>
              <select v-model="form.comision.modalidad">
                <option value="porcentaje">Porcentaje</option>
                <option value="porcentaje_con_minimo">Porcentaje con mínimo</option>
                <option value="valor_fijo">Valor fijo</option>
              </select>
            </div>
            <div v-if="form.comision.activa && form.comision.modalidad === 'valor_fijo'" class="field">
              <label>Valor fijo comisión</label>
              <input
                :value="formatInputMoney(form.comision.valorMinimo)"
                type="text"
                inputmode="numeric"
                @input="updateMoneyField('comision.valorMinimo', $event.target.value)"
              />
            </div>
            <div v-if="form.comision.activa && form.comision.modalidad === 'porcentaje_con_minimo'" class="field">
              <label>Valor mínimo comisión</label>
              <input
                :value="formatInputMoney(form.comision.valorMinimo)"
                type="text"
                inputmode="numeric"
                @input="updateMoneyField('comision.valorMinimo', $event.target.value)"
              />
            </div>
            <div v-if="form.comision.activa" class="field checkbox-field">
              <label><input v-model="form.comision.aplicaIva" type="checkbox" /> IVA sobre comisión</label>
            </div>
          </div>

          <!-- Seguro / póliza -->
          <div class="strip-block">
            <h4 class="strip-label">Seguro / póliza</h4>
            <div class="field checkbox-field">
              <label><input v-model="form.aplicarSeguro" type="checkbox" /> Aplicar seguro / póliza</label>
            </div>
            <div v-if="form.aplicarSeguro">
              <div class="field">
                <label>Modalidad</label>
                <select v-model="form.seguro.modalidad">
                  <option value="porcentaje">Porcentaje</option>
                  <option value="valor_fijo">Valor fijo</option>
                </select>
              </div>
              <div v-if="form.seguro.modalidad === 'porcentaje'" class="field">
                <label>% seguro</label>
                <input v-model.number="form.seguro.porcentaje" type="number" min="0" step="0.01" />
              </div>
              <div v-if="form.seguro.modalidad === 'valor_fijo'" class="field">
                <label>Valor fijo seguro</label>
                <input
                  :value="formatInputMoney(form.seguro.valorFijo)"
                  type="text"
                  inputmode="numeric"
                  @input="updateMoneyField('seguro.valorFijo', $event.target.value)"
                />
              </div>
              <div class="field">
                <label>Base de cálculo</label>
                <select v-model="form.seguro.base">
                  <option v-for="op in seguroBaseOpciones" :key="op" :value="op">{{ seguroBaseLabel(op) }}</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Gastos bancarios -->
          <div class="strip-block">
            <h4 class="strip-label">Gastos bancarios</h4>
            <div class="field checkbox-field">
              <label><input v-model="form.aplicarGastosBancarios" type="checkbox" /> Aplicar gastos bancarios</label>
            </div>
            <div v-if="form.aplicarGastosBancarios">
              <div class="field">
                <label>Modalidad</label>
                <select v-model="form.gastosBancarios.modalidad">
                  <option value="cuatro_por_mil">Cuatro por mil</option>
                  <option value="porcentaje">Porcentaje personalizado</option>
                  <option value="valor_fijo">Valor fijo</option>
                </select>
              </div>
              <div v-if="form.gastosBancarios.modalidad === 'porcentaje'" class="field">
                <label>% gastos bancarios</label>
                <input v-model.number="form.gastosBancarios.porcentaje" type="number" min="0" step="0.001" />
              </div>
              <div v-if="form.gastosBancarios.modalidad === 'valor_fijo'" class="field">
                <label>Valor fijo</label>
                <input
                  :value="formatInputMoney(form.gastosBancarios.valorFijo)"
                  type="text"
                  inputmode="numeric"
                  @input="updateMoneyField('gastosBancarios.valorFijo', $event.target.value)"
                />
              </div>
            </div>
          </div>

          <!-- Retenciones -->
          <div class="strip-block">
            <h4 class="strip-label">Retenciones</h4>
            <div class="field checkbox-field">
              <label><input v-model="form.condicionesTributarias.aplicarRetencionFuente" type="checkbox" /> Retención en la fuente</label>
            </div>
            <div v-if="form.condicionesTributarias.aplicarRetencionFuente" class="field">
              <label>% retención fuente</label>
              <input v-model.number="form.retenciones.fuente.porcentaje" type="number" min="0" step="0.01" />
            </div>

            <div class="field checkbox-field">
              <label><input v-model="form.condicionesTributarias.aplicarRetencionIca" type="checkbox" /> ReteICA</label>
            </div>
            <div v-if="form.condicionesTributarias.aplicarRetencionIca" class="field">
              <label>Tarifa ICA (por mil)</label>
              <input v-model.number="form.retenciones.ica.tarifaPorMil" type="number" min="0" step="0.01" />
            </div>

            <div v-if="ivaCanonActivo" class="field checkbox-field">
              <label><input v-model="form.condicionesTributarias.aplicarRetencionIva" type="checkbox" /> Retención de IVA</label>
            </div>
          </div>

        </div>
      </section>

      <!-- Resultados arriendo -->
      <div class="sim-results">
        <div class="card">
          <div class="table-wrapper results-table-wrapper">
            <table class="results-table">
              <thead>
                <tr>
                  <th>Ingresos</th>
                  <th>Valor</th>
                  <th>Descuentos</th>
                  <th>Valor</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(fila, idx) in resultadoFilas" :key="idx">
                  <td>{{ fila.ingreso ? fila.ingreso.label : '' }}</td>
                  <td>{{ fila.ingreso ? fmtMoney(fila.ingreso.value) : '' }}</td>
                  <td>{{ fila.descuento ? fila.descuento.label : '' }}</td>
                  <td>{{ fila.descuento ? fmtMoney(fila.descuento.value) : '' }}</td>
                </tr>
                <tr class="row-total">
                  <td><strong>TOTAL INGRESOS</strong></td>
                  <td><strong>{{ fmtMoney(calc.totalIngresos) }}</strong></td>
                  <td><strong>TOTAL DESCUENTOS</strong></td>
                  <td><strong>{{ fmtMoney(calc.totalDescuentos) }}</strong></td>
                </tr>
                <tr class="row-subtotal">
                  <td colspan="3"><strong>SUBTOTAL RENTA A RECIBIR ANTES DE GASTOS BANCARIOS</strong></td>
                  <td><strong>{{ fmtMoney(calc.subtotalAntesGastosBancarios) }}</strong></td>
                </tr>
                <tr v-if="form.aplicarGastosBancarios && calc.gastosBancarios > 0" class="row-subtotal">
                  <td colspan="3"><strong>{{ calc.gastosBancariosLabel }}</strong></td>
                  <td><strong>{{ fmtMoney(calc.gastosBancarios) }}</strong></td>
                </tr>
                <tr class="row-grand-total">
                  <td colspan="3"><strong>VALOR TOTAL RENTA A RECIBIR POR EL PROPIETARIO</strong></td>
                  <td><strong>{{ fmtMoney(calc.valorRentaRecibir) }}</strong></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Tarjetas resumen -->
        <div class="summary-strip">
          <div class="summary-item">
            <span>Canon mensual</span>
            <div class="summary-item-value">{{ fmtMoney(calc.canon) }}</div>
          </div>
          <div class="summary-item">
            <span>Total descuentos</span>
            <div class="summary-item-value">{{ fmtMoney(calc.totalDescuentos) }}</div>
          </div>
          <div class="summary-item summary-item-highlight">
            <span>Valor neto a recibir</span>
            <div class="summary-item-value">{{ fmtMoney(calc.valorRentaRecibir) }}</div>
          </div>
        </div>
      </div>

      <!-- Acciones -->
      <div class="sim-actions-bottom">
        <div v-if="notaPieHtml" class="sim-nota-pie" v-html="notaPieHtml"></div>
        <button type="button" :disabled="!canPrint" @click="imprimir">Imprimir / PDF</button>
      </div>

    </div>
  </div>
</template>

<script>
import { formatMoney, safeMoney } from './composables/formatMoney.js';

export default {
  name: 'SimuladorArriendo',

  props: {
    configuracion: { type: Object, default: () => ({}) },
    logo: { type: String, default: '' },
  },

  data() {
    const cfg = this.normalizarConfiguracion(this.configuracion);
    return {
      tipoInmuebleOpciones: [
        { value: 'vivienda', text: 'Vivienda' },
        { value: 'local_comercial', text: 'Local comercial' },
        { value: 'oficina', text: 'Oficina' },
        { value: 'consultorio', text: 'Consultorio' },
        { value: 'bodega', text: 'Bodega' },
        { value: 'parqueadero', text: 'Parqueadero' },
        { value: 'otro', text: 'Otro' },
      ],
      seguroBaseOpciones: ['canon', 'canon_mas_administracion', 'canon_mas_iva', 'canon_mas_administracion_mas_iva'],
      ui: { showAdvanced: !!cfg.interfaz?.expandirOpcionesAvanzadasPorDefecto },
      form: this.buildInitialForm(cfg),
    };
  },

  computed: {
    normalizedConfig() {
      return this.normalizarConfiguracion(this.configuracion);
    },

    isLoggedIn() {
      return !!this.configuracion?.system?.isLoggedIn;
    },

    showRestrictedRentOptions() {
      return this.isLoggedIn;
    },

    resolvedLogo() {
      return this.logo || this.normalizedConfig.logo || '';
    },

    notaPie() {
      return (this.configuracion.notaPie || '').trim();
    },

    notaPieHtml() {
      return this.formatNoteHtml(this.notaPie);
    },

    grupoTributarioInmueble() {
      return this.resolverGrupoTributarioInmueble();
    },

    mostrarBloqueIvaCanon() {
      return this.grupoTributarioInmueble !== 'vivienda';
    },

    ivaCanonActivo() {
      const grupo = this.grupoTributarioInmueble;
      if (grupo === 'comercial') {
        return this.form.regimenPropietario === 'responsable_iva' || this.form.propietarioResponsableIva;
      }
      if (grupo === 'validar') {
        return this.form.aplicaIvaCanonManual || this.form.regimenPropietario === 'responsable_iva';
      }
      return false;
    },

    calc() {
      const ivaCanon = this.calcularIvaCanon();
      const comision = this.calcularComision(ivaCanon);
      const ivaComision = this.calcularIvaComision(comision);
      const seguro = this.calcularSeguro(ivaCanon);
      const retencionFuente = this.calcularRetencionFuente();
      const retencionIca = this.calcularReteIca();
      const retencionIva = this.calcularReteIva(ivaCanon);
      const otrosDescuentos = this.calcularOtrosDescuentos();
      const totalIngresos = safeMoney(this.form.canon + ivaCanon);
      const descuentosSinBancarios = comision + ivaComision + seguro + retencionFuente + retencionIca + retencionIva + otrosDescuentos;
      const subtotalAntesGastosBancarios = Math.max(0, safeMoney(totalIngresos - descuentosSinBancarios));
      const gastosBancarios = this.calcularGastosBancarios(subtotalAntesGastosBancarios);
      const totalDescuentos = safeMoney(descuentosSinBancarios);
      let gastosBancariosLabel = 'Gastos bancarios';

      if (this.form.aplicarGastosBancarios && gastosBancarios > 0) {
        const modalidadGastos = this.form.gastosBancarios.modalidad;
        if (modalidadGastos === 'cuatro_por_mil') {
          gastosBancariosLabel = 'Gastos bancarios (4×1.000 s/ subtotal a recibir)';
        } else if (modalidadGastos === 'porcentaje') {
          gastosBancariosLabel = `Gastos bancarios (${this.form.gastosBancarios.porcentaje}% s/ subtotal a recibir)`;
        }
      }

      return {
        canon: safeMoney(this.form.canon),
        administracion: this.form.tieneAdministracion ? safeMoney(this.form.valorAdministracion) : 0,
        ivaCanon,
        totalIngresos,
        subtotalAntesGastosBancarios,
        valorComision: comision,
        ivaComision,
        valorSeguro: seguro,
        gastosBancarios,
        gastosBancariosLabel,
        retencionFuente,
        retencionIca,
        retencionIva,
        otrosDescuentos,
        totalDescuentos,
        valorRentaRecibir: safeMoney(subtotalAntesGastosBancarios - gastosBancarios),
      };
    },

    ingresosRows() {
      const rows = [{ label: 'Canon de arrendamiento', value: this.calc.canon }];
      if (this.ivaCanonActivo) {
        const pct = this.normalizedConfig.porcentajeIva;
        rows.push({ label: `IVA ${pct}% sobre canon (inmueble comercial / régimen común)`, value: this.calc.ivaCanon });
      }
      return rows;
    },

    descuentosRows() {
      const rows = [];
      const pct = this.normalizedConfig.porcentajeIva;
      if (this.form.comision.activa && this.calc.valorComision > 0) {
        rows.push({ label: 'Comisión inmobiliaria', value: this.calc.valorComision });
        if (this.form.comision.aplicaIva && this.calc.ivaComision > 0) {
          rows.push({ label: `IVA sobre comisión (${pct}%)`, value: this.calc.ivaComision });
        }
      }
      if (this.form.aplicarSeguro && this.calc.valorSeguro > 0) {
        rows.push({ label: 'Seguro / póliza de arrendamiento', value: this.calc.valorSeguro });
      }
      if (this.form.condicionesTributarias.aplicarRetencionFuente && this.calc.retencionFuente > 0) {
        rows.push({ label: `Retención en la fuente (${this.form.retenciones.fuente.porcentaje}%) sobre arrendamiento`, value: this.calc.retencionFuente });
      }
      if (this.form.condicionesTributarias.aplicarRetencionIca && this.calc.retencionIca > 0) {
        rows.push({ label: `ReteICA (${this.form.retenciones.ica.tarifaPorMil}/1000) sobre arrendamiento`, value: this.calc.retencionIca });
      }
      if (this.form.condicionesTributarias.aplicarRetencionIva && this.ivaCanonActivo && this.calc.retencionIva > 0) {
        rows.push({ label: `Retención de IVA (${pct}%)`, value: this.calc.retencionIva });
      }
      return rows;
    },

    resultadoFilas() {
      const len = Math.max(this.ingresosRows.length, this.descuentosRows.length);
      return Array.from({ length: len }, (_, i) => ({
        ingreso: this.ingresosRows[i] || null,
        descuento: this.descuentosRows[i] || null,
      }));
    },

    errores() {
      return this.validarFormulario();
    },

    canPrint() {
      return this.errores.length === 0;
    },
  },

  watch: {
    configuracion: {
      deep: true,
      handler() {
        this.syncFormWithConfig(this.normalizedConfig);
      },
    },
    'form.tipoInmueble'() { this.reiniciarCamposCondicionales(); },
    'form.tieneAdministracion'() { this.reiniciarCamposCondicionales(); },
    'form.aplicarSeguro'() { this.reiniciarCamposCondicionales(); },
    'form.aplicarGastosBancarios'() { this.reiniciarCamposCondicionales(); },
    'form.condicionesTributarias.aplicarRetencionFuente'() { this.reiniciarCamposCondicionales(); },
    'form.condicionesTributarias.aplicarRetencionIca'() { this.reiniciarCamposCondicionales(); },
    'form.condicionesTributarias.aplicarRetencionIva'() { this.reiniciarCamposCondicionales(); },
    'form.regimenPropietario'(val) {
      if (val === 'responsable_iva') { this.form.propietarioResponsableIva = true; this.form.aplicaIvaCanonManual = true; }
      else if (val === 'no_responsable') { this.form.propietarioResponsableIva = false; this.form.aplicaIvaCanonManual = false; }
    },
    'form.regimenArrendatario'(val) {
      if (val === 'responsable_iva') { this.form.condicionesTributarias.aplicarRetencionFuente = true; this.form.condicionesTributarias.aplicarRetencionIca = true; }
      else if (val === 'no_responsable') { this.form.condicionesTributarias.aplicarRetencionFuente = false; this.form.condicionesTributarias.aplicarRetencionIca = false; }
    },
  },

  methods: {
    normalizarNumero(v, def = 0) {
      const n = Number(v);
      return Number.isFinite(n) ? Math.max(0, n) : def;
    },

    escapeHtml(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    },

    formatNoteHtml(value) {
      const text = String(value || '').trim();
      if (!text) return '';
      if (/<[a-z][\s\S]*>/i.test(text)) {
        return text;
      }

      return text
        .split(/\n{2,}/)
        .map((block) => `<p>${this.escapeHtml(block).replace(/\n/g, '<br>')}</p>`)
        .join('');
    },

    safeMoney(v) {
      return safeMoney(v);
    },

    normalizarConfiguracion(cfg) {
      const c = cfg || {};
      const bool = (key, override, def) => override != null ? !!override : c[key] !== undefined ? String(c[key]) === '1' : def;

      const porcentajeIva = this.normalizarNumero(c.porcentajeIva ?? c.valoresTributarios?.porcentajeIvaGeneral, 19);
      const comisionPct = this.normalizarNumero(c.comision?.porcentaje ?? c.comisionTotalInmobiliaria, 9.5);
      const comisionMin = this.normalizarNumero(c.comision?.valorMinimo ?? c.comisionMinimaInmobiliaria, 0);
      const seguroPct = this.normalizarNumero(c.seguro?.porcentaje ?? c.seguroCanonArrendamiento, 2.5);
      const gastosFijo = this.normalizarNumero(c.gastosBancarios?.valorFijo ?? c.costoBancarioFijo, 0);
      const retFuentePct = this.normalizarNumero(c.retenciones?.fuente?.porcentaje ?? c.porcentajeRetencionFuente, 0);
      const retIcaMil = this.normalizarNumero(c.retenciones?.ica?.tarifaPorMil ?? c.porcentajeRetencionIca, 0);
      const retIvaPct = this.normalizarNumero(c.retenciones?.iva?.porcentaje ?? c.porcentajeRetencionIVA, 0);

      return {
        porcentajeIva,
        logo: c.logo || '',
        interfaz: { expandirOpcionesAvanzadasPorDefecto: false },
        comision: {
          activa: bool('comisionActiva', c.comision?.activa, true),
          modalidad: c.comision?.modalidad || c.comisionModalidad || 'porcentaje_con_minimo',
          porcentaje: comisionPct,
          valorMinimo: comisionMin,
          aplicaIva: bool('comisionAplicaIva', c.comision?.aplicaIva, true),
        },
        administracion: {
          activaPorDefecto: false,
          incluirEnBaseComision: bool('incluirAdministracionEnBaseComision', c.administracion?.incluirEnBaseComision, true),
          incluirEnBaseSeguro: bool('incluirAdministracionEnBaseSeguro', c.administracion?.incluirEnBaseSeguro, true),
        },
        seguro: {
          activoPorDefecto: bool('seguroActivo', c.seguro?.activoPorDefecto, true),
          modalidad: c.seguro?.modalidad || c.seguroModalidad || 'porcentaje',
          porcentaje: seguroPct,
          valorFijo: this.normalizarNumero(c.seguro?.valorFijo ?? c.seguroValorFijo, 0),
          base: c.seguro?.base || c.seguroBase || 'canon_mas_administracion_mas_iva',
        },
        gastosBancarios: {
          activosPorDefecto: bool('gastosBancariosActivos', c.gastosBancarios?.activosPorDefecto, true),
          modalidad: c.gastosBancarios?.modalidad || c.gastosBancariosModalidad || 'cuatro_por_mil',
          porcentaje: this.normalizarNumero(c.gastosBancarios?.porcentaje, 0.004),
          valorFijo: gastosFijo,
          base: c.gastosBancarios?.base || 'canon_mas_administracion',
        },
        retenciones: {
          fuente: {
            activaPorDefecto: bool('retencionFuenteActiva', c.retenciones?.fuente?.activaPorDefecto, true),
            porcentaje: retFuentePct,
          },
          ica: {
            activaPorDefecto: bool('retencionIcaActiva', c.retenciones?.ica?.activaPorDefecto, true),
            tarifaPorMil: retIcaMil,
          },
          iva: {
            activaPorDefecto: bool('retencionIvaActiva', c.retenciones?.iva?.activaPorDefecto, false),
            porcentaje: retIvaPct,
          },
        },
      };
    },

    buildInitialForm(cfg) {
      return {
        tipoInmueble: 'vivienda',
        canon: 0,
        tieneAdministracion: !!cfg.administracion.activaPorDefecto,
        valorAdministracion: 0,
        incluirAdministracionEnBaseComision: !!cfg.administracion.incluirEnBaseComision,
        incluirAdministracionEnBaseSeguro: !!cfg.administracion.incluirEnBaseSeguro,
        regimenPropietario: '',
        regimenArrendatario: '',
        propietarioResponsableIva: false,
        aplicaIvaCanonManual: false,
        condicionesTributarias: {
          aplicarRetencionFuente: !!cfg.retenciones.fuente.activaPorDefecto,
          aplicarRetencionIca: !!cfg.retenciones.ica.activaPorDefecto,
          aplicarRetencionIva: !!cfg.retenciones.iva.activaPorDefecto,
        },
        retenciones: {
          fuente: { porcentaje: this.normalizarNumero(cfg.retenciones.fuente.porcentaje) },
          ica: { tarifaPorMil: this.normalizarNumero(cfg.retenciones.ica.tarifaPorMil) },
          iva: { porcentaje: this.normalizarNumero(cfg.retenciones.iva.porcentaje) },
        },
        comision: {
          activa: cfg.comision.activa !== false,
          modalidad: cfg.comision.modalidad || 'porcentaje_con_minimo',
          porcentaje: this.normalizarNumero(cfg.comision.porcentaje),
          valorMinimo: this.normalizarNumero(cfg.comision.valorMinimo),
          aplicaIva: !!cfg.comision.aplicaIva,
        },
        aplicarSeguro: !!cfg.seguro.activoPorDefecto,
        seguro: {
          modalidad: cfg.seguro.modalidad || 'porcentaje',
          porcentaje: this.normalizarNumero(cfg.seguro.porcentaje),
          valorFijo: this.normalizarNumero(cfg.seguro.valorFijo),
          base: cfg.seguro.base || 'canon_mas_administracion_mas_iva',
        },
        aplicarGastosBancarios: !!cfg.gastosBancarios.activosPorDefecto,
        gastosBancarios: {
          modalidad: cfg.gastosBancarios.modalidad || 'cuatro_por_mil',
          porcentaje: this.normalizarNumero(cfg.gastosBancarios.porcentaje, 0.004),
          valorFijo: this.normalizarNumero(cfg.gastosBancarios.valorFijo),
          base: cfg.gastosBancarios.base || 'canon_mas_administracion',
        },
        mostrarOtrosDescuentos: false,
        otrosDescuentos: [],
      };
    },

    syncFormWithConfig(cfg) {
      this.form.incluirAdministracionEnBaseComision = !!cfg.administracion.incluirEnBaseComision;
      this.form.incluirAdministracionEnBaseSeguro = !!cfg.administracion.incluirEnBaseSeguro;
      this.form.comision.aplicaIva = !!cfg.comision.aplicaIva;
    },

    resolverGrupoTributarioInmueble() {
      const inmueble = this.form.tipoInmueble;
      const viviendas = ['vivienda', 'parqueadero'];
      const comerciales = ['local_comercial', 'oficina', 'consultorio', 'bodega'];
      if (viviendas.includes(inmueble)) return 'vivienda';
      if (comerciales.includes(inmueble)) return 'comercial';
      return 'validar';
    },

    reiniciarCamposCondicionales() {
      // no-op: campos condicionales ya son reactivos vía v-if
    },

    calcularBaseCanon() {
      let base = this.form.canon;
      if (this.form.tieneAdministracion) base += this.form.valorAdministracion;
      return base;
    },

    calcularBaseSeguro(ivaCanon) {
      const base = this.form.seguro.base || 'canon';
      let val = this.form.canon;
      const admin = this.form.tieneAdministracion && this.form.incluirAdministracionEnBaseSeguro ? this.form.valorAdministracion : 0;
      if (base === 'canon') return val;
      if (base === 'canon_mas_administracion') return val + admin;
      if (base === 'canon_mas_iva') return val + ivaCanon;
      if (base === 'canon_mas_administracion_mas_iva') return val + admin + ivaCanon;
      return val;
    },

    calcularBaseComision(ivaCanon) {
      let base = this.form.canon;
      if (this.form.incluirAdministracionEnBaseComision && this.form.tieneAdministracion) {
        base += this.form.valorAdministracion;
      }
      return base;
    },

    calcularIvaCanon() {
      if (!this.ivaCanonActivo || !this.form.canon) return 0;
      const pct = this.normalizedConfig.porcentajeIva;
      return safeMoney(this.form.canon * pct / 100);
    },

    calcularComision(ivaCanon) {
      if (!this.form.comision.activa) return 0;
      const base = this.calcularBaseComision(ivaCanon);
      if (base <= 0) return 0;
      const modalidad = this.form.comision.modalidad;
      if (modalidad === 'valor_fijo') return safeMoney(this.form.comision.valorMinimo);
      const pct = safeMoney(base * this.form.comision.porcentaje / 100);
      if (modalidad === 'porcentaje_con_minimo') {
        return Math.max(pct, safeMoney(this.form.comision.valorMinimo));
      }
      return pct;
    },

    calcularIvaComision(comision) {
      if (!this.form.comision.aplicaIva || comision <= 0) return 0;
      const pct = this.normalizedConfig.porcentajeIva;
      return safeMoney(comision * pct / 100);
    },

    calcularSeguro(ivaCanon) {
      if (!this.form.aplicarSeguro) return 0;
      const base = this.calcularBaseSeguro(ivaCanon);
      if (base <= 0) return 0;
      if (this.form.seguro.modalidad === 'valor_fijo') return safeMoney(this.form.seguro.valorFijo);
      return safeMoney(base * this.form.seguro.porcentaje / 100);
    },

    calcularGastosBancarios(baseValorAGirar) {
      if (!this.form.aplicarGastosBancarios) return 0;
      const modalidad = this.form.gastosBancarios.modalidad;
      if (modalidad === 'valor_fijo') return safeMoney(this.form.gastosBancarios.valorFijo);
      const base = Math.max(0, baseValorAGirar);
      if (modalidad === 'cuatro_por_mil') return safeMoney(base * 0.004);
      return safeMoney(base * this.form.gastosBancarios.porcentaje / 100);
    },

    calcularRetencionFuente() {
      if (!this.form.condicionesTributarias.aplicarRetencionFuente || !this.form.canon) return 0;
      return safeMoney(this.form.canon * this.form.retenciones.fuente.porcentaje / 100);
    },

    calcularReteIca() {
      if (!this.form.condicionesTributarias.aplicarRetencionIca || !this.form.canon) return 0;
      return safeMoney(this.form.canon * this.form.retenciones.ica.tarifaPorMil / 1000);
    },

    calcularReteIva(ivaCanon) {
      if (!this.form.condicionesTributarias.aplicarRetencionIva || !this.ivaCanonActivo) return 0;
      const pct = this.normalizedConfig.porcentajeIva;
      return safeMoney(ivaCanon * pct / 100);
    },

    calcularOtrosDescuentos() {
      return this.form.otrosDescuentos?.reduce((acc, d) => acc + (d.valor || 0), 0) || 0;
    },

    validarFormulario() {
      const errores = [];
      if (!this.form.canon || this.form.canon <= 0) errores.push('El canon mensual debe ser mayor a cero.');
      return errores;
    },

    formatInputMoney(v) {
      if (!v) return '';
      return String(Math.round(Number(v) || 0));
    },

    updateMoneyField(field, rawValue) {
      const num = Number(String(rawValue).replace(/[^0-9]/g, '')) || 0;
      const parts = field.split('.');
      if (parts.length === 1) {
        this.form[field] = num;
      } else if (parts.length === 2) {
        this.form[parts[0]][parts[1]] = num;
      }
    },

    fmtMoney(v) {
      return formatMoney(v);
    },

    calcularResumen() {
      return {
        canonMensual: this.calc.canon,
        totalDescuentos: this.calc.totalDescuentos,
        subtotalAntesGastosBancarios: this.calc.subtotalAntesGastosBancarios,
        gastosBancarios: this.calc.gastosBancarios,
        valorNeto: this.calc.valorRentaRecibir,
      };
    },

    seguroBaseLabel(base) {
      const map = {
        canon: 'Canon',
        canon_mas_administracion: 'Canon + administración',
        canon_mas_iva: 'Canon + IVA sobre canon',
        canon_mas_administracion_mas_iva: 'Canon + administración + IVA sobre canon',
      };
      return map[base] || base;
    },

    buildPrintHtml() {
      const logo = this.resolvedLogo ? `<img src="${this.resolvedLogo}" alt="Logo" class="print-logo" />` : '';
      const fecha = new Date().toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' });
      const resumen = this.calcularResumen();
      const filas = this.resultadoFilas.map(f => `
        <tr>
          <td>${f.ingreso ? f.ingreso.label : ''}</td>
          <td>${f.ingreso ? this.fmtMoney(f.ingreso.value) : ''}</td>
          <td>${f.descuento ? f.descuento.label : ''}</td>
          <td>${f.descuento ? this.fmtMoney(f.descuento.value) : ''}</td>
        </tr>
      `).join('');

      return `
        <div class="print-root">
          <div class="print-header">
            ${logo}
            <div>
              <h1>Simulador de Arriendo</h1>
              <p>Fecha: ${fecha}</p>
              <p>Canon: ${this.fmtMoney(this.calc.canon)}</p>
              ${this.form.tieneAdministracion ? `<p>Administración: ${this.fmtMoney(this.calc.administracion)}</p>` : ''}
            </div>
          </div>
          <div class="print-summary">
            <div class="print-card"><strong>Canon mensual</strong><span>${this.fmtMoney(resumen.canonMensual)}</span></div>
            <div class="print-card"><strong>Total descuentos</strong><span>${this.fmtMoney(resumen.totalDescuentos)}</span></div>
            <div class="print-card highlight"><strong>Valor neto a recibir</strong><span>${this.fmtMoney(resumen.valorNeto)}</span></div>
          </div>
          <table>
            <thead><tr><th>Ingresos</th><th>Valor</th><th>Descuentos</th><th>Valor</th></tr></thead>
            <tbody>
              ${filas}
              <tr>
                <td><strong>TOTAL INGRESOS</strong></td>
                <td><strong>${this.fmtMoney(this.calc.totalIngresos)}</strong></td>
                <td><strong>TOTAL DESCUENTOS</strong></td>
                <td><strong>${this.fmtMoney(this.calc.totalDescuentos)}</strong></td>
              </tr>
              <tr>
                <td colspan="3"><strong>SUBTOTAL RENTA A RECIBIR ANTES DE GASTOS BANCARIOS</strong></td>
                <td><strong>${this.fmtMoney(this.calc.subtotalAntesGastosBancarios)}</strong></td>
              </tr>
              ${this.form.aplicarGastosBancarios && this.calc.gastosBancarios > 0 ? `
              <tr>
                <td colspan="3"><strong>${this.calc.gastosBancariosLabel}</strong></td>
                <td><strong>${this.fmtMoney(this.calc.gastosBancarios)}</strong></td>
              </tr>
              ` : ''}
              <tr>
                <td colspan="3"><strong>VALOR TOTAL RENTA A RECIBIR POR EL PROPIETARIO</strong></td>
                <td><strong>${this.fmtMoney(this.calc.valorRentaRecibir)}</strong></td>
              </tr>
            </tbody>
          </table>
          <p class="print-note">
            Esta simulación presenta valores aproximados con fines informativos. El resultado puede variar según las condiciones tributarias de las partes, la destinación del inmueble y las políticas comerciales configuradas por la inmobiliaria. Para confirmar la liquidación definitiva, valide la información con el asesor responsable.
          </p>
        </div>
      `;
    },

    imprimir() {
      if (!this.canPrint) return;
      const estilos = [
        'body{font-family:Arial,sans-serif;color:#1f2937;padding:24px;margin:0;}',
        '.print-header{display:flex;gap:16px;align-items:center;margin-bottom:20px;}',
        '.print-logo{max-height:60px;}',
        '.print-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px;}',
        '.print-card{border:1px solid #e5e7eb;border-radius:8px;padding:12px;background:#f8fafc;display:flex;flex-direction:column;gap:6px;}',
        '.highlight{background:#374151;color:#fff;}',
        'table{width:100%;border-collapse:collapse;}',
        'th,td{border:1px solid #e5e7eb;padding:8px;text-align:left;font-size:12px;}',
        'th{background:#1f2937;color:#fff;}',
        '.print-note{margin-top:16px;font-size:11px;color:#6b7280;}',
      ].join('');
      const html = this.buildPrintHtml();
      const win = window.open('', '_blank', 'width=960,height=720');
      if (win) {
        win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>Simulador de Arriendo</title><style>${estilos}</style></head><body>${html}</body></html>`);
        win.document.close();
        win.focus();
        win.onload = () => { win.print(); win.onafterprint = () => win.close(); };
      }
    },
  },
};
</script>

<style scoped>
.simulador-arriendo {
  color: #111827;
  font-size: 14px;
  line-height: 1.5;
}

.sim-layout {
  display: grid;
  gap: 18px;
}

.card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
}

.sim-form-strip,
.sim-results .card {
  padding: 18px;
}

.strip-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}

.strip-block {
  flex: 1 1 240px;
  min-width: 0;
}

.strip-block-wide {
  flex: 2 1 440px;
  min-width: 0;
}

.strip-label {
  margin: 0 0 10px;
  color: #6b7280;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.grid {
  display: grid;
  gap: 14px;
}

.grid-2 {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.grid-3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.field {
  min-width: 0;
}

.field label {
  display: block;
  margin-bottom: 6px;
  color: #111827;
  font-size: 12px;
  font-weight: 600;
}

.field input,
.field select {
  width: 100%;
  min-width: 0;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  background: #ffffff;
  color: #111827;
  font-size: 14px;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  box-sizing: border-box;
}

.strip-block {
  padding: 14px;
  background: #f8fafc;
  border: 1px solid #dbe3ee;
  border-radius: 12px;
}

.strip-block-wide {
  background: linear-gradient(180deg, #ffffff, #f8fafc);
}

.field input:focus,
.field select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.14);
}

.checkbox-field label {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 0;
  font-size: 13px;
}

.checkbox-field input[type="checkbox"] {
  width: 16px;
  height: 16px;
  margin: 0;
  accent-color: #2563eb;
}

.label-hint {
  margin-left: 4px;
  color: #6b7280;
  font-size: 10px;
}

.inline-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.badge {
  display: inline-block;
  padding: 3px 9px;
  border-radius: 9999px;
  font-size: 10px;
  font-weight: 700;
}

.badge-warning {
  background: #fef3c7;
  color: #92400e;
}

.table-wrapper {
  width: 100%;
  overflow-x: auto;
}

.results-table {
  width: 100%;
  min-width: 640px;
  border-collapse: collapse;
  table-layout: fixed;
}

.results-table th,
.results-table td {
  padding: 12px 10px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
  vertical-align: top;
}

.results-table th {
  background: #f8fafc;
  color: #334155;
  font-size: 12px;
  font-weight: 700;
}

.results-table td {
  font-size: 13px;
}

.row-total {
  background: #f8fafc;
}

.row-subtotal {
  background: #eef4ff;
}

.row-grand-total {
  background: #1f2937;
  color: #ffffff;
}

.summary-strip {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-top: 16px;
}

.summary-item {
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: linear-gradient(180deg, #ffffff, #f8fafc);
  text-align: center;
}

.summary-item span {
  display: block;
  color: #64748b;
  font-size: 12px;
  font-weight: 600;
}

.summary-item-highlight {
  background: linear-gradient(135deg, #1f2937, #334155);
  color: #ffffff;
  border-color: transparent;
}

.summary-item-highlight span {
  color: rgba(255, 255, 255, 0.78);
}

.summary-item-value {
  margin-top: 6px;
  font-size: 20px;
  font-weight: 700;
}

.sim-actions-bottom {
  margin-top: 18px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
}

.sim-actions-bottom button {
  padding: 11px 18px;
  border: 0;
  border-radius: 10px;
  background: linear-gradient(135deg, #0f172a, #334155);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18);
}

.sim-actions-bottom button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.sim-nota-pie {
  flex: 1 1 auto;
  margin: 0;
  color: #6b7280;
  font-size: 12px;
}

.sim-nota-pie :deep(p),
.sim-nota-pie :deep(ul),
.sim-nota-pie :deep(ol) {
  margin: 0 0 0.85em;
}

.sim-nota-pie :deep(ul),
.sim-nota-pie :deep(ol) {
  padding-left: 18px;
}

@media (max-width: 900px) {
  .grid-3,
  .grid-2,
  .summary-strip {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .sim-form-strip,
  .sim-results .card {
    padding: 14px;
  }

  .strip-grid {
    gap: 12px;
  }

  .sim-actions-bottom {
    flex-direction: column;
    align-items: stretch;
  }

  .sim-actions-bottom button {
    width: 100%;
  }
}
</style>
