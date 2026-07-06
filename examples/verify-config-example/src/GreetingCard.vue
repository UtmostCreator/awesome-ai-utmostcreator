<template>
  <p>{{ greeting }}</p>
</template>

<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{ name: string }>();
const greeting = computed(() => `Hello, ${props.name}!`);
</script>
