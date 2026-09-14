<script setup lang="ts">
import { RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppNav from '@/components/AppNav.vue'

const auth = useAuthStore()
</script>

<template>
  <!-- The router guard resolves who is signed in before the first view renders,
       so there is nothing meaningful to show until it has answered. -->
  <div v-if="!auth.ready" class="boot">Loading…</div>

  <template v-else>
    <AppNav v-if="auth.isAuthenticated" />
    <RouterView />
  </template>
</template>

<style scoped>
.boot {
  display: grid;
  place-items: center;
  min-height: 60vh;
  color: var(--text-muted);
}
</style>
