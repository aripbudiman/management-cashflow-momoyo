import { ref } from 'vue';

export interface GeoCoordinates {
    latitude: number;
    longitude: number;
    accuracy: number | null;
}

export function useGeolocation() {
    const coords = ref<GeoCoordinates | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    /**
     * Hitung jarak dua koordinat GPS dalam satuan meter menggunakan rumus Haversine.
     */
    const calculateDistance = (
        lat1: number,
        lon1: number,
        lat2: number,
        lon2: number,
    ): number => {
        const R = 6371000; // Radius bumi dalam meter
        const dLat = ((lat2 - lat1) * Math.PI) / 180;
        const dLon = ((lon2 - lon1) * Math.PI) / 180;
        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos((lat1 * Math.PI) / 180) *
                Math.cos((lat2 * Math.PI) / 180) *
                Math.sin(dLon / 2) *
                Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return Math.round(R * c * 10) / 10;
    };

    /**
     * Ambil koordinat GPS perangkat saat ini dengan akurasi tinggi.
     */
    const getLocation = (): Promise<GeoCoordinates> => {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                const err =
                    'Browser Anda tidak mendukung layanan geolokasi GPS.';
                error.value = err;
                reject(new Error(err));
                return;
            }

            isLoading.value = true;
            error.value = null;

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const result: GeoCoordinates = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                    };
                    coords.value = result;
                    isLoading.value = false;
                    resolve(result);
                },
                (err) => {
                    isLoading.value = false;
                    let message = 'Gagal mendeteksi lokasi GPS.';
                    if (err.code === err.PERMISSION_DENIED) {
                        message =
                            'Izin lokasi GPS ditolak. Harap izinkan akses lokasi di browser smartphone Anda.';
                    } else if (err.code === err.POSITION_UNAVAILABLE) {
                        message =
                            'Informasi sinyal GPS tidak tersedia. Pastikan GPS smartphone aktif.';
                    } else if (err.code === err.TIMEOUT) {
                        message =
                            'Waktu permintaan lokasi GPS habis. Silakan coba kembali.';
                    }
                    error.value = message;
                    reject(new Error(message));
                },
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0,
                },
            );
        });
    };

    return {
        coords,
        isLoading,
        error,
        getLocation,
        calculateDistance,
    };
}
