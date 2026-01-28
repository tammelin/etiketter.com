// Constants for text layout calculations (in mm)
const LINE_HEIGHT_MM = 8;
const VERTICAL_PADDING_MM = 10;
const CHAR_WIDTH_MM = 3;
const HORIZONTAL_PADDING_MM = 10;

/**
 * Calculate maximum number of text lines based on sticker height
 */
export function calculateMaxLines(heightMm: number): number {
    const availableHeight = heightMm - VERTICAL_PADDING_MM;
    return Math.max(1, Math.floor(availableHeight / LINE_HEIGHT_MM));
}

/**
 * Calculate maximum characters per line based on sticker width
 */
export function calculateMaxChars(widthMm: number): number {
    const availableWidth = widthMm - HORIZONTAL_PADDING_MM;
    return Math.max(1, Math.floor(availableWidth / CHAR_WIDTH_MM));
}

/**
 * Get line height constant (for SVG generation)
 */
export function getLineHeightMm(): number {
    return LINE_HEIGHT_MM;
}

/**
 * Get vertical padding constant (for SVG generation)
 */
export function getVerticalPaddingMm(): number {
    return VERTICAL_PADDING_MM;
}
