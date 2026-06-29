<template>
  <Teleport to="body">
    <div class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
      <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-100 px-6 py-4 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800">Preview: {{ page.name }}</h2>
          <button @click="$emit('close')" class="text-slate-400 hover:text-slate-700 text-xl">✕</button>
        </div>
        <div class="p-6 space-y-8">
          <template v-for="block in page.blocks" :key="block.order">
            <!-- hero -->
            <div v-if="block.type === 'hero'" class="py-12 text-center">
              <h1 class="text-3xl font-bold text-slate-900 mb-3">{{ block.data.heading }}</h1>
              <p class="text-slate-500 max-w-xl mx-auto">{{ block.data.subheading }}</p>
            </div>
            <!-- text -->
            <div v-else-if="block.type === 'text'" class="prose max-w-none text-slate-700 whitespace-pre-line">
              {{ block.data.text }}
            </div>
            <!-- text_image -->
            <div v-else-if="block.type === 'text_image'" class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
              <p class="text-slate-600 whitespace-pre-line">{{ block.data.text }}</p>
              <img v-if="block.data.image" :src="block.data.image" class="rounded-xl w-full object-cover" />
            </div>
            <!-- service_cards -->
            <div v-else-if="block.type === 'service_cards'">
              <h2 class="text-xl font-bold text-slate-800 mb-4">{{ block.data.heading }}</h2>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div v-for="card in page.service_cards" :key="card.id"
                  class="border border-slate-200 rounded-xl p-4">
                  <div class="text-2xl mb-2">{{ card.icon }}</div>
                  <h3 class="font-semibold text-slate-800 text-sm mb-1">{{ card.title }}</h3>
                  <p class="text-xs text-slate-500">{{ card.description }}</p>
                </div>
              </div>
            </div>
            <!-- project_grid -->
            <div v-else-if="block.type === 'project_grid'">
              <h2 class="text-xl font-bold text-slate-800 mb-4">{{ block.data.heading }}</h2>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="(proj, i) in block.data.projects" :key="i"
                  class="border border-slate-200 rounded-xl p-4">
                  <h3 class="font-semibold text-slate-800 text-sm mb-1">{{ proj.title }}</h3>
                  <p class="text-xs text-slate-500">{{ proj.description }}</p>
                </div>
              </div>
            </div>
            <!-- contact_form -->
            <div v-else-if="block.type === 'contact_form'" class="bg-slate-50 rounded-xl p-6">
              <h2 class="text-xl font-bold text-slate-800 mb-2">{{ block.data.heading }}</h2>
              <p v-if="block.data.email" class="text-sm text-slate-600">📧 {{ block.data.email }}</p>
              <p v-if="block.data.phone" class="text-sm text-slate-600">📞 {{ block.data.phone }}</p>
            </div>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({ page: Object })
defineEmits(['close'])
</script>
