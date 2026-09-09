/**
 * La aritmética del simulador de arriendo.
 *
 * La administración la recauda la inmobiliaria junto con el canon y se la gira
 * a la copropiedad. Faltaba de las dos columnas: la comisión y el seguro se
 * cobraban sobre ella —así viene configurado por defecto— pero se restaban de
 * un total de ingresos donde esa plata nunca había entrado. El neto salía bien
 * de casualidad, porque tampoco se descontaba el giro; lo que el asesor le
 * enseñaba al propietario era una tabla que no cuadraba consigo misma.
 *
 * El componente es un `.vue` y aquí no hay compilador de SFC, pero su `<script>`
 * es un objeto de opciones de JavaScript corriente: se extrae y se le monta un
 * `this` con los métodos y las computadas. Es la única forma de probar el
 * cálculo sin arrastrar Vue a las pruebas, y el cálculo es lo que se prueba.
 */

const fs = require('fs');
const path = require('path');

const SFC = path.join(__dirname, '../../assets/src/simulator/SimuladorArriendo.vue');
const COMPOSABLE = '../../assets/src/simulator/composables/formatMoney.js';

/** El objeto de opciones del componente, sin Vue de por medio. */
function cargarOpciones() {
  const vue = fs.readFileSync(SFC, 'utf8');
  const script = vue.slice(vue.indexOf('<script>') + '<script>'.length, vue.lastIndexOf('</script>'));

  const cuerpo = script
    .replace(
      /import \{([^}]*)\} from '\.\/composables\/formatMoney\.js';/,
      `const {$1} = require('${COMPOSABLE}');`
    )
    .replace('export default', 'module.exports =');

  const modulo = { exports: {} };
  // eslint-disable-next-line no-new-func
  new Function('require', 'module', 'exports', cuerpo)(require, modulo, modulo.exports);

  return modulo.exports;
}

const opciones = cargarOpciones();

/** Un componente falso: métodos y computadas colgando del mismo `this`. */
function instanciar(configuracion, form) {
  const vm = {};
  Object.entries(opciones.methods).forEach(([nombre, fn]) => { vm[nombre] = fn.bind(vm); });
  Object.entries(opciones.computed).forEach(([nombre, fn]) => {
    Object.defineProperty(vm, nombre, { get: fn.bind(vm), configurable: true });
  });
  vm.configuracion = configuracion;
  vm.form = form;
  return vm;
}

const CONFIG = { porcentajeIva: 19 };

/** Un simulador con el formulario por defecto y los ajustes de la prueba. */
function simulador(ajustes = {}) {
  const arranque = instanciar(CONFIG, null);
  const form = arranque.buildInitialForm(arranque.normalizarConfiguracion(CONFIG));

  Object.assign(form, {
    canon: 1000000,
    tipoInmueble: 'vivienda',
    tieneAdministracion: true,
    valorAdministracion: 300000,
    incluirAdministracionEnBaseComision: true,
    incluirAdministracionEnBaseSeguro: true,
    aplicarSeguro: true,
    aplicarGastosBancarios: false,
  }, ajustes);

  form.comision = { ...form.comision, activa: true, modalidad: 'porcentaje', porcentaje: 10, aplicaIva: false, ...(ajustes.comision || {}) };
  form.seguro = { ...form.seguro, modalidad: 'porcentaje', porcentaje: 3, base: 'canon_mas_administracion', ...(ajustes.seguro || {}) };
  form.condicionesTributarias = {
    aplicarRetencionFuente: false,
    aplicarRetencionIca: false,
    aplicarRetencionIva: false,
    ...(ajustes.condicionesTributarias || {}),
  };

  return instanciar(CONFIG, form);
}

const valorDe = (filas, texto) => (filas.find((f) => f.label.includes(texto)) || {}).value;

