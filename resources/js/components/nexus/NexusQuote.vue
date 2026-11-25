<script setup lang="ts">
import type { ChatReply } from '@/composables/useNexusChat';

defineProps<{ reply: ChatReply; own?: boolean }>();
const emit = defineEmits<{ jump: [id: number] }>();
</script>

<template>
    <button
        type="button"
        class="mb-1 block w-full rounded-xl border-l-[3px] border-[#23c6c8]/70 px-3 py-2 text-left transition duration-200 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[#23c6c8] motion-reduce:transition-none"
        :class="
            own
                ? 'bg-cyan-900/[0.04] hover:bg-cyan-900/[0.07]'
                : 'bg-slate-100/70 hover:bg-slate-100'
        "
        :aria-label="`Ir al mensaje citado de ${reply.role === 'assistant' ? 'Nexus' : 'ti'}`"
        @click="emit('jump', reply.id)"
    >
        <span
            class="block text-xs font-semibold"
            :class="own ? 'text-[#0f7f82]' : 'text-[#139b9e]'"
        >
            {{ reply.role === 'assistant' ? 'Nexus' : 'Tú' }}
        </span>
        <span
            class="line-clamp-2 text-xs leading-5"
            :class="own ? 'text-slate-600' : 'text-slate-500'"
        >
            {{ reply.text }}
        </span>
    </button>
</template>
