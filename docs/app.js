/* Catálogo JAT · versión estática de demostración (GitHub Pages).
   Los productos vienen de data/products.js; la lista se guarda en localStorage
   y los archivos Excel/PDF se generan en el navegador. */

const EMPRESA_DEFAULT = 'Sistemas Agropecuarios JAT';
const LOGO = 'assets/logo-jat.png';

const norm = s => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

function fecha() {
  const d = new Date(), p = n => String(n).padStart(2, '0');
  return `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

function store(key, value) {
  try {
    if (value === undefined) return JSON.parse(localStorage.getItem(key));
    localStorage.setItem(key, JSON.stringify(value));
  } catch (e) { return null; }
}

/* ---------- Exportación (independiente del DOM para poder probarla) ---------- */

// Líneas de datos del pedido: contacto (si lo hay) y si es para aparcería Pilgrim's.
function detalles(contacto, pilgrims) {
  return [contacto ? 'Contacto: ' + contacto : null, pilgrims ? "Aparcería Pilgrim's" : null].filter(Boolean);
}

// items: [{code, name, type, description, qty, img}] donde img = {dataUrl, ext, width, height} | null
// logo:  {dataUrl, ext, width, height} | null
// info:  líneas de detalles()
async function buildXlsx(ExcelJS, items, empresa, logo, info = []) {
  const wb = new ExcelJS.Workbook();
  const ws = wb.addWorksheet('Lista', { views: [{ state: 'frozen', ySplit: 4 }] });
  ws.columns = [16, 45, 16, 18, 10].map(width => ({ width }));

  ws.getRow(1).height = logo ? 50 : 24;
  ws.getCell('B1').value = empresa;
  ws.getCell('B1').font = { bold: true, size: 14 };
  ws.getCell('B1').alignment = { vertical: 'middle' };
  ws.getCell('B2').value = 'Lista de productos · ' + fecha();
  ws.getCell('B3').value = info.join(' · ');
  if (logo) {
    const id = wb.addImage({ base64: logo.dataUrl, extension: logo.ext });
    const s = 100 / Math.max(logo.width, logo.height);
    ws.addImage(id, { tl: { col: 0.05, row: 0.05 }, ext: { width: logo.width * s, height: logo.height * s } });
  }

  const head = ws.getRow(4);
  head.values = ['Código', 'Nombre', 'Tipo', 'Imagen', 'Cantidad'];
  head.eachCell(c => {
    c.font = { bold: true, color: { argb: 'FFFFFFFF' } };
    c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1F2937' } };
  });

  items.forEach((it, i) => {
    const row = ws.getRow(5 + i);
    row.values = [it.code, it.name, it.type, null, it.qty];
    row.alignment = { vertical: 'middle', wrapText: true };
    if (it.img) {
      row.height = 60;
      const id = wb.addImage({ base64: it.img.dataUrl, extension: it.img.ext });
      const s = 72 / Math.max(it.img.width, it.img.height);
      ws.addImage(id, { tl: { col: 3.05, row: 4 + i + 0.05 }, ext: { width: it.img.width * s, height: it.img.height * s } });
    }
  });
  return wb.xlsx.writeBuffer();
}

function buildPdf(jsPDF, items, empresa, logo, info = []) {
  const doc = new jsPDF({ unit: 'pt', format: 'a4' });
  const W = doc.internal.pageSize.getWidth(), M = 40;
  let x = M;
  if (logo) {
    const w = 70, h = w * logo.height / logo.width;
    doc.addImage(logo.dataUrl, logo.ext.toUpperCase(), M, 30, w, h, 'logo', 'FAST');
    x = M + w + 14;
  }
  doc.setFont('helvetica', 'bold').setFontSize(16).setTextColor(17, 24, 39).text(empresa, x, 50);
  doc.setFont('helvetica', 'normal').setFontSize(9).setTextColor(85, 85, 85).text('Lista de productos · ' + fecha(), x, 66);
  if (info.length) doc.text(info.join('  ·  '), x, 79);
  doc.setDrawColor(237, 28, 36).setLineWidth(2.5).line(M, 90, W - M, 90);

  doc.autoTable({
    startY: 102,
    margin: { left: M, right: M },
    head: [['Código', 'Imagen', 'Producto', 'Cant.']],
    body: items.map(it => [it.code, '', it.name + '\n' + it.type, String(it.qty)]),
    theme: 'grid',
    rowPageBreak: 'avoid',
    styles: { fontSize: 8, cellPadding: 5, valign: 'middle', lineColor: [221, 221, 221], textColor: [31, 41, 55] },
    headStyles: { fillColor: [31, 41, 55], textColor: 255 },
    columnStyles: {
      0: { cellWidth: 72 },
      1: { cellWidth: 64, minCellHeight: 58 },
      2: { fontStyle: 'bold' },
      3: { cellWidth: 36, halign: 'center' },
    },
    didDrawCell(data) {
      const img = data.section === 'body' && data.column.index === 1 && items[data.row.index]?.img;
      if (!img) return;
      const c = data.cell, max = Math.min(c.width, c.height) - 8;
      const s = max / Math.max(img.width, img.height), w = img.width * s, h = img.height * s;
      doc.addImage(img.dataUrl, img.ext.toUpperCase(), c.x + (c.width - w) / 2, c.y + (c.height - h) / 2, w, h, undefined, 'FAST');
    },
  });
  return doc;
}

function mensajeWhatsapp(items, empresa, info = []) {
  const lineas = items.map((it, i) => `${i + 1}. ${it.code} - ${it.name}\n    Cantidad: ${it.qty}`);
  return `*Lista de productos - ${empresa}*\n${[fecha(), ...info].join('\n')}\n\n${lineas.join('\n')}\n\nTotal de productos: ${items.length}`;
}

/* ---------- Interfaz ---------- */

async function loadImage(url) {
  try {
    const blob = await (await fetch(url)).blob();
    const dataUrl = await new Promise((ok, fail) => {
      const r = new FileReader(); r.onload = () => ok(r.result); r.onerror = fail; r.readAsDataURL(blob);
    });
    const el = new Image(); el.src = dataUrl; await el.decode();
    return { dataUrl, ext: blob.type.includes('png') ? 'png' : 'jpeg', width: el.naturalWidth, height: el.naturalHeight };
  } catch (e) {
    return null;
  }
}

function descargar(blob, nombre) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = nombre;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

function catalogo() {
  const all = window.PRODUCTS || [];
  const byCode = Object.fromEntries(all.map(p => [p.code, p]));
  const index = all.map(p => ({ p, text: norm([p.code, p.name, p.type, p.description].join(' ')) }));

  return {
    q: '', type: '', results: all, limit: 40, qtys: {}, busy: false, error: '', confirmClear: false,
    types: [...new Set(all.map(p => p.type))].sort(),
    list: (store('lista') || []).filter(i => byCode[i.code]),
    empresa: store('empresa') || '',
    contacto: store('contacto') || '',
    pilgrims: store('pilgrims') === true,

    init() {},
    info() { return detalles(this.contacto.trim(), this.pilgrims); },
    buscar() {
      const words = norm(this.q).split(/\s+/).filter(Boolean);
      this.results = index
        .filter(({ p, text }) => (!this.type || p.type === this.type) && words.every(w => text.includes(w)))
        .map(({ p }) => p);
      this.limit = 40;
    },
    listItems() { return this.list.map(i => ({ ...byCode[i.code], qty: i.qty })); },
    whatsappUrl() {
      return 'https://wa.me/?text=' + encodeURIComponent(mensajeWhatsapp(this.listItems(), this.empresa.trim() || EMPRESA_DEFAULT, this.info()));
    },
    guardar() {
      store('lista', this.list); store('empresa', this.empresa);
      store('contacto', this.contacto); store('pilgrims', this.pilgrims);
    },
    agregar(p) {
      const qty = Math.max(1, parseInt(this.qtys[p.code]) || 1);
      const found = this.list.find(i => i.code === p.code);
      found ? found.qty += qty : this.list.push({ code: p.code, qty });
      this.qtys[p.code] = '';
      this.guardar();
    },
    actualizar(code, qty) {
      const found = this.list.find(i => i.code === code);
      if (found) found.qty = Math.max(1, parseInt(qty) || 1);
      this.guardar();
    },
    quitar(code) { this.list = this.list.filter(i => i.code !== code); this.guardar(); },
    vaciar() { this.list = []; this.confirmClear = false; this.guardar(); },

    async exportar(format) {
      this.busy = true; this.error = '';
      try {
        const empresa = this.empresa.trim() || EMPRESA_DEFAULT;
        const logo = await loadImage(LOGO);
        const items = await Promise.all(this.listItems().map(async it => ({
          ...it, img: it.image ? await loadImage('img/' + it.image) : null,
        })));
        const nombre = 'lista-' + new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '');
        if (format === 'xlsx') {
          const buf = await buildXlsx(window.ExcelJS, items, empresa, logo, this.info());
          descargar(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), nombre + '.xlsx');
        } else {
          descargar(buildPdf(window.jspdf.jsPDF, items, empresa, logo, this.info()).output('blob'), nombre + '.pdf');
        }
      } catch (e) {
        console.error(e);
        this.error = 'No se pudo generar el archivo. Intenta de nuevo.';
      } finally {
        this.busy = false;
      }
    },
  };
}

if (typeof module !== 'undefined') module.exports = { buildXlsx, buildPdf, detalles, mensajeWhatsapp };
