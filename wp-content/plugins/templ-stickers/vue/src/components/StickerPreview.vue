<script setup lang="ts">
import { ref, computed, watch, useTemplateRef } from 'vue';
import type { FormValues } from '@/types';
import { generateStickerSVGWithInlinedSymbol } from '@/services/svgGenerator';

const props = defineProps<{
    formValues: FormValues;
}>();

const svgContent = ref<string | null>(null);
const currentColor = ref('#ffffff');
const currentWidth = ref(0);
const currentShape = ref('');
const wrapperRef = useTemplateRef<HTMLDivElement>('wrapper');

const svgDisplayWidth = computed(() =>
    currentWidth.value ? `min(${currentWidth.value * 3}px, 100%)` : '100%'
);

const borderRadius = computed(() => {
    const s = currentShape.value.toLowerCase();
    if (s === 'rund' || s === 'round') return '50%';
    if (s === 'oval' || s === 'ellipse') return '50%';
    return '0px';
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
        currentShape.value = newValues.size.shape || '';

        svgContent.value = await generateStickerSVGWithInlinedSymbol({
            size: newValues.size,
            color: props.formValues.color || '#ffffff',
            symbol: newValues.symbol,
            textLines: newValues.textLines,
            textAlignment: newValues.textAlignment,
        });
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
    background: white;
}

.svg-wrapper {
    border: 1px solid lightgrey;
    transition: width 0.4s ease, aspect-ratio 0.4s ease, border-radius 0.4s ease;
    overflow: hidden;
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
