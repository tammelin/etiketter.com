<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { fetchFormFields, fetchSticker, submitSticker, updateSticker } from '@/services/api';
import { addToCart, updateCartFragments, openBricksMiniCart } from '@/services/cart';
import type { FormFields, FormValues, TextLine } from '@/types';
import { calculateMaxLines, calculateMaxChars } from '@/utils/textCalculations';
import StickerPreview from '@/components/StickerPreview.vue';
import { __ } from '@/utils/i18n';

const uuid = ref<string | undefined>(new URLSearchParams(window.location.search).get('sticker-uuid') ?? undefined);

const formFields = ref<FormFields | null>(null);
const previewRef = ref<InstanceType<typeof StickerPreview> | null>(null);

// Create a default text line
function createDefaultTextLine(): TextLine {
    return {
        content: '',
        fontFamily: 'sans-serif',
        fontStyle: 'normal',
        fontWeight: 'normal',
    };
}

const formValues = ref<FormValues>({
    size: null,
    color: '',
    symbol: '',
    textLines: [],
    textAlignment: 'center',
});

// Calculate max chars based on selected size
const maxChars = computed(() => {
    if (!formValues.value.size) return 0;
    if (formValues.value.size.max_chars) return formValues.value.size.max_chars;
    const width = parseInt(formValues.value.size.dimensions.width, 10);
    return calculateMaxChars(width);
});

// Watch for size changes to update text lines array
watch(
    () => formValues.value.size,
    (newSize) => {
        if (!newSize) {
            formValues.value.textLines = [];
            return;
        }

        const newMaxLines = newSize.max_rows ?? calculateMaxLines(parseInt(newSize.dimensions.height, 10));
        const currentLines = formValues.value.textLines;

        if (currentLines.length < newMaxLines) {
            const linesToAdd = newMaxLines - currentLines.length;
            for (let i = 0; i < linesToAdd; i++) {
                currentLines.push(createDefaultTextLine());
            }
        } else if (currentLines.length > newMaxLines) {
            formValues.value.textLines = currentLines.slice(0, newMaxLines);
        }
    },
    { immediate: true }
);

onMounted(async () => {
    formFields.value = await fetchFormFields();

    if (uuid.value) {
        const response = await fetchSticker(uuid.value);
        const data = response.data;
        formValues.value.size = data.size;
        formValues.value.color = data.color;
        formValues.value.symbol = data.symbol;
        formValues.value.textLines = data.textLines;
        formValues.value.textAlignment = data.textAlignment;
    }
});

const isEditing = computed(() => !!uuid.value);

async function onFormSubmit() {
    const svgContent = previewRef.value?.getSvgContent();

    if (uuid.value) {
        await updateSticker(uuid.value, formValues.value, svgContent);
        // Refresh WooCommerce cart fragments so the thumbnail updates
        const refreshResponse = await fetch('/?wc-ajax=get_refreshed_fragments');
        const refreshData = await refreshResponse.json();
        if (refreshData.fragments) {
            updateCartFragments(refreshData.fragments);
        }
        openBricksMiniCart();
    } else {
        const data = await submitSticker(formValues.value, svgContent);
        const cartData = await addToCart(data.product_id, data.sticker_uuid);
        if (cartData.fragments) {
            updateCartFragments(cartData.fragments);
        }
        openBricksMiniCart();
        uuid.value = data.sticker_uuid;
        history.replaceState({}, '', '?sticker-uuid=' + data.sticker_uuid);
    }
}

async function onSaveAsNew() {
    const svgContent = previewRef.value?.getSvgContent();
    const data = await submitSticker(formValues.value, svgContent);
    const cartData = await addToCart(data.product_id, data.sticker_uuid);
    if (cartData.fragments) {
        updateCartFragments(cartData.fragments);
    }
    openBricksMiniCart();
    uuid.value = data.sticker_uuid;
    history.replaceState({}, '', '?sticker-uuid=' + data.sticker_uuid);
}

function onCreateNew() {
    if (!confirm(__('confirmCreateNew'))) return;
    uuid.value = undefined;
    history.replaceState({}, '', window.location.pathname);
}

// Enforce character limit on text input
function onTextInput(index: number, event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.value.length > maxChars.value) {
        input.value = input.value.slice(0, maxChars.value);
        formValues.value.textLines[index].content = input.value;
    }
}
</script>

