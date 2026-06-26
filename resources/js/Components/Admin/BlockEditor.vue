<template>
  <div class="space-y-3">
    <div
      v-for="(block, idx) in blocks"
      :key="idx"
      class="relative border-2 rounded-xl p-4 cursor-pointer"
      :class="idx === active ? 'border-blue-500 bg-blue-50/30' : 'border-slate-200 bg-white'"
      @click="active = idx"
    >
      <span
        class="absolute -top-3 left-3 text-xs font-semibold px-2 py-0.5 rounded text-white"
        :class="blockColor(block.type)"
      >{{ block.type.replace(/_/g, ' ').toUpperCase() }}</span>

      <template v-if="block.type === 'hero'">
        <input v-model="block.data.heading" placeholder="Heading"
          class="w-full text-lg font-bold bg-transparent outline-none mb-1" @click.stop />
        <input v-model="block.data.subheading" placeholder="Subheading"
          class="w-full text-sm text-slate-500 bg-transparent outline-none" @click.stop />
      </template>

      <template v-else-if="block.type === 'text'">
        <textarea v-model="block.data.content" placeholder="Text content..."
          class="w-full bg-transparent outline-none text-sm resize-none min-h-20" @click.stop />
      </template>

      <template v-else-if="block.type === 'text_image'">
        <textarea v-model="block.data.text" placeholder="Text content..."
          class="w-full bg-transparent outline-none text-sm resize-none min-h-16 mb-2" @click.stop />
        <input v-model="block.data.image" placeholder="Image URL or upload path"
          class="w-full border border-slate-200 rounded px-3 py-1.5 text-sm outline-none" @click.stop />
      </template>

      <template v-else>
        <p class="text-sm text-slate-400 italic">{{ blockDescription(block.type) }}</p>
      </template>

      <div class="absolute top-2 right-2 flex gap-1" @click.stop>
        <button @click="move(idx, -1)" :disabled="idx === 0"
          class="text-slate-400 hover:text-slate-700 disabled:opacity-30 text-xs px-1">▲</button>
        <button @click="move(idx, 1)" :disabled="idx === blocks.length - 1"
          class="text-slate-400 hover:text-slate-700 disabled:opacity-30 text-xs px-1">▼</button>
        <button @click="remove(idx)"
          class="text-red-400 hover:text-red-600 text-xs px-1">✕</button>
      </div>
    </div>

    <div class="border-2 border-dashed border-slate-200 rounded-xl p-4">
      <p class="text-xs text-slate-400 mb-3 text-center">Add block</p>
      <div class="flex flex-wrap gap-2 justify-center">
        <button
          v-for="type in blockTypes"
          :key="type"
          @click="addBlock(type)"
          class="text-xs px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
        >{{ type.replace(/_/g, ' ') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({ modelValue: Array, blockTypes: Array })
const emit = defineEmits(['update:modelValue'])

const active = ref(null)

const blocks = computed({
  get: () => props.modelValue ?? [],
  set: val => emit('update:modelValue', val),
})

const addBlock = (type) => {
  blocks.value = [...blocks.value, { type, order: blocks.value.length, data: {} }]
}

const remove = (idx) => {
  blocks.value = blocks.value.filter((_, i) => i !== idx)
  if (active.value === idx) active.value = null
}

const move = (idx, dir) => {
  const arr = [...blocks.value]
  const target = idx + dir
  if (target < 0 || target >= arr.length) return
  ;[arr[idx], arr[target]] = [arr[target], arr[idx]]
  blocks.value = arr
}

const blockColor = (t) => ({
  hero:          'bg-blue-500',
  text:          'bg-amber-500',
  text_image:    'bg-purple-500',
  service_cards: 'bg-violet-500',
  project_grid:  'bg-teal-500',
  contact_form:  'bg-rose-500',
}[t] ?? 'bg-slate-500')

const blockDescription = (t) => ({
  service_cards: 'Renders all featured service cards in a grid',
  project_grid:  'Renders projects from the linked workspace in a grid',
  contact_form:  'Renders a contact/inquiry form section',
}[t] ?? '')
</script>
