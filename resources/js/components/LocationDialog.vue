<script setup lang="ts">
import { ExternalLink } from '@lucide/vue';
import { computed } from 'vue';
import LocationMap from '@/components/LocationMap.vue';
import type { MapPoint } from '@/components/LocationMap.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

// Dialog peta lokasi absensi (clock in & clock out)
export interface LocationInfo {
    title: string;
    clockIn?: { lat: number; lng: number; time?: string | null } | null;
    clockOut?: { lat: number; lng: number; time?: string | null } | null;
    branch?: { lat: number; lng: number; radius: number; name: string } | null;
    mode?: string | null;
}

const props = defineProps<{ modelValue: LocationInfo | null }>();
const emit = defineEmits<{ (e: 'update:modelValue', value: null): void }>();

const points = computed<MapPoint[]>(() => {
    const v = props.modelValue;
    if (!v) return [];
    const out: MapPoint[] = [];
    if (v.clockIn) out.push({ lat: v.clockIn.lat, lng: v.clockIn.lng, label: `Clock In${v.clockIn.time ? ' ' + v.clockIn.time : ''}`, color: '#16a34a' });
    if (v.clockOut) out.push({ lat: v.clockOut.lat, lng: v.clockOut.lng, label: `Clock Out${v.clockOut.time ? ' ' + v.clockOut.time : ''}`, color: '#2563eb' });
    return out;
});

const osmLink = (p: { lat: number; lng: number }) => `https://www.openstreetmap.org/?mlat=${p.lat}&mlon=${p.lng}#map=18/${p.lat}/${p.lng}`;
</script>

<template>
    <Dialog :open="!!modelValue" @update:open="(v: boolean) => !v && emit('update:modelValue', null)">
        <DialogContent v-if="modelValue" class="max-h-[92vh] max-w-xl overflow-y-auto rounded-3xl p-5">
            <DialogHeader>
                <DialogTitle class="text-base font-bold">{{ modelValue.title }}</DialogTitle>
                <DialogDescription class="text-xs">
                    Lokasi saat absen
                    <span v-if="modelValue.mode === 'flexible'" class="font-semibold text-amber-600">· Mode Fleksibel (bebas lokasi)</span>
                </DialogDescription>
            </DialogHeader>

            <LocationMap :points="points" :branch="modelValue.branch" height-class="h-72" />

            <div class="space-y-1.5 text-xs">
                <div v-if="modelValue.clockIn" class="flex items-center justify-between gap-2">
                    <span><span class="mr-1.5 inline-block size-2.5 rounded-full bg-green-600"></span>Clock In {{ modelValue.clockIn.time }} · <span class="font-mono">{{ modelValue.clockIn.lat.toFixed(6) }}, {{ modelValue.clockIn.lng.toFixed(6) }}</span></span>
                    <a :href="osmLink(modelValue.clockIn)" target="_blank" rel="noopener" class="inline-flex items-center gap-0.5 text-rose-600"><ExternalLink class="size-3" /> Peta</a>
                </div>
                <div v-if="modelValue.clockOut" class="flex items-center justify-between gap-2">
                    <span><span class="mr-1.5 inline-block size-2.5 rounded-full bg-blue-600"></span>Clock Out {{ modelValue.clockOut.time }} · <span class="font-mono">{{ modelValue.clockOut.lat.toFixed(6) }}, {{ modelValue.clockOut.lng.toFixed(6) }}</span></span>
                    <a :href="osmLink(modelValue.clockOut)" target="_blank" rel="noopener" class="inline-flex items-center gap-0.5 text-rose-600"><ExternalLink class="size-3" /> Peta</a>
                </div>
                <div v-if="modelValue.branch" class="text-gray-500"><span class="mr-1.5 inline-block size-2.5 rounded-full bg-rose-600"></span>Area cabang {{ modelValue.branch.name }} (radius {{ modelValue.branch.radius }} m)</div>
            </div>
        </DialogContent>
    </Dialog>
</template>