<template>
    <div class="sticker-form-container">
        <form @submit.prevent="onFormSubmit" class="sticker-form">
            <h3>{{ __('chooseSize') }}</h3>
            <fieldset>
                <div v-for="size in formFields?.sizes" :key="size.dimensions.width + size.dimensions.height">
                    <input
                        type="radio"
                        :id="`${size.dimensions.width}x${size.dimensions.height}`"
                        name="size"
                        :value="size"
                        v-model="formValues.size"
                    />
                    <label :for="`${size.dimensions.width}x${size.dimensions.height}`">
                        {{ size.dimensions.width }} x {{ size.dimensions.height }} mm - {{ size.shape }}
                    </label>
                </div>
            </fieldset>

            <h3>{{ __('chooseColor') }}</h3>
            <fieldset>
                <div v-for="color in formFields?.colors" :key="color.name">
                    <input type="radio" :id="color.name" name="color" :value="color.color" v-model="formValues.color" />
                    <label :for="color.name">
                        {{ color.name }} -
                        <span
                            :style="{ backgroundColor: color.color, display: 'inline-block', width: '20px', height: '20px' }"
                        ></span>
                    </label>
                </div>
            </fieldset>

            <h3>{{ __('chooseSymbol') }}</h3>
            <fieldset>
                <div v-for="symbol in formFields?.symbols" :key="symbol.symbol.description">
                    <input
                        type="radio"
                        :id="symbol.symbol.description"
                        name="symbol"
                        :value="symbol.symbol.image"
                        v-model="formValues.symbol"
                    />
                    <label :for="symbol.symbol.description">
                        <img :src="symbol.symbol.image" :alt="symbol.symbol.description" style="width: 50px; height: 50px" />
                        {{ symbol.symbol.description }}
                    </label>
                </div>
            </fieldset>

            <template v-if="formValues.size">
                <h3>{{ __('addText') }}</h3>
                <fieldset class="text-input-section">
                    <div v-for="(line, index) in formValues.textLines" :key="index" class="text-line">
                        <div class="text-line-input">
                            <label :for="`text-line-${index}`">{{ __('row') }} {{ index + 1 }}</label>
                            <input
                                type="text"
                                :id="`text-line-${index}`"
                                v-model="line.content"
                                :maxlength="maxChars"
                                @input="onTextInput(index, $event)"
                                :placeholder="__('maxChars').replace('%s', String(maxChars))"
                            />
                            <span class="char-count">{{ line.content.length }}/{{ maxChars }}</span>
                        </div>
                        <div class="text-line-style">
                            <label>
                                <input type="radio" :name="`font-${index}`" value="sans-serif" v-model="line.fontFamily" />
                                {{ __('sansSerif') }}
                            </label>
                            <label>
                                <input type="radio" :name="`font-${index}`" value="serif" v-model="line.fontFamily" />
                                {{ __('serif') }}
                            </label>
                            <label>
                                <input type="checkbox" :checked="line.fontStyle === 'italic'" @change="line.fontStyle = line.fontStyle === 'italic' ? 'normal' : 'italic'" />
                                {{ __('italic') }}
                            </label>
                            <label>
                                <input type="checkbox" :checked="line.fontWeight === 'bold'" @change="line.fontWeight = line.fontWeight === 'bold' ? 'normal' : 'bold'" />
                                {{ __('bold') }}
                            </label>
                        </div>
                    </div>

                    <div class="text-alignment">
                        <span>{{ __('textAlignment') }}</span>
                        <label>
                            <input type="radio" name="alignment" value="left" v-model="formValues.textAlignment" />
                            {{ __('alignLeft') }}
                        </label>
                        <label>
                            <input type="radio" name="alignment" value="center" v-model="formValues.textAlignment" />
                            {{ __('alignCenter') }}
                        </label>
                        <label>
                            <input type="radio" name="alignment" value="right" v-model="formValues.textAlignment" />
                            {{ __('alignRight') }}
                        </label>
                    </div>
                </fieldset>
            </template>

            <div class="form-actions">
                <button type="submit">{{ isEditing ? __('save') : __('saveAndAddToCart') }}</button>
                <button v-if="isEditing" type="button" @click="onSaveAsNew">{{ __('saveAsNewAndAddToCart') }}</button>
                <button v-if="isEditing" type="button" @click="onCreateNew">{{ __('createNew') }}</button>
            </div>
        </form>

        <StickerPreview ref="previewRef" :formValues="formValues" />
    </div>
</template>

<style scoped>
.sticker-form-container {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.sticker-form {
    flex: 1;
    min-width: 300px;
}

:deep(.sticker-preview) {
    position: sticky;
    top: 1rem;
    align-self: flex-start;
}

.text-input-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.text-line {
    border: 1px solid #eee;
    padding: 0.75rem;
    border-radius: 4px;
}

.text-line-input {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.text-line-input input[type='text'] {
    flex: 1;
    padding: 0.5rem;
}

.char-count {
    font-size: 0.8rem;
    color: #666;
    min-width: 50px;
}

.text-line-style {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.text-line-style label {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.text-alignment {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-top: 0.5rem;
    border-top: 1px solid #eee;
}

.text-alignment label {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.form-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}
</style>
