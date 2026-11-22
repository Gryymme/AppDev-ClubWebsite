// PHP renders options; JavaScript coordinates dependent selects.
document.addEventListener('DOMContentLoaded', () => {
    const region = document.getElementById('region');
    const province = document.getElementById('province');
    const city = document.getElementById('city_municipality');
    if (!region || !province || !city) return;

    const requests = new Map();
    function reset(select, label) {
        requests.get(select)?.abort();
        requests.delete(select);
        select.replaceChildren(new Option(label, ''));
        select.disabled = true;
    }
    async function load(select, type, id) {
        const controller = new AbortController();
        requests.set(select, controller);
        try {
            const query = new URLSearchParams({ type, id, format: 'options' });
            const response = await fetch(`database/fetch_locations.php?${query}`, { signal: controller.signal });
            if (!response.ok) throw new Error('Location request failed');
            const html = await response.text();
            if (requests.get(select) !== controller) return;
            select.innerHTML = html;
            select.disabled = false;
            select.setCustomValidity('');
        } catch (error) {
            if (error.name !== 'AbortError' && requests.get(select) === controller) {
                select.replaceChildren(new Option('Unable to load. Please reselect the location.', ''));
                select.disabled = false;
                select.setCustomValidity('Please reselect the region or province to retry.');
            }
        }
    }
    region.addEventListener('change', () => {
        reset(province, 'Select Province');
        reset(city, 'Select City/Municipality');
        if (!region.value) return;
        if (region.selectedOptions[0].dataset.regionCode === 'NCR') {
            province.replaceChildren(new Option('Not Applicable', 'NA'));
            load(city, 'cities_by_region', region.value);
        } else {
            load(province, 'provinces', region.value);
        }
    });
    province.addEventListener('change', () => {
        reset(city, 'Select City/Municipality');
        if (province.value) load(city, 'cities', province.value);
    });
});
