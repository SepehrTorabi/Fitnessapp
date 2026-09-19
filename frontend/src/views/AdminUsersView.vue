<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import { useAuthStore } from '@/stores/auth'
import { formatDate } from '@/calendar'
import { usePreferencesStore } from '@/stores/preferences'
import type { AdminUser, Role } from '@/api/types'
import { DsAlert, DsCard } from '@/design-system/components'

/**
 * Who may do what.
 *
 * Deliberately the smallest screen that does the job. It shows a name, an
 * address, whether the account is confirmed, and two checkboxes - and nothing
 * else, because administering roles never requires knowing somebody's weight or
 * reading their diary. The API sends no more than this either, so the
 * restraint is enforced rather than merely observed.
 *
 * The one rule worth knowing about lives on the server: the last remaining
 * administrator cannot be demoted. This screen does not try to predict that,
 * it just reports what the server says - a client-side guess would be wrong the
 * moment somebody else changes a role in another tab.
 */
const { t, locale } = useI18n()
const auth = useAuthStore()
const preferences = usePreferencesStore()
const apiMessage = useApiMessage()

const users = ref<AdminUser[]>([])
const assignableRoles = ref<Role[]>([])
const query = ref('')

const loading = ref(true)
const error = ref('')
const notice = ref('')
/** The row currently being saved, so only its own button shows a busy state. */
const savingId = ref<number | null>(null)

/** Local, unsaved role edits, keyed by user id. */
const drafts = ref<Record<number, Role[]>>({})

/**
 * What each role lets somebody do, shown once above the table rather than
 * beside every checkbox. Repeated per row it was the same two sentences six
 * times over, and it made each row taller than the account it described.
 */
const roleHints: Record<string, string> = {
  ROLE_TRAINER: 'role.trainerHint',
  ROLE_USER_ADMIN: 'role.adminHint',
}

const me = computed(() => auth.user?.id ?? null)

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const response = await api.adminUsers(query.value.trim() || undefined)

    users.value = response.users
    assignableRoles.value = response.assignableRoles
    drafts.value = Object.fromEntries(
      response.users.map((user) => [user.id, user.roles.filter(isAssignable)]),
    )
  } catch (e) {
    error.value = apiMessage(e, 'admin.loadFailed')
  } finally {
    loading.value = false
  }
}

function isAssignable(role: Role): boolean {
  return role !== 'ROLE_USER'
}

function has(user: AdminUser, role: Role): boolean {
  return drafts.value[user.id]?.includes(role) ?? false
}

function toggle(user: AdminUser, role: Role): void {
  const current = drafts.value[user.id] ?? []

  drafts.value = {
    ...drafts.value,
    [user.id]: current.includes(role)
      ? current.filter((r) => r !== role)
      : [...current, role],
  }
}

/** Whether this row differs from what the server last told us. */
function isDirty(user: AdminUser): boolean {
  const draft = [...(drafts.value[user.id] ?? [])].sort()
  const saved = [...user.roles.filter(isAssignable)].sort()

  return draft.join() !== saved.join()
}

async function save(user: AdminUser): Promise<void> {
  savingId.value = user.id
  error.value = ''
  notice.value = ''

  try {
    const updated = (await api.setUserRoles(user.id, drafts.value[user.id] ?? [])).user

    users.value = users.value.map((u) => (u.id === updated.id ? updated : u))
    notice.value = t('admin.saved')

    // Somebody who has just changed their own roles is looking at a stale copy
    // of themselves everywhere else in the app - the navigation included.
    if (updated.id === me.value) await auth.refresh()
  } catch (e) {
    error.value = apiMessage(e, 'admin.saveFailed')
  } finally {
    savingId.value = null
  }
}

function joined(user: AdminUser): string {
  return formatDate(user.createdAt.slice(0, 10), locale.value, preferences.calendar)
}

onMounted(load)
</script>

