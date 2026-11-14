<script setup lang="ts">
/**
 * The product images full screen, zoomable. Loaded only when a visitor opens it.
 *
 * - Esc closes (the native <dialog>), the arrow keys move the way they point: in a right-to-left
 *   page the left arrow is "next".
 * - Double click or double tap zooms to 2.5× and back; pinch zooms from 1× to 4×; dragging pans a
 *   zoomed image, and a swipe moves to the next or previous image when it is not zoomed.
 */
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

import { t } from '@/lib/i18n';
import { ChevronLeft, ChevronRight, X, ZoomIn } from '@/lib/icons';

const props = defineProps<{ images: string[]; start: number; name: string }>();
const emit = defineEmits<{ close: [] }>();

const MAX_SCALE = 4;
const DOUBLE_ZOOM = 2.5;

const dialog = ref<HTMLDialogElement>();
const index = ref(Math.min(Math.max(props.start, 0), props.images.length - 1));
const view = reactive({ scale: 1, x: 0, y: 0 });

const rtl = computed(() => usePage().props.dir === 'rtl');
const current = computed(() => props.images[index.value]);
const zoomed = computed(() => view.scale > 1.01);

onMounted(() => dialog.value?.showModal());

watch(index, reset);

function reset(): void {
    view.scale = 1;
    view.x = 0;
    view.y = 0;
}

function close(): void {
    dialog.value?.close();
}

/** +1 is the next image, -1 the previous one, in reading order. */
function go(step: number): void {
    const count = props.images.length;
    if (count > 1) index.value = (index.value + step + count) % count;
}

function onKey(event: KeyboardEvent): void {
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
        event.preventDefault();
        const forward = event.key === 'ArrowRight' ? 1 : -1;
        go(rtl.value ? -forward : forward);
    }
}

function zoomTo(scale: number, originX = 0, originY = 0): void {
    const next = Math.min(Math.max(scale, 1), MAX_SCALE);
    // Keep the point under the finger (relative to the stage centre) where it is.
    const ratio = next / view.scale;
    view.x = originX - (originX - view.x) * ratio;
    view.y = originY - (originY - view.y) * ratio;
    view.scale = next;
    if (next === 1) reset();
}

function toggleZoom(originX = 0, originY = 0): void {
    zoomTo(zoomed.value ? 1 : DOUBLE_ZOOM, originX, originY);
}

// ---------------------------------------------------------------- pointers: pan, pinch, swipe, double tap

const stage = ref<HTMLElement>();
const pointers = new Map<number, { x: number; y: number }>();
let pinch: { distance: number; scale: number } | null = null;
let drag: { x: number; y: number; viewX: number; viewY: number } | null = null;
let lastTap = 0;
let lastPointerType = 'mouse';

function centred(event: PointerEvent | MouseEvent): { x: number; y: number } {
    const box = stage.value?.getBoundingClientRect();

    return box ? { x: event.clientX - box.left - box.width / 2, y: event.clientY - box.top - box.height / 2 } : { x: 0, y: 0 };
}

function distance(): number {
    const [a, b] = [...pointers.values()];

    return a && b ? Math.hypot(a.x - b.x, a.y - b.y) : 0;
}

function onPointerDown(event: PointerEvent): void {
    lastPointerType = event.pointerType;
    stage.value?.setPointerCapture(event.pointerId);
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

    if (pointers.size === 2) {
        pinch = { distance: distance(), scale: view.scale };
        drag = null;
    } else if (pointers.size === 1) {
        drag = { x: event.clientX, y: event.clientY, viewX: view.x, viewY: view.y };
    }
}

function onPointerMove(event: PointerEvent): void {
    if (!pointers.has(event.pointerId)) return;
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

    if (pinch && pointers.size === 2 && pinch.distance > 0) {
        zoomTo(pinch.scale * (distance() / pinch.distance));
    } else if (drag && zoomed.value) {
        view.x = drag.viewX + (event.clientX - drag.x);
        view.y = drag.viewY + (event.clientY - drag.y);
    }
}