describe('la cuota de administración', () => {
  it('entra en los ingresos, porque el arrendatario la paga junto con el canon', () => {
    const vm = simulador();

    expect(valorDe(vm.ingresosRows, 'administración')).toBe(300000);
    expect(vm.calc.totalIngresos).toBe(1300000);
  });

  it('sale por descuentos, porque se le gira a la copropiedad', () => {
    const vm = simulador();

    expect(valorDe(vm.descuentosRows, 'girada a la copropiedad')).toBe(300000);
  });

  it('no aparece en ninguna columna cuando el inmueble no tiene', () => {
    const vm = simulador({ tieneAdministracion: false, valorAdministracion: 0 });

    expect(vm.ingresosRows.some((f) => /dministra/.test(f.label))).toBe(false);
    expect(vm.descuentosRows.some((f) => /dministra/.test(f.label))).toBe(false);
    expect(vm.calc.totalIngresos).toBe(1000000);
  });

  /**
   * El fallo de fondo: la comisión y el seguro se cobran sobre canon +
   * administración, así que esa base tiene que caber dentro de lo que la tabla
   * declara como ingresos. Antes no cabía y por eso la tabla no cuadraba.
   */
  it('deja la base de la comisión dentro de lo que declaran los ingresos', () => {
    const vm = simulador();

    expect(vm.calcularBaseComision(0)).toBe(1300000);
    expect(vm.calcularBaseComision(0)).toBeLessThanOrEqual(vm.calc.totalIngresos);
    expect(vm.calcularBaseSeguro(0)).toBeLessThanOrEqual(vm.calc.totalIngresos);
  });
});

describe('la tabla de resultados', () => {
  it('cuadra: ingresos menos descuentos menos gastos bancarios es el neto', () => {
    const vm = simulador({
      canon: 4500000,
      valorAdministracion: 850000,
      aplicarGastosBancarios: true,
      condicionesTributarias: { aplicarRetencionFuente: true, aplicarRetencionIca: true },
    });
    vm.form.gastosBancarios = { ...vm.form.gastosBancarios, modalidad: 'cuatro_por_mil' };
    vm.form.retenciones = { fuente: { porcentaje: 3.5 }, ica: { tarifaPorMil: 9.66 } };

    const c = vm.calc;

    expect(c.subtotalAntesGastosBancarios).toBe(c.totalIngresos - c.totalDescuentos);
    expect(c.valorRentaRecibir).toBe(c.subtotalAntesGastosBancarios - c.gastosBancarios);
  });

  /**
   * La queja que destapó todo esto: una columna cuyo total no es la suma de lo
   * que enseña. Si una partida entra en el total pero no en la columna —o al
   * revés—, el propietario ve una tabla que no cuadra.
   */
  it('suma en cada columna exactamente lo que enseña', () => {
    const vm = simulador({
      canon: 4500000,
      valorAdministracion: 850000,
      condicionesTributarias: { aplicarRetencionFuente: true, aplicarRetencionIca: true },
    });
    vm.form.retenciones = { fuente: { porcentaje: 3.5 }, ica: { tarifaPorMil: 9.66 } };

    const suma = (filas) => filas.reduce((acc, f) => acc + f.value, 0);

    expect(vm.ingresosRows.length).toBeGreaterThan(1);
    expect(vm.descuentosRows.length).toBeGreaterThan(1);
    expect(suma(vm.ingresosRows)).toBe(vm.calc.totalIngresos);
    expect(suma(vm.descuentosRows)).toBe(vm.calc.totalDescuentos);
  });

  /**
   * Girar la administración no le quita ni le pone nada al propietario: entra y
   * sale por el mismo valor. Si alguna vez deja de cuadrar, el neto de todos
   * los simuladores publicados se mueve.
   */
  it('deja el neto del propietario igual que si la administración no pasara por ahí', () => {
    const conAdministracion = simulador({ seguro: { base: 'canon' }, incluirAdministracionEnBaseComision: false });
    const sinAdministracion = simulador({
      tieneAdministracion: false,
      valorAdministracion: 0,
      seguro: { base: 'canon' },
      incluirAdministracionEnBaseComision: false,
    });

    expect(conAdministracion.calc.valorRentaRecibir).toBe(sinAdministracion.calc.valorRentaRecibir);
  });
});

/** Reproduce los valores enviados por los ajustes de WordPress, sin forzarlos en el formulario. */
function desdeAjustes(ajustes = {}) {
  const configuracion = {
    porcentajeIva: '19', comisionActiva: '1', comisionModalidad: 'porcentaje',
    comisionTotalInmobiliaria: '10', comisionAplicaIva: '1',
    incluirAdministracionEnBaseComision: '1', incluirAdministracionEnBaseSeguro: '1',
    seguroActivo: '1', seguroCanonArrendamiento: '2.5',
    seguroBase: 'canon_mas_administracion_mas_iva',
    retencionFuenteActiva: '0', retencionIcaActiva: '0', gastosBancariosActivos: '0',
    ...ajustes,
  };
  const vm = instanciar(configuracion, null);
  vm.form = vm.buildInitialForm(vm.normalizedConfig);
  Object.assign(vm.form, { canon: 1000000, tieneAdministracion: true, valorAdministracion: 300000 });
  return vm;
}

