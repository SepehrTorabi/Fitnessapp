<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="page">
    <div class="card">
      <h1>{{ t('notFound.title') }}</h1>
      <p class="muted">{{ t('notFound.body') }}</p>
      <RouterLink to="/">{{ t('notFound.back') }}</RouterLink>
    </div>
  </div>
</template>
