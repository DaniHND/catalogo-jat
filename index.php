<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Catálogo de productos</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-100 text-slate-800" x-data="catalogo()" x-init="init()">
<div class="max-w-7xl mx-auto p-4 grid lg:grid-cols-3 gap-4">

  <!-- Búsqueda y resultados -->
  <section class="lg:col-span-2">
    <header class="flex items-center gap-3 mb-4">
      <img src="assets/logo-jat.png" alt="JAT" class="h-12 w-auto">
      <h1 class="text-xl font-bold">Catálogo de productos</h1>
    </header>
    <div class="flex gap-2 mb-4">
      <input x-model="q" @input.debounce.300ms="buscar()" type="search" placeholder="Buscar por código o nombre…"
             class="flex-1 rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
      <select x-model="type" @change="buscar()" class="rounded-lg border border-slate-300 px-3 py-2">
        <option value="">Todos los tipos</option>
        <template x-for="t in types" :key="t"><option :value="t" x-text="t"></option></template>
      </select>
    </div>

    <p x-show="!loading && results.length === 0" class="text-slate-500">Sin resultados.</p>
    <div class="grid sm:grid-cols-2 gap-3">
      <template x-for="p in results" :key="p.id">
        <article class="bg-white rounded-xl shadow-sm p-3 flex gap-3">
          <div class="w-24 h-24 shrink-0 rounded-lg bg-slate-100 overflow-hidden flex items-center justify-center text-slate-400 text-xs">
            <img x-show="p.image_url" :src="p.image_url" :alt="p.name" class="w-full h-full object-cover">
            <span x-show="!p.image_url">Sin imagen</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-xs text-slate-500" x-text="p.code + ' · ' + p.type"></p>
            <h3 class="font-semibold leading-tight" x-text="p.name"></h3>
            <p class="text-sm text-slate-600 line-clamp-2" x-text="p.description"></p>
            <div class="flex items-center gap-2 mt-2">
              <input type="number" min="1" x-model.number="qtys[p.id]" :placeholder="1" title="Cantidad"
                     class="w-16 rounded border border-slate-300 px-2 py-1 text-sm">
              <input type="number" min="0" step="0.01" x-model.number="prices[p.id]" placeholder="Precio" title="Precio por unidad"
                     class="w-20 rounded border border-slate-300 px-2 py-1 text-sm">
              <button @click="agregar(p)" class="rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm px-3 py-1">Agregar</button>
            </div>
          </div>
        </article>
      </template>
    </div>
  </section>

  <!-- Lista -->
  <aside class="bg-white rounded-xl shadow-sm p-4 h-fit lg:sticky lg:top-4">
    <div class="flex items-center justify-between mb-3">
      <h2 class="font-bold">Mi lista <span class="text-slate-500 font-normal" x-text="'(' + list.length + ')'"></span></h2>
      <button x-show="list.length" @click="vaciar()" class="text-sm text-red-600 hover:underline">Vaciar</button>
    </div>

    <p x-show="!list.length" class="text-sm text-slate-500">Aún no has agregado productos.</p>
    <ul class="divide-y">
      <template x-for="i in list" :key="i.id">
        <li class="py-2 flex items-center gap-2">
          <div class="flex-1 min-w-0">
            <p class="text-xs text-slate-500" x-text="i.code"></p>
            <p class="text-sm font-medium truncate" x-text="i.name"></p>
          </div>
          <input type="number" min="1" :value="i.qty" @change="actualizar(i.id, $event.target.value, i.price)" title="Cantidad"
                 class="w-14 rounded border border-slate-300 px-2 py-1 text-sm">
          <input type="number" min="0" step="0.01" :value="i.price || ''" placeholder="Precio" @change="actualizar(i.id, i.qty, $event.target.value)" title="Precio por unidad"
                 class="w-20 rounded border border-slate-300 px-2 py-1 text-sm">
          <button @click="quitar(i.id)" class="text-red-600 text-lg leading-none" title="Quitar">&times;</button>
        </li>
      </template>
    </ul>

    <p x-show="total() > 0" class="mt-3 text-right text-sm font-semibold">Total: <span x-text="money(total())"></span></p>

    <div class="mt-4" x-show="list.length">
      <label for="empresa" class="block text-sm font-medium mb-1">Nombre de la empresa</label>
      <input id="empresa" x-model="empresa" @input="guardarEmpresa()" type="text" maxlength="120" placeholder="Escribe el nombre de la empresa"
             class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      <label for="contacto" class="block text-sm font-medium mb-1 mt-3">Nombre de contacto</label>
      <input id="contacto" x-model="contacto" @input="guardarEmpresa()" type="text" maxlength="120" placeholder="Escribe el nombre del contacto"
             class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      <label for="comentario" class="block text-sm font-medium mb-1 mt-3">Comentario</label>
      <textarea id="comentario" x-model="comentario" @input="guardarEmpresa()" rows="3" maxlength="500" placeholder="Escribe un comentario (aparecerá al final de la lista)"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
      <label class="flex items-center gap-2 mt-3 text-sm font-medium cursor-pointer">
        <input type="checkbox" x-model="pilgrims" @change="guardarEmpresa()" class="w-4 h-4 accent-blue-600">
        Es para aparcería Pilgrim's
      </label>
    </div>
    <div class="grid grid-cols-2 gap-2 mt-3" x-show="list.length">
      <a :href="exportUrl('xlsx')" class="text-center rounded-lg bg-green-600 hover:bg-green-700 text-white py-2 text-sm">Excel</a>
      <a :href="exportUrl('pdf')" class="text-center rounded-lg bg-red-600 hover:bg-red-700 text-white py-2 text-sm">PDF</a>
      <a :href="whatsappUrl()" target="_blank" rel="noopener"
         class="col-span-2 flex items-center justify-center gap-2 rounded-lg bg-[#25D366] hover:bg-[#1ebe5b] text-white py-2 text-sm font-medium">
        <svg viewBox="0 0 24 24" class="w-4 h-4 fill-current" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1-.3-.1-.5-.1-.7.1-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.3-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.4-.5c.2-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.8-.7 2-1.5.2-.7.2-1.3.2-1.5-.1-.1-.3-.2-.6-.3zM12 21.8c-1.8 0-3.5-.5-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4C2.7 15.6 2.2 13.8 2.2 12 2.2 6.6 6.6 2.2 12 2.2c2.6 0 5.1 1 6.9 2.9 1.8 1.8 2.9 4.3 2.9 6.9 0 5.4-4.4 9.8-9.8 9.8zM20.5 3.5C18.2 1.2 15.2 0 12 0 5.4 0 0 5.4 0 12c0 2.1.6 4.2 1.6 6L0 24l6.2-1.6c1.8 1 3.8 1.5 5.8 1.5 6.6 0 12-5.4 12-12 0-3.2-1.2-6.2-3.5-8.4z"/></svg>
        Enviar por WhatsApp
      </a>
    </div>
  </aside>
