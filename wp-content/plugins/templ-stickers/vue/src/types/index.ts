export interface Dimensions {
    width: string;
    height: string;
}

export interface Size {
    dimensions: Dimensions;
    shape: string;
}

export interface Color {
    name: string;
    color: string;
}

export interface Symbol {
    symbol: {
        image: string;
        description: string;
    };
}

export interface FormFields {
    sizes: Size[];
    colors: Color[];
    symbols: Symbol[];
}

export interface FormValues {
    size: string;
    color: string;
    symbol: string;
}

export interface StickerSubmitResponse {
    status: string;
    data: FormValues;
    post_id: number;
    sticker_uuid: string;
    product_id: number;
}

export interface CartResponse {
    fragments?: Record<string, string>;
    cart_hash?: string;
}
