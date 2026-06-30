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
        <div @click.stop>
          <ImagePicker v-model="block.data.image" context="pages" />
        </div>
      </template>

      <!-- Service Cards block — shows cards with drag-to-reorder -->
      <template v-else-if="block.type === 'service_cards'">
        <div @click.stop>
          <input v-model="block.data.heading" placeholder="Section heading (optional)"
            class="w-full bg-transparent outline-none text-sm font-medium mb-3 border-b border-slate-100 pb-1" />
          <p v-if="!serviceCards.length" class="text-xs text-slate-400 italic">
            No service cards yet — <a href="/admin/service-cards" class="text-blue-500 underline">add some here</a>
          </p>
          <ul v-else class="space-y-1.5">
            <li
              v-for="(card, ci) in orderedCards"
              :key="card.id"
              draggable="true"
              @dragstart="cardDragIdx = ci"
              @dragover.prevent="cardDragOver = ci"
              @drop.prevent="dropCard(ci)"
              @dragend="cardDragIdx = cardDragOver = null"
              class="flex items-center gap-2 rounded-lg border border-slate-100 px-3 py-2 bg-white select-none transition"
              :class="cardDragOver === ci ? 'border-blue-400 bg-blue-50' : ''"
            >
              <span class="text-slate-300 cursor-grab text-sm">⠿</span>
              <span v-if="card.icon" class="text-base">{{ card.icon }}</span>
              <span class="flex-1 text-sm font-medium text-slate-700 truncate">{{ card.title }}</span>
              <span class="text-xs text-slate-400 truncate max-w-32 hidden sm:block">{{ card.description }}</span>
            </li>
          </ul>
          <a href="/admin/service-cards" class="mt-2 text-xs text-blue-500 hover:underline block">
            + Manage service cards →
          </a>
        </div>
      </template>

      <!-- Project Grid block -->
      <template v-else-if="block.type === 'project_grid'">
        <div @click.stop>
          <input v-model="block.data.heading" placeholder="Section heading"
            class="w-full bg-transparent outline-none text-sm font-medium mb-2" />
          <label class="text-xs text-slate-400 block mb-1">Link to workspace (optional)</label>
          <select v-model="block.data.workspace_id"
            class="w-full border border-slate-200 rounded-lg px-3 py-1.5 text-sm outline-none bg-white mb-3">
            <option :value="undefined">— Use inline project data —</option>
            <option v-for="ws in workspaces" :key="ws.id" :value="ws.id">{{ ws.name }} ({{ ws.projects?.length ?? 0 }} products)</option>
          </select>

          <!-- Linked workspace products with drag-to-reorder -->
          <template v-if="block.data.workspace_id">
            <p class="text-xs text-slate-400 mb-1.5">Drag to reorder products on the public page</p>
            <ul class="space-y-1.5">
              <li
                v-for="(product, pi) in orderedProducts(block.data.workspace_id)"
                :key="product.id"
                draggable="true"
                @dragstart="productDragIdx = pi; productDragWs = block.data.workspace_id"
                @dragover.prevent="productDragOver = pi"
                @drop.prevent="dropProduct(pi, block.data.workspace_id)"
                @dragend="productDragIdx = productDragOver = null"
                class="flex items-center gap-2 rounded-lg border border-slate-100 px-3 py-2 bg-white select-none"
                :class="productDragOver === pi ? 'border-teal-400 bg-teal-50' : ''"
              >
                <span class="text-slate-300 cursor-grab text-sm">⠿</span>
                <span class="flex-1 text-sm font-medium text-slate-700 truncate">{{ product.name }}</span>
                <span class="text-xs px-1.5 py-0.5 rounded"
                  :class="product.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
                  {{ product.status }}
                </span>
              </li>
            </ul>
          </template>
        </div>
      </template>

      <!-- Contact Form block -->
      <template v-else-if="block.type === 'contact_form'">
        <div @click.stop class="space-y-2">
          <input v-model="block.data.heading" placeholder="Section heading (e.g. Get in touch)"
            class="w-full bg-transparent outline-none text-sm font-medium border-b border-slate-100 pb-1" />
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="text-xs text-slate-400 block mb-0.5">Email</label>
              <input v-model="block.data.email" placeholder="you@example.com" type="email"
                class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-sm outline-none" />
            </div>
            <div>
              <label class="text-xs text-slate-400 block mb-0.5">Phone</label>
              <input v-model="block.data.phone" placeholder="+1 555 000 0000" type="tel"
                class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-sm outline-none" />
            </div>
          </div>
          <!-- Links -->
          <div>
            <label class="text-xs text-slate-400 block mb-1">Links</label>
            <div v-for="(link, li) in (block.data.links ?? [])" :key="li" class="flex gap-1.5 mb-1.5">
              <input v-model="link.label" placeholder="Label (e.g. LinkedIn)"
                class="w-28 border border-slate-200 rounded-lg px-2 py-1 text-xs outline-none flex-shrink-0" />
              <input v-model="link.url" placeholder="https://..."
                class="flex-1 border border-slate-200 rounded-lg px-2 py-1 text-xs outline-none" />
              <button @click="removeLink(block, li)"
                class="text-red-400 hover:text-red-600 text-xs px-1.5">✕</button>
            </div>
            <button @click="addLink(block)"
              class="text-xs text-blue-500 hover:text-blue-700 mt-0.5">+ Add link</button>
          </div>
        </div>
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
import axios from 'axios'
import ImagePicker from '@/Components/Admin/ImagePicker.vue'

const props = defineProps({
  modelValue:   Array,
  blockTypes:   Array,
  workspaces:   { type: Array, default: () => [] },
  serviceCards: { type: Array, default: () => [] },
})
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

// --- Service card drag-to-reorder ---
const cardDragIdx  = ref(null)
const cardDragOver = ref(null)
const cardOrder    = ref([...props.serviceCards].sort((a, b) => a.sort_order - b.sort_order))
const orderedCards = computed(() => cardOrder.value)

const dropCard = async (toIdx) => {
  if (cardDragIdx.value === null || cardDragIdx.value === toIdx) return
  const arr = [...cardOrder.value]
  const [item] = arr.splice(cardDragIdx.value, 1)
  arr.splice(toIdx, 0, item)
  cardOrder.value = arr
  await axios.post('/admin/service-cards/reorder', { ids: arr.map(c => c.id) })
}

// --- Product drag-to-reorder ---
const productDragIdx  = ref(null)
const productDragOver = ref(null)
const productDragWs   = ref(null)
const productOrders   = ref({}) // keyed by workspace_id

const orderedProducts = (wsId) => {
  if (!productOrders.value[wsId]) {
    const ws = props.workspaces.find(w => w.id === wsId)
    productOrders.value[wsId] = ws ? [...(ws.projects ?? [])].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)) : []
  }
  return productOrders.value[wsId]
}

const dropProduct = async (toIdx, wsId) => {
  if (productDragIdx.value === null || productDragIdx.value === toIdx) return
  const arr = [...orderedProducts(wsId)]
  const [item] = arr.splice(productDragIdx.value, 1)
  arr.splice(toIdx, 0, item)
  productOrders.value[wsId] = arr
  await axios.post('/admin/products/reorder', { ids: arr.map(p => p.id) })
}

// --- Contact form link helpers ---
const addLink    = (block) => { block.data.links = [...(block.data.links ?? []), { label: '', url: '' }] }
const removeLink = (block, idx) => { block.data.links = block.data.links.filter((_, i) => i !== idx) }

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
}[t] ?? '')
</script>
