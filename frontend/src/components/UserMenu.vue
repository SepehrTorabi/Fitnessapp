<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

/**
 * Everything that belongs to the person rather than to the app, behind their
 * own name.
 *
 * Body data, settings and signing out were three more items in a navigation bar
 * that is meant to hold the three things the app is *for*. They are not places
 * you go while using it - they are places you go once, or when something is
 * wrong - and the name is where people already look for them. It is also where
 * the next one goes: changing a password, exporting data, deleting an account.
 *
 * Built as a real menu rather than a hover card: it opens on click, it can be
 * driven entirely from the keyboard, and it closes on Escape or on a click
 * anywhere else - a hover menu is unusable on a touch screen and hostile to
 * anyone whose pointer is not steady.
 */
const auth = useAuthStore()
const router = useRouter()
const { t } = useI18n()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
const items = ref<HTMLElement | null>(null)

const displayName = computed(() => auth.user?.displayName ?? '')

/** The first letter, as a stand-in for an avatar we do not have. */
const initial = computed(() => displayName.value.trim().charAt(0).toUpperCase() || '?')

function toggle(): void {
  open.value = !open.value

  if (open.value) void nextTick(() => focusItem(0))
}

function close(returnFocus = false): void {
  open.value = false

  if (returnFocus) root.value?.querySelector<HTMLButtonElement>('.trigger')?.focus()
}

function focusItem(index: number): void {
  const focusable = items.value?.querySelectorAll<HTMLElement>('[data-menuitem]')

  if (!focusable || 0 === focusable.length) return

  // Wrapping rather than stopping at the ends: a five-item menu is faster to
  // reach the bottom of by pressing Up once.
  const target = (index + focusable.length) % focusable.length

  focusable[target]?.focus()
}

function currentIndex(): number {
  const focusable = [...(items.value?.querySelectorAll<HTMLElement>('[data-menuitem]') ?? [])]

  return focusable.indexOf(document.activeElement as HTMLElement)
}

function onKeydown(event: KeyboardEvent): void {
  if ('Escape' === event.key) {
    event.preventDefault()
    close(true)

    return
  }

  if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
    event.preventDefault()

    if (!open.value) {
      toggle()

      return
    }

    focusItem(currentIndex() + ('ArrowDown' === event.key ? 1 : -1))
  }
}

async function signOut(): Promise<void> {
  close()
  await auth.logout()
  await router.push({ name: 'login' })
}

function onPointerDown(event: MouseEvent): void {
  if (open.value && root.value && !root.value.contains(event.target as Node)) close()
}

document.addEventListener('mousedown', onPointerDown)
onBeforeUnmount(() => document.removeEventListener('mousedown', onPointerDown))
</script>

<template>
  <div ref="root" class="user-menu" @keydown="onKeydown">
    <button
      type="button"
      class="trigger"
      :aria-expanded="open"
      aria-haspopup="menu"
      :aria-label="t('nav.accountMenu')"
      @click="toggle"
    >
      <span class="avatar" aria-hidden="true">{{ initial }}</span>
      <span class="name">{{ displayName }}</span>
      <span class="caret" :class="{ 'caret-up': open }" aria-hidden="true"></span>
    </button>

    <div v-if="open" ref="items" class="menu" role="menu">
      <p class="menu-head">
        <span class="menu-name">{{ displayName }}</span>
        <!-- The address is always left-to-right, whatever the interface
             direction: an e-mail read right to left has its parts reordered. -->
        <span class="menu-email" dir="ltr">{{ auth.user?.email }}</span>
      </p>

      <RouterLink to="/profile" class="menu-item" role="menuitem" data-menuitem @click="close()">
        <span class="menu-icon" aria-hidden="true">📐</span>{{ t('nav.profile') }}
      </RouterLink>

      <RouterLink to="/settings" class="menu-item" role="menuitem" data-menuitem @click="close()">
        <span class="menu-icon" aria-hidden="true">⚙</span>{{ t('nav.settings') }}
      </RouterLink>

      <!-- Only for administrators, and only in this menu: it belongs with the
           things you do occasionally, not in a navigation bar most people would
           see a permanently useless item in. -->
      <RouterLink
        v-if="auth.isUserAdmin"
        to="/admin/users"
        class="menu-item"
        role="menuitem"
        data-menuitem
        @click="close()"
      >
        <span class="menu-icon" aria-hidden="true">👥</span>{{ t('nav.admin') }}
      </RouterLink>

      <hr class="menu-rule" />

      <button
        type="button"
        class="menu-item menu-button"
        role="menuitem"
        data-menuitem
        @click="signOut"
      >
        <span class="menu-icon" aria-hidden="true">↩</span>{{ t('nav.signOut') }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.user-menu { position: relative; }

.trigger {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-2xs);
  padding: var(--spacing-3xs) var(--spacing-2xs);
  background: transparent;
  border-color: transparent;
  color: var(--text-headings);
  font-weight: var(--type-font-weight-semi-bold);
  max-width: 220px;
}

.trigger:hover { background: var(--surface-subtle); }

.avatar {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  flex: none;
  border-radius: 50%;
  background: var(--surface-action);
  color: var(--text-on-action);
  font-size: var(--fontsize-body-small);
}

.name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/*
 * Physical borders, deliberately - the one place in this file where a logical
 * property would be wrong. This chevron points *down*, and down is the same
 * direction in every script. Built from border-inline-end it would flip with
 * the layout and end up pointing sideways in Farsi.
 */
.caret {
  width: 6px;
  height: 6px;
  flex: none;
  border-bottom: 2px solid currentColor;
  border-right: 2px solid currentColor;
  transform: rotate(45deg) translate(-2px, -2px);
  transition: transform 0.15s ease;
}

.caret-up { transform: rotate(-135deg) translate(-2px, -2px); }

.menu {
  position: absolute;
  top: calc(100% + 6px);
  /* Anchored to the end of the inline axis: the right in English and German,
     the left once the interface is mirrored for Farsi. One property, both. */
  inset-inline-end: 0;
  z-index: 30;
  min-width: 232px;
  padding: var(--spacing-3xs);
  background: var(--surface-primary);
  border: var(--border-width-small) solid var(--border-primary);
  border-radius: var(--border-radius-medium);
  box-shadow: 0 10px 30px rgb(0 0 0 / 18%);
}

.menu-head {
  display: flex;
  flex-direction: column;
  gap: 1px;
  margin: 0;
  padding: var(--spacing-2xs) var(--spacing-small) var(--spacing-small);
}

.menu-name { font-weight: var(--type-font-weight-semi-bold); color: var(--text-headings); }

.menu-email {
  font-size: var(--fontsize-body-small);
  color: var(--text-body);
  overflow: hidden;
  text-overflow: ellipsis;
  /* The address reads left to right, but the element still belongs to the
     mirrored layout, so it is aligned to the start of the inline axis. */
  text-align: start;
}

.menu-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-2xs);
  width: 100%;
  padding: var(--spacing-2xs) var(--spacing-small);
  border-radius: var(--border-radius-small);
  color: var(--text-body);
  font-size: var(--fontsize-body-medium);
  text-align: start;
}

.menu-item:hover {
  background: var(--surface-subtle);
  color: var(--text-headings);
  text-decoration: none;
}

.menu-button {
  background: transparent;
  border-color: transparent;
  font: inherit;
  font-weight: var(--type-font-weight-regular, 400);
  cursor: pointer;
}

.menu-icon { width: 18px; text-align: center; flex: none; }

.menu-rule {
  margin: var(--spacing-3xs) 0;
  border: none;
  border-top: var(--border-width-small) solid var(--border-primary);
}
</style>
