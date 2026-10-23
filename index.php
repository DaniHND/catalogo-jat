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
    <h1 class="text-xl font-bold mb-3">Catálogo de productos</h1>
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
              <input type="number" min="1" x-model.number="qtys[p.id]" :placeholder="1"
                     class="w-16 rounded border border-slate-300 px-2 py-1 text-sm">
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
          <input type="number" min="1" :value="i.qty" @change="actualizar(i.id, $event.target.value)"
                 class="w-16 rounded border border-slate-300 px-2 py-1 text-sm">
          <button @click="quitar(i.id)" class="text-red-600 text-lg leading-none" title="Quitar">&times;</button>
        </li>
      </template>
    </ul>

    <div class="grid grid-cols-2 gap-2 mt-4" x-show="list.length">
      <a href="api.php?r=export&format=xlsx" class="text-center rounded-lg bg-green-600 hover:bg-green-700 text-white py-2 text-sm">Excel</a>
      <a href="api.php?r=export&format=pdf" class="text-center rounded-lg bg-red-600 hover:bg-red-700 text-white py-2 text-sm">PDF</a>
    </div>
  </aside>
</div>

<script>
function catalogo() {
  return {
    q: '', type: '', types: [], results: [], list: [], qtys: {}, loading: false,
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
      this.list = await this.post('list/add', { product_id: p.id, qty: this.qtys[p.id] || 1 });
      this.qtys[p.id] = '';
    },
    async actualizar(id, qty) { this.list = await this.post('list/update', { product_id: id, qty: parseInt(qty) || 1 }); },
    async quitar(id) { this.list = await this.post('list/remove', { product_id: id }); },
    async vaciar() { if (confirm('¿Vaciar la lista?')) this.list = await this.post('list/clear', {}); },
  };
}
</script>
</body>
</html>
