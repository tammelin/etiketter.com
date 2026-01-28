<script setup lang="ts">
import { ref, watch } from 'vue';
import type { FormValues } from '@/types';
import { generateStickerSVGWithInlinedSymbol } from '@/services/svgGenerator';

const props = defineProps<{
    formValues: FormValues;
}>();

const svgContent = ref<string | null>(null);

// Watch form values and regenerate SVG when they change
watch(
    () => props.formValues,
    async (newValues) => {
        if (!newValues.size) {
            svgContent.value = null;
            return;
        }

        svgContent.value = await generateStickerSVGWithInlinedSymbol({
            size: newValues.size,
            color: newValues.color || '#ffffff',
            symbol: newValues.symbol,
            textLines: newValues.textLines,
            textAlignment: newValues.textAlignment,
        });
    },
    { deep: true, immediate: true }
);

// Expose method for parent to access the current SVG content
defineExpose({
    getSvgContent: () => svgContent.value,
});
</script>

<template>
    <div class="sticker-preview">
        <h3>Preview</h3>
        <div v-if="svgContent" class="preview-container" v-html="svgContent"></div>
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
    background: #f9f9f9;
}

.preview-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 150px;
    background: white;
    border-radius: 4px;
    padding: 1rem;
}

.preview-container :deep(svg) {
    max-width: 100%;
    max-height: 300px;
    height: auto;
}

.preview-placeholder {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 150px;
    color: #666;
}
</style>
