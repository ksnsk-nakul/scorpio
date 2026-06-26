<template>
  <AdminLayout>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-slate-800">Service Cards</h1>
      <Link href="/admin/service-cards/create"
        class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">
        + New Card
      </Link>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <div v-if="cards.length === 0" class="p-8 text-center text-slate-400 text-sm">
        No service cards yet.
      </div>
      <ul class="divide-y divide-slate-100">
        <li v-for="card in cards" :key="card.id"
          class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50">
          <span class="text-2xl w-8">{{ card.icon || '🎴' }}</span>
          <div class="flex-1 min-w-0">
            <p class="font-medium text-slate-800 text-sm">{{ card.title }}</p>
            <p class="text-xs text-slate-400 truncate">{{ card.description }}</p>
          </div>
          <span v-if="card.featured"
            class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Featured</span>
          <span v-if="card.page" class="text-xs text-blue-600">→ {{ card.page.name }}</span>
          <div class="flex gap-2 flex-shrink-0">
            <Link :href="`/admin/service-cards/${card.id}/edit`"
              class="text-xs text-slate-500 hover:text-slate-800 px-2 py-1 border border-slate-200 rounded">
              Edit
            </Link>
            <button @click="deleteCard(card.id)"
              class="text-xs text-red-500 hover:text-red-700 px-2 py-1 border border-red-100 rounded">
              Delete
            </button>
          </div>
        </li>
      </ul>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ cards: Array, pages: Array })

const deleteCard = (id) => {
  if (confirm('Delete this card?')) {
    router.delete(`/admin/service-cards/${id}`)
  }
}
</script>