function onPointerUp(event: PointerEvent): void {
    const wasDrag = drag;
    pointers.delete(event.pointerId);

    if (pointers.size < 2) pinch = null;
    if (pointers.size > 0) return;

    drag = null;

    if (!wasDrag) return;

    const moved = event.clientX - wasDrag.x;

    if (!zoomed.value && Math.abs(moved) > 50 && Math.abs(moved) > Math.abs(event.clientY - wasDrag.y)) {
        // A swipe towards the start of the line brings the next image.
        const towardsStart = rtl.value ? moved > 0 : moved < 0;
        go(towardsStart ? 1 : -1);

        return;
    }

    if (event.pointerType !== 'mouse' && Math.abs(moved) < 10) {
        const now = Date.now();
        if (now - lastTap < 300) {
            const point = centred(event);
            toggleZoom(point.x, point.y);
            lastTap = 0;
        } else {
            lastTap = now;
        }
    }
}

function onDoubleClick(event: MouseEvent): void {
    // A finger's double tap is handled in onPointerUp; some browsers also send dblclick for it.
    if (lastPointerType !== 'mouse') return;

    const point = centred(event);
    toggleZoom(point.x, point.y);
}

function onWheel(event: WheelEvent): void {
    if (!event.ctrlKey && !zoomed.value) return;
    event.preventDefault();
    const point = centred(event);
    zoomTo(view.scale * (event.deltaY < 0 ? 1.15 : 1 / 1.15), point.x, point.y);
}
</script>

<template>
    <dialog
        ref="dialog"
        class="m-0 size-full max-h-none max-w-none bg-[#111] p-0 text-white backdrop:bg-black/80"
        :aria-label="t('Product images')"
        @keydown="onKey"
        @close="emit('close')"
    >
        <div class="flex h-full flex-col">
            <div class="flex items-center justify-between gap-3 px-4 py-3">
                <p class="text-sm tabular-nums" aria-live="polite">{{ t(':n of :count', { n: index + 1, count: images.length }) }}</p>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="grid size-11 cursor-pointer place-items-center rounded-full bg-white/10 hover:bg-white/20"
                        :aria-label="zoomed ? t('Zoom out') : t('Zoom in')"
                        :aria-pressed="zoomed"
                        @click="toggleZoom()"
                    >
                        <ZoomIn class="size-5" aria-hidden="true" />
                    </button>
                    <button type="button" class="grid size-11 cursor-pointer place-items-center rounded-full bg-white/10 hover:bg-white/20" :aria-label="t('Close')" @click="close">
                        <X class="size-5" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div
                ref="stage"
                class="relative min-h-0 flex-1 touch-none overflow-hidden select-none"
                :class="zoomed ? 'cursor-grab active:cursor-grabbing' : 'cursor-zoom-in'"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
                @dblclick="onDoubleClick"
                @wheel="onWheel"
            >
                <img
                    v-if="current"
                    :key="current"
                    :src="current"
                    :alt="t('Image :n', { n: index + 1 }) + ' — ' + name"
                    draggable="false"
                    class="absolute inset-0 m-auto max-h-full max-w-full object-contain transition-transform duration-150 ease-out motion-reduce:transition-none"
                    :style="{ transform: `translate(${view.x}px, ${view.y}px) scale(${view.scale})` }"
                />

                <template v-if="images.length > 1">
                    <!-- Logical sides: in a right-to-left page "previous" sits on the right. -->
                    <button
                        type="button"
                        class="absolute start-3 top-1/2 grid size-12 -translate-y-1/2 cursor-pointer place-items-center rounded-full bg-black/40 hover:bg-black/60"
                        :aria-label="t('Previous')"
                        @pointerdown.stop
                        @click="go(-1)"
                    >
                        <ChevronLeft class="size-6 rtl:rotate-180" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="absolute end-3 top-1/2 grid size-12 -translate-y-1/2 cursor-pointer place-items-center rounded-full bg-black/40 hover:bg-black/60"
                        :aria-label="t('Next')"
                        @pointerdown.stop
                        @click="go(1)"
                    >
                        <ChevronRight class="size-6 rtl:rotate-180" aria-hidden="true" />
                    </button>
                </template>
            </div>

            <p class="px-4 py-3 text-center text-xs text-white/70">{{ t('Double tap or pinch to zoom.') }}</p>
        </div>
    </dialog>
</template>
