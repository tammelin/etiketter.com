<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { fetchFormFields, submitSticker } from '@/services/api';
import { addToCart, updateCartFragments, openBricksMiniCart } from '@/services/cart';
import type { FormFields, FormValues } from '@/types';
import { useRoute } from 'vue-router';

// Get uuid from path params
const route = useRoute();
const uuid = route.params.uuid as string | undefined;

if(uuid) {
    console.log('UUID from URL:', uuid);
}

const formFields = ref<FormFields | null>(null);
const formValues = ref<FormValues>({
    size: '',
    color: '',
    symbol: ''
});

onMounted(async () => {
    formFields.value = await fetchFormFields();
});

async function onFormSubmit() {
    const data = await submitSticker(formValues.value);
    const cartData = await addToCart(data.product_id, data.sticker_uuid);

    if (cartData.fragments) {
        updateCartFragments(cartData.fragments);
    }
    openBricksMiniCart();
}
</script>

<template>
    <div>
        <form @submit.prevent="onFormSubmit">
            <h3>Välj storlek</h3>
            <fieldset>
                <div v-for="size in formFields?.sizes" :key="size.dimensions.width + size.dimensions.height">
                    <input type="radio" :id="`${size.dimensions.width}x${size.dimensions.height}`" name="size" :value="size" v-model="formValues.size" />
                    <label :for="`${size.dimensions.width}x${size.dimensions.height}`">
                        {{ size.dimensions.width }} x {{ size.dimensions.height }} mm - {{ size.shape }}
                    </label>

                </div>
            </fieldset>
            <h3>Välj färg</h3>
            <fieldset>
                <div v-for="color in formFields?.colors" :key="color.name">
                    <input type="radio" :id="color.name" name="color" :value="color.color" v-model="formValues.color" />
                    <label :for="color.name">
                        {{ color.name }} - <span :style="{ backgroundColor: color.color, display: 'inline-block', width: '20px', height: '20px' }"></span>
                    </label>
                </div>
            </fieldset>
            <h3>Välj symbol</h3>
            <fieldset>
                <div v-for="symbol in formFields?.symbols" :key="symbol.symbol.description">
                    <input type="radio" :id="symbol.symbol.description" name="symbol" :value="symbol.symbol.image" v-model="formValues.symbol" />
                    <label :for="symbol.symbol.description">
                        <img :src="symbol.symbol.image" :alt="symbol.symbol.description" style="width: 50px; height: 50px;" />
                        {{ symbol.symbol.description }}
                    </label>
                </div>
            </fieldset>
            <button type="submit">Skicka</button>
        </form>
    </div>
</template>