import type { FormFields, FormValues, StickerSubmitResponse } from '@/types';

const API_BASE = import.meta.env.VITE_API_BASE_URL;

export async function fetchFormFields(): Promise<FormFields> {
    const response = await fetch(`${API_BASE}/wp-json/templ-stickers/v1/form-fields`);
    return response.json();
}

export async function submitSticker(formValues: FormValues): Promise<StickerSubmitResponse> {
    const response = await fetch(`${API_BASE}/wp-json/templ-stickers/v1/submit-sticker`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formValues)
    });
    return response.json();
}
