<script setup lang="ts">
import { ref, computed, watch, nextTick, useTemplateRef } from 'vue';
import type { FormValues } from '@/types';
import { generateStickerSVGWithInlinedSymbol } from '@/services/svgGenerator';

const props = defineProps<{
    formValues: FormValues;
}>();

const svgContent = ref<string | null>(null);
const currentColor = ref('#ffffff');
const currentWidth = ref(0);
const currentHeight = ref(0);
const currentShape = ref('');
const wrapperRef = useTemplateRef<HTMLDivElement>('wrapper');

const svgDisplayWidth = computed(() =>
    currentWidth.value ? `min(${currentWidth.value * 3}px, 100%)` : '100%'
);

const borderRadius = computed(() => {
    const s = currentShape.value.toLowerCase();
    if (s === 'round' || s === 'oval' || s === 'ellipse') return '50%';
    // SVG rect uses rx = min(w,h) * 0.08 mm.
    // Display scale is 3px/mm, capped so max display width = currentWidth * 3px.
    // Use the same ratio: rx_px = min(w,h) * 0.08 * 3px/mm, but scale down if container is narrower.
    // Expressed as percentage of the rendered width: rx / displayWidth = min(w,h)*0.08*3 / (w*3) = min(w,h)*0.08/w
    // For height percentage: min(w,h)*0.08/h
    // This gives different % per axis — use px instead via a known max scale.
    const w = currentWidth.value;
    const h = currentHeight.value;
    if (!w || !h) return '0px';
    const rxMm = Math.min(w, h) * 0.08;
    const pxPerMm = 3; // matches svgDisplayWidth multiplier
    const rxPx = rxMm * pxPerMm;
    return `${rxPx}px`;
});


// Update color immediately — CSS variable for transition, attribute for export
watch(() => props.formValues.color, (color) => {
    currentColor.value = color || '#ffffff';
    wrapperRef.value?.querySelector('.sticker-shape')?.setAttribute('fill', color || '#ffffff');
});

// Regenerate SVG only for structural changes
watch(
    () => ({
        size: props.formValues.size,
        symbol: props.formValues.symbol,
        textLines: props.formValues.textLines,
        textAlignment: props.formValues.textAlignment,
    }),
    async (newValues) => {
        if (!newValues.size) {
            svgContent.value = null;
            return;
        }

        currentWidth.value = parseInt(newValues.size.dimensions.width, 10);
        currentHeight.value = parseInt(newValues.size.dimensions.height, 10);
        currentShape.value = newValues.size.shape || '';

        svgContent.value = await generateStickerSVGWithInlinedSymbol({
            size: newValues.size,
            color: props.formValues.color || '#ffffff',
            symbol: newValues.symbol,
            textLines: newValues.textLines,
            textAlignment: newValues.textAlignment,
        });

        // After render: measure text widths and shrink font size if any line overflows
        await nextTick();
        const svgEl = wrapperRef.value?.querySelector('svg');
        if (svgEl) {
            const shape = newValues.size.shape.toLowerCase();
            const isRound = shape === 'round' || shape === 'oval' || shape === 'ellipse';
            const sideLayout = newValues.symbol && !isRound;
            const symbolAreaWidth = sideLayout ? parseInt(newValues.size.dimensions.width, 10) * 0.35 : 0;
            const MARGIN = 2;
            const stickerWidth = parseInt(newValues.size.dimensions.width, 10);
            const widthFraction = isRound ? 0.65 : 1;
            const usableWidth = (stickerWidth - symbolAreaWidth - MARGIN * 2) * widthFraction;

            const textEls = Array.from(svgEl.querySelectorAll<SVGTextElement>('text'));
            if (textEls.length) {
                const maxTextWidth = Math.max(...textEls.map(el => el.getBBox().width));
                if (maxTextWidth > usableWidth) {
                    const scale = usableWidth / maxTextWidth;
                    const LINE_HEIGHT_RATIO = 1.4;
                    const paddingFraction = 0.1;
                    const newFontSize = Math.max(1, parseFloat(textEls[0].getAttribute('font-size') || '5') * scale);
                    const newLineHeight = newFontSize * LINE_HEIGHT_RATIO;

                    // Recalculate vertical centering for the scaled font size
                    const stickerHeight = parseInt(newValues.size.dimensions.height, 10);
                    const symbolBottom = !isRound && !sideLayout && newValues.symbol
                        ? stickerHeight * 0.1 + Math.min(parseInt(newValues.size.dimensions.width, 10), stickerHeight) * 0.3
                        : 0;
                    const textAreaTop = symbolBottom > 0 ? symbolBottom : 0;
                    const textAreaHeight = stickerHeight - textAreaTop;
                    const usableHeight = textAreaHeight * (1 - paddingFraction * 2);
                    const totalTextHeight = textEls.length * newLineHeight;
                    const newFirstY = textAreaTop + textAreaHeight * paddingFraction + (usableHeight - totalTextHeight) / 2 + newLineHeight * 0.8;

                    textEls.forEach((el, i) => {
                        el.setAttribute('font-size', String(newFontSize));
                        el.setAttribute('y', String(newFirstY + i * newLineHeight));
                    });
                    svgContent.value = wrapperRef.value!.innerHTML;
                }
            }
        }
    },
    { deep: true, immediate: true }
);

defineExpose({
    getSvgContent: () => svgContent.value,
});
</script>

<template>
    <div class="sticker-preview">
        <h3>Preview</h3>
        <div v-if="svgContent" class="preview-container">
            <div
                ref="wrapper"
                class="svg-wrapper"
                :style="{
                    '--sticker-color': currentColor,
                    width: svgDisplayWidth,
                    borderRadius: borderRadius,
                }"
                v-html="svgContent"
            ></div>
        </div>
        <div v-else class="preview-placeholder">
            <p>Select a size to see preview</p>
        </div>
    </div>
</template>

<style scoped>
.sticker-preview {
    padding: 1rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f8f8f8;
}

.preview-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 150px;
    border-radius: 4px;
    padding: 1rem;
}

.svg-wrapper {
    transition: width 0.4s ease, aspect-ratio 0.4s ease, border-radius 0.4s ease;
    overflow: hidden;
    box-shadow: 0 0 0 1px lightgrey;
}

.svg-wrapper :deep(svg) {
    display: block;
    width: 100%;
    height: 100%;
}

.svg-wrapper :deep(.sticker-shape) {
    transition: fill 0.3s ease;
    fill: var(--sticker-color) !important;
}

.preview-placeholder {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 150px;
    color: #666;
}
</style>
