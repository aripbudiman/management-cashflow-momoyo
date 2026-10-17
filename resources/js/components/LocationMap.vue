<script setup lang="ts">
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

// Peta Leaflet + tile OpenStreetMap (gratis, tanpa API key).
// Memakai circleMarker agar tidak bergantung pada file gambar ikon bawaan Leaflet.
export interface MapPoint {
    lat: number;
    lng: number;
    label: string;
    color?: string;
}

const props = withDefaults(
    defineProps<{
        points: MapPoint[];
        branch?: { lat: number; lng: number; radius: number; name: string } | null;
        heightClass?: string;
    }>(),
    { branch: null, heightClass: 'h-48' },
);

const el = ref<HTMLDivElement | null>(null);
let map: L.Map | null = null;
let layer: L.LayerGroup | null = null;

const render = () => {
    if (!map || !layer) return;
    layer.clearLayers();
    const bounds: L.LatLngExpression[] = [];

    if (props.branch) {
        const c = L.circle([props.branch.lat, props.branch.lng], {
            radius: props.branch.radius,
            color: '#e11d48',
            weight: 1.5,
            fillColor: '#e11d48',
            fillOpacity: 0.08,
        }).bindTooltip(`${props.branch.name} (radius ${props.branch.radius} m)`);
        layer.addLayer(c);
        L.circleMarker([props.branch.lat, props.branch.lng], { radius: 5, color: '#e11d48', fillColor: '#e11d48', fillOpacity: 1 })
            .bindTooltip(props.branch.name)
            .addTo(layer);
        bounds.push(c.getBounds().getNorthEast(), c.getBounds().getSouthWest());
    }

    props.points.forEach((p) => {
        L.circleMarker([p.lat, p.lng], {
            radius: 8,
            color: '#ffffff',
            weight: 2,
            fillColor: p.color ?? '#2563eb',
            fillOpacity: 1,
        })
            .bindTooltip(p.label, { permanent: props.points.length <= 2, direction: 'top', offset: [0, -6] })
            .addTo(layer!);
        bounds.push([p.lat, p.lng]);
    });

    if (bounds.length) {
        map.fitBounds(L.latLngBounds(bounds), { padding: [28, 28], maxZoom: 18 });
    }
};

onMounted(async () => {
    await nextTick();
    if (!el.value) return;
    map = L.map(el.value, { zoomControl: true, attributionControl: true }).setView([-6.2, 106.8], 15);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);
    layer = L.layerGroup().addTo(map);
    render();
    // Saat dipasang di dalam dialog, ukuran container baru final setelah animasi: hitung ulang
    setTimeout(() => {
        map?.invalidateSize();
        render();
    }, 250);
});

watch(() => [props.points, props.branch], render, { deep: true });

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <div ref="el" class="z-0 w-full overflow-hidden rounded-2xl border border-gray-200 dark:border-zinc-800" :class="heightClass"></div>
</template>