<template>
  <div class="page">
    <h1>{{ t('admin.title') }}</h1>
    <p class="muted intro">{{ t('admin.intro') }}</p>

    <form class="row search" @submit.prevent="load">
      <input v-model="query" type="search" :placeholder="t('admin.search')" />
      <button type="submit">{{ t('common.search') }}</button>
    </form>

    <!-- The legend, so the table itself can stay compact. -->
    <ul v-if="assignableRoles.length" class="legend">
      <li v-for="role in assignableRoles" :key="role">
        <strong>{{ t(`role.${role}`) }}</strong>
        <span class="muted"> — {{ t(roleHints[role] ?? '') }}</span>
      </li>
    </ul>

    <DsAlert v-if="error" status="error" class="block">{{ error }}</DsAlert>
    <DsAlert v-else-if="notice" status="success" class="block">{{ notice }}</DsAlert>

    <p v-if="loading" class="muted">{{ t('common.loading') }}</p>

    <p v-else-if="users.length === 0" class="empty">{{ t('admin.noUsers') }}</p>

    <DsCard v-else>
      <!-- Its own scroll container: a table of accounts with two role
           checkboxes each does not fit a phone, and the rule is that the page
           body never scrolls sideways even when a table has to. -->
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th scope="col">{{ t('admin.tableUser') }}</th>
              <th scope="col">{{ t('admin.tableRoles') }}</th>
              <th scope="col">{{ t('admin.tableJoined') }}</th>
              <th class="shrink"><span class="visually-hidden">{{ t('admin.save') }}</span></th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td>
                <strong>{{ user.displayName }}</strong>
                <span v-if="user.id === me" class="muted small">&nbsp;({{ t('admin.you') }})</span>
                <!-- Always left to right, whatever the interface direction: an
                     address read right to left has its parts reordered. -->
                <span class="muted small block" dir="ltr">{{ user.email }}</span>
                <span v-if="!user.verified" class="badge">{{ t('admin.unverified') }}</span>
              </td>

              <td>
                <div class="roles">
                  <label v-for="role in assignableRoles" :key="role" class="role">
                    <input
                      type="checkbox"
                      :checked="has(user, role)"
                      @change="toggle(user, role)"
                    />
                    <span>{{ t(`role.${role}`) }}</span>
                  </label>
                </div>
              </td>

              <td class="muted small nowrap">{{ joined(user) }}</td>

              <td class="num shrink">
                <button
                  type="button"
                  class="secondary small-btn"
                  :disabled="!isDirty(user) || savingId === user.id"
                  @click="save(user)"
                >
                  {{ savingId === user.id ? t('common.saving') : t('admin.save') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </DsCard>
  </div>
</template>

<style scoped>
.intro { margin: 0 0 var(--spacing-medium); max-width: 70ch; }
.search { margin-bottom: var(--spacing-medium); }
.search input { flex: 1; max-width: 320px; }
.block { margin-bottom: var(--spacing-medium); }

.legend {
  list-style: none;
  margin: 0 0 var(--spacing-medium);
  padding: var(--spacing-2xs) var(--spacing-small);
  background: var(--surface-subtle);
  border-radius: var(--border-radius-small);
  font-size: var(--fontsize-body-small);
}

.legend li + li { margin-top: 2px; }

.table-scroll { overflow-x: auto; }

/* Roles side by side rather than stacked: two checkboxes on one line keep a row
   the height of the name it belongs to. */
.roles { display: flex; flex-wrap: wrap; gap: var(--spacing-2xs) var(--spacing-medium); }

.role {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-3xs);
  margin: 0;
  white-space: nowrap;
  font-weight: var(--type-font-weight-regular, 400);
}

.role input { width: auto; }

.nowrap { white-space: nowrap; }
.shrink { width: 1%; white-space: nowrap; }
.small-btn { padding: 5px 11px; font-size: 13px; }
.block { display: block; }
</style>
