<script setup lang="ts">
import styles from '../css/Table.module.css'

/**
 * The design system's Table frame.
 *
 * Exposes the wrapper and the cell classes rather than taking rows as data: the
 * app's tables put components inside cells - badges, delete buttons, links -
 * which a data-driven table would have to grow an escape hatch for. The caller
 * writes ordinary <tr>/<td> and uses the classes this component provides.
 */
defineProps<{ caption?: string }>()

defineExpose({ styles })
</script>

<template>
  <div :class="styles.tableRoot">
    <div :class="styles.tableFrame">
      <div :class="styles.tableScroll">
        <table :class="styles.table">
          <caption v-if="caption" :class="styles.caption">{{ caption }}</caption>
          <slot :styles="styles" />
        </table>
      </div>
    </div>
  </div>
</template>
