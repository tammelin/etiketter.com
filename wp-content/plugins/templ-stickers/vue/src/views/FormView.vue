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
        textStyle: 'straight',
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

// Colors available for the selected size (falls back to global list)
const availableColors = computed(() =>
    formValues.value.size?.colors?.length
        ? formValues.value.size.colors
        : formFields.value?.colors ?? []
);

// Calculate max chars based on selected size
const maxChars = computed(() => {
    if (!formValues.value.size) return 0;
    if (formValues.value.size.max_chars) return formValues.value.size.max_chars;
    const width = parseInt(formValues.value.size.dimensions.width, 10);
    return calculateMaxChars(width);
});

const quantity = ref(1);

const quantityError = computed(() => {
    const rules = formValues.value.size?.quantity;
    if (!rules) return null;
    const qty = quantity.value;
    if (qty < rules.min) return `Minsta antal är ${rules.min}`;
    if (rules.max !== '' && qty > rules.max) return `Högsta antal är ${rules.max}`;
    if ((qty - rules.min) % rules.step !== 0) return `Antal måste vara i steg om ${rules.step} (t.ex. ${rules.min}, ${rules.min + rules.step}, ...)`;
    return null;
});

const lineTotal = computed(() => {
    const size = formValues.value.size;
    if (!size) return null;
    return size.unit_price * quantity.value + size.base_price;
});

