document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('property_operation');
    if (!select) return;

    const blocks = document.querySelectorAll('.property-price-block');

    function matchBlocks(slug) {
        const wantsRent = slug.includes('arriendo') || slug.includes('rent');
        const wantsSale = slug.includes('venta') || slug.includes('sale');
        const wantsAdmin = slug.includes('admin') || slug.includes('adm');
        const wantsPermuta = slug.includes('permuta') || slug.includes('swap');

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
        matchBlocks(slug);
    }

    handleChange();
    select.addEventListener('change', handleChange);
});
