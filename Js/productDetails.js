'use strict';

document.addEventListener('DOMContentLoaded', async () => {
    const $ = (id) => document.getElementById(id);
    const productId = new URLSearchParams(window.location.search).get('id');
    if (!productId || !/^[1-9]\d*$/.test(productId)) {
        $('statusMessage').textContent = 'Product ID is missing or invalid.';
        return;
    }

    const quantity = $('quantity');
    const normaliseQuantity = () => {
        const value = Number(quantity.value);
        quantity.value = String(Math.min(99, Math.max(1, Number.isFinite(value) ? Math.trunc(value) : 1)));
        return Number(quantity.value);
    };
    $('decreaseQuantity').addEventListener('click', () => {
        quantity.value = String(Math.max(1, normaliseQuantity() - 1));
    });
    $('increaseQuantity').addEventListener('click', () => {
        quantity.value = String(Math.min(99, normaliseQuantity() + 1));
    });
    quantity.addEventListener('change', normaliseQuantity);

    try {
        const response = await fetch(`../Api/product_details.php?id=${encodeURIComponent(productId)}`);
        const data = await response.json();
        if (!response.ok || !data.success || !data.product || Number(data.product.id) !== Number(productId)) {
            throw new Error('Product could not be loaded.');
        }
        const product = data.product;
        const title = product.title || 'Product';
        document.title = `${title} – Flash Food`;
        $('productId').value = product.id;
        $('productTitle').textContent = title;
        $('productMerchant').textContent = product.merchant || '';
        $('productPrice').textContent = formatPrice(product.price);
        // Do not infer country or food category from a merchant name or a numeric code.
        $('productOrigin').textContent = product.origin || 'Not specified';
        $('productCategory').textContent = product.category && !/^\d+$/.test(String(product.category))
            ? product.category : 'Not specified';
        const packSize = title.match(/(\d+(?:[.,]\d+)?)\s*(kg|g|ml|l|pcs|stk)\b/i);
        $('productSize').textContent = packSize ? `${packSize[1]} ${packSize[2]}` : 'Not specified';
        const image = $('productImage');
        if (product.image) {
            image.addEventListener('error', () => {
                image.hidden = true;
                $('imageUnavailable').hidden = false;
            });
            image.alt = title;
            image.src = `../Style/Images/products/${encodeURIComponent(product.image)}`;
            image.hidden = false;
        } else {
            $('imageUnavailable').hidden = false;
        }

        let climate = null;
        try {
            climate = JSON.parse($('productClimateData')?.textContent || 'null');
        } catch {
            // Missing or invalid climate data must never produce a green default.
        }
        renderClimate(climate, productId, $);
        showRelatedProducts(Array.isArray(data.relatedProducts) ? data.relatedProducts : []);
        $('statusMessage').hidden = true;
        $('productDetails').hidden = false;
    } catch (error) {
        console.error(error);
        $('statusMessage').textContent = 'Product could not be loaded. Please try again.';
    }

    function showRelatedProducts(products) {
        const container = $('relatedProducts');
        container.replaceChildren();
        products.forEach((product) => {
            if (!/^\d+$/.test(String(product.id))) return;
            const link = document.createElement('a');
            link.className = 'related-card';
            link.href = `product.php?id=${encodeURIComponent(product.id)}`;
            if (product.image) {
                const image = document.createElement('img');
                image.src = `../Style/Images/products/${encodeURIComponent(product.image)}`;
                image.alt = product.title || 'Related product';
                image.loading = 'lazy';
                image.addEventListener('error', () => { image.hidden = true; });
                link.append(image);
            }
            const information = document.createElement('div');
            information.className = 'related-card-information';
            const title = document.createElement('h3');
            title.textContent = product.title || 'Product';
            const merchant = document.createElement('p');
            merchant.textContent = product.merchant || '';
            const price = document.createElement('strong');
            price.textContent = formatPrice(product.price);
            information.append(title, merchant, price);
            link.append(information);
            container.append(link);
        });
        $('relatedSection').hidden = container.children.length === 0;
    }
});

function formatPrice(price) {
    const amount = Number(price);
    return Number.isFinite(amount)
        ? `${amount.toLocaleString('da-DK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} DKK`
        : 'Price not available';
}

function renderClimate(climate, productId, getElement) {
    const box = getElement('climateBox');
    const label = getElement('climateLabel');
    const value = getElement('climateValue');
    const example = getElement('climateExample');
    box.className = 'climate-box unknown';
    label.textContent = 'Climate impact';
    value.textContent = 'Information not available';
    example.hidden = true;
    if (!climate || Number(climate.product_id) !== Number(productId)) return;
    const state = ['low', 'medium', 'high'].includes(climate.className) ? climate.className : 'unknown';
    box.className = `climate-box ${state}`;
    label.textContent = {
        low: 'Lower climate impact', medium: 'Medium climate impact',
        high: 'Higher climate impact', unknown: 'Climate impact'
    }[state];
    if (climate.available && Number.isFinite(climate.value) && climate.value >= 0
        && ['per kg', 'per serving'].includes(climate.unit)) {
        value.textContent = `${climate.value.toFixed(1)} kg CO₂e ${climate.unit}`;
        example.hidden = climate.is_example !== true;
    }
}
