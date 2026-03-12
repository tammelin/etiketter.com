import type { Size, TextLine, TextAlignment } from '@/types';

export interface StickerDesign {
    size: Size;
    color: string;
    symbol: string;
    textLines: TextLine[];
    textAlignment: TextAlignment;
}

// Cache for fetched SVG content
const svgCache = new Map<string, string>();

/**
 * Fetch SVG content from URL and return the inner SVG elements
 */
export async function fetchSvgContent(url: string): Promise<string> {
    if (!url) return '';

    // Return cached version if available
    if (svgCache.has(url)) {
        return svgCache.get(url)!;
    }

    try {
        const response = await fetch(url);
        const svgText = await response.text();

        // Parse the SVG and extract the content
        const parser = new DOMParser();
        const doc = parser.parseFromString(svgText, 'image/svg+xml');
        const svgElement = doc.querySelector('svg');

        if (!svgElement) {
            console.error('No SVG element found in response');
            return '';
        }

        // Get the SVG content as string
        const serializer = new XMLSerializer();
        const svgString = serializer.serializeToString(svgElement);

        svgCache.set(url, svgString);
        return svgString;
    } catch (error) {
        console.error('Failed to fetch SVG:', error);
        return '';
    }
}

/**
 * Generate SVG string with inlined SVG symbol
 */
function isRoundShape(shape: string): boolean {
    const s = shape.toLowerCase();
    return s === 'round' || s === 'oval' || s === 'ellipse';
}

export async function generateStickerSVGWithInlinedSymbol(design: StickerDesign): Promise<string> {
    const width = parseInt(design.size.dimensions.width, 10);
    const height = parseInt(design.size.dimensions.height, 10);
    const sideLayout = design.symbol ? !isRoundShape(design.size.shape) : false;

    const shapeElement = generateShape(design.size.shape, width, height, design.color);

    // For side layout: symbol takes left 35%, text gets right 65%
    // For top layout: symbol takes top portion, text gets remaining height below
    const symbolAreaWidth = sideLayout ? width * 0.35 : 0;
    const symbolBottom = !sideLayout && design.symbol ? height * 0.1 + Math.min(width, height) * 0.3 : 0;

    const textElements = generateTextElements(
        design.textLines, design.textAlignment, width, height,
        { symbolBottom, textLeft: symbolAreaWidth }
    );

    let symbolElement = '';
    if (design.symbol) {
        const svgContent = await fetchSvgContent(design.symbol);
        if (svgContent) {
            symbolElement = generateInlinedSymbol(svgContent, width, height, sideLayout);
        }
    }

    const fontCSS: string = (window as any).templStickersFontCSS ?? '';
    const defs = fontCSS ? `<defs><style>${fontCSS}</style></defs>` : '';

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}">
  ${defs}
  ${shapeElement}
  ${symbolElement}
  ${textElements}
