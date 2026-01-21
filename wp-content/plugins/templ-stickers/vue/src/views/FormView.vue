<script setup lang="ts">
import { ref } from 'vue';

interface Dimensions {
    width: string;
    height: string;
}

interface Size {
    dimensions: Dimensions;
    shape: string;
}

interface Color {
    name: string;
    color: string;
}

interface Symbol {
    symbol: {
        image: string;
        description: string;
    };
}

interface FormFields {
    sizes: Size[];
    colors: Color[];
    symbols: Symbol[];
}

const formFields = ref<FormFields | null>(null);

fetch(`${import.meta.env.VITE_API_BASE_URL}/wp-json/templ-stickers/v1/form-fields`)
    .then(response => response.json())
    .then((json: FormFields) => {
        formFields.value = json;
    });

const formValues = ref({
    size: '',
    color: '',
    symbol: ''
});

const onFormSubmit = () => {
    console.log('Form submitted');
    console.log(formValues.value);
};
</script>

<template>
    <div>
        <pre>{{ formValues }}</pre>
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
    <pre>{{ formFields }}</pre>
</template>