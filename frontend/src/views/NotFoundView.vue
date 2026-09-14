<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="page">
    <div class="card">
      <h1>Page not found</h1>
      <p class="muted">That address does not lead anywhere.</p>
      <RouterLink to="/">Back to the dashboard</RouterLink>
    </div>
  </div>
</template>
