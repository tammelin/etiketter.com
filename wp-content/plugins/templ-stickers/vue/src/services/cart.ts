import type { CartResponse } from '@/types';

export async function addToCart(productId: number, stickerUuid: string, quantity: number): Promise<CartResponse> {
    const formData = new FormData();
    formData.append('product_id', String(productId));
    formData.append('quantity', String(quantity));
    formData.append('sticker_uuid', stickerUuid);

    const response = await fetch('/?wc-ajax=add_to_cart', {
        method: 'POST',
        body: formData
    });
    return response.json();
}

export function updateCartFragments(fragments: Record<string, string>): void {
    for (const selector in fragments) {
        const element = document.querySelector(selector);
        if (element) {
            element.innerHTML = fragments[selector];
        }
    }
}

export function openBricksMiniCart(): void {
    const toggle = document.querySelector('.bricks-woo-toggle');
    const targetSelector = toggle?.getAttribute('data-toggle-target');
    if (!targetSelector) return;

    const cartDetails = document.querySelector(targetSelector);
    if (!cartDetails) return;

    cartDetails.classList.add('active');
    cartDetails.closest('.brxe-woocommerce-mini-cart')?.classList.add('show-cart-details');
}
