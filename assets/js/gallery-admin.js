document.addEventListener('DOMContentLoaded', () => {
    const data = window.pluginInmobiliarioGallery || {};
    const list = document.getElementById('property_gallery_list');
    const input = document.getElementById('property_gallery');
    const addBtn = document.getElementById('property_gallery_add');
    const clearBtn = document.getElementById('property_gallery_clear');
    if (!list || !input || !addBtn) return;

    let items = Array.isArray(data.items) ? data.items : [];

    function render() {
        list.innerHTML = '';
        items.forEach(item => {
            const li = document.createElement('li');
            li.dataset.id = item.id;
            li.style.listStyle = 'none';
            li.style.cursor = 'move';

            const img = document.createElement('img');
            img.src = item.url;
            img.style.width = '90px';
            img.style.height = '90px';
            img.style.objectFit = 'cover';
            img.style.border = '1px solid #ccd0d4';
            img.style.borderRadius = '3px';
            li.appendChild(img);
            list.appendChild(li);
        });
        input.value = items.map(i => i.id).join(',');
    }

    function openMediaFrame() {
        const frame = wp.media({
            title: 'Selecciona imágenes',
            multiple: true,
            library: { type: 'image' },
        });

        frame.on('select', () => {
            const selection = frame.state().get('selection');
            items = [];
            selection.each(att => {
                const json = att.toJSON();
                items.push({
                    id: json.id,
                    url: json.sizes?.thumbnail?.url || json.url,
                });
            });
            render();
        });

        frame.open();
    }

    addBtn.addEventListener('click', (e) => {
        e.preventDefault();
        openMediaFrame();
    });

    clearBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        items = [];
        render();
    });

    render();

    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.sortable !== 'undefined') {
        jQuery(list).sortable({
            update() {
                const newOrder = [];
                list.querySelectorAll('li').forEach(li => {
                    const id = parseInt(li.dataset.id, 10);
                    const found = items.find(i => i.id === id);
                    if (found) newOrder.push(found);
                });
                items = newOrder;
                input.value = items.map(i => i.id).join(',');
            },
        });
    }
});
