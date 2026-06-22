import axios from 'axios';
import { router } from '@inertiajs/react';

const api = axios.create({
    baseURL: '/',
    headers: {
        'Content-Type': 'application/json',
    },
    withCredentials: true, // include cookies for session-based auth
});

api.interceptors.response.use(
    (response) => {
        if (response.data && typeof response.data.data === 'object' && !Array.isArray(response.data.data)) {
            // Promote nested data keys for consistency across API responses
            const meta = response.data.meta;
            Object.assign(response.data, response.data.data);
            delete response.data.data;
            if (meta) response.data.meta = meta;
        }
        return response;
    },
    (error) => {
        if (error.response?.status === 403) {
            const message = error.response?.data?.message;
            router.visit('/admin/unauthorized', {
                data: { message },
            });
        }
        return Promise.reject(error);
    }
);

export default api;