</div>

<script>
function catalogo() {
  return {
    q: '', type: '', types: [], results: [], list: [], qtys: {}, prices: {}, loading: false, empresa: '', contacto: '', comentario: '', pilgrims: false,
    exportUrl(format) {
      return 'api.php?' + new URLSearchParams({
        r: 'export', format, empresa: this.empresa.trim(), contacto: this.contacto.trim(), comentario: this.comentario.trim(), pilgrims: this.pilgrims ? '1' : '0',
      });
    },
    whatsappUrl() {
      const d = new Date(), p = n => String(n).padStart(2, '0');
      const fecha = `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
      const empresa = this.empresa.trim() || <?= json_encode((require __DIR__ . '/config.php')['empresa'], JSON_UNESCAPED_UNICODE) ?>;
      const lineas = this.list.map((it, i) => `${i + 1}. ${it.code} - ${it.name}\n    Cantidad: ${it.qty}${it.price > 0 ? `\n    Precio: ${this.money(it.price)} · Total: ${this.money(it.price * it.qty)}` : ''}`);
      const contacto = this.contacto.trim() ? `Contacto: ${this.contacto.trim()}\n` : '';
      const pilgrims = this.pilgrims ? "Aparcería Pilgrim's\n" : '';
      const texto = `*Lista de productos - ${empresa}*\n${fecha}\n${contacto}${pilgrims}\n${lineas.join('\n')}\n\nTotal de productos: ${this.list.length}${this.total() > 0 ? `\nPrecio total: ${this.money(this.total())}` : ''}${this.comentario.trim() ? `

Comentario: ${this.comentario.trim()}` : ''}`;
      return 'https://wa.me/?text=' + encodeURIComponent(texto);
    },
    money(n) { return '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    total() { return this.list.reduce((s, i) => s + i.price * i.qty, 0); },
    guardarEmpresa() {
      try {
        localStorage.setItem('empresa', this.empresa);
        localStorage.setItem('contacto', this.contacto);
        localStorage.setItem('comentario', this.comentario);
        localStorage.setItem('pilgrims', this.pilgrims ? '1' : '');
      } catch (e) {}
    },
    async api(r, opts = {}) {
      const res = await fetch('api.php?r=' + r, opts);
      const data = await res.json();
      if (!res.ok) { alert(data.error || 'Error'); throw new Error(data.error); }
      return data;
    },
    post(r, body) {
      return this.api(r, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    },
    async init() {
      try {
        this.empresa = localStorage.getItem('empresa') || '';
        this.contacto = localStorage.getItem('contacto') || '';
        this.comentario = localStorage.getItem('comentario') || '';
        this.pilgrims = localStorage.getItem('pilgrims') === '1';
      } catch (e) {}
      this.types = await this.api('types');
      this.list = await this.api('list');
      this.buscar();
    },
    async buscar() {
      this.loading = true;
      const qs = new URLSearchParams({ q: this.q, type: this.type });
      this.results = await this.api('products&' + qs);
      this.loading = false;
    },
    async agregar(p) {
      this.list = await this.post('list/add', { product_id: p.id, qty: this.qtys[p.id] || 1, price: this.prices[p.id] ?? '' });
      this.qtys[p.id] = ''; this.prices[p.id] = '';
    },
    async actualizar(id, qty, price) { this.list = await this.post('list/update', { product_id: id, qty: parseInt(qty) || 1, price: parseFloat(price) || 0 }); },
    async quitar(id) { this.list = await this.post('list/remove', { product_id: id }); },
    async vaciar() { if (confirm('¿Vaciar la lista?')) this.list = await this.post('list/clear', {}); },
  };
}
</script>
</body>
</html>
