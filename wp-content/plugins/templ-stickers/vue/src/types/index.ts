export interface Dimensions {
    width: string;
    height: string;
}

export interface SizeQuantityRules {
    min: number;
    max: number | '';
    step: number;
}

export interface Size {
    dimensions: Dimensions;
    shape: string;
    product: number;
    max_rows?: number;
    max_chars?: number;
    colors?: Color[];
    quantity: SizeQuantityRules;
    unit_price: number;
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
    symbol_price: number;
}

export interface TextLine {
    content: string;
    textStyle: 'straight' | 'italic' | 'cursive';
    fontWeight: 'normal' | 'bold';
}

export type TextAlignment = 'left' | 'center' | 'right';

export interface FormValues {
    size: Size | null;
    color: string;
    symbol: string;
    textLines: TextLine[];
    textAlignment: TextAlignment;
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
