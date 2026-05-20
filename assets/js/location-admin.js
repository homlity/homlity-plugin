document.addEventListener('DOMContentLoaded', () => {
    const selects = document.querySelectorAll('.property-location-select');
    if (!selects.length || typeof homlityPluginLocation === 'undefined') {
        return;
    }

    const data = homlityPluginLocation;
    const restUrl = data.restUrl;
    const nonce = data.nonce;
    const selected = data.selected || {};

    const endpoints = {
        state: { parent: 'country', taxonomy: data.taxonomies.state },
        city: { parent: 'state', taxonomy: data.taxonomies.city },
        neighborhood: { parent: 'city', taxonomy: data.taxonomies.neighborhood },
    };

    async function fetchTerms(taxonomy, parentId) {
        const url = new URL(restUrl + 'homlity-real-estate/v1/location-terms');
        url.searchParams.set('taxonomy', taxonomy);
        url.searchParams.set('parent', parentId || 0);

        const res = await fetch(url.toString(), {
            headers: { 'X-WP-Nonce': nonce },
        });
        if (!res.ok) return [];
        return res.json();
    }

    function populate(select, terms, selectedId) {
        select.innerHTML = '';
        const placeholder = select.getAttribute('data-placeholder') || select.options[0]?.text || '';
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = placeholder;
        select.appendChild(defaultOpt);

        terms.forEach(term => {
            const opt = document.createElement('option');
            opt.value = term.id;
            opt.textContent = term.name;
            if (selectedId && parseInt(selectedId, 10) === term.id) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    async function loadChain(startKey) {
        const sequence = ['state', 'city', 'neighborhood'];
        const startIndex = sequence.indexOf(startKey);
        if (startIndex === -1) return;

        let parentId = document.getElementById('property_country')?.value || 0;
        if (startKey === 'state') {
            await load('state', parentId, selected.state);
            parentId = document.getElementById('property_state')?.value || 0;
            await load('city', parentId, selected.city);
            parentId = document.getElementById('property_city')?.value || 0;
            await load('neighborhood', parentId, selected.neighborhood);
        } else if (startKey === 'city') {
            await load('city', document.getElementById('property_state')?.value || 0, selected.city);
            await load('neighborhood', document.getElementById('property_city')?.value || 0, selected.neighborhood);
        } else if (startKey === 'neighborhood') {
            await load('neighborhood', document.getElementById('property_city')?.value || 0, selected.neighborhood);
        }
    }

    async function load(key, parentId, selectedId) {
        const info = endpoints[key];
        if (!info) return;
        const select = document.getElementById('property_' + key);
        if (!select) return;

        const terms = await fetchTerms(info.taxonomy, parentId);
        populate(select, terms, selectedId);
    }

    // Initial load respecting saved selections.
    loadChain('state');

    document.getElementById('property_country')?.addEventListener('change', async (e) => {
        await load('state', e.target.value, null);
        populate(document.getElementById('property_city'), [], null);
        populate(document.getElementById('property_neighborhood'), [], null);
    });

    document.getElementById('property_state')?.addEventListener('change', async (e) => {
        await load('city', e.target.value, null);
        populate(document.getElementById('property_neighborhood'), [], null);
    });

    document.getElementById('property_city')?.addEventListener('change', async (e) => {
        await load('neighborhood', e.target.value, null);
    });
});
