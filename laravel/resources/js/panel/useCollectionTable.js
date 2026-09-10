import { computed, ref, unref } from 'vue';
import { statusLabel } from './panelFormatters';

const collator = new Intl.Collator('pt-BR', {
    numeric: true,
    sensitivity: 'base',
});

function normalize(value) {
    return `${value ?? ''}`
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLocaleLowerCase('pt-BR')
        .trim();
}

function getValue(row, key) {
    return `${key || ''}`.split('.').reduce((value, part) => value?.[part], row);
}

export function useCollectionTable(source, options = {}) {
    const query = ref('');
    const status = ref('all');
    const sortKey = ref(options.sortKey || '');
    const sortDirection = ref(options.sortDirection || 'asc');
    const searchKeys = options.searchKeys || [];
    const statusKey = options.statusKey || 'status';

    const sourceRows = computed(() => unref(source) || []);
    const statusOptions = computed(() => [...new Set(sourceRows.value
        .map((row) => getValue(row, statusKey))
        .filter(Boolean))]
        .sort((left, right) => collator.compare(statusLabel(left), statusLabel(right)))
        .map((value) => ({ value, label: statusLabel(value) })));

    const rows = computed(() => {
        const normalizedQuery = normalize(query.value);
        const filtered = sourceRows.value.filter((row) => {
            const matchesStatus = status.value === 'all' || getValue(row, statusKey) === status.value;
            const matchesQuery = !normalizedQuery || searchKeys.some((key) => normalize(getValue(row, key)).includes(normalizedQuery));

            return matchesStatus && matchesQuery;
        });

        if (!sortKey.value) {
            return filtered;
        }

        return [...filtered].sort((left, right) => {
            const comparison = collator.compare(getValue(left, sortKey.value) ?? '', getValue(right, sortKey.value) ?? '');

            return sortDirection.value === 'desc' ? -comparison : comparison;
        });
    });

    function toggleSort(key) {
        if (sortKey.value === key) {
            sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        } else {
            sortKey.value = key;
            sortDirection.value = 'asc';
        }
    }

    function resetFilters() {
        query.value = '';
        status.value = 'all';
    }

    return {
        query,
        resetFilters,
        rows,
        sortDirection,
        sortKey,
        sourceRows,
        status,
        statusOptions,
        toggleSort,
    };
}