</svg>`;
}

/**
 * Generate shape element based on shape type
 */
function generateShape(shape: string, width: number, height: number, color: string): string {
    const normalizedShape = shape.toLowerCase();

    if (normalizedShape === 'round') {
        const radius = Math.min(width, height) / 2;
        const cx = width / 2;
        const cy = height / 2;
        return `<circle cx="${cx}" cy="${cy}" r="${radius}" fill="${color}" class="sticker-shape" />`;
    }

    if (normalizedShape === 'oval' || normalizedShape === 'ellipse') {
        const rx = width / 2;
        const ry = height / 2;
        return `<ellipse cx="${rx}" cy="${ry}" rx="${rx}" ry="${ry}" fill="${color}" class="sticker-shape" />`;
    }

    // Default: rectangle (rektangulär)
    return `<rect x="0" y="0" width="${width}" height="${height}" fill="${color}" class="sticker-shape" />`;
}

/**
 * Generate text elements for all lines
 */
function generateTextElements(
    textLines: TextLine[],
    alignment: TextAlignment,
    width: number,
    height: number,
    { symbolBottom = 0, textLeft = 0 }: { symbolBottom?: number; textLeft?: number } = {}
): string {
    if (!textLines.length) return '';

    // Trim leading and trailing empty lines, keep internal empty lines as spacers
    let first = textLines.findIndex(l => l.content.trim() !== '');
    let last = [...textLines].reverse().findIndex(l => l.content.trim() !== '');
    if (first === -1) return ''; // all empty
    const activeLines = textLines.slice(first, textLines.length - last);

    // Text area bounds (10% padding on each side)
    const textAreaLeft = textLeft;
    const textAreaWidth = width - textLeft;
    const textAreaTop = symbolBottom > 0 ? symbolBottom : 0;
    const textAreaHeight = height - textAreaTop;
    const paddingFraction = 0.1;
    const usableHeight = textAreaHeight * (1 - paddingFraction * 2);

    // Scale font size so all lines fit vertically; cap at 6mm
    const LINE_HEIGHT_RATIO = 1.3;
    const maxFontSize = 6;
    const fontSize = Math.max(1, Math.min(maxFontSize, usableHeight / (activeLines.length * LINE_HEIGHT_RATIO)));
    const lineHeight = fontSize * LINE_HEIGHT_RATIO;

    const totalTextHeight = activeLines.length * lineHeight;
    // Center the text block within the usable area
    const startY = textAreaTop + textAreaHeight * paddingFraction + (usableHeight - totalTextHeight) / 2 + lineHeight * 0.8;

    // Map alignment to SVG text-anchor
    const textAnchor = alignment === 'left' ? 'start' : alignment === 'right' ? 'end' : 'middle';

    // Calculate X position within the text area
    const x = alignment === 'left'
        ? textAreaLeft + 3
        : alignment === 'right'
            ? textAreaLeft + textAreaWidth - 3
            : textAreaLeft + textAreaWidth / 2;

    return activeLines
        .map((line, index) => {
            if (!line.content.trim()) return `<text x="${x}" y="${startY + index * lineHeight}" font-size="${fontSize}"> </text>`;

            const y = startY + index * lineHeight;
            let fontFamily: string;
            let fontStyle: string;
            let fontWeight: string;

            if (line.textStyle === 'cursive') {
                fontFamily = "'Cataneo', cursive";
                fontStyle = 'italic';
                fontWeight = line.fontWeight === 'bold' ? 'bold' : 'normal';
            } else {
                fontFamily = 'Ubuntu, sans-serif';
                fontStyle = line.textStyle === 'italic' ? 'italic' : 'normal';
                fontWeight = line.fontWeight === 'bold' ? '700' : 'normal';
            }

            return `<text
    x="${x}"
    y="${y}"
    text-anchor="${textAnchor}"
    font-family="${fontFamily}"
    font-style="${fontStyle}"
    font-weight="${fontWeight}"
    font-size="${fontSize}"
    fill="#000000"
  >${escapeXml(line.content)}</text>`;
        })
        .join('\n  ');
}

/**
 * Generate inlined SVG symbol wrapped in a <g> element with proper positioning
 */
function generateInlinedSymbol(svgContent: string, width: number, height: number, sideLayout = false): string {
    const symbolSize = Math.min(width, height) * 0.3;
    // Side layout: center symbol vertically in left 35% of width
    // Top layout: center symbol horizontally in top portion
    const x = sideLayout ? (width * 0.35 - symbolSize) / 2 : (width - symbolSize) / 2;
    const y = sideLayout ? (height - symbolSize) / 2 : height * 0.1;

    // Parse the SVG to extract viewBox and inner content
    const parser = new DOMParser();
    const doc = parser.parseFromString(svgContent, 'image/svg+xml');
    const svgElement = doc.querySelector('svg');

    if (!svgElement) return '';

    // Get the viewBox or calculate from width/height
    let viewBox = svgElement.getAttribute('viewBox');
    if (!viewBox) {
        const svgWidth = svgElement.getAttribute('width') || '100';
        const svgHeight = svgElement.getAttribute('height') || '100';
        viewBox = `0 0 ${parseFloat(svgWidth)} ${parseFloat(svgHeight)}`;
    }

    const [, , vbWidth, vbHeight] = viewBox.split(/\s+/).map(parseFloat);

    // Calculate scale to fit symbol into symbolSize while preserving aspect ratio
    const scale = Math.min(symbolSize / vbWidth, symbolSize / vbHeight);

    // Center the symbol within the allocated space
    const scaledWidth = vbWidth * scale;
    const scaledHeight = vbHeight * scale;
    const offsetX = x + (symbolSize - scaledWidth) / 2;
    const offsetY = y + (symbolSize - scaledHeight) / 2;

    // Get the inner content of the SVG
    const innerContent = svgElement.innerHTML;

    return `<g transform="translate(${offsetX}, ${offsetY}) scale(${scale})">
    ${innerContent}
  </g>`;
}

/**
 * Escape special XML characters
 */
function escapeXml(str: string): string {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}
