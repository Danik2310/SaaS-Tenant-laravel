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
    (response) => response,
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