// Watch for size changes to update text lines array (and reset quantity)
watch(
    () => formValues.value.size,
    (newSize) => {
        quantity.value = newSize?.quantity?.min ?? 1;
        if (!newSize) {
            formValues.value.textLines = [];
            return;
        }

        // Reset color if it's not available for the new size
        const colors = newSize.colors?.length ? newSize.colors : formFields.value?.colors ?? [];
        if (formValues.value.color && !colors.find(c => c.color === formValues.value.color)) {
            formValues.value.color = '';
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

    if (!uuid.value && formFields.value?.sizes?.length) {
        formValues.value.size = formFields.value.sizes[0];
        quantity.value = formFields.value.sizes[0].quantity?.min ?? 1;
        const firstSize = formFields.value.sizes[0];
        const colors = firstSize.colors?.length ? firstSize.colors : formFields.value.colors ?? [];
        if (colors.length) {
            formValues.value.color = colors[0].color;
        }
    }

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
        const cartData = await addToCart(data.product_id, data.sticker_uuid, quantity.value);
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
    const cartData = await addToCart(data.product_id, data.sticker_uuid, quantity.value);
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
            <h3><span class="step-number">1</span> {{ __('chooseSizeAndColor') }}</h3>

            <div class="size-and-color-container">
                <fieldset>
                    <div v-for="size in formFields?.sizes" :key="size.dimensions.width + size.dimensions.height">
                        <label :for="`${size.dimensions.width}x${size.dimensions.height}`">
                            <input
                            type="radio"
                            :id="`${size.dimensions.width}x${size.dimensions.height}`"
                            name="size"
                            :value="size"
                            v-model="formValues.size"
                            />
                            {{ size.dimensions.width }} x {{ size.dimensions.height }} mm - {{ size.shape }}
                        </label>
                    </div>
                </fieldset>
    
                <fieldset>
                    <div v-for="color in availableColors" :key="color.name">
                        <label :for="color.name">
                            <input type="radio" :id="color.name" name="color" :value="color.color" v-model="formValues.color" />
                            {{ color.name }}
                            <span
                                :style="{ backgroundColor: color.color, display: 'inline-block', width: '20px', height: '20px' }"
                            ></span>
                        </label>
                    </div>
                </fieldset>
            </div>

            <!-- <template v-if="formValues.size"> -->
                <h3><span class="step-number">2</span> {{ __('addText') }}</h3>
                <div class="text-and-preview-container">
                    <fieldset class="text-input-section">
                        <div class="headers">
                            <span></span>
                            <span>{{ __('columnText') }}</span>
                            <span>{{ __('columnStraight') }}</span>
                            <span>{{ __('columnItalic') }}</span>
                            <span>{{ __('columnCursive') }}</span>
                            <span>{{ __('columnBold') }}</span>
                        </div>
                        <div v-for="(line, index) in formValues.textLines" :key="index" class="text-line">
                            <label :for="`text-line-${index}`">
                                {{ __('row') }}
                                <!-- {{ index + 1 }} -->
                            </label>
                            <input
                                type="text"
                                :id="`text-line-${index}`"
                                v-model="line.content"
                                :maxlength="maxChars"
                                @input="onTextInput(index, $event)"
                                :placeholder="__('maxChars').replace('%s', String(maxChars))"
                            />
                            <!-- <span class="char-count">{{ line.content.length }}/{{ maxChars }}</span> -->
                            <label>
                                <input type="radio" :name="`font-${index}`" value="straight" v-model="line.textStyle" />
                                <!-- {{ __('columnStraight') }} -->
                            </label>
                            <label>
                                <input type="radio" :name="`font-${index}`" value="italic" v-model="line.textStyle" />
                                <!-- {{ __('columnItalic') }} -->
                            </label>
                            <label>
                                <input type="radio" :name="`font-${index}`" value="cursive" v-model="line.textStyle" />
                                <!-- {{ __('columnCursive') }} -->
                            </label>
                            <label>
                                <input type="checkbox" :checked="line.fontWeight === 'bold'" @change="line.fontWeight = line.fontWeight === 'bold' ? 'normal' : 'bold'" />
                                <!-- {{ __('columnBold') }} -->
                            </label>
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
                        </div>
                    </fieldset>
                    <div class="preview-conainer">
                        <StickerPreview ref="previewRef" :formValues="formValues" />
                            <div class="quantity-input">
                            <label for="sticker-quantity">Antal</label>
                            <input
                                id="sticker-quantity"
                                type="number"
                                v-model.number="quantity"
                                :min="formValues.size?.quantity?.min ?? 1"
                                :max="formValues.size?.quantity?.max || undefined"
                                :step="formValues.size?.quantity?.step ?? 1"
                            />
                            <span v-if="quantityError" class="quantity-error">{{ quantityError }}</span>
                            <span v-else-if="lineTotal !== null" class="line-total">
                                {{ lineTotal.toLocaleString('sv-SE', { style: 'currency', currency: 'SEK' }) }}
                            </span>
                        </div>
                    </div>
                </div>
            <!-- </template> -->

            <h3><span class="step-number">3</span> {{ __('chooseSymbol') }}</h3>
            <fieldset class="symbol-fieldset">
                <div
                    v-for="symbol in formFields?.symbols"
                    :key="symbol.symbol.description"
                    class="symbol-tile"
                    :class="{ selected: formValues.symbol === symbol.symbol.image }"
                    @click="formValues.symbol = symbol.symbol.image"
                >
                    <input
                        type="radio"
                        :id="symbol.symbol.description"
                        name="symbol"
                        :value="symbol.symbol.image"
                        v-model="formValues.symbol"
                    />
                    <img :src="symbol.symbol.image" :alt="symbol.symbol.description" />
                    <button
                        v-if="formValues.symbol === symbol.symbol.image"
                        type="button"
                        class="symbol-deselect"
                        @click.stop="formValues.symbol = ''"
                        aria-label="Avmarkera symbol"
                    >&#x2715;</button>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" :disabled="!!quantityError">{{ isEditing ? __('save') : __('saveAndAddToCart') }}</button>
                <button v-if="isEditing" type="button" @click="onSaveAsNew" :disabled="!!quantityError">{{ __('saveAsNewAndAddToCart') }}</button>
                <button v-if="isEditing" type="button" @click="onCreateNew">{{ __('createNew') }}</button>
            </div>
        </form>

    </div>
</template>

<style lang="scss" scoped>
// Colors
$color-primary: #e5392a;
$color-accent: #e5392a;
$color-accent-light: #fce8e6;
$color-step-line: #e0e0e0;
$color-white: #fff;

form {
    padding-left: 80px;
    fieldset {
        // Reset
        border: none;
        margin: 0;
        padding: 0;
    }
    h3 {
        position: relative;
        .step-number {
            position: absolute;
            left: -40px;
            top: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: $color-primary;
            color: $color-white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            &::after {
                // Add a dashed line connecting the step number to the fieldset
                content: '';
                width: 0;
                height: 9999rem;
                left: 50%;
                border-left: 2px dashed $color-step-line;
                z-index: -1;
                top: 1em;
                position: absolute;
            }
        }
    }
}
.size-and-color-container {
    display: flex;
    gap: 4rem;
    margin-bottom: 2rem;
}
.text-and-preview-container {
    display: flex;
    gap: 2rem;
    margin-bottom: 2rem;
    fieldset {
        width: 50%;
        min-width: 35em;
    }
    .preview-conainer {
        width: 50%;
    }
    .headers,
    .text-line {
        display: grid;
        grid-template-columns: 3em 1fr repeat(4, 5em);
        align-items: center;
        gap: 0.5rem;
    }
    .headers {
        span {
            text-align: center;
            font-weight: bold;
            &:first-child {
                text-align: left;
            }
            &:nth-child(2) {
                text-align: left;
            }
        }
    }
    .text-line {
        &:not(:first-child) {
            margin-top: 1rem;
        }
        label {
            font-weight: bold;
            &:not(:first-child) {
                display: flex;
                justify-content: center;
            }
        }
    }
    .text-alignment {
        margin-top: 1.5rem;
        display: flex;
        align-items: center;
        gap: 2rem;
        & > span {
            font-weight: bold;
            margin-right: 1rem;
        }
    }
}
.symbol-fieldset {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}
.symbol-tile {
    position: relative;
    width: 90px;
    height: 90px;
    border: 2px solid transparent;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color 0.15s;

    input[type="radio"] {
        display: none;
    }

    img {
        width: 60px;
        height: 60px;
        object-fit: contain;
        pointer-events: none;
    }

    &:hover {
        border-color: $color-accent;
    }

    &.selected {
        border-color: $color-accent;
        box-shadow: 0 0 0 3px $color-accent-light;
    }
}
.symbol-deselect {
    position: absolute;
    top: -10px;
    right: -10px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2px solid $color-accent;
    background: $color-white;
    color: $color-accent;
    font-size: 11px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}
</style>
