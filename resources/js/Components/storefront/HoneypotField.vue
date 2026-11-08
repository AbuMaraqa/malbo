<script setup lang="ts">
/** Hidden from people; a filled value marks a bot. The server thanks it and saves nothing. */
const model = defineModel<string>({ default: '' });
</script>

<template>
    <div class="absolute -start-[9999px] size-px overflow-hidden" aria-hidden="true">
        <label>
            Website
            <input v-model="model" type="text" name="website" tabindex="-1" autocomplete="off" />
        </label>
    </div>
</template>
