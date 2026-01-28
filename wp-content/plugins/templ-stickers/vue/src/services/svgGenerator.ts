import type { Size, TextLine, TextAlignment } from '@/types';
import { getLineHeightMm, getVerticalPaddingMm } from '@/utils/textCalculations';

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
export async function generateStickerSVGWithInlinedSymbol(design: StickerDesign): Promise<string> {
    const width = parseInt(design.size.dimensions.width, 10);
    const height = parseInt(design.size.dimensions.height, 10);

    const shapeElement = generateShape(design.size.shape, width, height, design.color);
    const textElements = generateTextElements(design.textLines, design.textAlignment, width, height);

    let symbolElement = '';
    if (design.symbol) {
        const svgContent = await fetchSvgContent(design.symbol);
        if (svgContent) {
            symbolElement = generateInlinedSymbol(svgContent, width, height);
        }
    }

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" width="${width}mm" height="${height}mm">
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

    if (normalizedShape === 'rund' || normalizedShape === 'round') {
        // Circle - use the smaller dimension as diameter
        const radius = Math.min(width, height) / 2;
        const cx = width / 2;
        const cy = height / 2;
        return `<circle cx="${cx}" cy="${cy}" r="${radius}" fill="${color}" />`;
    }

    if (normalizedShape === 'oval' || normalizedShape === 'ellipse') {
        const rx = width / 2;
        const ry = height / 2;
        return `<ellipse cx="${rx}" cy="${ry}" rx="${rx}" ry="${ry}" fill="${color}" />`;
    }

    // Default: rectangle (rektangulär)
    return `<rect x="0" y="0" width="${width}" height="${height}" fill="${color}" />`;
}

/**
 * Generate text elements for all lines
 */
function generateTextElements(
    textLines: TextLine[],
    alignment: TextAlignment,
    width: number,
    height: number
): string {
    if (!textLines.length) return '';

    const lineHeight = getLineHeightMm();
    const verticalPadding = getVerticalPaddingMm();

    // Calculate starting Y position to center text block vertically
    const totalTextHeight = textLines.length * lineHeight;
    const startY = (height - totalTextHeight) / 2 + lineHeight / 2 + verticalPadding / 2;

    // Map alignment to SVG text-anchor
    const textAnchor = alignment === 'left' ? 'start' : alignment === 'right' ? 'end' : 'middle';

    // Calculate X position based on alignment
    const x = alignment === 'left' ? 5 : alignment === 'right' ? width - 5 : width / 2;

    return textLines
        .map((line, index) => {
            if (!line.content.trim()) return '';

            const y = startY + index * lineHeight;
            const fontFamily = line.fontFamily === 'serif' ? 'Georgia, serif' : 'Arial, sans-serif';

            return `<text
    x="${x}"
    y="${y}"
    text-anchor="${textAnchor}"
    font-family="${fontFamily}"
    font-style="${line.fontStyle}"
    font-weight="${line.fontWeight}"
    font-size="6"
    fill="#000000"
  >${escapeXml(line.content)}</text>`;
        })
        .filter(Boolean)
        .join('\n  ');
}

/**
 * Generate inlined SVG symbol wrapped in a <g> element with proper positioning
 */
function generateInlinedSymbol(svgContent: string, width: number, height: number): string {
    const symbolSize = Math.min(width, height) * 0.3;
    const x = (width - symbolSize) / 2;
    const y = height * 0.1;

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