describe('ajustes de administración e IVA', () => {
  for (const comision of ['0', '1']) {
    for (const seguro of ['0', '1']) {
      for (const iva of ['0', '1']) {
        it(`respeta administración comisión=${comision}, póliza=${seguro} e IVA=${iva}`, () => {
          const vm = desdeAjustes({
            incluirAdministracionEnBaseComision: comision,
            incluirAdministracionEnBaseSeguro: seguro,
            comisionAplicaIva: iva,
          });
          expect(vm.calc.valorComision).toBe(comision === '1' ? 130000 : 100000);
          expect(vm.calc.valorSeguro).toBe(seguro === '1' ? 32500 : 25000);
          expect(vm.calc.ivaComision).toBe(iva === '1' ? (comision === '1' ? 24700 : 19000) : 0);
          expect(vm.calc.totalIngresos).toBe(1300000);
          expect(vm.calc.valorRentaRecibir).toBe(1000000 - vm.calc.valorComision - vm.calc.valorSeguro - vm.calc.ivaComision);
        });
      }
    }
  }

  it.each(['canon', 'canon_mas_iva'])('excluye administración cuando la base de póliza es %s', (base) => {
    const vm = desdeAjustes({ seguroBase: base });
    expect(vm.calc.valorSeguro).toBe(25000);
  });

  it('ignora la cuota almacenada si el inmueble no tiene administración', () => {
    const vm = desdeAjustes();
    vm.form.tieneAdministracion = false;
    expect(vm.calc.valorComision).toBe(100000);
    expect(vm.calc.valorSeguro).toBe(25000);
    expect(vm.calc.ivaComision).toBe(19000);
  });

  it('aplica IVA sobre el mínimo y respeta los valores fijos', () => {
    const vm = desdeAjustes({ comisionModalidad: 'porcentaje_con_minimo', comisionMinimaInmobiliaria: '150000', seguroModalidad: 'valor_fijo', seguroValorFijo: '40000' });
    expect(vm.calc.valorComision).toBe(150000);
    expect(vm.calc.ivaComision).toBe(28500);
    expect(vm.calc.valorSeguro).toBe(40000);
    vm.form.comision.modalidad = 'valor_fijo';
    vm.form.canon = 5000000;
    expect(vm.calc.valorComision).toBe(150000);
    expect(vm.calc.ivaComision).toBe(28500);
  });

  it('acepta booleanos y no interpreta la cadena cero como verdadero', () => {
    const vm = desdeAjustes({
      incluirAdministracionEnBaseComision: true, comisionAplicaIva: true,
      administracion: { incluirEnBaseSeguro: '0' },
      comision: { aplicaIva: '0' },
    });
    expect(vm.calc.valorComision).toBe(130000);
    expect(vm.calc.valorSeguro).toBe(25000);
    expect(vm.calc.ivaComision).toBe(0);
    expect(desdeAjustes({ comisionAplicaIva: true }).calc.ivaComision).toBe(24700);
  });

  it('carga todos los ajustes tardíos sin borrar el canon ni la cuota', () => {
    const vm = desdeAjustes();
    vm.configuracion = { ...vm.configuracion, comisionTotalInmobiliaria: '8',
      incluirAdministracionEnBaseComision: '0', incluirAdministracionEnBaseSeguro: '0',
      comisionAplicaIva: '0', seguroCanonArrendamiento: '3', seguroBase: 'canon' };
    opciones.watch.configuracion.handler.call(vm);
    expect(vm.form.canon).toBe(1000000);
    expect(vm.form.valorAdministracion).toBe(300000);
    expect(vm.form.tieneAdministracion).toBe(true);
    expect(vm.calc.valorComision).toBe(80000);
    expect(vm.calc.valorSeguro).toBe(30000);
    expect(vm.calc.ivaComision).toBe(0);
  });
});
