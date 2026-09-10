<template>
  <div class="kt-card admin-data-table w-full">
    <div class="kt-card-header flex-wrap gap-3">
      <div class="flex flex-col gap-1">
        <h3 class="kt-card-title">{{ tituloTabela }}</h3>
        <span v-if="subtituloTabela" class="text-2sm text-secondary-foreground">
          {{ subtituloTabela }}
        </span>
      </div>

      <div v-if="showSearch" class="kt-input kt-input-sm max-w-64">
        <i class="ki-filled ki-magnifier text-muted-foreground"></i>
        <input
          type="text"
          :placeholder="searchPlaceholder"
          v-model="search"
          @input="onSearch"
          @keyup.enter="onSearch"
        />
      </div>
    </div>

    <div class="kt-card-table">
      <div
        class="grid"
        :id="datatableId"
      >
        <div ref="tableBodyContainer" class="kt-scrollable-x-auto">
          <table
            class="kt-table kt-table-border table-auto"
            :id="tableIdResolved"
          >
            <thead>
              <tr>
                <th v-if="selectable" class="admin-table-col-check">
                  <input
                    class="kt-checkbox kt-checkbox-sm"
                    type="checkbox"
                    @change="toggleAll"
                    :checked="allSelected"
                    :disabled="!filteredData.length"
                  />
                </th>

                <th v-if="hasActions" class="admin-table-col-actions">
                  <span class="kt-table-col">
                    <span class="kt-table-col-label">Ações</span>
                  </span>
                </th>

                <th
                  v-for="(t, key) in titulos"
                  :key="key"
                  :class="getHeaderCellClass(t)"
                  :data-kt-datatable-column="key"
                  :aria-sort="getSortAria(key)"
                >
                  <button
                    type="button"
                    class="kt-table-col"
                    :class="{ 'is-disabled': !isColumnSortable(t) }"
                    :disabled="!isColumnSortable(t)"
                    @click="ordenarPor(key)"
                  >
                    <span class="kt-table-col-label">{{ t.titulo }}</span>
                    <span v-if="isColumnSortable(t)" class="kt-table-col-sort"></span>
                  </button>
                </th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="!filteredData.length">
                <td :colspan="tableColspan" class="admin-table-empty">
                  {{ emptyText }}
                </td>
              </tr>

              <tr
                v-for="(obj, idx) in filteredData"
                :key="obj.id || idx"
                :data-row-id="obj.id ?? ''"
                :class="{
                  checked: selectedItems.includes(obj.id),
                  'cursor-pointer': linhaClicavel
                }"
                @click="onRowClick(obj)"
              >
                <td v-if="selectable">
                  <input
                    type="checkbox"
                    class="kt-checkbox kt-checkbox-sm"
                    v-model="selectedItems"
                    :value="obj.id"
                    @click.stop
                  />
                </td>

                <td v-if="hasActions" class="admin-table-cell-actions">
                  <div
                    class="table-actions-dropdown"
                    data-kt-dropdown="true"
                    data-kt-dropdown-trigger="click"
                    data-kt-dropdown-placement="bottom-start"
                    :class="{ open: activeActionMenu === rowActionKey(obj, idx) }"
                    @click.stop
                  >
                    <button
                      class="kt-btn kt-btn-md kt-btn-icon kt-btn-outline rounded-full"
                      type="button"
                      data-kt-dropdown-toggle="true"
                      aria-label="Abrir ações"
                      aria-haspopup="menu"
                      :aria-expanded="activeActionMenu === rowActionKey(obj, idx)"
                      @click.stop="toggleActionMenu(rowActionKey(obj, idx))"
                    >
                      <i class="ki-filled ki-dots-vertical text-md"></i>
                    </button>

                    <div
                      v-show="activeActionMenu === rowActionKey(obj, idx)"
                      class="kt-dropdown-menu table-actions-dropdown-menu w-44"
                      data-kt-dropdown-menu="true"
                      role="menu"
                      :class="{ open: activeActionMenu === rowActionKey(obj, idx) }"
                    >
                      <ul class="kt-dropdown-menu-sub">
                        <li v-if="visualizar?.visivel" role="none">
                          <button
                            type="button"
                            class="kt-dropdown-menu-link"
                            role="menuitem"
                            :data-bs-toggle="visualizar.dataToggle"
                            :data-bs-target="visualizar.dataTarget"
                            @click.stop="handleActionClick(obj, 'visualizar')"
                          >
                            <i :class="getActionIcon('visualizar')"></i>
                            <span>{{ visualizar.label || 'Ver' }}</span>
                          </button>
                        </li>

                        <li v-if="atualizar?.visivel" role="none">
                          <button
                            type="button"
                            class="kt-dropdown-menu-link"
                            role="menuitem"
                            :data-bs-toggle="atualizar.dataToggle"
                            :data-bs-target="atualizar.dataTarget"
                            @click.stop="handleActionClick(obj, 'atualizar')"
                          >
                            <i :class="getActionIcon('atualizar')"></i>
                            <span>{{ atualizar.label || 'Editar' }}</span>
                          </button>
                        </li>

                        <li v-if="remover?.visivel" role="none">
                          <button
                            type="button"
                            class="kt-dropdown-menu-link"
                            role="menuitem"
                            :data-bs-toggle="remover.dataToggle"
                            :data-bs-target="remover.dataTarget"
                            @click.stop="handleActionClick(obj, 'remover')"
                          >
                            <i :class="getActionIcon('remover')"></i>
                            <span>{{ remover.label || 'Remover' }}</span>
                          </button>
                        </li>
                      </ul>
                    </div>
                  </div>
                </td>

                <td
                  v-for="(t, chaveValor) in titulos"
                  :key="chaveValor"
                  :class="getBodyCellClass(t)"
                >

              <!-- Device info -->
              <div v-if="t.tipo === 'device-info'" class="flex flex-col">
                <template v-if="obj[chaveValor]">
                  <span class="font-bold text-capitalize">
                    <span
                      class="kt-badge me-2"
                      :class="obj[chaveValor].so === 'android' ? 'kt-badge-success kt-badge-outline' : 'kt-badge-secondary kt-badge-outline'"
                    >
                      {{ obj[chaveValor].so }}
                    </span>
                    {{ obj[chaveValor].modelo }}
                  </span>

                  <small class="text-muted-foreground">
                    SO {{ obj[chaveValor].versao_so }} • App {{ obj[chaveValor].versao_app }}
                  </small>

                  <small v-if="obj[chaveValor].geofence_version" class="text-muted-foreground">
                    Regiões {{ obj[chaveValor].geofence_version }}
                    <template v-if="obj[chaveValor].geofence_semantic_version">
                      • {{ obj[chaveValor].geofence_semantic_version }}
                    </template>
                  </small>
                </template>

                <span v-else class="text-muted-foreground">—</span>
              </div>

              <!-- Texto comum -->
              <span v-if="t.tipo === 'texto'" v-html="comQuebraLinha(obj[chaveValor])"></span>

              <!-- Número com pontuação (1, 10, 200, 3.000, 10.500, etc) -->
              <span v-else-if="t.tipo === 'number'">
                {{ formatarNumero(obj[chaveValor], t) }}
              </span>

              <!-- Número + mini gráfico Apex (sparkline de barras) -->
              <div v-else-if="t.tipo === 'apex-mini-bar'" class="table-mini-apex-cell">
                <span class="table-mini-apex-value">
                  {{ formatarNumero(getMiniChartValue(obj[chaveValor]), t) }}
                </span>

                <div
                  class="table-mini-apex-chart"
                  :style="{ height: `${t.height || 34}px`, width: t.width || '120px' }"
                  data-apex-mini-chart="1"
                  :data-chart-key="miniChartContainerKey(obj, chaveValor, idx)"
                  :data-chart-row-id="obj.id ?? idx"
                  :data-chart-row-index="idx"
                  :data-chart-field="chaveValor"
                ></div>
              </div>

              <!-- Data -->
              <span v-else-if="t.tipo === 'data'">
                {{ formatarData(obj[chaveValor], false) }}
              </span>

              <!-- Data/hora -->
              <span v-else-if="t.tipo === 'datahora'">
                {{ formatarData(obj[chaveValor], true) }}
              </span>

              <!-- Imagem simples -->
              <img v-else-if="t.tipo === 'imagem'" :src="'/storage/' + obj[chaveValor]" width="30" height="30" />

              <!-- Data/hora ISO -->
              <span v-else-if="t.tipo === 'datahoraiso'">
                {{ obj[chaveValor] }}
              </span>

              <!-- Switch de Status (booleano/numérico) -->
              <div v-else-if="t.tipo === 'status'">
                <input
                  class="kt-switch kt-switch-md"
                  type="checkbox"
                  :checked="obj[chaveValor] == 1"
                  @change="$emit('status-toggled', { id: obj.id, campo: chaveValor, valor: !obj[chaveValor] })"
                  @click.stop
                />
                <label class="kt-form-label"></label>
              </div>

              <!-- Usuário com foto -->
              <div v-else-if="t.tipo === 'usuario' && obj[chaveValor]" class="flex items-center">
                <div class="kt-symbol kt-symbol-circle kt-symbol-40 overflow-hidden me-3">
                  <div v-if="obj[chaveValor].foto" class="kt-symbol kt-symbol-label">
                    <img :src="obj[chaveValor].foto" alt="Foto" class="h-9 rounded-full" @error="$event.target.src = '/assets/media/avatars/blank.png'" />
                  </div>
                  <div v-else class="kt-symbol kt-symbol-label text-xl bg-primary-soft text-primary">
                    {{ obj[chaveValor].nome?.substring(0, 1).toUpperCase() }}
                  </div>
                </div>
                <div class="flex flex-col">
                  <a href="#" class="text-mono hover:text-primary">{{ obj[chaveValor].nome }}</a>
                  <span class="text-xs text-secondary-foreground">{{ obj[chaveValor].email }}</span>
                </div>
              </div>

              <!-- Usuário com máscara de e-mail -->
              <div v-else-if="t.tipo === 'usuario-mascarado' && obj[chaveValor]" class="flex items-center">
                <div class="kt-symbol kt-symbol-circle kt-symbol-40 overflow-hidden me-3">
                  <div v-if="obj[chaveValor].foto" class="kt-symbol kt-symbol-label">
                    <img :src="obj[chaveValor].foto" alt="Foto" class="h-9 rounded-full" @error="$event.target.src = '/assets/media/avatars/blank.png'" />
                  </div>
                  <div v-else class="kt-symbol kt-symbol-label text-xl bg-primary-soft text-primary">
                    {{ obj[chaveValor].nome?.substring(0, 1).toUpperCase() }}
                  </div>
                </div>
                <div class="flex flex-col">
                  <a href="#" class="text-mono hover:text-primary">{{ obj[chaveValor].nome }}</a>
                  <span class="text-xs text-secondary-foreground">
                    {{ mascararEmail(obj[chaveValor].email) }}
                  </span>
                </div>
              </div>

              <!-- Telefone mascarado -->
              <span v-else-if="t.tipo === 'telefone'">
                {{ mascararTelefone(obj[chaveValor]) }}
              </span>

              <!-- CPF mascarado -->
              <span v-else-if="t.tipo === 'cpf'">
                {{ mascararCpf(obj[chaveValor]) }}
              </span>

              <!-- Valor monetário -->
              <span v-else-if="t.tipo === 'valor'">
                {{ Number(parseNumeroBr(obj[chaveValor])).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}
              </span>

              <!-- Saldo escondido -->
              <div v-else-if="t.tipo === 'saldo'" class="flex items-center gap-2">
                <span>•••••••••</span>
                <button class="kt-btn kt-btn-md kt-btn-icon kt-btn-outline rounded-full">
                  <i class="mdi mdi-eye"></i>
                </button>
              </div>

              <!-- Informações (objeto chave/valor) -->
              <div v-else-if="t.tipo === 'informacoes' && obj[chaveValor]">
                <div v-for="(valor, chave) in obj[chaveValor]" :key="chave">
                  <strong>{{ formatarChave(chave) }}:</strong> {{ valor }}
                </div>
              </div>

              <!-- Totais de sorteio com ações -->
              <div v-else-if="t.tipo === 'raffle-totals' && obj[chaveValor]" class="raffle-totals-cell">
                <div class="raffle-total-line">
                  <span>Prêmios</span>
                  <strong>{{ obj[chaveValor].premios ?? 0 }}</strong>
                </div>

                <button
                  type="button"
                  class="raffle-total-action"
                  @click.stop="$emit('row-action', obj, 'raffle-participants')"
                >
                  <span>Participantes</span>
                  <strong>{{ obj[chaveValor].participantes ?? 0 }}</strong>
                </button>

                <button
                  type="button"
                  class="raffle-total-action"
                  @click.stop="$emit('row-action', obj, 'raffle-tickets')"
                >
                  <span>Bilhetes</span>
                  <strong>{{ obj[chaveValor].bilhetes ?? 0 }}</strong>
                </button>
              </div>

              <!-- Badge genérico com maps -->
              <div v-else-if="t.tipo === 'badge'">
                <span
                  class="kt-badge font-semibold p-3"
                  :class="t.classMap?.[obj[chaveValor]] || 'kt-badge-secondary kt-badge-outline'"
                >
                  <i
                    v-if="t.iconMap && t.iconMap[obj[chaveValor]]"
                    class="me-1"
                    :class="t.iconMap[obj[chaveValor]]"
                  ></i>
                  {{ t.labelMap?.[obj[chaveValor]] ?? obj[chaveValor] }}
                </span>
              </div>

              <!-- Layout genérico tipo "detalhado" (subtexto aceita string | string[] | function) -->
              <div v-else-if="t.tipo === 'detalhado'">
                <!-- Badge (se configurado) -->
                <span v-if="t.subcampos?.badge"
                  class="kt-badge font-bold p-2 mb-1"
                  :class="t.subcampos.badge.classMap?.[obj[t.subcampos.badge.campo]] || 'kt-badge-secondary kt-badge-outline'"
                >
                  {{ t.subcampos.badge.labelMap?.[obj[t.subcampos.badge.campo]] || obj[t.subcampos.badge.campo] }}
                </span>

                <div class="clear"></div>

                <!-- Título -->
                <span v-if="t.subcampos?.titulo" class="font-semibold text-mono">
                  {{ obj[t.subcampos.titulo] }}
                </span>

                <div class="clear"></div>

                <!-- Subtexto -->
                <small v-if="t.subcampos?.subtexto" class="text-xs text-muted-foreground">
                  {{ getSubtextoDetalhado(obj, t) }}
                </small>
              </div>

              <!-- Thumbnail de imagem com fallback -->
              <div v-else-if="t.tipo === 'thumbnail'" class="kt-symbol">
                <img
                  :src="resolveThumbnailUrl(obj[chaveValor])"
                  alt="thumbnail"
                  class="table-thumbnail object-cover"
                  :style="getThumbnailStyle(t)"
                  @error="(e) => { if (!e.target.dataset.fallbackApplied) { e.target.dataset.fallbackApplied = '1'; e.target.src = '/assets/media/no-image.webp' } }"
                />
              </div>

              <!-- Contador de itens (ex: anexos) -->
              <span v-else-if="t.tipo === 'count'">
                <span class="kt-badge kt-badge-secondary kt-badge-outline p-2">{{ getCount(obj[chaveValor]) }}</span>
              </span>

              <!-- Valor padrão -->
              <span v-else>
                {{ obj[chaveValor] }}
              </span>
            </td>
          </tr>
            </tbody>
          </table>
        </div>

        <div
          v-if="!semPaginacao"
          class="kt-card-footer justify-center md:justify-between flex-col md:flex-row gap-5 text-secondary-foreground text-sm font-medium"
        >
          <div class="flex items-center gap-2 order-2 md:order-1">
            Mostrar
            <select
              class="kt-select w-20"
              name="perpage"
              v-model="itensPorPagina"
              @change="onChangePerPage"
            >
              <option v-for="opcao in opcoesPorPagina" :key="opcao" :value="opcao">
                {{ opcao }}
              </option>
            </select>
            por página
          </div>

          <div class="flex items-center gap-4 order-1 md:order-2">
            <span>{{ paginationInfo }}</span>

            <div class="kt-datatable-pagination">
              <button
                type="button"
                class="kt-datatable-pagination-button kt-datatable-pagination-prev"
                :disabled="currentPage === 1"
                @click="mudarPagina(currentPage - 1)"
              >
                <i class="ki-filled ki-black-left"></i>
              </button>

              <button
                v-for="n in visiblePages"
                :key="n"
                type="button"
                class="kt-datatable-pagination-button"
                :class="{ active: n === currentPage }"
                @click="mudarPagina(n)"
              >
                {{ n }}
              </button>

              <button
                type="button"
                class="kt-datatable-pagination-button kt-datatable-pagination-next"
                :disabled="currentPage === paginas"
                @click="mudarPagina(currentPage + 1)"
              >
                <i class="ki-filled ki-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { formatDate, formatDateTime } from '../utils/dateFormat'

export default {
  props: {
    dados: { type: Array, default: () => [] },
    titulos: { type: Object, default: () => ({}) },
    visualizar: { type: Object, default: () => ({ visivel: false }) },
    atualizar: { type: Object, default: () => ({ visivel: false }) },
    remover: { type: Object, default: () => ({ visivel: false }) },
    termoBusca: { type: String, default: '' },
    linhaClicavel: { type: Boolean, default: false },
    semPaginacao: { type: Boolean, default: false },
    tituloTabela: { type: String, default: 'Listagem' },
    subtituloTabela: { type: String, default: '' },
    searchPlaceholder: { type: String, default: 'Buscar...' },
    emptyText: { type: String, default: 'Nenhum registro encontrado.' },
    showSearch: { type: Boolean, default: true },
    selectable: { type: Boolean, default: true },
    tableId: { type: String, default: '' }
  },
  emits: ['status-toggled', 'set-store', 'row-action', 'update:selected', 'row-click'],
  data() {
    return {
      isMobileOrTablet: false,
      selectedItems: [],
      search: this.termoBusca || '',
      currentPage: 1,
      itensPorPagina: 10,
      opcoesPorPagina: [10, 20, 50, 100, 200, 300, 400, 500],
      ordenacao: { campo: null, direcao: 'asc' },
      activeActionMenu: null,
      datatableId: `admin_datatable_${Math.random().toString(36).slice(2, 10)}`,
      miniApexCharts: {},
      miniApexChartSignatures: {},
      miniApexMissingWarned: false
    }
  },
  computed: {
    tableIdResolved() {
      return this.tableId || `${this.datatableId}_table`;
    },
    hasActions() {
      return Boolean(this.visualizar?.visivel || this.atualizar?.visivel || this.remover?.visivel);
    },
    processedData() {
      let resultado = [...this.dados];

      if (this.search) {
        const q = this.search.toLowerCase();
        resultado = resultado.filter(obj =>
          Object.values(obj).some(value => {
            if (typeof value === 'object' && value !== null) {
              return Object.values(value)
                .join(' ')
                .toLowerCase()
                .includes(q);
            }
            return String(value).toLowerCase().includes(q);
          })
        );
      }

      if (this.ordenacao.campo) {
        const conf = this.titulos[this.ordenacao.campo] || {};
        const tipo = conf.tipo;

        resultado.sort((a, b) => {
          let va = a[this.ordenacao.campo];
          let vb = b[this.ordenacao.campo];

          if (tipo === 'number' || tipo === 'numero' || tipo === 'valor' || tipo === 'count' || tipo === 'apex-mini-bar') {
            if (tipo === 'count') {
              va = this.getCount(va);
              vb = this.getCount(vb);
            } else if (tipo === 'apex-mini-bar') {
              va = this.getMiniChartValue(va);
              vb = this.getMiniChartValue(vb);
            } else {
              va = this.parseNumeroBr(va);
              vb = this.parseNumeroBr(vb);
            }

            if (va < vb) return this.ordenacao.direcao === 'asc' ? -1 : 1;
            if (va > vb) return this.ordenacao.direcao === 'asc' ? 1 : -1;
            return 0;
          }

          if (va && typeof va === 'object') va = va.nome || va.email || Object.values(va)[0] || '';
          if (vb && typeof vb === 'object') vb = vb.nome || vb.email || Object.values(vb)[0] || '';

          va = va?.toString().toLowerCase() ?? '';
          vb = vb?.toString().toLowerCase() ?? '';

          if (va < vb) return this.ordenacao.direcao === 'asc' ? -1 : 1;
          if (va > vb) return this.ordenacao.direcao === 'asc' ? 1 : -1;
          return 0;
        });
      }

      return resultado;
    },
    filteredData() {
      if (this.semPaginacao) {
        return this.processedData;
      }

      return this.processedData.slice(this.startIdx, this.endIdx);
    },
    paginas() {
      if (this.semPaginacao) return 1;

      const total = Math.ceil(this.processedData.length / this.itensPorPagina);
      return Math.max(total, 1);
    },
    startIdx() {
      return (this.currentPage - 1) * this.itensPorPagina;
    },
    endIdx() {
      return this.startIdx + this.itensPorPagina;
    },
    allSelected() {
      return this.filteredData.length && this.filteredData.every(item => this.selectedItems.includes(item.id));
    },
    tableColspan() {
      return Object.keys(this.titulos || {}).length + (this.selectable ? 1 : 0) + (this.hasActions ? 1 : 0);
    },
    paginationInfo() {
      const total = this.processedData.length;
      if (!total) return '0 registros';

      const start = this.semPaginacao ? 1 : this.startIdx + 1;
      const end = this.semPaginacao ? total : Math.min(this.endIdx, total);
      return `${start}-${end} de ${total}`;
    },
    visiblePages() {
      const total = this.paginas;
      const maxButtons = 5;

      if (total <= maxButtons) {
        return Array.from({ length: total }, (_, index) => index + 1);
      }

      let start = Math.max(1, this.currentPage - 2);
      let end = Math.min(total, start + maxButtons - 1);

      if (end - start + 1 < maxButtons) {
        start = Math.max(1, end - maxButtons + 1);
      }

      return Array.from({ length: end - start + 1 }, (_, index) => start + index);
    }
  },
  watch: {
    selectedItems: {
      handler(novo) {
        // Emite seleção para telas que precisarem de ações em massa
        this.$emit('update:selected', novo);
      },
      deep: true
    },
    paginas(novoTotal) {
      if (this.currentPage > novoTotal) {
        this.currentPage = novoTotal || 1;
      }
    }
  },
  methods: {
    checkScreen() {
      this.isMobileOrTablet = window.innerWidth < 992;
    },
    isColumnSortable(coluna = {}) {
      return coluna.sortable !== false && coluna.ordenavel !== false;
    },
    getSortAria(key) {
      if (this.ordenacao.campo !== key) return 'none';
      return this.ordenacao.direcao === 'asc' ? 'asc' : 'desc';
    },
    getHeaderCellClass(coluna = {}) {
      const classes = [];

      if (coluna.headerClass) classes.push(coluna.headerClass);
      if (coluna.thClass) classes.push(coluna.thClass);
      if (coluna.widthClass) classes.push(coluna.widthClass);
      if (coluna.width) classes.push(coluna.width);

      if (!classes.length) {
        classes.push(this.getDefaultColumnClass(coluna));
      }

      return classes;
    },
    getBodyCellClass(coluna = {}) {
      const classes = [];

      if (coluna.cellClass) classes.push(coluna.cellClass);
      if (coluna.tdClass) classes.push(coluna.tdClass);

      if (coluna.tipo === 'number' || coluna.tipo === 'numero' || coluna.tipo === 'valor' || coluna.tipo === 'count') {
        classes.push('text-foreground', 'font-normal');
      }

      return classes;
    },
    getDefaultColumnClass(coluna = {}) {
      const tipo = coluna.tipo;

      if (tipo === 'status' || tipo === 'count' || tipo === 'number' || tipo === 'numero') return 'admin-table-col-sm';
      if (tipo === 'apex-mini-bar') return 'admin-table-col-chart';
      if (tipo === 'usuario' || tipo === 'usuario-mascarado' || tipo === 'detalhado') return 'admin-table-col-lg';
      if (tipo === 'data' || tipo === 'datahora' || tipo === 'datahoraiso') return 'admin-table-col-md';

      return 'admin-table-col-md';
    },
    rowActionKey(obj, idx) {
      return String(obj?.id ?? `row-${idx}`);
    },
    toggleActionMenu(key) {
      this.activeActionMenu = this.activeActionMenu === key ? null : key;
    },
    closeActionMenu() {
      this.activeActionMenu = null;
    },
    handleActionClick(obj, acao) {
      this.setStore(obj, acao);
      this.closeActionMenu();
    },
    getActionIcon(acao) {
      if (acao === 'remover') return 'ki-filled ki-trash';
      if (acao === 'atualizar') return 'ki-filled ki-pencil';
      return 'ki-filled ki-search-list';
    },
    onChangePerPage() {
      this.currentPage = 1;
      this.closeActionMenu();
    },
    mudarPagina(pagina) {
      if (pagina < 1 || pagina > this.paginas) return;
      this.currentPage = pagina;
      this.closeActionMenu();
    },
    onSearch() {
      this.currentPage = 1;
      this.closeActionMenu();
    },
    ordenarPor(campo) {
      const coluna = this.titulos?.[campo] || {};
      if (!this.isColumnSortable(coluna)) return;

      if (this.ordenacao.campo === campo) {
        this.ordenacao.direcao = this.ordenacao.direcao === 'asc' ? 'desc' : 'asc';
      } else {
        this.ordenacao.campo = campo;
        this.ordenacao.direcao = 'asc';
      }
    },
    toggleAll(e) {
      if (!this.selectable) return;

      if (e.target.checked) {
        this.selectedItems = this.filteredData.map(item => item.id);
      } else {
        this.selectedItems = [];
      }
    },
    setStore(obj, acao = 'visualizar') {
      this.$emit('set-store', obj, acao);
      this.$emit('row-action', { obj, acao });
    },
    onRowClick(obj) {
      if (!this.linhaClicavel) return;
      this.closeActionMenu();
      this.$emit('row-click', obj);
    },
    setRowSelectedById(id, options = {}) {
      if (id === null || id === undefined) return false;

      const checked = options.checked !== false;
      const unique = options.unique === true;
      const targetId = String(id);

      const dataItem = [...(this.filteredData || []), ...(this.dados || [])]
        .find(item => String(item?.id) === targetId);

      const valueToStore = dataItem?.id ?? id;
      const hasSelected = this.selectedItems.some(item => String(item) === targetId);

      if (checked) {
        if (unique) {
          const jaUnicoSelecionado =
            this.selectedItems.length === 1 &&
            String(this.selectedItems[0]) === targetId;

          if (!jaUnicoSelecionado) {
            this.selectedItems = [valueToStore];
          }

          return true;
        }

        if (hasSelected) return true;
        this.selectedItems = unique ? [valueToStore] : [...this.selectedItems, valueToStore];
        return true;
      }

      if (!hasSelected) return true;
      this.selectedItems = this.selectedItems.filter(item => String(item) !== targetId);
      return true;
    },
    clearSelectedRows() {
      if (!this.selectedItems.length) return false;
      this.selectedItems = [];
      return true;
    },
    scrollToRowById(id, options = {}) {
      if (id === null || id === undefined) return false;

      const targetId = String(id);
      const rows = this.$el?.querySelectorAll?.('tbody tr[data-row-id]');
      const row = Array.from(rows || []).find(item => item.dataset.rowId === targetId);

      if (!row) return false;

      const behavior = options.behavior || 'smooth';
      const block = options.block || 'center';

      row.scrollIntoView({ behavior, block, inline: 'nearest' });
      return true;
    },
    hasMiniApexColumns() {
      return Object.values(this.titulos || {}).some(coluna => coluna?.tipo === 'apex-mini-bar');
    },
    miniChartContainerKey(obj, campo, idx) {
      const rowId = obj?.id ?? `row-${idx}`;
      return `${campo}::${rowId}`;
    },
    getMiniChartValue(valor) {
      if (valor && typeof valor === 'object') {
        return this.parseNumeroBr(valor.value ?? 0);
      }

      return this.parseNumeroBr(valor);
    },
    getMiniChartPayload(valor, conf = {}) {
      const fallbackSize = 7;
      const fallbackSeries = Array.from({ length: fallbackSize }, () => 0);
      const fallbackLabels = Array.from({ length: fallbackSize }, (_, i) => `D${i + 1}`);

      if (!valor || typeof valor !== 'object') {
        return {
          value: this.getMiniChartValue(valor),
          series: fallbackSeries,
          labels: fallbackLabels,
          color: conf.color || '#009EF7'
        };
      }

      const rawSeries = Array.isArray(valor.series) ? valor.series : [];
      const series = (rawSeries.length ? rawSeries : fallbackSeries)
        .slice(-fallbackSize)
        .map(item => {
          const numero = Number(item);
          return Number.isFinite(numero) ? numero : 0;
        });

      let labels = Array.isArray(valor.labels) ? valor.labels.map(item => String(item)) : [];
      labels = labels.slice(-series.length);
      if (labels.length !== series.length) {
        labels = Array.from({ length: series.length }, (_, i) => `D${i + 1}`);
      }

      return {
        value: this.getMiniChartValue(valor),
        series,
        labels,
        color: valor.color || conf.color || '#009EF7'
      };
    },
    resolveMiniChartContext(el) {
      const field = el?.dataset?.chartField;
      if (!field) return null;

      const rowId = String(el?.dataset?.chartRowId ?? '');
      const rowIndex = Number(el?.dataset?.chartRowIndex);

      let row = null;

      if (rowId) {
        row = (this.filteredData || []).find(item => String(item?.id ?? '') === rowId) || null;
      }

      if (!row && Number.isInteger(rowIndex) && rowIndex >= 0) {
        row = this.filteredData?.[rowIndex] || null;
      }

      if (!row) return null;

      return {
        row,
        field,
        conf: this.titulos?.[field] || {}
      };
    },
    buildMiniApexChartOptions(payload, conf = {}) {
      const height = Number(conf.height) > 0 ? Number(conf.height) : 34;
      const categories = payload.labels || [];

      return {
        chart: {
          type: 'bar',
          height,
          sparkline: { enabled: true },
          animations: { enabled: false },
          toolbar: { show: false },
          parentHeightOffset: 0
        },
        series: [
          {
            name: conf.titulo || 'Métrica',
            data: payload.series || []
          }
        ],
        colors: [payload.color || conf.color || '#009EF7'],
        dataLabels: { enabled: false },
        stroke: { width: 0 },
        grid: {
          show: false,
          padding: { top: 0, bottom: 0, left: 0, right: 0 }
        },
        plotOptions: {
          bar: {
            columnWidth: '70%',
            borderRadius: 2
          }
        },
        xaxis: {
          categories,
          labels: { show: false },
          axisBorder: { show: false },
          axisTicks: { show: false },
          tooltip: { enabled: false }
        },
        yaxis: {
          show: false,
          min: 0
        },
        tooltip: {
          theme: 'light',
          x: {
            formatter: (_, { dataPointIndex }) => categories[dataPointIndex] || ''
          },
          y: {
            formatter: (value) => Number(value || 0).toLocaleString('pt-BR')
          }
        }
      };
    },
    destroyMiniApexChartByKey(key) {
      const chart = this.miniApexCharts?.[key];
      if (chart?.destroy) {
        try {
          chart.destroy();
        } catch (error) {
          // Ignora erros de destroy quando o nó já foi removido.
        }
      }

      if (this.miniApexCharts?.[key]) {
        delete this.miniApexCharts[key];
      }

      if (this.miniApexChartSignatures?.[key]) {
        delete this.miniApexChartSignatures[key];
      }
    },
    destroyMiniApexCharts(keysToKeep = null) {
      const keep = keysToKeep instanceof Set ? keysToKeep : null;
      Object.keys(this.miniApexCharts || {}).forEach((key) => {
        if (keep && keep.has(key)) return;
        this.destroyMiniApexChartByKey(key);
      });
    },
    renderMiniApexCharts() {
      if (!this.hasMiniApexColumns()) {
        this.destroyMiniApexCharts();
        return;
      }

      const containers = Array.from(
        this.$el?.querySelectorAll?.('[data-apex-mini-chart="1"]') || []
      );

      if (!containers.length) {
        this.destroyMiniApexCharts();
        return;
      }

      if (!window?.ApexCharts) {
        if (!this.miniApexMissingWarned) {
          console.warn('ApexCharts não está disponível para renderizar mini gráficos da tabela.');
          this.miniApexMissingWarned = true;
        }
        this.destroyMiniApexCharts();
        return;
      }

      const activeKeys = new Set();

      containers.forEach((el) => {
        const chartKey = el?.dataset?.chartKey;
        if (!chartKey) return;

        activeKeys.add(chartKey);

        const context = this.resolveMiniChartContext(el);
        if (!context) {
          this.destroyMiniApexChartByKey(chartKey);
          return;
        }

        const payload = this.getMiniChartPayload(context.row?.[context.field], context.conf);
        const signature = JSON.stringify({
          value: payload.value,
          series: payload.series,
          labels: payload.labels,
          color: payload.color,
          height: context.conf?.height || 34,
          width: context.conf?.width || '120px'
        });

        if (
          this.miniApexCharts?.[chartKey]
          && this.miniApexChartSignatures?.[chartKey] === signature
        ) {
          return;
        }

        this.destroyMiniApexChartByKey(chartKey);
        el.innerHTML = '';

        const chart = new window.ApexCharts(el, this.buildMiniApexChartOptions(payload, context.conf));
        this.miniApexCharts[chartKey] = chart;
        this.miniApexChartSignatures[chartKey] = signature;

        const renderResult = chart.render();
        if (renderResult?.catch) {
          renderResult.catch(() => {
            this.destroyMiniApexChartByKey(chartKey);
          });
        }
      });

      this.destroyMiniApexCharts(activeKeys);
    },
    formatarData(valor, comHora = false) {
      return comHora ? formatDateTime(valor) : formatDate(valor);
    },
    mascararTelefone(telefone) {
      if (!telefone) return ''
      // Padrão (62) 9****-1234 a partir de dígitos
      const digits = telefone.replace(/\D/g, '');
      return digits.replace(/^(\d{2})(\d{1})(\d{4})(\d{4})$/, '($1) $2****-$4');
    },
    mascararEmail(email) {
      if (!email || !email.includes('@')) return ''
      const [user, domain] = email.split('@')
      const maskedUser = user.length > 2 ? user.substring(0, 2) + '****' : (user[0] || '*') + '****'
      // máscara simples no domínio (mantém TLD)
      const maskedDomain = domain.replace(/^(.).*(\..*)$/, (m, a, b) => `${a}***${b}`)
      return `${maskedUser}@${maskedDomain}`
    },
    mascararCpf(cpf) {
      if (!cpf) return ''
      const digits = cpf.replace(/\D/g, '');
      return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '***.$2.$3-**')
    },
    comQuebraLinha(str) {
      return String(str ?? '').replace(/\n/g, '<br>');
    },
    formatarChave(chave) {
      return chave.charAt(0).toUpperCase() + chave.slice(1).replace(/_/g, ' ');
    },
    mapearStatus(valor) {
      switch (valor) {
        case 1: return 'Ativo';
        case 0: return 'Em aprovação';
        case 2: return 'Encerrado';
        default: return 'Indefinido';
      }
    },
    mapearClasseBadge(valor) {
      switch (valor) {
        case 1: return 'kt-badge-success kt-badge-outline';
        case 0: return 'kt-badge-warning kt-badge-outline';
        case 2: return 'kt-badge-primary kt-badge-outline';
        default: return 'kt-badge-secondary kt-badge-outline';
      }
    },
    // --- NOVOS HELPERS PARA NÚMEROS ---
    parseNumeroBr(val) {
      if (val === null || val === undefined || val === '') return 0
      if (typeof val === 'number') return val
      // remove currency, spaces, etc; converte "1.234,56" -> "1234.56"
      const limpo = String(val).replace(/[^\d,-.]/g, '').replace(/\./g, '').replace(',', '.')
      const n = parseFloat(limpo)
      return isNaN(n) ? 0 : n
    },
    formatarNumero(val, conf = {}) {
      // conf.decimais opcional para forçar casas
      const n = this.parseNumeroBr(val)
      const hasDec = Number.isFinite(conf.decimais)
      const opts = hasDec
        ? { minimumFractionDigits: conf.decimais, maximumFractionDigits: conf.decimais }
        : {}
      return n.toLocaleString('pt-BR', opts)
    },
    // --- NOVOS HELPERS EXISTENTES ---
    getSubtextoDetalhado(obj, t) {
      const sub = t?.subcampos?.subtexto;
      if (!sub) return '';
      if (typeof sub === 'function') return sub(obj);
      if (Array.isArray(sub)) {
        return sub.map(k => obj?.[k]).filter(Boolean).join(' • ');
      }
      return obj?.[sub] ?? '';
    },
    getCount(v) {
      if (Array.isArray(v)) return v.length;
      if (v && typeof v === 'object' && 'length' in v) return Number(v.length) || 0;
      return 0;
    },
    resolveThumbnailUrl(value) {
      const url = String(value || '').trim();
      if (!url) return '/assets/media/no-image.webp';
      if (/^(https?:)?\/\//i.test(url) || url.startsWith('data:') || url.startsWith('blob:')) return url;
      if (url.startsWith('/')) return url;
      return `/storage/${url.replace(/^storage\//, '')}`;
    },
    getThumbnailStyle(conf = {}) {
      const width = Number(conf.width) > 0 ? Number(conf.width) : 50;
      const height = Number(conf.height) > 0 ? Number(conf.height) : 50;

      return {
        width: `${width}px`,
        height: `${height}px`,
        minWidth: `${width}px`,
        borderRadius: conf.radius || '6px'
      };
    }
  },
  mounted() {
    this.checkScreen();
    window.addEventListener('resize', this.checkScreen);
    document.addEventListener('click', this.closeActionMenu);
    this.renderMiniApexCharts();
  },
  updated() {
    this.renderMiniApexCharts();
  },
  beforeUnmount() {
    this.destroyMiniApexCharts();
    window.removeEventListener('resize', this.checkScreen);
    document.removeEventListener('click', this.closeActionMenu);
  }
}
</script>

<style scoped>
.admin-data-table {
  min-width: 0;
  width: 100%;
}
.admin-table-col-check {
  width: 60px;
}
.admin-table-cell-actions {
  position: relative;
  width: 60px;
  white-space: nowrap;
}
.table-actions-dropdown {
  position: relative;
  display: inline-flex;
  justify-content: center;
}
.table-actions-dropdown-menu {
  position: absolute;
  top: calc(100% + 0.35rem);
  z-index: 60;
}
.table-actions-dropdown[data-kt-dropdown-placement$="-start"] .table-actions-dropdown-menu {
  left: 0;
  right: auto;
}
.table-actions-dropdown[data-kt-dropdown-placement$="-end"] .table-actions-dropdown-menu {
  left: auto;
  right: 0;
}
.table-actions-dropdown-menu .kt-dropdown-menu-link {
  border: 0;
  background: transparent;
}
.raffle-totals-cell {
  min-width: 150px;
  display: grid;
  gap: 4px;
}
.raffle-total-line,
.raffle-total-action {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  min-height: 24px;
  border-radius: 6px;
  font-size: 12px;
}
.raffle-total-line {
  color: var(--muted-foreground);
}
.raffle-total-action {
  width: 100%;
  padding: 2px 6px;
  border: 1px solid var(--border);
  background: var(--background);
  color: var(--mono);
  cursor: pointer;
  text-align: left;
}
.raffle-total-action:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: color-mix(in srgb, var(--primary) 7%, transparent);
}
.raffle-total-action strong,
.raffle-total-line strong {
  font-weight: 700;
}
.clear { display: block; height: 2px; line-height: 0; }
.table-mini-apex-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 120px;
}
.table-mini-apex-value {
  font-weight: 600;
  font-size: 0.8rem;
  line-height: 1.1;
  color: hsl(var(--foreground));
}
.table-mini-apex-chart {
  /* overflow: hidden; */
}
</style>
