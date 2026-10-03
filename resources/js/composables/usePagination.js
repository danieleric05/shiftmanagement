// Le paginateur Laravel produit ses libellés en anglais (« &laquo; Previous »,
// « Next &raquo; ») ; on les traduit côté client, l'application étant en français.
const TRADUCTIONS = {
    Previous: 'Précédent',
    Next: 'Suivant',
};

export function libellePagination(label) {
    return String(label ?? '').replace(/\b(Previous|Next)\b/g, (mot) => TRADUCTIONS[mot]);
}
