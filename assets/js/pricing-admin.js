document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('property_operation');
    if (!select) return;

    const blocks = document.querySelectorAll('.property-price-block');

    function matchBlocks(slug, baseId) {
        const wantsPermuta = baseId === 4 || slug.includes('permuta') || slug.includes('swap');
        const wantsRent = baseId === 1 || baseId === 3 || slug.includes('arriendo') || slug.includes('rent');
        const wantsSale = baseId === 2 || baseId === 3 || slug.includes('venta') || slug.includes('sale');
        const wantsAdmin = wantsRent || wantsSale || slug.includes('admin') || slug.includes('adm');

        blocks.forEach(block => {
            const type = block.getAttribute('data-gestion');
            let show = false;
            if (type === 'rent') show = wantsRent || wantsPermuta;
            if (type === 'sale') show = wantsSale || wantsPermuta;
            if (type === 'admin') show = wantsAdmin || wantsPermuta;
            block.style.display = show ? '' : 'none';
        });
    }

    function handleChange() {
        const selected = select.options[select.selectedIndex];
        const slug = selected ? (selected.dataset.slug || '').toLowerCase() : '';
        const baseId = selected ? parseInt(selected.dataset.baseId || '0', 10) : 0;
        matchBlocks(slug, baseId);
    }

    handleChange();
    select.addEventListener('change', handleChange);
});
